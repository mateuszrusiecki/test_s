<?php

class Customer {

	protected $user;
	protected $meta;

	public function meta( $var, $raw = false ) {

		if( ! isset( $this->meta[ $var ] ) ) {
			$this->meta[ $var ] = get_user_meta( $this->ID, $var, true );
			return $this->meta[ $var ];
		}

		$meta = $this->meta[ $var ];
		if( ! $raw && is_array( $meta ) && ( count( $meta ) == 1 ) ) {
			$meta = array_pop( $meta );
		}
		return $meta;
	}

	public function __construct( $user = null ) {

		if( is_numeric( $user ) ) {
			$user = new WP_User( intval( $user ) );
		}

		if( empty( $user ) ) {
			$user = wp_get_current_user();
		}

		$this->meta = array();
		if( $user && ( $user instanceof WP_User ) && $user->ID ) {
			$this->user = $user;
//			$this->meta = get_user_meta( $user->ID );
//			foreach ( $this->meta as $key => $value ) {
//				$value = $this->meta( $key );
//				$this->meta[ $key ] = is_string( $value ) && ( $nv = @unserialize( $value ) ) ? $nv : $value;
//
//			}
		}
	}

	public function __get( $var ) {
		if( property_exists( $this, $var ) ) {
			return $this->$var;
		}
		return $this->_get_from_user( $var );
	}

	public function clear_meta() {
		$this->meta = array();
	}

	//adyen_ref
	public function make_reference() {
		$this->meta['reference'] = $this->ID . '_' . Campaign::generate_coupon_code( 32 );
	}

	//referral
	public function make_ref() {
		$this->meta['ss_ref'] = Campaign::generate_coupon_code( 7 );
	}


	public function get_credits() {
		return intval( $this->meta( 'credits' ) );
	}

	public function set_credits( $credits, $save = false ) {
		$credits = max( 0, intval( $credits ) );
		$this->meta[ 'credits' ] = $credits;
		if( $save ) {
			update_user_meta( $this->ID, 'credits', $credits );
		}
	}

	public function has_referral_credit() {
		return intval( $this->meta( 'referrals' ) ) > 0;
	}

	public function use_referral_credit() {
		$referrals = intval( $this->meta( 'referrals' ) ) - 1;
		$this->meta[ 'referrals' ] = max( 0, $referrals );
	}

	public function add_referral( $customer = false ) {

		SS_Logger::write( '-- Customer::add_referral --' );
		SS_Logger::write( $this );

		$option_key = 'ss-referral-options';
		$options = json_decode( get_option( $option_key, '[]' ), true );
		if( ! isset( $options['n'], $options['p'], $options['a'] ) ) {
			return false;
		}

		$referrals = max( 0, intval( $this->meta( 'referrals' ) ) );
		$credits = max( 0, intval( $this->meta( 'credits' ) ) );

		SS_Logger::write( $referrals );
		SS_Logger::write( $credits );
		SS_Logger::write( $customer );

		if( $customer ) {
			add_post_meta( $this->ID, 'referred', "$customer->ID:$credits:" . date('Y-m-d') );
		}

		$ucs = $this->get_orders( -1, array(
			'order' => 'ASC',
			'post_status' => 'upcoming',
		) );

		$add = true;

		$n = max( 0, intval( $options[ 'n' ] ) );
		$a = floatval( $options[ 'a' ] );

		foreach ( $ucs as $uc ) {

			if( $n == 0 ) {
				break;
			}

			if( empty( $uc->campaign ) ) {

				SS_Logger::write( $uc );

				// $options = json_decode( get_option( 'ss-referral-options', '[]' ), true );
				// $amount = isset( $options[ 'a' ] ) ? floatval( $options[ 'a' ] ) : 0 ;
				// $max = 0.01;

				if( $options[ 'p' ] ) {
					$uc->price = max( $max, $uc->price * ( ( 100 - $a ) / 100 ) );
				} else {
					$uc->price = max( $max, $uc->price - $a );
				}

				$uc->campaign = array(
					'p' => $options[ 'p' ],
					'a' => $a,
				);

				SS_Logger::write( $uc->campaign );

				$uc->save();

				$n --;
				// $add = false;
				// break;
			}
		}

		if( $n ) {
			//$this->save();
			$this->meta[ 'referrals' ] = $referrals + $n;
			update_user_meta( $this->ID, 'referrals', $referrals + $n );

			$this->set_credits( $credits + ( $n * $a ), true );
		}

		return true;
	}

	public function uses_other_shipping_address() {
		$billing = trim( $this->meta( 'billing_postcode' ) . ' ' . $this->meta( 'billing_house_number' ) . ' ' . $this->meta( 'billing_house_number_suffix' ) );
		$shipping = trim( $this->meta( 'shipping_postcode' ) . ' ' . $this->meta( 'shipping_house_number' ) . ' ' . $this->meta( 'shipping_house_number_suffix' ) );
		return ! empty( $shipping ) && ( $billing != $shipping );
	}

	public function get_subscriptions( $n = -1, $attr = array() ) {
		$attr['author'] = $this->ID;
		$attr['posts_per_page'] = $n;

		if( ! isset( $attr['order'] ) ) {
			$attr['order'] = 'DESC';
		}
		if( ! isset( $attr['orderby'] ) ) {
			$attr['orderby'] = 'date';
		}

		return Subscription::query( $attr );
	}
	public function get_orders( $n = 10, $attr = array() ) {
		$attr['author'] = $this->ID;
		$attr['posts_per_page'] = $n;

		if( ! isset( $attr['order'] ) ) {
			$attr['order'] = 'DESC';
		}
		if( ! isset( $attr['orderby'] ) ) {
			$attr['orderby'] = 'meta_value';
			$attr['meta_key'] = 'date';
		}
		return SS_Order::query( $attr );
	}

