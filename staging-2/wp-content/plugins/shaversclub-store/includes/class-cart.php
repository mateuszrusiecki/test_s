<?php

class Cart extends CustomPostType {
    static $post_type = 'cart';
    static $post_type_plural = 'carts';

    private static $cart;

    private $subscriptions;
    private $products;
    private $campaign;
    private $ref_options;

    private $shadow_subs;

    public function __construct( $post = null ) {
        // (nog) geen carts in db, dus post negeren
        parent::__construct();
        $this->subscriptions = array();
        $this->products = array();
        $this->shadow_subs = array();
    }

    public static function get( $id = null ) {
        return static::$cart;
    }

    public static function _new() {
        static::$cart = new Cart;
        return static::$cart;
    }

    public static function get_cart_from_session() {
        static::$cart = isset( $_SESSION['cart'] ) ? unserialize( $_SESSION['cart'] ) : new Cart;

        if( is_user_logged_in() ) {
            $c = new Customer;
            $c_cart = $c->meta( '_session_cart' );

            if( is_string( $c_cart ) ) {
                $c_cart = @unserialize( $c_cart );
            }

            if( $c_cart instanceof Cart ) {
                $campaign = $c_cart->get_campaign();
                if( $campaign && is_int( $campaign ) ) {
                    $c_campaign = new Campaign( $campaign );
                    $c_cart->set_campaign( $c_campaign );
                }
                static::$cart = $c_cart;
            }
            static::$cart->save_session();
            delete_user_meta( $c->ID, '_session_cart' );
        }

        return static::$cart;
    }

    public function get_current_subscription( $make_if_empty = false ) {
        if( $make_if_empty && empty( $this->subscriptions ) ) {
            $subscription = new Subscription;
            $this->subscriptions[] = $subscription;
        }
        return current( $this->subscriptions );
    }

    public function add_subscription( $subscription ) {
        $this->subscriptions[] = $subscription;
    }
    public function remove_subscription( $subscription ) {
        $key = array_search( $subscription, $this->subscriptions );
        if( $key !== false ) {
            unset( $this->subscriptions[ $key ] );
        }
    }


    public function set_product_quantity( $product ) {
        $this->products[ $product->ID ] = $product->quantity;
    }

    public function add_product( $product ) {

        $id = 0;
        if( is_numeric( $product ) && ( $pid = intval( $product ) ) ) {
            $id = $pid;
        } elseif( ( $product instanceof \Product ) && $product->ID ) {
            $id = $product->ID;
        }

        if( $id ) {
            if( ! isset( $this->products[ $id ] ) ) {
                $this->products[ $id ] = 0;
            }
            $this->products[ $id ] += $product->quantity;
        }

    }

    public function get_products_price() {
        $price = 0;
        foreach( $this->products as $id => $quantity ) {
            if( $p = Product::get( $id ) ) {
                $price += $p->get_price() * $quantity;
            }
        }
        return $price;
    }

    public function get_subscription_price() {
        $price = 0;
        foreach( $this->subscriptions as $subscription ) {
            $price += $subscription->get_total_price( false, true );
        }
        return $price;
    }

    public function get_total_price() {
        return $this->get_products_price() + $this->get_subscription_price();
    }

    public function remove_product( $product ) {

        $id = 0;
        if( is_numeric( $product ) && ( $pid = intval( $product ) ) ) {
            $id = $pid;
        } elseif( ( $product instanceof \Product ) && $product->ID ) {
            $id = $product->ID;
        }

        if( $id && isset( $this->products[ $id ] ) ) {
            $this->products[ $id ] -= $product->quantity;
            if( $this->products[ $id ] <= 0 ) {
                unset( $this->products[ $id ] );
            }
        }

    }

    public function set_ref_options( $ref_options ) {
        $this->ref_options = $ref_options;
    }

    public function get_ref_options() {
        return $this->ref_options;
    }

    public function set_campaign( $campaign ) {
        $this->campaign = $campaign;
    }
    public function get_campaign() {
        return $this->campaign;
    }
    public function get_subscriptions() {
        return $this->subscriptions;
    }

    public function get_products() {
        return $this->products;
    }

    public function clear_subscriptions() {
        $this->subscriptions = array();
    }

    public function save_session() {
        $_SESSION['cart'] = serialize( $this );
    }

    public static function shortcodes() {
        add_shortcode( 'ss_cart', array( 'Cart', 'cart_view' ) );
        add_shortcode( 'ss_bamigo_cart', array( 'Cart', 'bamigo_cart_view' ) );
        add_shortcode( 'ss_123topdeal_cart', array( 'Cart', '_123topdeal_cart_view' ) );
        add_shortcode( 'ss_groupon_cart', array( 'Cart', 'groupon_cart_view' ) );
    }

    public static function cart_view( $atts ) {
        ob_start();
        include plugin_dir_path( __FILE__ ) . 'views/cart.php';
        return ob_get_clean();
    }

    public static function bamigo_cart_view( $atts ) {
        ob_start();
        include plugin_dir_path( __FILE__ ) . 'views/bamigo_cart.php';
        return ob_get_clean();
    }

    public static function _123topdeal_cart_view( $atts ) {
        ob_start();
        include plugin_dir_path( __FILE__ ) . 'views/123topdeal_cart.php';
        return ob_get_clean();
    }

    public static function groupon_cart_view( $atts ) {
        ob_start();
        include plugin_dir_path( __FILE__ ) . 'views/groupon_cart.php';
        return ob_get_clean();
    }

    public static function ajax() {
        add_action( 'wp_ajax_nopriv_select_product', array( 'Cart', 'ajax_select_product' ) );
        add_action( 'wp_ajax_select_product', array( 'Cart', 'ajax_select_product' ) );

        add_action( 'wp_ajax_nopriv_get_product_info', array( 'Cart', 'ajax_get_product_info' ) );
        add_action( 'wp_ajax_get_product_info', array( 'Cart', 'ajax_get_product_info' ) );

        add_action( 'wp_ajax_nopriv_ss_add_to_cart', array( 'Cart', 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_ss_add_to_cart', array( 'Cart', 'ajax_add_to_cart' ) );

        add_action( 'wp_ajax_nopriv_ss_remove_from_cart', array( 'Cart', 'ajax_remove_from_cart' ) );
        add_action( 'wp_ajax_ss_remove_from_cart', array( 'Cart', 'ajax_remove_from_cart' ) );

        add_action( 'wp_ajax_nopriv_ss_update_quantities', array( 'Cart', 'ajax_update_quantities' ) );
        add_action( 'wp_ajax_ss_update_quantities', array( 'Cart', 'ajax_update_quantities' ) );

        add_action( 'wp_ajax_nopriv_ss_get_cart_status', array( 'Cart', 'ajax_get_cart_status' ) );
        add_action( 'wp_ajax_ss_get_cart_status', array( 'Cart', 'ajax_get_cart_status' ) );

        add_action( 'wp_ajax_nopriv_select_interval', array( 'Cart', 'ajax_select_interval' ) );
        add_action( 'wp_ajax_select_interval', array( 'Cart', 'ajax_select_interval' ) );

        add_action( 'wp_ajax_nopriv_get_address', array( 'Cart', 'ajax_get_address' ) );
        add_action( 'wp_ajax_get_address', array( 'Cart', 'ajax_get_address' ) );

        add_action( 'wp_ajax_nopriv_ss_check_coupon', array( 'Cart', 'ajax_check_coupon' ) );
        add_action( 'wp_ajax_ss_check_coupon', array( 'Cart', 'ajax_check_coupon' ) );

        add_action( 'wp_ajax_place_order', array( 'Cart', 'ajax_place_order' ) );

        add_action( 'wp_ajax_nopriv_session_check', array( 'Cart', 'ajax_session_check' ) );
        add_action( 'wp_ajax_session_check', array( 'Cart', 'ajax_session_check' ) );

		add_action( 'wp_ajax_nopriv_test', array( 'Cart', '__test' ) );
		add_action( 'wp_ajax_test', array( 'Cart', '__test' ) );

        add_action( 'wp_ajax_nopriv_place_external_order', array( 'Cart', 'ajax_place_external_order' ) );
        add_action( 'wp_ajax_nopriv_export_users', array( 'Cart', 'ajax_export_users' ) );

        add_action( 'wp_ajax_nopriv_external_check_coupon', array( 'Cart', 'ajax_external_check_coupon' ) );

	}


    public static function __test() {
        /*
        if( is_user_logged_in() ) {
            $c = new Customer;
            $c_cart = $c->meta( '_session_cart' );
            var_dump($c_cart);
            wp_die();

            if( is_string( $c_cart ) ) {
                $c_cart = @unserialize( $c_cart );
            }

            if( $c_cart instanceof Cart ) {
                static::$cart = $c_cart;
            }
            //static::$cart->save_session();
            //delete_user_meta( $c->ID, '_session_cart' );
        }

        $cart = Cart::get_cart_from_session();
        $subscription = $cart->get_current_subscription( true );

        var_dump($subscription->get_campaigns());
*/

//$sub = new Subscription(24829);

        echo PHP_SESSION_ACTIVE == session_status();

        die();
        $c =  new Customer(3501);
        var_dump($c->meta('_session_cart'));
//echo $c->meta('_session_cart');
        $cart = Cart::get_cart_from_session();
        echo strlen(serialize($cart));
        echo ":";
        if( $campaign = $cart->get_campaign() ) {
            $cart->set_campaign( $campaign->ID );
        }
        echo strlen(serialize($cart));
        wp_die();
        var_dump($cart->get_campaign());
        $coupon = 'SWBSYDZTO8';

        $old_sub = Subscription::query_one( array(
            'post_status' => 'on-hold',
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => 'coupon', 'value' => $coupon ),
            ),
        ) );
        if( $old_sub && ( $old_ac = $old_sub->meta('active_campaign') ) ) {

            if( is_string( $old_ac ) ) {
                $old_ac = @unserialize( $old_ac );
            }

            if( is_array( $old_ac ) && isset( $old_ac['c'] ) ) {

                $old_campaign = new Campaign( $old_ac['c'] );
                if( $old_campaign->has_post() ) {

                    $cart->set_campaign( $old_campaign );
                    $out = array(
                        'status' => 'success',
                        'message' => $old_campaign->post_title,
                    );
                }

            }

        }

        wp_die();
        $a = HelperFunctions::get_address_details('7641xr', '187');
        var_dump($a);
        $customer = new Customer(1451);
        $a = Customer::mail_new( $customer->ID );
        var_dump($a);
    }

    public static function ajax_session_check() {
        wp_die( PHP_SESSION_ACTIVE == session_status() );
    }