	public function is_used_coupon( $coupon ) {

		if( ! $this->ID || ! $coupon ) {
			return false;
		}

		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare(
			"	SELECT ID
				FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON (ID = post_id)
				WHERE post_author = %d
					AND post_type IN (%s, %s)
					AND post_status NOT IN (%s, %s, %s, %s, %s)
					AND meta_key = 'coupon'
					AND meta_value = %s
			", $this->ID, SS_Order::$post_type, Subscription::$post_type, 'on-hold', 'cancelled', 'failed', 'pending', 'upcoming', $coupon ) );

	}

	public function get_used_coupons() {

		if( ! $this->ID ) {
			return [];
		}

		global $wpdb;
		return (array)$wpdb->get_col( $wpdb->prepare(
			"	SELECT meta_value
				FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON (ID = post_id)
				WHERE post_author = %d
					AND post_type IN (%s, %s)
					AND post_status NOT IN (%s, %s, %s, %s)
					AND meta_key = 'coupon'
			", $this->ID, SS_Order::$post_type, Subscription::$post_type, 'on-hold', 'cancelled', 'failed', 'pending' ) );

	}

	public function is_recurring( $deep = false ) {
		return ! empty( $deep ? SS_Mollie::get_mandate( $this ) : $this->meta('mollie_mandate_id') );
	}
/*
	public function is_recurring() {
		$response = Adyen::_list( $this, true );
		return isset( $response, $response['details'] ) && ! empty( $response['details']  );
	}
*/
	public function has_order() {
		return ! empty( SS_Order::query_one( array(
			'author' => $this->ID,
		) ) );
	}

	public function clear_used_ref( $order_id, $update ) {
		$used_ref = $this->meta( 'ss_used_ref' );

		SS_Logger::write( 'Customer:clear_used_ref' );
		SS_Logger::write( $used_ref );
		SS_Logger::write( "$order_id" );

		if( $used_ref && ( strpos( $used_ref, $order_id . ':' ) !== 0 ) ) {
			SS_Logger::write( strpos( $used_ref, $order_id . ':' ) );
			return false;
		}

		unset( $this->meta['ss_used_ref'] );
		if( $update ) {
			delete_user_meta( $this->ID, 'ss_used_ref' );
		}
	}

	public function set_used_ref( $ref ) {
		if( $this->meta( 'ss_used_ref' ) ) {
			return false;
		}
		$this->meta['ss_used_ref'] = $ref;
		return true;
	}

	public function set_email( $email ) { // pas op, deze wordt gebruikt voor logins
		if( $e = HelperFunctions::verify_email( $email ) ) {
			$this->user->user_email = $e;
		}
	}

	public function set_first_name( $first_name ) {
		$first_name = trim( (string)$first_name );
//		if( ! empty( $first_name) ) {
			$this->meta[ 'first_name' ] = $first_name;
//		}
	}

	public function set_last_name( $last_name ) {
		$last_name = trim( (string)$last_name );
//		if( ! empty( $last_name) ) {
			$this->meta[ 'last_name' ] = $last_name;
//		}
	}
	public function set_billing_phone( $billing_phone ) {
		$billing_phone = trim( (string)$billing_phone );
		$this->meta[ 'billing_phone' ] = $billing_phone;
	}

	public function set_billing_street_name( $street_name ) {
		$street_name = trim( (string)$street_name );
//		if( ! empty( $street_name) ) {
			$this->meta[ 'billing_street_name' ] = $street_name;
//		}
	}

	public function set_billing_extra_line( $extra_line ) {
		$extra_line = trim( (string)$extra_line );
//		if( ! empty( $extra_line) ) {
			$this->meta[ 'billing_extra_line' ] = $extra_line;
//		}
	}

	public function set_billing_country( $country ) {
		$country = trim( (string)$country );
//		if( ! empty( $country) ) {
			$this->meta[ 'billing_country' ] = $country;
//		}
	}

	public function set_billing_postcode( $postcode ) {
		$postcode = trim( (string)$postcode );
//		if( ! empty( $postcode) ) {
			$this->meta[ 'billing_postcode' ] = $postcode;
//		}
	}

	public function set_billing_house_number( $house_number ) {
		$house_number = trim( (string)$house_number );
//		if( ! empty( $house_number) ) {
			$this->meta[ 'billing_house_number' ] = $house_number;
//		}
	}

	public function set_billing_house_number_suffix( $house_number_suffix ) {
		$house_number_suffix = trim( (string)$house_number_suffix );
//		if( ! empty( $house_number_suffix) ) {
			$this->meta[ 'billing_house_number_suffix' ] = $house_number_suffix;
//		}
	}

	public function set_billing_city( $city ) {
		$postcode = trim( (string)$city );
//		if( ! empty( $city) ) {
			$this->meta[ 'billing_city' ] = $city;
//		}
	}
	public function set_shipping_street_name( $street_name ) {
		$street_name = trim( (string)$street_name );
//		if( ! empty( $street_name) ) {
			$this->meta[ 'shipping_street_name' ] = $street_name;
//		}
	}
	public function set_shipping_extra_line( $extra_line ) {
		$extra_line = trim( (string)$extra_line );
//		if( ! empty( $extra_line) ) {
			$this->meta[ 'shipping_extra_line' ] = $extra_line;
//		}
	}
	public function set_shipping_country( $country ) {
		$country = trim( (string)$country );
//		if( ! empty( $country) ) {
			$this->meta[ 'shipping_country' ] = $country;
//		}
	}

	public function set_shipping_postcode( $postcode ) {
		$postcode = trim( (string)$postcode );
//		if( ! empty( $postcode) ) {
			$this->meta[ 'shipping_postcode' ] = $postcode;
//		}
	}

	public function set_shipping_house_number( $house_number ) {
		$house_number = trim( (string)$house_number );
//		if( ! empty( $house_number) ) {
			$this->meta[ 'shipping_house_number' ] = $house_number;
//		}
	}
	public function set_shipping_house_number_suffix( $house_number_suffix ) {
		$house_number_suffix = trim( (string)$house_number_suffix );
//		if( ! empty( $house_number_suffix) ) {
			$this->meta[ 'shipping_house_number_suffix' ] = $house_number_suffix;
//		}
	}

	public function set_shipping_city( $city ) {
		$postcode = trim( (string)$city );
//		if( ! empty( $city) ) {
			$this->meta[ 'shipping_city' ] = $city;
//		}
	}

	public function set_iban( $iban ) {
		$iban = trim( (string)$iban );
//		if( ! empty( $city) ) {
			$this->meta[ 'iban' ] = $iban;
//		}
	}

	public function get_small_billing_address() {
		return $this->meta( 'billing_street_name' ) . ' ' . $this->meta( 'billing_house_number' ) . ' ' . $this->meta( 'billing_house_number_suffix' ). ' ' . $this->meta( 'billing_extra_line' ) . '<br>' .
				$this->meta( 'billing_postcode' ) . ' ' . $this->meta( 'billing_city' ) . '<br>';
	}

	public function get_small_shipping_address() {
		return $this->meta( 'shipping_street_name' ) . ' ' . $this->meta( 'shipping_house_number' ) . ' ' . $this->meta( 'shipping_house_number_suffix' ) . ' ' . $this->meta( 'shipping_extra_line' ) . '<br>' .
				$this->meta( 'shipping_postcode' ) . ' ' . $this->meta( 'shipping_city' ) . '<br>';
	}

	public function get_formatted_billing_address() {
		$wcc = new WC_Countries;
		return $wcc->get_formatted_address( array(
			'first_name' => $this->meta( 'first_name' ),
			'last_name'  => $this->meta( 'last_name' ),
			'company'    => $this->meta( 'billing_company' ),
			'address_1'  => $this->meta( 'billing_street_name' ) . ' ' . $this->meta( 'billing_house_number' ) . ' ' . $this->meta( 'billing_house_number_suffix' ),
			'address_2'  => $this->meta( 'billing_extra_line' ),
			'city'       => $this->meta( 'billing_city' ),
//			'state'      => $this->meta( 'billing_state' ),
			'postcode'   => $this->meta( 'billing_postcode' ),
			'country'    => $this->meta( 'billing_country' ),
		) );
	}

	public function get_formatted_shipping_address() {
		$wcc = new WC_Countries;
		return $wcc->get_formatted_address( array(
			'first_name' => $this->meta( 'first_name' ),
			'last_name'  => $this->meta( 'last_name' ),
			'company'    => $this->meta( 'shipping_company' ),
			'address_1'  => $this->meta( 'shipping_street_name' ) . ' ' . $this->meta( 'shipping_house_number' ) . ' ' . $this->meta( 'shipping_house_number_suffix' ),
			'address_2'  => $this->meta( 'shipping_extra_line' ),
			'city'       => $this->meta( 'shipping_city' ),
//			'state'      => $this->meta( 'shipping_state' ),
			'postcode'   => $this->meta( 'shipping_postcode' ),
			'country'    => $this->meta( 'shipping_country' ),
		) );
	}

	public function save() {
		foreach ( $this->meta as $key => $value ) {
			if( in_array( $key, array( 'session_tokens' ) ) ) {
				continue;
			}
			update_user_meta( $this->ID, $key, $value ); //$this->meta( $key ) );
		}
		return wp_update_user( array( 'ID' => $this->ID, 'user_email' => $this->user_email ) );
	}


	public function admin_edit_customer_form() {
?>
		<h4><?php _e( 'Billing details', 'shaversclub-store' ); ?></h4>
		<input type="hidden" name="edit_id" id="edit_id" value="<?php echo $this->ID; ?>" />
		<label><?php _e( 'Name', 'shaversclub-store' ); ?></label>
		<br>
		<input type="text" placeholder="Voornaam" name="edit_first_name" id="edit_first_name" value="<?php echo $this->meta( 'first_name' ); ?>" />
		<input type="text" placeholder="Achternaam" name="edit_last_name" id="edit_last_name" value="<?php echo $this->meta( 'last_name' ); ?>" />
		<br>

		<label><?php _e( 'Address', 'shaversclub-store' ); ?></label>
		<br>
		<input type="text" placeholder="Straat" name="edit_billing_street_name" id="edit_billing_street_name" value="<?php echo $this->meta( 'billing_street_name' ); ?>" />
		<input type="text" placeholder="Huisnummer" name="edit_billing_house_number" id="edit_billing_house_number" value="<?php echo $this->meta( 'billing_house_number' ); ?>" />
		<input type="text" placeholder="Toevoeging" name="edit_billing_house_number_suffix" id="edit_billing_house_number_suffix" value="<?php echo $this->meta( 'billing_house_number_suffix' ); ?>" />
		<br>
		<input type="text" placeholder="Extra adresregel" name="edit_billing_extra_line" id="edit_billing_extra_line" value="<?php echo $this->meta( 'billing_extra_line' ); ?>" />
		<input type="text" placeholder="Postcode" name="edit_billing_postcode" id="edit_billing_postcode" value="<?php echo $this->meta( 'billing_postcode' ); ?>" />
		<br>
		<input type="text" placeholder="Plaats" name="edit_billing_city" id="edit_billing_city" value="<?php echo $this->meta( 'billing_city' ); ?>" />
		<input type="text" placeholder="Land" name="edit_billing_country" id="edit_billing_country" value="<?php echo $this->meta( 'billing_country' ); ?>" />
		<br>

		<label><?php _e( 'Email', 'shaversclub-store' ); ?></label>
		<br>
		<input type="text" placeholder="Email" name="edit_email" id="edit_email" value="<?php echo $this->user_email; ?>" />
		<br>
		<label><?php _e( 'Phone', 'shaversclub-store' ); ?></label>
		<br>
		<input type="text" placeholder="Telefoon nummer" name="edit_billing_phone" id="edit_billing_phone" value="<?php echo $this->meta( 'billing_phone' ); ?>" />
		<br>

		<h4><?php _e( 'Shipping address', 'shaversclub-store' ); ?></h4>
		<input type="text" placeholder="Straat" name="edit_shipping_street_name" id="edit_shipping_street_name" value="<?php echo $this->meta( 'shipping_street_name' ); ?>" />
		<input type="text" placeholder="Huisnummer" name="edit_shipping_house_number" id="edit_shipping_house_number" value="<?php echo $this->meta( 'shipping_house_number' ); ?>" />
		<input type="text" placeholder="Toevoeging" name="edit_shipping_house_number_suffix" id="edit_shipping_house_number_suffix" value="<?php echo $this->meta( 'shipping_house_number_suffix' ); ?>" />
		<br>
		<input type="text" placeholder="Extra adresregel" name="edit_shipping_extra_line" id="edit_shipping_extra_line" value="<?php echo $this->meta( 'shipping_extra_line' ); ?>" />
		<input type="text" placeholder="Postcode" name="edit_shipping_postcode" id="edit_shipping_postcode" value="<?php echo $this->meta( 'shipping_postcode' ); ?>" />
		<br>
		<input type="text" placeholder="Plaats" name="edit_shipping_city" id="edit_shipping_city" value="<?php echo $this->meta( 'shipping_city' ); ?>" />
		<input type="text" placeholder="Land" name="edit_shipping_country" id="edit_shipping_country" value="<?php echo $this->meta( 'shipping_country' ); ?>" />
		<br>

		<button class="edit_customer_submit"><?php _e( 'Save', 'shaversclub-store' ); ?></button>
<?php
	}

	private function _get_from_user( $var ) {
		return $this->user && $this->user->$var ? $this->user->$var : false;
	}

	public function user_register( $user_id ) {
		update_user_meta( $user_id, 'ss_ref', Campaign::generate_coupon_code( 7 ) );
	}

	public function reset_password() {
		global $wpdb, $wp_hasher;
		$key = wp_generate_password( 20, false );
		do_action( 'retrieve_password_key', $this->user_login, $key );
		if ( empty( $wp_hasher ) ) {
			if ( ! class_exists('PasswordHash') ) {
				require_once ( ABSPATH . 'wp-includes/class-phpass.php');
			}
			$wp_hasher = new PasswordHash( 8, true );
		}
		$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
		$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $this->user_login ) );
		Customer::mail_reset_password( $this->ID, $key );
	}

	public static function mail_new( $user_id ) {
		global $ec_template_args, $ec_email_html, $ec_email_css;
		$EC_Settings = new EC_Settings;
		$user = new WP_User( $user_id );
		$ec_template_args['user_login'] = $user->user_login;
		include SS_PATH . 'includes/email/customer-new-account.php';
		return HelperFunctions::mail( $user->user_email, '[ShaversClub] ' . __( 'Your new account', 'shaversclub-store' ), $ec_email_html );
	}

	public static function mail_reset_password( $user_id, $key ) {
		global $ec_template_args, $ec_email_html, $ec_email_css;
		$EC_Settings = new EC_Settings;
		$user = new WP_User( $user_id );
		$ec_template_args['user_login'] = $user->user_login;
		$ec_template_args['reset_key'] = $key;
		include SS_PATH . 'includes/email/customer-reset-password.php';
		return HelperFunctions::mail( $user->user_email, '[ShaversClub] ' . __( 'Reset password', 'shaversclub-store' ), $ec_email_html );
	}

	public static function shortcodes() {
		add_shortcode( 'ss_my_account', array( 'Customer', 'customer_view' ) );
		add_shortcode( 'ss_reset_password', array( 'Customer', 'reset_password_view' ) );
	}

	public static function customer_view( $atts ) {
		ob_start();
		include plugin_dir_path( __FILE__ ) . 'views/customer.php';
		return ob_get_clean();
	}
	public static function reset_password_view( $atts ) {
		ob_start();
		include plugin_dir_path( __FILE__ ) . 'views/reset_password.php';
		return ob_get_clean();
	}

	public static function ajax() {
		
		//Test functies na development weer verwijderen
        add_action( 'wp_ajax_ss_login', array( 'Customer', 'ajax_login' ) );
        add_action( 'wp_ajax_ss_login_cookie', array( 'Customer', 'ajax_login_cookie' ) );
		//add_action( 'wp_ajax_ss_register', array( 'Customer', 'ajax_register' ) );
		//Test functies na development weer verwijderen

        add_action( 'wp_ajax_nopriv_ss_login', array( 'Customer', 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_ss_login_cookie', array( 'Customer', 'ajax_login_cookie' ) );
		add_action( 'wp_ajax_nopriv_ss_register', array( 'Customer', 'ajax_register' ) );
		add_action( 'wp_ajax_nopriv_ss_forgot', array( 'Customer', 'ajax_forgot' ) );
		add_action( 'wp_ajax_ss_personal_details', array( 'Customer', 'ajax_personal_details' ) );
		add_action( 'wp_ajax_ss_change_password', array( 'Customer', 'ajax_change_password' ) );
		add_action( 'wp_ajax_nopriv_ss_reset_password', array( 'Customer', 'ajax_reset_password' ) );

		add_action( 'wp_ajax_ss_edit_iban', array( 'Customer', 'ajax_edit_iban' ) );

		add_action( 'wp_ajax_ss_new_order', array( 'Customer', 'ajax_new_order' ) );
		add_action( 'wp_ajax_ss_add_to_order', array( 'Customer', 'ajax_add_to_order' ) );

		add_action( 'wp_ajax_ss_new_sub', array( 'Customer', 'ajax_new_sub' ) );
		add_action( 'wp_ajax_ss_add_to_sub', array( 'Customer', 'ajax_add_to_sub' ) );

		add_action( 'wp_ajax_ss_edit_customer', array( 'Customer', 'ajax_edit_customer' ) );

		add_action( 'wp_ajax_ss_edit_customer_first', array( 'Customer', 'ajax_edit_customer_first' ) );
		add_action( 'wp_ajax_ss_edit_customer_second', array( 'Customer', 'ajax_edit_customer_second' ) );

        add_action( 'wp_ajax_nopriv_ss_external_register', array( 'Customer', 'ajax_external_register' ) );
        add_action( 'wp_ajax_nopriv_external_register', array( 'Customer', 'ajax_external_register' ) );
	}


	public static function ajax_edit_customer_first() {

		$out;

		$customer = new Customer;
		if( $customer->ID ) {

			$customer->set_first_name( $_POST[ 'first_name' ] );
			$customer->set_last_name( $_POST[ 'last_name' ] );

			$customer->set_email( $_POST['email'] );
			$customer->set_billing_phone( $_POST['phone'] );

			$customer->save();

			$out = [ 'status' => 'success' ];

		} else {
			$out = [ 'status' => 'error', 'message' => __( 'Could not save customer', 'shaversclub-store' ) ];
		}

		wp_die( json_encode( $out ) );
	}



	public static function ajax_edit_customer_second() {

		$out;

		$customer = new Customer;
		if( $customer->ID ) {

			$customer->set_billing_house_number( $_POST[ 'house_number' ] );
			$customer->set_billing_house_number_suffix( $_POST[ 'house_number_suffix' ] );
			$customer->set_billing_postcode( $_POST[ 'postcode' ] );
			$customer->set_billing_street_name( $_POST[ 'street_name' ] );
			$customer->set_billing_extra_line( $_POST[ 'extra_line' ] );
			$customer->set_billing_city( $_POST[ 'city' ] );
			$customer->set_billing_country( $_POST[ 'country' ] );

			$customer->set_shipping_house_number( $_POST[ 'house_number' ] );
			$customer->set_billing_house_number_suffix( $_POST[ 'house_number_suffix' ] );
			$customer->set_shipping_postcode( $_POST[ 'postcode' ] );
			$customer->set_shipping_street_name( $_POST[ 'street_name' ] );
			$customer->set_billing_extra_line( $_POST[ 'extra_line' ] );
			$customer->set_shipping_city( $_POST[ 'city' ] );
			$customer->set_shipping_country( $_POST[ 'country' ] );

			$customer->save();

			$out = [ 'status' => 'success' ];

		} else {
			$out = [ 'status' => 'error', 'message' => __( 'Could not save customer', 'shaversclub-store' ) ];
		}

		wp_die( json_encode( $out ) );
	}

	public static function ajax_edit_customer() {

		$out;

		$customer = new Customer( $_POST['id'] );
		if( $customer->ID == $_POST['id'] ) {

			$customer->set_first_name( $_POST[ 'first_name' ] );
			$customer->set_last_name( $_POST[ 'last_name' ] );

			$customer->set_email( $_POST['email'] );

			$customer->set_billing_house_number( $_POST[ 'billing_house_number' ] );
			$customer->set_billing_house_number_suffix( $_POST[ 'billing_house_number_suffix' ] );
			$customer->set_billing_postcode( $_POST[ 'billing_postcode' ] );
			$customer->set_billing_street_name( $_POST[ 'billing_street_name' ] );
			$customer->set_billing_extra_line( $_POST[ 'billing_extra_line' ] );
			$customer->set_billing_city( $_POST[ 'billing_city' ] );
			$customer->set_billing_country( $_POST[ 'billing_country' ] );
			$customer->set_billing_phone( $_POST['billing_phone'] );

			$customer->set_shipping_house_number( $_POST[ 'shipping_house_number' ] );
			$customer->set_shipping_house_number_suffix( $_POST[ 'shipping_house_number_suffix' ] );
			$customer->set_shipping_postcode( $_POST[ 'shipping_postcode' ] );
			$customer->set_shipping_street_name( $_POST[ 'shipping_street_name' ] );
			$customer->set_shipping_extra_line( $_POST[ 'shipping_extra_line' ] );
			$customer->set_shipping_city( $_POST[ 'shipping_city' ] );
			$customer->set_shipping_country( $_POST[ 'shipping_country' ] );

			$customer->save();

			$out = array(
				'status' => 'success',
			);
		} else {
			$out = array(
				'status' => 'error',
				'message' => __( 'Could not save customer', 'shaversclub-store' ),
			);
		}

		wp_die( json_encode( $out ) );
	}

	public static function ajax_edit_iban() {
		$out;

		SS_Logger::write( 'Customer:ajax_edit_iban' );

		if( isset( $_POST['iban'] )
			&& ( $iban = HelperFunctions::check_iban( $_POST['iban'] ) )
		) {
			$customer = new Customer;
			//$customer->set_iban( $iban );
			//$customer->save();

			SS_Logger::write( $customer );

			try { SS_Mollie::revoke_mandate( $customer ); } catch ( Exception $e ) {}

			try {
				SS_Mollie::create_mandate( $customer, $iban );

				$out = array(
					'status' => 'success',
					'message' => __( 'New IBAN will be used for next order', 'shaversclub-store' ),
				);
			} catch ( Mollie_API_Exception $e ) {
				$out = array(
					'status' => 'error',
					'message' => htmlspecialchars( $e->getField() ) . ': ' . htmlspecialchars( $e->getMessage() ),
				);
			} catch ( Exception $e ) {
				$out = array(
					'status' => 'error',
					'message' => $e->getMessage(),
				);
			}


		} else {
			$out = array(
				'status' => 'error',
				'message' => __( 'Invalid IBAN given', 'shaversclub-store' ),
			);
		}

		wp_die( json_encode( $out ) );
	}

	private static function set_iban_or_die( $customer ) {

		SS_Logger::write( 'Customer:set_iban_or_die' );
		if( isset( $_POST['iban'] ) ) {

			SS_Logger::write( 'isset( $_POST[iban] )' );

			$message = false;
			if( $iban = HelperFunctions::check_iban( $_POST['iban'] ) ) {

				try { SS_Mollie::revoke_mandate( $customer ); } catch ( Exception $e ) {}

				try {
					SS_Mollie::create_mandate( $customer, $iban );
				} catch ( Mollie_API_Exception $e ) {
					$message = htmlspecialchars( $e->getField() ) . ': ' . htmlspecialchars( $e->getMessage() );
				} catch ( Exception $e ) {
					$message = $e->getMessage();
				}

			} else {
				$message = __( 'Invalid IBAN given', 'shaversclub-store' );
			}

			if( $message ) {
				SS_Logger::write( $message );
				wp_die( json_encode( array(
					'status' => 'error',
					'message' => $message,
				) ) );
			}

		}
	}

	public static function ajax_add_to_sub() {
		$customer = new Customer;
		$product = new Product( $_POST['pid'] );
		$subscriptions = $customer->get_subscriptions( 1, array(
			'p' => $_POST['subscription'],
		) );
		$out;

		SS_Logger::write( 'Customer:ajax_add_to_sub' );

		SS_Logger::write( $_POST['pid'] );
		SS_Logger::write( $_POST['subscription'] );

		SS_Logger::write( $customer );
		SS_Logger::write( $product );

		self::set_iban_or_die( $customer );

		if( $product->has_post() && ! empty( $subscriptions ) ) {
			$subscription = array_pop( $subscriptions );
			$subscription->_fill_vars();

			SS_Logger::write( $subscription );

			$subscription->add_recurring_product( $product );
			$subscription->save();
			$out = array(
				'status' => 'success',
				'message' => __( 'Product successfully add to this service', 'shaversclub-store' ),
			);
		} else {
			$out = array(
				'status' => 'error',
				'message' => __( 'Could not add product to this service', 'shaversclub-store' ),
			);
		}
		wp_die( json_encode( $out ) );
	}

	public static function ajax_new_sub() {
		$customer = new Customer;
		$subscription = new Subscription;

		$out;

		$product = new Product( $_POST['pid'] );

		$interval = DateInterval::createFromDateString( $_POST['interval'] );

		SS_Logger::write( 'Customer:ajax_new_sub' );

		SS_Logger::write( $_POST['pid'] );
		SS_Logger::write( $_POST['interval'] );
		SS_Logger::write( $_POST['payment'] );

		SS_Logger::write( $customer );
		SS_Logger::write( $product );

		self::set_iban_or_die( $customer );

		if( $product
			&& in_array( $_POST[ 'payment' ], array( 'ideal', 'sepadirectdebit' ) )
			&& ! HelperFunctions::date_interval_empty( $interval )
		) {
			$subscription->add_recurring_product( $product );
			$subscription->set_payment( $_POST[ 'payment' ] );
			$subscription->set_interval( $interval );
			$subscription->set_customer( $customer );
			$order = $subscription->to_order();
			$subscription->save( array( 'post_status' => 'active' ) );
			$order->save();

			SS_Logger::write( $subscription );
			SS_Logger::write( $order );

			if( $customer->is_recurring() ) {
				$out = array(
					'status' => 'success',
					'message' => __( 'Your order was successfully placed', 'shaversclub-store' ),
				);

			} else {
				$out = $order->make_mollie_payment();
/*
				$out = array(
					'status' => 'redirect',
					'mollie' => $order->make_mollie_payment(),
				//	'form' => $order->make_adyen_form(),
				);
*/
			}


		} else {
			$out = array(
				'status' => 'error',
				'message' => __( 'Could not make order', 'shaversclub-store' ),
			);
		}
		wp_die( json_encode( $out ) );
	}

	public static function ajax_new_order() {
		$customer = new Customer;
		$order = new SS_Order;

		$out;

		$product = new Product( $_POST['pid'] );


		SS_Logger::write( 'Customer:ajax_new_order' );

		SS_Logger::write( $_POST['pid'] );

		SS_Logger::write( $customer );
		SS_Logger::write( $product );

		self::set_iban_or_die( $customer );

		if( $product && in_array( $_POST[ 'payment' ], array( 'ideal', 'sepadirectdebit' ) ) ) {
			$order->add_product( $product );
			$order->shipping_price = $product->get_shipping();
			$order->payment = $_POST[ 'payment' ];
			$order->set_customer( $customer );
			$order->save();

			SS_Logger::write( $order );

			if( $customer->is_recurring() ) {
				$out = array(
					'status' => 'success',
					'message' => __( 'Your order was successfully placed', 'shaversclub-store' ),
				);

			} else {
				$out = $order->make_mollie_payment();
/*
				$out = array(
					'status' => 'redirect',
					'mollie' => $order->make_mollie_payment(),
				//	'form' => $order->make_adyen_form(),
				);
*/
			}

		} else {
			$out = array(
				'status' => 'error',
				'message' => __( 'Could not make order', 'shaversclub-store' ),
			);
		}
		wp_die( json_encode( $out ) );
	}

	public static function ajax_add_to_order() {

		$customer = new Customer;
		$product = new Product( $_POST['pid'] );
		$orders = $customer->get_orders( 1, array(
			'p' => $_POST['order'],
			'post_status' => 'upcoming',
			'meta_key' => 'date',
			'meta_value' => date( 'Y-m-d H:i:s' ),
			'meta_compare' => '>',
		) );


		SS_Logger::write( 'Customer:ajax_add_to_order' );

		SS_Logger::write( $_POST['pid'] );
		SS_Logger::write( $_POST['order'] );

		SS_Logger::write( $customer );
		SS_Logger::write( $product );

		self::set_iban_or_die( $customer );
		$out;

		if( $product->has_post() && ! empty( $orders ) ) {
			$order = array_pop( $orders );
			$order->add_product( $product );
			$order->save();

			SS_Logger::write( $order );

			$out = array(
				'status' => 'success',
				'message' => __( 'Product successfully add to this order', 'shaversclub-store' ),
			);
		} else {
			$out = array(
				'status' => 'error',
				'message' => __( 'Could not add product to this order', 'shaversclub-store' ),
			);
		}
		wp_die( json_encode( $out ) );
	}

	public static function ajax_personal_details() {
		$out;

		$customer = new Customer;
		$customer->set_first_name( $_POST['first_name'] );
		$customer->set_last_name( $_POST['last_name'] );

		$customer->set_billing_street_name( $_POST['billing_street_name'] );
		$customer->set_billing_extra_line( $_POST['billing_extra_line'] );
		$customer->set_billing_house_number( $_POST['billing_house_number'] );
		$customer->set_billing_house_number_suffix( $_POST['billing_house_number_suffix'] );

		$customer->set_billing_postcode( $_POST['billing_postcode'] );
		$customer->set_billing_city( $_POST['billing_city'] );
		$customer->set_billing_country( $_POST['billing_country'] );

		$customer->set_shipping_street_name( $_POST['billing_street_name'] );
		$customer->set_shipping_extra_line( $_POST['billing_extra_line'] );
		$customer->set_shipping_house_number( $_POST['billing_house_number'] );
		$customer->set_shipping_house_number_suffix( $_POST['billing_house_number_suffix'] );

		$customer->set_shipping_postcode( $_POST['billing_postcode'] );
		$customer->set_shipping_city( $_POST['billing_city'] );
		$customer->set_shipping_country( $_POST['billing_country'] );

		if( ( $email = HelperFunctions::verify_email( $_POST['email'] ) ) !== false ) {
			$customer->user->user_email = $email;
		}
		$customer->set_billing_phone( $_POST['billing_phone'] );

		if( $customer->save() ) {
			$customer = new Customer;
			$out = array(
				'status' => 'success',
				'message' => __( 'Personal details saved', 'shaversclub-store' ),
				'user' => array(
					'first_name' => $customer->meta( 'first_name' ),
					'last_name' => $customer->meta( 'last_name' ),
					'billing_street_name' => $customer->meta( 'billing_street_name' ),
					'billing_house_number' => $customer->meta( 'billing_house_number' ),
					'billing_house_number_suffix' => $customer->meta( 'billing_house_number_suffix' ),
					'billing_postcode' => $customer->meta( 'billing_postcode' ),
					'billing_city' => $customer->meta( 'billing_city' ),
					'billing_country' => $customer->meta( 'billing_country' ),
					'billing_phone' => $customer->meta( 'billing_phone' ),
					'email' => $customer->user_email,
				),
			);
		} else {
			$out = array(
				'status' => 'error',
				'message' => __( 'Could not save personal details', 'shaversclub-store' ),
			);
		}
		wp_die( json_encode( $out ) );
	}

	public static function ajax_change_password() {
		global $wpdb;

		$out = array(
			'status' => 'error',
			'message' => __( 'Could not change password', 'shaversclub-store' ),
		);

		$user = wp_get_current_user();
		if( $user && ( $user instanceof WP_User ) && $user->ID
			&& isset( $_POST['p'], $_POST['n'] )
			&& wp_check_password( $_POST['p'], $user->user_pass, $user->ID )
		) {
			$hash = wp_hash_password( $_POST['n'] );
			$wpdb->update( $wpdb->users, array( 'user_pass' => $hash ), array( 'ID' => $user->ID ) );
			wp_cache_delete( $user->ID, 'users' );

			wp_signon( array (
				'user_login' => $user->user_login,
				'user_password' => $_POST['n'],
				'remember' => false,
			), false );

			$out = array(
				'status' => 'success',
				'message' => __( 'Password changed', 'shaversclub-store' ),
			);
		}

		wp_die( json_encode( $out ) );
	}

	public static function ajax_reset_password() {
		global $wpdb;

		$out = array(
			'status' => 'error',
			'message' => __( 'Could not reset password', 'shaversclub-store' ),
		);

		$user = new WP_User( $_SESSION['_rp_user'] );
		if( $user && ( $user instanceof WP_User ) && $user->ID
			&& isset( $_POST['n'], $_POST['c'] )
		) {
			$hash = wp_hash_password( $_POST['n'] );
			$wpdb->update( $wpdb->users, array( 'user_pass' => $hash, 'user_activation_key' => '' ), array( 'ID' => $user->ID ) );
			unset($_SESSION['_rp_user']);
			wp_cache_delete( $user->ID, 'users' );

			$page = get_page_by_path( 'my-account' );

			$out = array(
				'status' => 'success',
				'url' => get_permalink( $page->ID ),
			);
		}

		wp_die( json_encode( $out ) );
	}

	public static function ajax_forgot() {

		$out = array(
			'status' => 'error',
			'message' => __( 'Could not send email', 'shaversclub-store' ),
		);

		if( ! check_ajax_referer( 'ss-ajax-login-nonce', 'security', false ) ) {
			$out['message'] = __( 'Bad referral', 'shaversclub-store' );
			wp_die( json_encode( $out ) );
		}


		if( isset( $_POST['email'] ) ) {
			if( $user = get_user_by( 'email', $_POST['email'] ) ) {
				$c = new Customer( $user );
				$c->reset_password();
				$out = array(
					'status' => 'success',
					'message' => __( 'A reset link was sent to your email address', 'shaversclub-store' ),
				);
			}
		}
		wp_die( json_encode( $out ) );
	}

	public static function ajax_login() {

		$out = array(
			'status' => 'error',
		);

		if( ! check_ajax_referer( 'ss-ajax-login-nonce', 'security', false ) ) {
			$out['message'] = __( 'Bad referral', 'shaversclub-store' );
			wp_die( json_encode( $out ) );
		}

		$cart = Cart::get_cart_from_session();

		SS_Logger::write( 'Customer:ajax_login' );

		if( isset( $_POST['email'], $_POST['password'] ) ) {
			$signon = wp_signon( array (
				'user_login' => $_POST['email'],
				'user_password' => $_POST['password'],
				'remember' => isset( $_POST['rememberme'] ) ? $_POST['rememberme'] : false,
			), false );

			if( is_wp_error( $signon ) ) {

				$messages = array(
					'invalid_username' => 'email',
					'invalid_email' => 'email',
					'incorrect_password' => 'password',
					'too_many_retries' => 'password',
				);

				$out['error_messages'] = array();
				foreach ( $signon->errors as $key => $message ) {

					if( ! isset( $messages[ $key ] ) ) {
						SS_Logger::write( $key );
					}

					$key = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
					$out['error_messages'][ $key ] = implode( '<br>', $message );
				}

			} else {
				$customer = new Customer( $signon );
				SS_Logger::write( $customer );
				if( $campaign = $cart->get_campaign() ) {
					SS_Logger::write( $campaign );
					$cart->set_campaign( $campaign->ID );
				}
				SS_Logger::write( serialize($cart) );
				update_user_meta( $signon->ID, '_session_cart', $cart );
				$page = get_page_by_path( 'mijn-account' );
				$out = array(
					'status' => 'success',
					'logout_url' => wp_logout_url( '__cart_url__' ),
					'url' => get_permalink( $page->ID ),
				);
				if( trim( $_POST[ 'referrer' ], '?/ ' ) ) {

					if( isset( $_SESSION[ 'ss_ref_id' ] ) && ( $_SESSION[ 'ss_ref_id' ] == $customer->ID ) ) {
						unset( $_SESSION['ss_ref'], $_SESSION['ss_ref_id'] );
						$out[ 'trigger_product_select' ] = true;
					}

					$out[ 'user' ] = array(
						'name' => $customer->display_name,
						'first_name' => $customer->meta( 'first_name' ),
						'last_name' => $customer->meta( 'last_name' ),
						'billing_postcode' => $customer->meta( 'billing_postcode' ),
						'billing_house_number' => $customer->meta( 'billing_house_number' ),
						'billing_house_number_suffix' => $customer->meta( 'billing_house_number_suffix' ),
						'billing_street_name' => $customer->meta( 'billing_street_name' ),
						'billing_extra_line' => $customer->meta( 'billing_extra_line' ),
						'billing_city' => $customer->meta( 'billing_city' ),
						'billing_country' => $customer->meta( 'billing_country' ),
						'is_recurring' => $customer->is_recurring(),
/*
						'shipping_postcode' => $customer->meta( 'shipping_postcode' ),
						'shipping_house_number' => $customer->meta( 'shipping_house_number' ),
						'shipping_house_number_suffix' => $customer->meta( 'shipping_house_number_suffix' ),
*/
					);
				}
			}
		} else {
			SS_Logger::write( json_encode( $_POST ) );
			$out['message'] = __( 'Incorrect input', 'shaversclub-store' );
		}
		wp_die( json_encode( $out ) );
	}


    public static function ajax_login_cookie() {
        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', 'ajax_login' .PHP_EOL, FILE_APPEND);
        $out = array(
            'status' => 'error',
        );

        $cart = Cart::get_cart_from_session();

        SS_Logger::write( 'Customer:ajax_login' );

        if( isset( $_POST['email'], $_POST['password'] ) ) {
            $signon = wp_signon( array (
                'user_login' => $_POST['email'],
                'user_password' => $_POST['password'],
                'remember' => isset( $_POST['rememberme'] ) ? $_POST['rememberme'] : false,
            ), false );

            $result = self::get_auth_cookie($signon->ID, isset( $_POST['rememberme'] ) ? $_POST['rememberme'] : false, false);
            //$result = wp_signon_cookie($signon->ID, isset( $_POST['rememberme'] ) ? $_POST['rememberme'] : false);

            if( is_wp_error( $signon ) ) {

                $messages = array(
                    'invalid_username' => 'email',
                    'invalid_email' => 'email',
                    'incorrect_password' => 'password',
                    'too_many_retries' => 'password',
                );

                $out['error_messages'] = array();
                foreach ( $signon->errors as $key => $message ) {

                    if( ! isset( $messages[ $key ] ) ) {
                        SS_Logger::write( $key );
                    }

                    $key = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
                    $out['error_messages'][ $key ] = implode( '<br>', $message );
                }

            } else {
                $customer = new Customer( $signon );
                SS_Logger::write( $customer );
                if( $campaign = $cart->get_campaign() ) {
                    SS_Logger::write( $campaign );
                    $cart->set_campaign( $campaign->ID );
                }
                SS_Logger::write( serialize($cart) );
                update_user_meta( $signon->ID, '_session_cart', $cart );
                $page = get_page_by_path( 'mijn-account' );
                $out = array(
                    'status' => 'success',
                    'logout_url' => wp_logout_url( '__cart_url__' ),
                    'url' => get_permalink( $page->ID ),
                    'cookie' => $result['value'],
                    'cookie_name' => $result['name'],
                    'cookie_expire' => $result['expire']
                );
                if( trim( $_POST[ 'referrer' ], '?/ ' ) ) {

                    if( isset( $_SESSION[ 'ss_ref_id' ] ) && ( $_SESSION[ 'ss_ref_id' ] == $customer->ID ) ) {
                        unset( $_SESSION['ss_ref'], $_SESSION['ss_ref_id'] );
                        $out[ 'trigger_product_select' ] = true;
                    }

                    $out[ 'user' ] = array(
                        'name' => $customer->display_name,
                        'first_name' => $customer->meta( 'first_name' ),
                        'last_name' => $customer->meta( 'last_name' ),
                        'billing_postcode' => $customer->meta( 'billing_postcode' ),
                        'billing_house_number' => $customer->meta( 'billing_house_number' ),
                        'billing_house_number_suffix' => $customer->meta( 'billing_house_number_suffix' ),
                        'billing_street_name' => $customer->meta( 'billing_street_name' ),
                        'billing_extra_line' => $customer->meta( 'billing_extra_line' ),
                        'billing_city' => $customer->meta( 'billing_city' ),
                        'billing_country' => $customer->meta( 'billing_country' ),
                        'is_recurring' => $customer->is_recurring(),
                        /*
                                                'shipping_postcode' => $customer->meta( 'shipping_postcode' ),
                                                'shipping_house_number' => $customer->meta( 'shipping_house_number' ),
                                                'shipping_house_number_suffix' => $customer->meta( 'shipping_house_number_suffix' ),
                        */
                    );
                }
            }
        } else {
            SS_Logger::write( json_encode( $_POST ) );
            $out['message'] = __( 'Incorrect input', 'shaversclub-store' );
        }

        //file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', json_encode($out) .PHP_EOL, FILE_APPEND);
        wp_die( json_encode( $out ) );
    }


    static function get_auth_cookie( $user_id, $remember = false, $secure = '', $token = '' ) {

        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', 'get_auth_cookie' .PHP_EOL, FILE_APPEND);
        if ( $remember ) {
            /**
             * Filters the duration of the authentication cookie expiration period.
             *
             * @since 2.8.0
             *
             * @param int  $length   Duration of the expiration period in seconds.
             * @param int  $user_id  User ID.
             * @param bool $remember Whether to remember the user login. Default false.
             */
            $expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember );

            /*
             * Ensure the browser will continue to send the cookie after the expiration time is reached.
             * Needed for the login grace period in wp_validate_auth_cookie().
             */
            $expire = $expiration + ( 12 * HOUR_IN_SECONDS );
        } else {
            /** This filter is documented in wp-includes/pluggable.php */
            $expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember );
            $expire = 0;
        }

        $expiration = time() + ( 12 * HOUR_IN_SECONDS );

        if ( '' === $token ) {
            $manager = WP_Session_Tokens::get_instance( $user_id );
            $token   = $manager->create( $expiration );
        }

        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', json_encode($token) .PHP_EOL, FILE_APPEND);
        $logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

        /**
         * Fires immediately before the logged-in authentication cookie is set.
         *
         * @since 2.6.0
         * @since 4.9.0 The `$token` parameter was added.
         *
         * @param string $logged_in_cookie The logged-in cookie.
         * @param int    $expire           The time the login grace period expires as a UNIX timestamp.
         *                                 Default is 12 hours past the cookie's expiration time.
         * @param int    $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
         *                                 Default is 14 days from now.
         * @param int    $user_id          User ID.
         * @param string $scheme           Authentication scheme. Default 'logged_in'.
         * @param string $token            User's session token to use for this cookie.
         */
        do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in', $token );


        //setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, false, true);
        setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, time() + (86400 * 30), COOKIEPATH, COOKIE_DOMAIN);

        return [
            'name' => LOGGED_IN_COOKIE,
            'value' => $logged_in_cookie,
            'expire' => $expiration
        ];
    }


    public static function ajax_register() {

        $out = array(
            'status' => 'error',
        );

        if( ! check_ajax_referer( 'ss-ajax-login-nonce', 'security', false ) ) {
            $out['message'] = __( 'Bad referral', 'shaversclub-store' );
            wp_die( json_encode( $out ) );
        }

        $cart = Cart::get_cart_from_session();
        SS_Logger::write( 'Customer:ajax_register' );

        if( isset( $_POST['email'], $_POST['password'] ) ) {
            SS_Logger::write( $_POST['email'] );

            if( false === HelperFunctions::verify_email( $_POST['email'] ) ) {
                $out['error_messages'][ 'email' ] = __( '<strong>ERROR</strong>: Invalid email', 'shaversclub-store' );
            } else {

                $signon = wp_insert_user( array (
                    'user_login' => $_POST['email'],
                    'user_email' => $_POST['email'],
                    'user_pass' => $_POST['password'],
                ) );

                if( is_wp_error( $signon ) ) {

                    $messages = array(
                        'existing_user_login' => 'email',
                        'existing_user_email' => 'email',
                        'incorrect_password' => 'password',
                    );

                    $out['error_messages'] = array();
                    foreach ( $signon->errors as $key => $message ) {
                        $key = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
                        $out['error_messages'][ $key ] = implode( '<br>', $message );
                    }

                } elseif( $user = get_user_by( 'id', $signon ) ) {

                    if( $campaign = $cart->get_campaign() ) {
                        SS_Logger::write( $campaign );
                        $cart->set_campaign( $campaign->ID );
                    }
                    SS_Logger::write( serialize($cart) );
                    update_user_meta( $user->ID, '_session_cart', $cart );

                    wp_set_current_user( $signon, $user->user_login );
                    wp_set_auth_cookie( $signon );
                    do_action( 'wp_login', $user->user_login );

                    $customer = new Customer( $user );
                    // $customer->make_ref();

                    if( isset( $_POST[ 'first_name' ] ) ) {
                        $customer->set_first_name( $_POST[ 'first_name' ] );
                    }

                    if( isset( $_POST[ 'last_name' ] ) ) {
                        $customer->set_last_name( $_POST[ 'last_name' ] );
                    }

                    $customer->save();

                    SS_Logger::write( $customer );
                    // Customer::mail_new( $customer->ID );
                    $page = get_page_by_path( 'mijn-account' );
                    $out = array(
                        'status' => 'success',
                        'logout_url' => wp_logout_url( '__cart_url__' ),
                        'url' => get_permalink( $page->ID ),
                        'user' => array(
                            'name' => $user->display_name,
                            'first_name' => $customer->meta( 'first_name' ),
                            'last_name' => $customer->meta( 'last_name' ),
                        ),
                    );
                } else {
                    SS_Logger::write( 'Failed to register 1' );
                    $out['message'] = __( 'Failed to register', 'shaversclub-store' );
                }
            }
        } else {
            SS_Logger::write( 'Failed to register 2' );
            $out['message'] = __( 'Failed to register', 'shaversclub-store' );
        }
        wp_die( json_encode( $out ) );
    }