    public static function ajax_check_coupon() {
        $out = array(
            'status' => 'error',
            'message' => __( 'Coupon code not found', 'shaversclub-store' ),
        );

        if( isset( $_POST['coupon'] ) && $_POST['coupon'] ) {

            $campaign = Campaign::query_one( array(
                'meta_key' => 'cc_' . $_POST[ 'coupon' ],
                'meta_value' => date( 'Y-m-d H:i:s' ),
                'meta_compare' => '>',
            ) );

            SS_Logger::write( 'Cart:ajax_check_coupon' );

            $cart = Cart::get_cart_from_session();
            $cart->set_campaign( null );
            if( $campaign ) {

                SS_Logger::write( $campaign );

                $cart->set_campaign( $campaign );
                $out = array(
                    'status' => 'success',
                    'message' => $campaign->post_title,
                );
            } else {

                $old_sub = Subscription::query_one( array(
                    'post_status' => 'on-hold',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array( 'key' => 'coupon', 'value' => $_POST['coupon'] ),
                    ),
                ) );

                SS_Logger::write( $old_sub );

                if( $old_sub && ( $old_ac = $old_sub->meta('active_campaign') ) ) {

                    if( is_string( $old_ac ) ) {
                        $old_ac = @unserialize( $old_ac );
                    }

                    SS_Logger::write( $old_ac );

                    if( is_array( $old_ac ) && isset( $old_ac['c'] ) ) {

                        $old_campaign = new Campaign( $old_ac['c'] );

                        SS_Logger::write( $old_campaign );

                        if( $old_campaign->has_post() ) {

                            $cart->set_campaign( $old_campaign );
                            $out = array(
                                'status' => 'success',
                                'message' => $old_campaign->post_title,
                            );
                        }

                    }

                } else {

                    $customer = new Customer;
                    $users = $user_query = new WP_User_Query( array(
                        'meta_key' => 'ss_ref',
                        'meta_value' => $_POST[ 'coupon' ],
                        'exclude' => $customer->ID ? [ $customer->ID ] : [],
                    ) );

                    if( $users->results ) {

                        $user = $users->results[0];
                        $referrer = new Customer( $user );
                        $_SESSION[ 'ss_ref' ] = $_POST[ 'coupon' ];

                        if(
                            ( ! $customer->ID || ( $referrer->ID != $customer->ID ) )
                            && ( ! $customer->meta( 'ss_used_ref' ) )
                        ) {

                            $options = json_decode( get_option( 'ss-referral-options', '[]' ), true );

                            if( is_array( $options ) ) {
                                $cart->set_ref_options( $options );
                            }

                            $out = array(
                                'status' => 'success',
                                'message' => 'Doorverwezen door ' . $user->display_name,
                            );

                        }

                    }

                }
            }
            $cart->save_session();
            $out = array_merge( $out, $cart->apply_discount() );
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_external_check_coupon() {
        $out = array(
            'status' => 'error',
            'message' => __( 'Coupon code not found', 'shaversclub-store' ),
        );

        if(empty( $_POST['coupon'])) {
            wp_die( json_encode( $out ) );
        }

        $campaign = Campaign::query_one( array(
            'meta_key' => 'cc_' . $_POST[ 'coupon' ],
            'meta_value' => date( 'Y-m-d H:i:s' ),
            'meta_compare' => '>',
        ) );

        SS_Logger::write( 'Cart:ajax_external_check_coupon' );

        //$cart = Cart::get_cart_from_session();
        //$cart->set_campaign( null );
        if( $campaign ) {
            SS_Logger::write( $campaign );

            //$cart->set_campaign( $campaign );
            $out = array(
                'status' => 'success',
                'message' => $campaign->post_title,
            );
        } else {

            $old_sub = Subscription::query_one( array(
                'post_status' => 'on-hold',
                'meta_query' => array(
                    'relation' => 'AND',
                    array( 'key' => 'coupon', 'value' => $_POST['coupon'] ),
                ),
            ));

            SS_Logger::write( $old_sub );

            if( $old_sub && ( $old_ac = $old_sub->meta('active_campaign') ) ) {
                $out = self::oldSub($old_ac, $out);
            } else {
                $out = self::refCoupon($_POST['coupon'], $out);
            }
        }

        $out = array_merge( $out, self::apply_custom_discount($campaign, $_POST['total'], $out['options']) );


        wp_die( json_encode( $out ) );
    }

    private static function oldSub($old_ac, $out)
    {
        if( is_string( $old_ac ) ) {
            $old_ac = @unserialize( $old_ac );
        }

        SS_Logger::write( $old_ac );

        if( is_array( $old_ac ) && isset( $old_ac['c'] ) ) {

            $old_campaign = new Campaign( $old_ac['c'] );

            SS_Logger::write( $old_campaign );

            if( $old_campaign->has_post() ) {

                return array(
                    'status' => 'success',
                    'old_campaign' => $old_campaign,
                );
            }

        }

        return $out;
    }

    private static function refCoupon($coupon, $out)
    {
        $customer = new Customer;
        $users = $user_query = new WP_User_Query( array(
            'meta_key' => 'ss_ref',
            'meta_value' => $coupon,
            'exclude' => $customer->ID ? [ $customer->ID ] : [],
        ) );

        if( $users->results ) {

            $user = $users->results[0];
            $referrer = new Customer( $user );
            $_SESSION[ 'ss_ref' ] = $coupon;

            if(
                ( ! $customer->ID || ( $referrer->ID != $customer->ID ) )
                && ( ! $customer->meta( 'ss_used_ref' ) )
            ) {

                $options = json_decode( get_option( 'ss-referral-options', '[]' ), true );

                return array(
                    'status' => 'success',
                    'options' => $options,
                    'message' => 'Doorverwezen door ' . $user->display_name,
                );

            }

        }

        return $out;
    }

    public static function apply_custom_discount($campaign, $total_price, $options = [])
    {
        $discount = 0;

        if( $campaign ) {
            $tps = $campaign->apply( $total_price, true, true );
            $total_price = $tps[0];
            $discount += $tps[1];
        }

        if($options) {

            $amount = isset( $options[ 'a' ] ) ? floatval( $options[ 'a' ] ) : 0 ;
            $max = 0.01;

            $new_total_price = ( isset( $options[ 'p' ] ) && $options[ 'p' ] )
                ? max( $max, $total_price * ( ( 100 - $amount ) / 100 ) )
                : max( $max, $total_price - $amount );
            $discount += $total_price - $new_total_price;
            $total_price = $new_total_price;

        }

        return compact( 'total_price', 'discount' );
    }

    public function apply_discount() {

        $discount = 0;
        $total_price = $this->get_total_price();

        if( $campaign = $this->get_campaign() ) {
            $tps = $campaign->apply( $total_price, true, true );
            $total_price = $tps[0];
            $discount += $tps[1];
        }

        if( $options = (array)$this->get_ref_options() ) {

            $amount = isset( $options[ 'a' ] ) ? floatval( $options[ 'a' ] ) : 0 ;
            $max = 0.01;

            $new_total_price = ( isset( $options[ 'p' ] ) && $options[ 'p' ] )
                ? max( $max, $total_price * ( ( 100 - $amount ) / 100 ) )
                : max( $max, $total_price - $amount );
            $discount += $total_price - $new_total_price;
            $total_price = $new_total_price;

        }

        return compact( 'total_price', 'discount' );

    }

    public static function ajax_get_product_info() {
        $out = [ 'status' => 'error' ];

        SS_Logger::write( 'Cart:ajax_get_product_info' );

        if( isset( $_POST['id'] ) ) {
            if( $product = Product::get( $_POST['id'] ) ) {
                SS_Logger::write( $product );

                $op = $product->get_other_recurring_product();
                $rp = $product->meta( 'is_serviceable' ) ? ($op ? $op : $product) : false;

                $img = wp_get_attachment_image_src( $product->meta( '_thumbnail_id' ), 'full' );
                $out = [
                    'id' => $product->ID,
                    'price' => $product->get_price(),
                    'title' => $product->meta( 'frontend_title' ),
                    'is_serviceable' => $product->meta( 'is_serviceable' ),
                    'other_recurring_product' => $op ? $op->ID : '',
                    'recurring_product_name' => $rp ? get_the_title( $rp->ID ) : '',
                    'recurring_price' => $rp ? $rp->get_price() : '',
                    'img' => isset( $img, $img[0] ) ? $img[0] : '',
                ];
            }
        }

        wp_die( json_encode( $out ) );

    }


    public static function ajax_get_cart_status() {

        $option_key = 'ss-' . \Subscription::$post_type . '-options';
        $options = json_decode( get_option( $option_key, '[]' ), true );

        $iv_options;
        if( isset( $options['interval_options'] ) ) {
            $iv_options = $options['interval_options'];
        }
        if( ! is_array( $iv_options ) ) {
            $iv_options = array();
        }

        $cart = Cart::get_cart_from_session();

        $subscriptions = $products = $shadow_subs = [];

        foreach( $cart->get_subscriptions() as $s ) {
            $ps = (array)$s->get_recurring_products();
            $p = current( $ps );
            if( ! $p ) {
                continue;
            }


            $ips = (array)$s->get_initial_products();
            $ip = current( $ips );
            // var_dump($ip);
            if( ! $ip ) {
                $ip = $p;
            }

            $key = HelperFunctions::format_date_interval( $s->get_interval() );
            $iv_label = '';
            if( isset( $iv_options[ $key ] ) ) {
                $iv_value = unserialize( $iv_options[ $key ] );
                $iv_label = $iv_value['label'];
            }
            $img = wp_get_attachment_image_src( $p->meta( '_thumbnail_id' ), 'thumbnail' );
            $img = $img ? $img[0] : '' ;

            $shipping_date = HelperFunctions::get_next_dow();

            $subscription_data = [
                'id' => $ip->ID,
                'title' => $p->meta( 'frontend_title' ),
                'product_line' => $p->meta( 'product_line' ),
                'subline' => $p->meta( 'subline' ),
                'quantity' => $p->quantity,
                'price' => $s->get_recurring_price(),
                'initial_price' => $s->get_initial_price() / $p->quantity,
                'img' => $img,
                'interval' => $key,
                'interval_label' => $iv_label,
                'shipping_date' => $shipping_date->format( 'd.m.Y' ),
                'recurring_product_name' => get_the_title( $p->ID ),
            ];

            $ps = (array)$s->get_initial_products();
            $ip = current( $ps );
            if( $ip && ( $ip->ID != $p->ID ) ) {

                $img = wp_get_attachment_image_src( $ip->meta( '_thumbnail_id' ), 'thumbnail' );
                $img = $img ? $img[0] : '' ;

                $subscription_data[ 'initial_title' ] = $ip->meta( 'frontend_title' );
                $subscription_data[ 'initial_product_line' ] = $ip->meta( 'product_line' );
                $subscription_data[ 'initial_subline' ] = $ip->meta( 'subline' );
                $subscription_data[ 'initial_img' ] = $img;

            }
            $subscriptions[] = $subscription_data;
        }

        foreach( $cart->get_products() as $id => $quantity ) {
            if( $product = Product::get( $id ) ) {

                $src = '';
                if( $thumb_id = $product->meta('_thumbnail_id') ) {
                    $img = wp_get_attachment_image_src( $thumb_id, 'full' );
                    if( $img && isset( $img[0] ) ) {
                        $src = $img[0];
                    }
                }

                $op = $product->get_other_recurring_product();
                $rp = $product->meta( 'is_serviceable' ) ? ($op ? $op : $product) : false;

                $products[ $id ] = [
                    'title' => $product->meta( 'frontend_title' ),
                    'product_line' => $product->meta( 'product_line' ),
                    'subline' => $product->meta( 'subline' ),
                    'quantity' => $quantity,
                    'price' => $product->get_price(),
                    'is_serviceable' => $product->meta( 'is_serviceable' ),
                    'other_recurring_product' => $op ? $op->ID : '',
                    'recurring_product_name' => $rp ? get_the_title( $rp->ID ) : '',
                    'recurring_price' => $rp ? $rp->get_price() : '',
                    'img' => $src,
                ];
            }
        }

        //$shadow_subs[0] = "aa";
        //$shadow_subs[1] = "bb";
        $shadow_subs[] = $cart->shadow_subs;


        foreach( $shadow_subs as $ss ) {

            /*
                        $ps = (array)$s->get_recurring_products();
                        $p = current( $ps );
                        if( ! $p ) {
                            continue;
                        }


                        $ips = (array)$s->get_initial_products();
                        $ip = current( $ips );
                        // var_dump($ip);
                        if( ! $ip ) {
                            $ip = $p;
                        }

                        $key = HelperFunctions::format_date_interval( $s->get_interval() );
                        $iv_label = '';
                        if( isset( $iv_options[ $key ] ) ) {
                            $iv_value = unserialize( $iv_options[ $key ] );
                            $iv_label = $iv_value['label'];
                        }
                        $img = wp_get_attachment_image_src( $p->meta( '_thumbnail_id' ), 'thumbnail' );
                        $img = $img ? $img[0] : '' ;

                        $shipping_date = HelperFunctions::get_next_dow();

                        $subscription_data = [
                            'id' => $ip->ID,
                            'title' => $p->meta( 'frontend_title' ),
                            'product_line' => $p->meta( 'product_line' ),
                            'subline' => $p->meta( 'subline' ),
                            'quantity' => $p->quantity,
                            'price' => $s->get_recurring_price(),
                            'initial_price' => $s->get_initial_price() / $p->quantity,
                            'img' => $img,
                            'interval' => $key,
                            'interval_label' => $iv_label,
                            'shipping_date' => $shipping_date->format( 'd.m.Y' ),
                            'recurring_product_name' => get_the_title( $p->ID ),
                        ];

                        $ps = (array)$s->get_initial_products();
                        $ip = current( $ps );
                        if( $ip && ( $ip->ID != $p->ID ) ) {

                            $img = wp_get_attachment_image_src( $ip->meta( '_thumbnail_id' ), 'thumbnail' );
                            $img = $img ? $img[0] : '' ;

                            $subscription_data[ 'initial_title' ] = $ip->meta( 'frontend_title' );
                            $subscription_data[ 'initial_product_line' ] = $ip->meta( 'product_line' );
                            $subscription_data[ 'initial_subline' ] = $ip->meta( 'subline' );
                            $subscription_data[ 'initial_img' ] = $img;

                        }
            */
            //		foreach($ss as $key => $value){
            //		$subscription_data = [
            //'id' => $key
            /*,
            'title' => $p->meta( 'frontend_title' ),
            'product_line' => $p->meta( 'product_line' ),
            'subline' => $p->meta( 'subline' ),
            'quantity' => $p->quantity,
            'price' => $s->get_recurring_price(),
            'initial_price' => $s->get_initial_price() / $p->quantity,
            'img' => $img,
            'interval' => $key,
            'interval_label' => $iv_label,
            'shipping_date' => $shipping_date->format( 'd.m.Y' ),
            'recurring_product_name' => get_the_title( $p->ID ),
*/
            //	];

            //	$subscriptions[$key] = $subscription_data;
            //}


        }

        wp_die( json_encode( compact( 'subscriptions', 'products','shadow_subs' ) ) );

    }

    public static function ajax_add_to_cart() {
        $out = [ 'status' => 'error' ];

        SS_Logger::write( 'Cart:ajax_add_to_cart' );

        if( isset( $_POST[ 'id' ] ) ) {

            SS_Logger::write( '-- id key --' );
            $cart = Cart::get_cart_from_session();

            if( $_POST[ 'id' ] == 'sub' && ! empty( $cart->get_subscriptions() ) ) {
                $out = [ 'status' => 'success' ];
            } elseif( $product = Product::get( $_POST['id'] ) ) {
                $product->quantity = isset( $_POST['quantity'] ) ? max( 1, intval( $_POST['quantity'] ) ) : 1;
                SS_Logger::write( $product );
                SS_Logger::write( $product->quantity );
                $cart->add_product( $product );
                $cart->save_session();
                $out = [ 'status' => 'success' ];
            }

            if (!isset($_COOKIE['wp_user_token'])) {
                $cookieExpire = 2592000; //30 days (86400 * 30)
                $cookieLength = 10;
                $random = openssl_random_pseudo_bytes($cookieLength);
                setcookie('wp_user_token', bin2hex($random), time() + $cookieExpire, '/', '.shaversclub.nl');
            }

            $data = [
                "id" => $_POST['id'],
                "quantity" => $_POST['quantity'],
                "subscription" => false,
                "wp_user_token" => $_COOKIE['wp_user_token']
            ];

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, API_URL);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

            curl_setopt($curl, CURLOPT_HEADER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json',));

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($curl);
            $response = curl_getinfo( $curl );
            curl_close($curl);

            $out['send'] = $data;
            $out['result'] = $result;
        }

        if( isset( $_POST['products'] ) ) {

            $cart = Cart::get_cart_from_session();
            SS_Logger::write( '-- products key --' );

            foreach( $_POST['products'] as $pid  => $quantity ) {
                if( $product = Product::get( $pid ) ) {
                    $product->quantity = max( 1, intval( $quantity ) );
                    SS_Logger::write( $product );
                    SS_Logger::write( $product->quantity );
                    $cart->add_product( $product );
                }
            }

            $cart->save_session();
            $out = [ 'status' => 'success' ];
        }

        wp_die( json_encode( $out ) );

    }

    public static function ajax_update_quantities() {
        $out = [ 'status' => 'success' ];

        SS_Logger::write( 'Cart:ajax_update_quantities' );

        if( isset( $_POST['quantities'] ) && ( $quantities = (array)$_POST['quantities'] ) ) {

            $q_log = json_encode($quantities) . "\n";

            $cart = Cart::get_cart_from_session();

            foreach( $quantities as $id => $quantity ) {
                if( $id == 'sub' ) {
                    $q_log .= " -- sub -- \n";
                    if( $subscription = $cart->get_current_subscription() ) {
                        foreach ( $subscription->get_initial_products() as $p ) {
                            $p->quantity = $quantity;
                            $q_log .= " -- ini $p->ID : $p->quantity -- \n";
                        }
                        foreach ( $subscription->get_recurring_products() as $p ) {
                            $p->quantity = $quantity;
                            $q_log .= " -- rec $p->ID : $p->quantity -- \n";
                        }
                    }
                } elseif( $product = \Product::get( $id ) ) {
                    $product->quantity = $quantity;
                    $cart->set_product_quantity( $product );
                    $q_log .= " -- order $product->ID : $product->quantity -- \n";
                }
            }

            SS_Logger::write( $q_log );

            $cart->save_session();
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_remove_from_cart() {
        $out = [ 'status' => 'error' ];

        SS_Logger::write( 'Cart:ajax_remove_from_cart' );

        if( isset( $_POST['id'] ) ) {

            $cart = Cart::get_cart_from_session();

            if( $_POST['id'] == 'sub' ) {
                $cart->clear_subscriptions();
                $cart->save_session();
                $out = [ 'status' => 'success' ];
            } elseif( $product = Product::get( $_POST['id'] ) ) {
                SS_Logger::write( $product );
                $product->quantity = 999999;
                $cart->remove_product( $product );
                $cart->save_session();
                $out = [ 'status' => 'success' ];
            }
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_select_product() {
        $out = array(
            'status' => 'error',
        );

        SS_Logger::write( 'Cart:ajax_select_product' );

        if( isset( $_POST['id'] ) ) {
            $cart = Cart::get_cart_from_session();
            $subscription = $cart->get_current_subscription( true );

            $product = $_POST[ 'id' ] == 'sub'
                ? current( (array)$subscription->get_initial_products() )
                : Product::get( $_POST['id'] );

            if( $product ) {

                SS_Logger::write( $product );
                $product->quantity = isset( $_POST['quantity'] ) ? max( 1, intval( $_POST['quantity'] ) ) : 1;

                $subscription->clear_initial_products();
                $subscription->add_initial_product( $product );

                $subscription->clear_recurring_products();
                $subscription->clear_recurring_packages();
                //unset($cart->shadow_subs);
                if( $recurring_product = $product->get_other_recurring_product() ) {
                    $recurring_product->quantity = $product->quantity;
                    $subscription->add_recurring_product( $recurring_product );
                } else {
                    $subscription->add_recurring_product( $product );
                }

                array_push($cart->shadow_subs, $product);
                //$this->shadow_subs[] = "aaaaa";
                //$this->shadow_subs[] = "bbbbb";


                /**
                 * ------------------------------------
                 */
                //$url = 'http://localhost:8073/api/cart';
                //$url = 'http://136.144.214.107/api/cart';
//                $url = 'https://shop.shaversclub.nl/api/cart';
//
//                $data = [
//                    "id" => $_POST['id'],
//                    "quantity" => $_POST['quantity'],
//                    "subscription" => true,
//                    "wp_user_token" => $_COOKIE['wp_user_token']
//                ];
//
//                $curl = curl_init();
//                curl_setopt($curl, CURLOPT_URL, $url);
//                curl_setopt($curl, CURLOPT_POST, 1);
//                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
//
//                curl_setopt($curl, CURLOPT_HEADER, 1);
//                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json',));
//
//                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//
//                $result = curl_exec($curl);
//                $response = curl_getinfo( $curl );
//                curl_close($curl);
//
//                $out['send'] = $data;
//                $out['response'] = $response;
//                $out['result'] = $result;

                /**
                 * ------------------------------------
                 */

                $cart->save_session();

                $total_price = $subscription->get_total_price( false, true );

                $discount = 0;
                if( $campaign = $cart->get_campaign() ) {
                    /*
                                        $ips = $campaign->apply( $initial_price, true, true );
                                        $initial_price = $ips[0];
                                        $discount = $ips[1];
                    */
                    $tps = $campaign->apply( $total_price, true, true );
                    $total_price = $tps[0];
                    $discount = $tps[1];
                }

                $customer = new Customer;
                $referrer = $amount = false;
                if( isset( $_SESSION[ 'ss_ref' ] ) && ! empty( $_SESSION[ 'ss_ref' ] ) ) {

                    if( $customer->ID && ( $ssur = $customer->meta('ss_used_ref') ) ) {
                        SS_Logger::write( '-- Al een referral gehad: ' . $ssur . ' --' );
                    } else {

                        $users = $user_query = new WP_User_Query( array(
                            'meta_key' => 'ss_ref',
                            'meta_value' => $_SESSION[ 'ss_ref' ],
                            'exclude' => array( $customer->ID ),
                        ) );

                        if( ! empty( $users->results ) ) {
                            $user = $users->results[0];
                            $referrer = new Customer( $user );

                            SS_Logger::write( $referrer );

                            if( $referrer->ID && ( $referrer->ID != $customer->ID ) ) {
                                $options = json_decode( get_option( 'ss-referral-options', '[]' ), true );

                                if( empty( $options ) ) {
                                    SS_Logger::write( '-- Geen referral opties --' );
                                    return false;
                                } else {

                                    $_SESSION[ 'ss_ref_id' ] = $referrer->ID;

                                    $max = 0.01;
                                    $amount = isset( $options[ 'a' ] ) ? floatval( $options[ 'a' ] ) : 0 ;

                                    if( isset( $options[ 'p' ] ) && $options[ 'p' ] ) {
                                        $new_total_price = max( $max, $total_price * ( ( 100 - $amount ) / 100 ) );
                                    } else {
                                        $new_total_price = max( $max, $total_price - $amount );
                                    }

                                    $discount += $total_price - $new_total_price;
                                    $total_price = $new_total_price;
                                }

                            } else {
                                SS_Logger::write( '-- Zelf referral --' );
                                $referrer = false;
                            }
                        }

                    }

                }


                $out = array(
                    'status' => 'success',
                    'pid' => $product->ID,
                    'title' => $product->get_title( null ),
                    'razor_img' => $product->get_razor_thumbnail('medium'),
                    'blades_img' => $product->get_blades_thumbnail('medium'),
                    'initial_price' => $subscription->get_initial_price( true ),
                    'shipping_price' => $subscription->get_shipping( true ),
                    'total_price' => HelperFunctions::format_price( $total_price ),
                );
                if( $discount ) {
                    $out['discount'] = HelperFunctions::format_price( $discount );
                }
                if( $referrer ) {
                    $out['referrer'] = array( $referrer->display_name, $amount );
                }
            }
        }
        wp_die( json_encode( $out ) );
    }

    public static function ajax_select_interval() {
        $out = array(
            'status' => 'error',
        );

        SS_Logger::write( 'Cart:ajax_select_interval' );

        if( isset( $_POST['interval'] ) ) {
            $cart = Cart::get_cart_from_session();
            $subscription = $cart->get_current_subscription( true );

            $di = DateInterval::createFromDateString( $_POST['interval'] );
            $key = HelperFunctions::format_date_interval( $di );

            SS_Logger::write( $key );

            if( ! empty( $key ) ) {
                $option_key = 'ss-' . Subscription::$post_type . '-options';
                $options = json_decode( get_option( $option_key, '[]' ), true );

                if( isset( $options['interval_options'], $options['interval_options'][ $key ] ) ) {
                    $iv_value = unserialize( $options['interval_options'][ $key ] );
                    $subscription->set_interval( $iv_value[ 'di' ] );
                    $cart->save_session();
                    $out = array(
                        'status' => 'success',
                        'interval' => $key,
                        'message' => sprintf( __( 'Subscription: %s (4 cartridges every %d months, free delivery)', 'shaversclub-store' ), $subscription->get_recurring_price( true ), $di->m ),
                    );
                }
            }

        }
        wp_die( json_encode( $out ) );
    }

    public static function ajax_get_address() {
        $out = array(
            'status' => 'error',
            'message' => __( 'No address found with this postcode and number', 'shaversclub-store' ),
        );

        if( isset( $_POST['postcode'], $_POST['number'] ) ) {
            if( $ad = HelperFunctions::get_address_details( $_POST['postcode'], $_POST['number'] ) ) {
                $out = array(
                    'status' => 'success',
                    'ad' => $ad,
                );
            }
        }
        wp_die( json_encode( $out ) );
    }

    public static function ajax_place_order() {
        $out = array(
            'status' => 'error',
            'message' => __( 'Could not place order', 'shaversclub-store' ),
        );

        /*, 'shipping_house_number', 'shipping_postcode', 'shipping_street_name', 'shipping_city'*/
        $required = [
            'first_name' => __( 'First name', 'shaversclub-store' ),
            'last_name' => __( 'Last name', 'shaversclub-store' ),
            'billing_house_number' => __( 'House number', 'shaversclub-store' ),
            'billing_postcode' => __( 'Postcode', 'shaversclub-store' ),
            'billing_street_name' => __( 'Street name', 'shaversclub-store' ),
            'billing_city' => __( 'City', 'shaversclub-store' ),
            'payment' => __( 'Payment', 'shaversclub-store' ),
        ];

        foreach ( $required as $key => $label ) {
            if( ! isset( $_POST[ $key ] ) || empty( trim( $_POST[ $key ] ) ) ) {
                $out[ 'message' ] = sprintf(__( '"%s" is a mandatory field', 'shaversclub-store' ), $label );
                wp_die( json_encode( $out ) );
            }
        }

        $cart = Cart::get_cart_from_session();
        $subscription = $cart->get_current_subscription( true );
        $cart_products = (array)$cart->get_products();
        $products = [];
        foreach( $cart_products as $id => $quantity ) {

            if( $quantity <= 0 ) {
                continue;
            }

            $product = \Product::get( $id );

            if( ! $product ) {
                continue;
            }

            $product->quantity = $quantity;
            $products[] = $product;
        }
        /*
                if( $subscription->has_post() ) {
                    $subscription->_fill_vars();
                }
        */

        $no_sub = empty( $subscription->get_initial_products() ) || empty( $subscription->get_interval() );
        $no_products = empty( $products );

        if( $no_sub && $no_products ) {
            $out['message'] = __( 'Could not place order, cart is empty', 'shaversclub-store' );
            wp_die( json_encode( $out ) );
        }
        /*
                if( empty( $subscription->get_initial_products() ) || empty( $subscription->get_interval() ) ) {
                    $out['message'] = __( 'Could not place order, make sure you have selected a product and an interval.', 'shaversclub-store' );
                    wp_die( json_encode( $out ) );
                }
        */
        $out = array(
            'status' => 'success',
        );

        $customer = new Customer;
        $customer->set_first_name( $_POST[ 'first_name' ] );
        $customer->set_last_name( $_POST[ 'last_name' ] );

        $customer->set_billing_house_number( $_POST[ 'billing_house_number' ] );
        $customer->set_billing_house_number_suffix( $_POST[ 'billing_house_number_suffix' ] );
        $customer->set_billing_postcode( $_POST[ 'billing_postcode' ] );
        $customer->set_billing_street_name( $_POST[ 'billing_street_name' ] );
        $customer->set_billing_extra_line( $_POST[ 'billing_extra_line' ] );
        $customer->set_billing_city( $_POST[ 'billing_city' ] );
        $customer->set_billing_country( 'NL' );

        $customer->set_shipping_house_number( $_POST[ 'billing_house_number' ] );
        $customer->set_shipping_house_number_suffix( $_POST[ 'billing_house_number_suffix' ] );
        $customer->set_shipping_postcode( $_POST[ 'billing_postcode' ] );
        $customer->set_shipping_street_name( $_POST[ 'billing_street_name' ] );
        $customer->set_shipping_extra_line( $_POST[ 'billing_extra_line' ] );
        $customer->set_shipping_city( $_POST[ 'billing_city' ] );
        $customer->set_shipping_country( 'NL' );

//		$customer->set_iban( $_POST[ 'iban' ] );

        $customer->save();

        $campaign = Campaign::query_one( array(
            'meta_key' => 'cc_' . $_POST[ 'coupon' ],
            'meta_value' => date( 'Y-m-d H:i:s' ),
            'meta_compare' => '>',
        ) );

        SS_Logger::write( 'Cart:ajax_place_order' );
        SS_Logger::write( $customer );

        $order = false;

        $campaign_used = $campaign && $customer->is_used_coupon( $_POST[ 'coupon' ] );

        if( ! $no_sub ) {

            $subscription->clear_campaigns( true );
            $subscription->set_payment( $_POST[ 'payment' ] );
            $subscription->set_customer( $customer );
            //		$subscription->activate();
            $subscription->next_order = new DateTime;


            if( ! $campaign_used ) {

                if( $campaign ) {
                    $campaign->remove_coupon( $_POST[ 'coupon' ] );
                    $campaign->save();
                    $subscription->add_campaign( $campaign, true );
                    $subscription->set_coupon( $_POST[ 'coupon' ] );

                    $campaign_used = true;

                    SS_Logger::write( $campaign );

                } else {

                    $old_sub = Subscription::query_one( array(
                        'post_status' => 'on-hold',
                        'meta_query' => array(
                            'relation' => 'AND',
                            array( 'key' => 'coupon', 'value' => $_POST['coupon'] ),
                        ),
                    ) );

                    SS_Logger::write( $old_sub );

                    if( $old_sub && ( $old_ac = $old_sub->meta('active_campaign') ) ) {

                        if( is_string( $old_ac ) ) {
                            $old_ac = @unserialize( $old_ac );
                        }

                        SS_Logger::write( $old_ac );

                        if( is_array( $old_ac ) && isset( $old_ac['c'] ) ) {

                            $old_campaign = new Campaign( $old_ac['c'] );

                            SS_Logger::write( $old_campaign );

                            if( $old_campaign->has_post() ) {

                                $subscription->add_campaign( $old_campaign, true );
                                $subscription->set_coupon( $_POST[ 'coupon' ] );

                                $campaign_used = true;

                                if( $old_orders = $old_sub->get_orders( -1 ) ) {
                                    foreach ( $old_orders as $o ) {
                                        wp_trash_post( $o->ID );
                                    }
                                }

                                if( $old_sub->ID != $subscription->ID ) {
                                    wp_trash_post( $old_sub->ID );
                                }
                            }

                        }

                    }

                }
            }

            if( $subscription->has_post() ) {
                $orders = $subscription->get_orders(-1); // there can be max 1
                foreach ( $orders as $o ) {
                    wp_trash_post( $o->ID );
                }
            }

            $subscription->save();
            $order = $subscription->to_order( true );

            SS_Logger::write( $subscription );
        }

        if( ! $order ) {
            $order = new SS_Order;
        }

        if( isset( $_POST[ 'shipping' ] ) && ( $_POST[ 'shipping' ] == 'express' ) ) {
            $order->set_shipping( 'express' );
            $order->shipping_price = 3.5;
        }

        $method = isset( $_POST[ 'payment' ] ) ? $_POST[ 'payment' ] : 'ideal';
        if( ! in_array( $method, [ 'ideal', 'sepadirectdebit', 'directdebit' ] ) ) {
            $method = 'ideal';
        }
        $order->payment = $method;

        if( ! $no_products ) {
            foreach( $products as $product ) {
                if( isset( $order->products[ $product->ID ] ) ) {
                    $product->quantity += $order->products[ $product->ID ];
                }
                $order->add_product( $product );
            }
        }

        if( ! $campaign_used ) {

            $max = $order->payment == 'ideal' ? 0.01 : 0;

            if( $campaign ) {

                if( $campaign->percent ) {
                    $order->price = max( $max, $order->get_price() * ( ( 100 - $campaign->amount ) / 100 ) );
                } else {
                    $order->price = max( $max, $order->get_price() - $campaign->amount );
                }

                $order->campaign = array(
                    'p' => $campaign->percent,
                    'a' => $campaign->amount,
                    'c' => $campaign->ID,
                );

                $order->set_coupon( $_POST[ 'coupon' ] );
                $campaign->remove_coupon( $_POST[ 'coupon' ] );

            } else {

                $old_order = SS_Order::query_one( array(
                    'post_status' => [ 'cancelled', 'failed', 'pending' ],
                    'meta_query' => array(
                        'relation' => 'AND',
                        array( 'key' => 'coupon', 'value' => $_POST[ 'coupon' ] ),
                    ),
                ) );

                SS_Logger::write( $old_order );

                if( $old_order && ( $old_ac = $old_order->campaign ) ) {

                    if( is_string( $old_ac ) ) {
                        $old_ac = @unserialize( $old_ac );
                    }

                    SS_Logger::write( $old_ac );

                    if( is_array( $old_ac ) && isset( $old_ac['c'] ) ) {

                        $old_campaign = new Campaign( $old_ac['c'] );

                        SS_Logger::write( $old_campaign );

                        if( $old_campaign->has_post() ) {

                            if( $old_campaign->percent ) {
                                $order->price = max( $max, $order->get_price() * ( ( 100 - $old_campaign->amount ) / 100 ) );
                            } else {
                                $order->price = max( $max, $order->get_price() - $old_campaign->amount );
                            }

                            $order->campaign = array(
                                'p' => $old_campaign->percent,
                                'a' => $old_campaign->amount,
                                'c' => $old_campaign->ID,
                            );

                            $order->set_coupon( $_POST[ 'coupon' ] );
                            // $old_campaign->remove_coupon( $_POST[ 'coupon' ] );

                            $campaign_used = true;

                            if( $old_order->ID != $order->ID ) {
                                wp_trash_post( $old_order->ID );
                            }
                        }

                    }

                }

            }

        }

        $used_ref = false;
        if( isset( $_SESSION[ 'ss_ref' ] ) && ! empty( $_SESSION[ 'ss_ref' ] ) ) {
            $used_ref = $order->apply_ref( $_SESSION[ 'ss_ref' ] );
        }

        $order->save( [ 'post_status' => 'pending' ] );

        SS_Logger::write( $order );

        $cart->save_session();

        //$out[ 'form' ] = $order->make_adyen_form();

        $out = $order->make_mollie_payment();

        if( $used_ref && is_array( $out ) && isset( $out['status'] ) && ( $out['status'] == 'success' ) ) {
            update_user_meta( $customer->ID, 'ss_used_ref', $order->ID . ':' . $used_ref );
        }

        SS_Logger::write( $out );

        wp_die( json_encode( $out ) );
    }

    public function ajax_export_users() {
        global $wpdb;

        //users 18834
        $start = 0;
        $stop = 1000;
        $usersStack = [];
        for($i=1; $i<=2; $i++) {
            $users = $wpdb->get_results("SELECT * FROM wpstg0_users LIMIT $start,$stop");
            $sql = 'INSERT INTO `users` (`email`, `password`, `created_at`, `user_status`, `wp_user_id`) VALUES' . PHP_EOL;
            $j=0;

            if ($users) {
                foreach ($users as $user) {
                    $sql .= "('{$user->user_email}', '{$user->user_pass}', '{$user->user_registered}', 1, {$user->ID})," . PHP_EOL;
                }
                if($j == 200 || $j == 400 || $j == 800) {
                    $sql .= 'INSERT INTO `users` (`email`, `password`, `created_at`, `user_status`, `wp_user_id`) VALUES' . PHP_EOL;
                }
                $j++;
            }
            $start += 1000;
            $stop += 1000;

            file_put_contents( SS_PATH . 'includes/uexport/ugroup_start_' . $start .'_stop_'. $stop. '.sql', $sql );
        }
        wp_die( json_encode( ['ajax_export_users'] ) );
    }

    public static function ajax_place_external_order() {
        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', PHP_EOL . '->ajax_place_external_order<-'. date('Y-m-d H:i:s') .PHP_EOL, FILE_APPEND);

        $out = array(
            'status' => 'error',
            'message' => __( 'Could not place order', 'shaversclub-store' ),
        );

        /*, 'shipping_house_number', 'shipping_postcode', 'shipping_street_name', 'shipping_city'*/
        $required = [
            'first_name' => __( 'First name', 'shaversclub-store' ),
            'last_name' => __( 'Last name', 'shaversclub-store' ),
            'billing_house_number' => __( 'House number', 'shaversclub-store' ),
            'billing_postcode' => __( 'Postcode', 'shaversclub-store' ),
            'billing_street_name' => __( 'Street name', 'shaversclub-store' ),
            'billing_city' => __( 'City', 'shaversclub-store' ),
            'payment' => __( 'Payment', 'shaversclub-store' ),
        ];


        foreach ( $required as $key => $label ) {
            if( ! isset( $_POST[ $key ] ) || empty( trim( $_POST[ $key ] ) ) ) {
                $out[ 'message' ] = sprintf(__( '"%s" is a mandatory field', 'shaversclub-store' ), $label );

                file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', json_encode($out) .PHP_EOL, FILE_APPEND);
                wp_die( json_encode( $out ) );
            }
        }

        $customerId = null;
        if(!empty($_POST[ 'wp_user_id' ])) {
            $customerId = intval($_POST[ 'wp_user_id' ]);
        }

        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', 'wp user id: '.$customerId . PHP_EOL, FILE_APPEND );
        $customer = new Customer($customerId);
        $customer->ID = $customerId;
        $customer->set_first_name( $_POST[ 'first_name' ] );
        $customer->set_last_name( $_POST[ 'last_name' ] );

        $customer->set_billing_house_number( $_POST[ 'billing_house_number' ] );
        $customer->set_billing_house_number_suffix( $_POST[ 'billing_house_number_suffix' ] );
        $customer->set_billing_postcode( $_POST[ 'billing_postcode' ] );
        $customer->set_billing_street_name( $_POST[ 'billing_street_name' ] );
        $customer->set_billing_extra_line( $_POST[ 'billing_extra_line' ] );
        $customer->set_billing_city( $_POST[ 'billing_city' ] );
        $customer->set_billing_country( 'NL' );

        $customer->set_shipping_house_number( $_POST[ 'billing_house_number' ] );
        $customer->set_shipping_house_number_suffix( $_POST[ 'billing_house_number_suffix' ] );
        $customer->set_shipping_postcode( $_POST[ 'billing_postcode' ] );
        $customer->set_shipping_street_name( $_POST[ 'billing_street_name' ] );
        $customer->set_shipping_extra_line( $_POST[ 'billing_extra_line' ] );
        $customer->set_shipping_city( $_POST[ 'billing_city' ] );
        $customer->set_shipping_country( 'NL' );

//		$customer->set_iban( $_POST[ 'iban' ] );

        $customer->save();

        $order = new SS_Order;

        $campaign = Campaign::query_one( array(
            'meta_key' => 'cc_' . $_POST[ 'coupon' ],
            'meta_value' => date( 'Y-m-d H:i:s' ),
            'meta_compare' => '>',
        ) );

        SS_Logger::write( 'Cart:ajax_place_order' );
        SS_Logger::write( $customer );

        $campaign_used = $campaign && $customer->is_used_coupon( $_POST[ 'coupon' ] );

        if( isset( $_POST[ 'shipping' ] ) && ( $_POST[ 'shipping' ] == 'express' ) ) {
            $order->set_shipping( 'express' );
            $order->shipping_price = 3.5;
        }


        $method = isset( $_POST[ 'payment' ] ) ? $_POST[ 'payment' ] : 'ideal';
        if( ! in_array( $method, [ 'ideal', 'sepadirectdebit', 'directdebit' ] ) ) {
            $method = 'ideal';
        }
        $order->payment = $method;


        $products = [];
        foreach( $_POST['products'] as $key => $id ) {

            if( $_POST['quantities'][$key] <= 0 ) {
                continue;
            }

            $product = \Product::get( $id );

            if( ! $product ) {
                continue;
            }

            $product->quantity = $_POST['quantities'][$key];
            $products[] = $product;
            $order->add_product( $product );
        }

        if( empty( $products )) {
            $out['message'] = __( 'Could not place order, cart is empty', 'shaversclub-store' );
            wp_die( json_encode( $out ) );
        }

        if( ! $campaign_used ) {
            $order = self::coupon($order, $campaign, $_POST[ 'coupon' ]);
        }

        $used_ref = false;
        if( isset( $_SESSION[ 'ss_ref' ] ) && ! empty( $_SESSION[ 'ss_ref' ] ) ) {
            $used_ref = $order->apply_ref( $_SESSION[ 'ss_ref' ] );
        }

        $order->save([
            'post_status' => 'pending',
            'post_author' => $customerId
        ]);

        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', 'order id: ' . $order->ID . PHP_EOL, FILE_APPEND );
        SS_Logger::write( $order );


        $subscriptions = [];
        if( $_POST['subscriptions']) {
            foreach( $_POST['subscriptions'] as $key => $sub ) {
                $subscriptions[] = self::subscription($sub, $campaign_used, $campaign, $customer);
            }
        }

        $out = array(
            'status' => 'success',
            'order_id' => $order->ID,
            'subscriptions' => $subscriptions
        );

        //$out = $order->make_mollie_payment();

//        if( $used_ref && is_array( $out ) && isset( $out['status'] ) && ( $out['status'] == 'success' ) ) {
//            update_user_meta( $customer->ID, 'ss_used_ref', $order->ID . ':' . $used_ref );
//        }

        SS_Logger::write( $out );

        wp_die( json_encode( $out ) );
    }

    private static function coupon($order, $campaign, $coupon)
    {

        $max = $order->payment == 'ideal' ? 0.01 : 0;

        if( $campaign ) {

            if( $campaign->percent ) {
                $order->price = max( $max, $order->get_price() * ( ( 100 - $campaign->amount ) / 100 ) );
            } else {
                $order->price = max( $max, $order->get_price() - $campaign->amount );
            }

            $order->campaign = array(
                'p' => $campaign->percent,
                'a' => $campaign->amount,
                'c' => $campaign->ID,
            );

            $order->set_coupon( $coupon );
            $campaign->remove_coupon( $coupon );

        } else {

            $old_order = SS_Order::query_one( array(
                'post_status' => [ 'cancelled', 'failed', 'pending' ],
                'meta_query' => array(
                    'relation' => 'AND',
                    array( 'key' => 'coupon', 'value' => $coupon ),
                ),
            ) );

            SS_Logger::write( $old_order );

            if( $old_order && ( $old_ac = $old_order->campaign ) ) {

                if( is_string( $old_ac ) ) {
                    $old_ac = @unserialize( $old_ac );
                }

                SS_Logger::write( $old_ac );

                if( is_array( $old_ac ) && isset( $old_ac['c'] ) ) {

                    $old_campaign = new Campaign( $old_ac['c'] );

                    SS_Logger::write( $old_campaign );

                    if( $old_campaign->has_post() ) {

                        if( $old_campaign->percent ) {
                            $order->price = max( $max, $order->get_price() * ( ( 100 - $old_campaign->amount ) / 100 ) );
                        } else {
                            $order->price = max( $max, $order->get_price() - $old_campaign->amount );
                        }

                        $order->campaign = array(
                            'p' => $old_campaign->percent,
                            'a' => $old_campaign->amount,
                            'c' => $old_campaign->ID,
                        );

                        $order->set_coupon( $coupon );
                        // $old_campaign->remove_coupon( $_POST[ 'coupon' ] );

                        $campaign_used = true;

                        if( $old_order->ID != $order->ID ) {
                            wp_trash_post( $old_order->ID );
                        }
                    }

                }

            }

        }

        return $order;
    }

    /**
     * @param $subData
     * @param $campaign_used
     * @param $campaign
     * @param $customer
     * @return mixed
     * @throws Exception
     */
    private static function subscription($subData, $campaign_used, $campaign, $customer)
    {
        $product = \Product::get( $subData['id'] );
        if( ! $product ) {
            return false;
        }

        $subscription = new Subscription;
        //$subscription->add_initial_product( $product );
        $subscription->add_recurring_product( $product );
        $subscription->clear_campaigns( true );
        $subscription->set_payment( $_POST['payment'] );
        $subscription->set_customer( $customer );
        $subscription->set_interval($subData['frequency'] . ' months');
        //		$subscription->activate();
        $subscription->next_order = new \DateTime($subData['start_date']);

        if( !$campaign_used && $_POST['coupon']) {
            if($campaign) {
                $campaign->remove_coupon( $_POST[ 'coupon' ] );
                $campaign->save();
                $subscription->add_campaign( $campaign, true );
                $subscription->set_coupon( $_POST[ 'coupon' ] );

                $campaign_used = true;

                SS_Logger::write( $campaign );

            } else {

                $old_sub = Subscription::query_one( array(
                    'post_status' => 'on-hold',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array( 'key' => 'coupon', 'value' => $_POST['coupon'] ),
                    ),
                ) );

                file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', '$old_sub: ' . json_encode($old_sub). PHP_EOL, FILE_APPEND );
                SS_Logger::write( $old_sub );

                if( $old_sub && ( $old_ac = $old_sub->meta('active_campaign') ) ) {

                    if( is_string( $old_ac ) ) {
                        $old_ac = @unserialize( $old_ac );
                    }

                    SS_Logger::write( $old_ac );

                    if( is_array( $old_ac ) && isset( $old_ac['c'] ) ) {

                        $old_campaign = new Campaign( $old_ac['c'] );

                        SS_Logger::write( $old_campaign );

                        if( $old_campaign->has_post() ) {

                            $subscription->add_campaign( $old_campaign, true );
                            $subscription->set_coupon( $_POST[ 'coupon' ] );

                            $campaign_used = true;

                            if( $old_orders = $old_sub->get_orders( -1 ) ) {
                                foreach ( $old_orders as $o ) {
                                    wp_trash_post( $o->ID );
                                }
                            }

                            if( $old_sub->ID != $subscription->ID ) {
                                wp_trash_post( $old_sub->ID );
                            }
                        }

                    }

                }

            }
        }

        $subscription->save();

        $order = $subscription->to_order( true );
        file_put_contents( SS_PATH . 'logs2/debug-' . date('Y-m-d') . '.log', 'sub id ' . $subscription->ID . PHP_EOL, FILE_APPEND );

        $order->save([
            'post_status' => 'upcoming'
        ]);
        $subData['wp_order_id'] = $order->ID;

        return $subData;
    }
}