    /**
     * register from shop.shaversclub.nl
     */
    public static function ajax_external_register() {

        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', PHP_EOL.'register ' . PHP_EOL, FILE_APPEND );
        $out = array(
            'status' => 'error',
        );
        //wp_die( json_encode(['test request']));
        //wp_die( json_encode( $_SESSION["HTTP_REFERER"] ) );

        SS_Logger::write( 'Customer:ajax_external_register' );

        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', json_encode($_POST) . PHP_EOL, FILE_APPEND );

        if( isset( $_POST['email'], $_POST['password'] ) ) {
            SS_Logger::write( $_POST['email'] );

            if( false === HelperFunctions::verify_email( $_POST['email'] ) ) {
                $out['error_messages'][ 'email' ] = __( '<strong>ERROR</strong>: Invalid email', 'shaversclub-store' );
            } else {

                $signon = wp_insert_user( array (
                    'user_login' => $_POST['email'],
                    'user_email' => $_POST['email'],
                    'user_pass' => $_POST['password'],
                ) );

                if( is_wp_error( $signon ) ) {

                    $messages = array(
                        'existing_user_login' => 'email',
                        'existing_user_email' => 'email',
                        'incorrect_password' => 'password',
                    );

                    $out['error_messages'] = array();
                    foreach ( $signon->errors as $key => $message ) {
                        $key = isset( $messages[ $key ] ) ? $messages[ $key ] : $key;
                        $out['error_messages'][ $key ] = implode( '<br>', $message );
                    }

                } elseif( $user = get_user_by( 'id', $signon ) ) {

                    wp_set_current_user( $signon, $user->user_login );
                    wp_set_auth_cookie( $signon );
                    do_action( 'wp_login', $user->user_login );

                    $customer = new Customer( $user );

                    if( isset( $_POST[ 'first_name' ] ) ) {
                        $customer->set_first_name( $_POST[ 'first_name' ] );
                    }

                    if( isset( $_POST[ 'last_name' ] ) ) {
                        $customer->set_last_name( $_POST[ 'last_name' ] );
                    }

                    $customer->save();

                    SS_Logger::write( $customer );
                    // Customer::mail_new( $customer->ID );
                    $page = get_page_by_path( 'mijn-account' );
                    $out = array(
                        'status' => 'success',
                        'logout_url' => wp_logout_url( '__cart_url__' ),
                        'url' => get_permalink( $page->ID ),
                        'user' => array(
                            'name' => $user->display_name,
                            'first_name' => $customer->meta( 'first_name' ),
                            'last_name' => $customer->meta( 'last_name' ),
                            'id' => $customer->ID,
                            'refCode' => $customer->ss_ref
                        ),
                    );
                } else {
                    SS_Logger::write( 'Failed to register 1' );
                    $out['message'] = __( 'Failed to register', 'shaversclub-store' );
                }
            }
        } else {
            SS_Logger::write( 'Failed to register 2' );
            $out['message'] = __( 'Failed to register', 'shaversclub-store' );
        }
        wp_die( json_encode( $out ) );
    }
}
