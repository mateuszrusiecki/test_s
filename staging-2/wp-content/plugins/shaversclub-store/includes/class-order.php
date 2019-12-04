<?php

class SS_Order extends CustomPostType {
    /*
        public $date;
        public $initial;
        public $price;
        public $shipping_price;
        public $subscription;
        public $payment;
        public $products; // list of products
        public $campaign; // applied campaign
    */
    private $got_it = [
        'date' => false,
        'initial' => false,
        'price' => false,
        'shipping_price' => false,
        'subscription' => false,
        'payment' => false,
        'products' => false,
        'campaign' => false,
    ];
    private $data = [];

    public function __construct( $post = null ) {
        parent::__construct( $post );

        $this->data['date'] = new DateTime;
        $this->data['price'] = null;
        $this->data['shipping_price'] = null;
        $this->data['payment'] = 'ideal';
        $this->data['products'] = array();
        $this->data['campaign'] = array();

        /*
                if( $post ) {
                    //$this->post->post_status = 'unpaid';
                    $this->date = new DateTime( $this->meta( 'date' ) );
                    $this->price = $this->meta( 'price' );
                    $this->shipping_price = $this->meta( 'shipping_price' );
                    $this->initial = $this->meta( 'initial' );
                    $this->campaign = $this->meta( 'campaign' );
                    if( is_string( $this->campaign ) ) {
                        $this->campaign = @unserialize( $this->campaign );
                    }

                    if( is_string( $this->price ) ) {
                        $this->price = floatval( $this->price );
                    }
                    if( is_string( $this->shipping_price ) ) {
                        $this->shipping_price = floatval( $this->shipping_price );
                    }

                    $this->payment = $this->meta( 'payment' );
                    $subscription = new Subscription( $this->meta( 'subscription' ) );
                    if( $subscription->has_post() ){
                        $this->subscription = $subscription;
                    }

                    $product_ids = explode( ',', $this->meta( 'products' ) );
                    foreach ( $product_ids as $id) {
                        $this->products[] = new Product( $id );
                    }
                }
        */
    }

    private function get_it( $var ) {
        switch ( $var ) {
            case 'date':
                $this->data['date'] = new DateTime( $this->meta( 'date' ) );
                $this->got_it[ $var ] = true;
                break;

            case 'initial':
                $this->data['initial'] = $this->meta( 'initial' );
                $this->got_it[ $var ] = true;
                break;

            case 'price':
            case 'shipping_price':
                $price = $this->meta( $var );
                if( is_string( $price ) ) {
                    $price = strlen( $price ) ? floatval( $price ) : null;
                }
                $this->data[ $var ] = $price;
                $this->got_it[ $var ] = true;
                break;

            case 'subscription':
                $subscription = new Subscription( $this->meta( 'subscription' ) );
                if( $subscription->has_post() ){
                    $this->data['subscription'] = $subscription;
                }
                $this->got_it[ $var ] = true;
                break;

            case 'payment':
                $this->data['payment'] = $this->meta( 'payment' );
                $this->got_it[ $var ] = true;
                break;

            case 'products':
                $product_ids = get_post_meta( $this->ID, '_product' );
                foreach ( $product_ids as $id) {
                    $this->data['products'][ $id ] = new Product( $id );
                    $this->data['products'][ $id ]->quantity = ( $q = $this->meta( '_quantity_' . $id ) ) ? $q : 1;
                }
                $this->got_it[ $var ] = true;
                break;

            case 'campaign':
                $this->data['campaign'] = $this->meta( 'campaign' );
                if( is_string( $this->data['campaign'] ) ) {
                    $this->data['campaign'] = @unserialize( $this->campaign );
                }
                $this->got_it[ $var ] = true;
                break;

            default:
                break;
        }
    }

    public function __get( $var ) {
        if( isset( $this->got_it[ $var ] ) ) {

            if( $this->has_post() && ! $this->got_it[ $var ] ) {
                $this->get_it( $var );
            }
            return isset( $this->data[ $var ] ) ? $this->data[ $var ] : null;
        }
        //Svar_dump($this->got_it);
        return parent::__get( $var );
    }

    public function __set( $var, $value ) {
        if( isset( $this->got_it[ $var ] ) ) {
            $this->got_it[ $var ] = true;
            $this->data[ $var ] = $value;
        } else {
            $this->$var = $value;
        }
    }

    public static function register_type( $settings = array() ) {
        $settings['supports'] = array( 'revisions', 'editor' => false );
        parent::register_type( $settings );
    }

    public function set_ref( $ref ) {
        $this->meta[ 'ref' ] = $ref;
    }

    public function apply_ref( $ref, $return_prices = false ) {

        $customer = $this->get_customer();

        SS_Logger::write( 'Order:apply_ref' );
        SS_Logger::write( $ref );
        SS_Logger::write( $this );
        SS_Logger::write( $customer );

        $users = $user_query = new WP_User_Query( array(
            'meta_key' => 'ss_ref',
            'meta_value' => $ref,
            'exclude' => array( $customer->ID ),
        ) );

        if( empty( $users->results ) ) {
            return false;
        }

        $user = $users->results[0];
        $referrer = new Customer( $user );
        SS_Logger::write( $referrer );
        if( $customer->ID && ( $referrer->ID == $customer->ID ) ) {
            SS_Logger::write( '-- Zelf referral --' );
            return false;
        }

        if( ! $customer->set_used_ref( $referrer->ID . ':' . $ref ) ) {
            SS_Logger::write( '-- Al een referral gehad: ' . $customer->meta('ss_used_ref') . ' --' );
            return false;
        }

        $this->meta[ 'ref' ] = $referrer->ID;

        $max = 0.01; //$this->initial ? 0.01 : 0 ;

        $options = json_decode( get_option( 'ss-referral-options', '[]' ), true );

        if( empty( $options ) ) {
            SS_Logger::write( '-- Geen referral opties --' );
            return false;
        }

        $amount = isset( $options[ 'a' ] ) ? floatval( $options[ 'a' ] ) : 0 ;

        SS_Logger::write( $options );

        $price = $this->get_price();

        if( isset( $options[ 'p' ] ) && $options[ 'p' ] ) {
            $this->price = max( $max, $this->get_price() * ( ( 100 - $amount ) / 100 ) );
        } else {
            $this->price = max( $max, $this->get_price() - $amount );
        }

        SS_Logger::write( $this->price );

        return $return_prices
            ? array( 'amount' => $amount, 'diff' => $price - $this->price )
            : $customer->meta( 'ss_used_ref' )
            ;
    }

    public function get_customer() {
        if( $this->subscription && ( $cid = intval( $this->subscription->post_author ) ) ) {//meta( 'customer' )
            return new Customer( $cid );
        }
        return new Customer( $this->post_author );
    }

    public function set_customer( $customer ) {
        if( $this->post ) {
            $this->post->post_author = $customer->ID;
        }
    }

    public function set_shipping( $shipping ) {
        $this->meta[ 'shipping' ] = $shipping;
    }

    public function set_coupon( $coupon ) {
        $this->meta[ 'coupon' ] = $coupon;
    }

    public function make_def_title() {
        $date = $this->date instanceof DateTime ? $this->date : new DateTime( $this->date );
        return 'Order - ' . $date->format( 'F d, Y @ H:i' );
    }

    public function make_recurring_payment() {

        if( $this->subscription && ( $this->subscription->post_status != 'active' ) ) {
            return false;
        }

        $this->mail('new-order');
        if ( $this->get_total_price() ) {
            $this->post->post_status = 'pending';
            $this->save();
            return SS_Mollie::payment( $this );
        } else {
            $this->paid();
            return ' -- free --';
        }
    }
    /*
        public function make_recurring_payment() {
            if( $this->subscription && ( $this->subscription->post_status != 'active' ) ) {
                return false;
            }

            if ( $this->get_total_price() ) {
                $response = Adyen::make_recurring_payment( $this, true );
                if( isset( $response[ 'errorType' ] ) && $response[ 'errorType' ] ) {
                    $this->mail('failed-order');
                    $this->post->post_status = 'cancelled';
                    $this->save();
                    if( $this->subscription && $this->subscription->has_post() ) {
                        $this->subscription->_fill_vars();
                        $this->subscription->set_status('on-hold');
                        $this->subscription->save();
                    }
                    return false;
                }
                $this->post->post_status = 'pending';
                $this->save();

            } else {
                $this->paid();
                $response = '--';
            }
            $this->mail('new-order');

            return $response;
        }
    */
    public function complete() {
        $this->last_campaign();
        $this->set_status('completed');
        $this->save();
        $this->mail('completed-order');
    }

    public function lock() {
        if( $this->meta('_paid_lock') ) {
            wp_die();
        }

        $this->meta['_paid_lock'] = 1;
        update_post_meta( $this->ID, '_paid_lock', 1 );
    }

    public function unlock() {
        if( isset( $this->meta['_paid_lock'] ) ) {
            unset( $this->meta['_paid_lock'] );
        }
        delete_post_meta( $this->ID, '_paid_lock' );
    }

    public function last_campaign() {

        $has_sub = false;
        if( $this->subscription && $this->subscription->has_post() ) {
            $this->subscription->_fill_vars();
            $has_sub = true;
        }

        if(
            $has_sub
            && $this->subscription->active_campaign
            && isset( $this->subscription->active_campaign['n'] )
            && ( $this->subscription->active_campaign['n'] == 0 )
        ) {
            $campaign = new Campaign( $this->subscription->active_campaign[ 'c' ] );

            SS_Logger::write( '-- last campaign --' );
            SS_Logger::write( $campaign );

            $this->subscription->active_campaign = array();
            $this->subscription->save();
            $campaign->add_to_mail_queue( $this );
            $campaign->save();
        }
    }

    public function paid() {

        SS_Logger::write( 'Order:paid' );
        SS_Logger::write( $this->post_status );
        SS_Logger::write( $this );

        if( ( $this->post->post_status == 'cancelled' ) || ( in_array( $this->post->post_status,  array( 'processing', 'processing_24', 'awaiting_shipping', 'completed' ) ) && $this->meta( '_paid_date' ) ) ) {
            return;
        }

        $has_sub = false;
        if( $this->subscription && $this->subscription->has_post() ) {
            $this->subscription->_fill_vars();
            $has_sub = true;
        }

        $this->post->post_status = $this->meta( 'shipping' ) == 'express' ? 'processing_24' : 'processing';
        $this->meta[ '_paid_date' ] = ( new DateTime )->format( 'Y-m-d H:i:s' );

        $customer = $this->get_customer();
        delete_user_meta( $customer->ID, 'iban' );

        if( ! empty( $this->meta( 'ref' ) ) ) {
            $referrer = new Customer( $this->meta( 'ref' ) );

            if( $referrer->ID != $customer->ID ) {
                SS_Logger::write( '-- ref --' );
                SS_Logger::write( $customer );
                SS_Logger::write( $referral );
                $referrer->add_referral();
                //$referrer->save();
                /*
                                unset( $this->meta[ 'ref' ] );
                                delete_post_meta( $this->ID, 'ref' );
                */
            }

        }

        $this->save();
        $this->mail('processing-order');
        if( $has_sub ) {

            if( $this->initial && ( $this->subscription->post_status == 'on-hold' ) ) {
                $this->subscription->activate();
            }

            if( $this->subscription->post_status == 'active' ) {
                $order = $this->subscription->to_order();
                $order->save();
            }

            $this->subscription->rename_coupon();
            $this->subscription->save();
        }
    }

    public function make_adyen_form() {
        return Adyen::form( $this );
    }
    public function make_mollie_payment() {
        return SS_Mollie::payment( $this );
    }

    public function set_status( $status ) {
        $s = strtolower( $status );
        if( in_array( $s, array( 'upcoming', 'pending', 'processing', 'processing_24', 'awaiting_shipping', 'completed', 'cancelled', 'failed' ) ) ) {
            if( ( $s == 'completed' ) && ( $this->post_status != $s ) ) {
                $this->meta[ 'was_completed' ] = date( 'Y-m-d' );
            }
            $this->post->post_status = $s;
            return true;
        }
        return false;
    }

    public function get_total_price( $format = false ) {
        $price = $this->get_price() + floatval( $this->shipping_price );
        return $format ? HelperFunctions::format_price( $price ) : floatval( $price );
    }

    public function get_price( $format = false ) {
        $price = $this->price;
        if( is_null( $price ) ) {
            $price = 0;
            foreach ( $this->products as $product ) {
                $price += $product->get_price() * $product->quantity;
            }
        }
        return $format ? HelperFunctions::format_price( $price ) : floatval( $price );
    }

    public function get_discount() {
        if( is_null( $this->price ) ) {
            $discount = 0;
        } else {
            $discount = -1 * floatval( $this->price );
            foreach ( $this->products as $product ) {
                $discount += $product->get_price() * $product->quantity;
            }
        }
        return $discount;
    }

    public function add_product( $product ) {
        $products = $this->products;

        $calc_quantity = 1;

        if( isset( $products[ $product->get_id() ] ) ) {
            if( $product->quantity ) {
                $products[ $product->get_id() ]->quantity = $product->quantity;
                $calc_quantity = $product->quantity;
            } else {
                $products[ $product->get_id() ]->quantity ++;
                $calc_quantity = 1;
            }
        } else {
            $products[ $product->get_id() ] = $product;
            $products[ $product->get_id() ]->quantity = $calc_quantity = $product->quantity ? $product->quantity : 1;
        }

        $this->products = $products;

        if( ! is_null( $this->price ) ) {
            $this->price += ( $product->get_price() * $calc_quantity );
        }
    }
    public function remove_product( $product ) {
        $products = $this->products;

        if( isset( $products[ $product->get_id() ] ) ) {

            if( ! is_null( $this->price ) ) {
                $this->price -= $products[ $product->get_id() ]->get_price();
            }

            $products[ $product->get_id() ]->quantity--;
            if( $products[ $product->get_id() ]->quantity <= 0 ) {
                unset( $products[ $product->get_id() ] );
            }

        }

        $this->products = $products;
    }

    public function save( $attr = array() ) {
        $this->meta[ 'date' ] = $this->date->format( 'Y-m-d H:i:s' );
        $this->meta[ 'price' ] = $this->price;
        $this->meta[ 'shipping_price' ] = $this->shipping_price;
        $this->meta[ 'payment' ] = $this->payment;
        $this->meta[ 'initial' ] = $this->initial;
        $this->meta[ 'campaign' ] = $this->campaign;
        $this->meta[ '_filter_price' ] = $this->get_price();
        if( $this->subscription && $this->subscription->ID ) {
            $this->meta[ 'subscription' ] = $this->subscription->ID;
        }

        if( is_array( $this->campaign ) && isset( $this->campaign['c'] ) ) {
            $this->meta[ '_campaign' ] = $this->campaign['c'];
        }

        $ids = ( array_map( function( $p ) { return $p->ID; }, $this->products ) ); //array_unique
        //$this->meta[ 'products' ] = implode( ',', $ids );
        // Tijdelijk
        unset( $this->meta[ 'products' ] );
        delete_post_meta( $this->ID, 'products' );

        delete_post_meta( $this->ID, '_product' );

        $meta = $this->meta( '_myparcel_shipments', true );
        if( ! empty( $meta ) ) {
            $this->meta[ '_myparcel_shipments' ] = array( $meta );
        }

        if( empty( $this->post ) ) {

            if( ! isset( $attr[ 'post_status' ] ) ) {
                $attr[ 'post_status' ] = 'upcoming';
            }

            if( $this->meta[ 'subscription' ] ) {
                $attr[ 'post_author' ] = $this->subscription->post_author;//meta( 'customer' )
            }
            $attr[ 'post_title' ] = $this->make_def_title();
        }
        parent::save( $attr );

        // als het een nieuwe order is moet parent::save eerst een ID genereren
        foreach ($ids as $id) {
            add_post_meta( $this->ID, '_product', $id );
            update_post_meta( $this->ID, '_quantity_' . $id, ( $q = $this->products[ $id ]->quantity ) ? $q : 1 );
        }

    }

    public function export() {
        $mp = get_option( 'woocommerce_myparcel_general_settings' );

        $api1 = new MyParcel_API( $mp['api_key'] );
        $api2 = new MyParcel_API( $mp['api_key'] );

        $id = false;

        $response = $api1->add_shipments( array( $this->get_export_vars() ) );
        if( isset( $response, $response['body']['data'], $response['body']['data']['ids'] ) && ! empty( $response['body']['data']['ids'] ) ) {
            $id = array_pop( $response['body']['data']['ids'] );
            $id = $id['id'];

            $response2 = $api2->get_shipments( array( $id ) );

            if( isset( $response2, $response2['body']['data'], $response2['body']['data']['shipments'] ) && ! empty( $response2['body']['data']['shipments'] ) ) {
                $shipment = array_pop( $response2['body']['data']['shipments'] );
                $this->meta[ '_myparcel_shipments' ] = array(
                    "$id" => array(
                        'shipment_id' => $id,
                        'status' => '',
                        'tracktrace' => '',
                        'shipment' => $shipment,
                    ),
                );

            }
        }

        return $id;
    }

    public function set_shipment( $shipment, $save = false ) {
        if( ! isset( $shipment['id'] ) || !is_numeric( $shipment['id'] ) ) {
            return false;
        }
        $id = $shipment['id'];
        $meta_shipment = array(
            "$id" => array(
                'shipment_id' => $id,
                'status' => '',
                'tracktrace' => '',
                'shipment' => $shipment,
            ),
        );
        $this->meta[ '_myparcel_shipments' ] = $meta_shipment;
        if( $save && $this->ID ) {
            update_post_meta( $this->ID, '_myparcel_shipments', $meta_shipment );
        }
    }


    public function get_export_vars() {
        $customer = $this->get_customer();
        return array(
            'recipient' => array(
                'cc' => (string)$customer->meta( 'shipping_country' ),
                'city' => (string)$customer->meta( 'shipping_city' ),
                'street' => (string)$customer->meta( 'shipping_street_name' ),
                'street_additional_info' => (string)$customer->meta( 'shipping_extra_line' ),
                'number' => (string)$customer->meta( 'shipping_house_number' ),
                'number_suffix' => (string)$customer->meta( 'shipping_house_number_suffix' ),
                'postal_code' => (string)$customer->meta( 'shipping_postcode' ),
                'person' => $customer->meta( 'first_name' ) . ' ' . $customer->meta( 'last_name' ),
                'phone' => (string)$customer->meta( 'billing_phone' ),
                'email' => (string)$customer->user_email,
            ),
            'options' => array(
                'package_type' => 3,
                'label_description' => "$this->ID",
            ),
            'carrier' => 1,
        );
    }

    public function get_print_vars() {
        $customer = $this->get_customer();
        return array(
            'q' => $customer->meta( 'first_name' ) . ' ' . $customer->meta( 'last_name' ) . ' ' . $this->ID,
        );
    }

    static $post_type = 'ss_order';
    static $post_type_plural = 'ss_orders';

    public static function manage_columns( $columns ) {
        global $typenow;

        if( $typenow != static::$post_type ) {
            return $columns;
        }

        return array(
            'cb' => __( 'Bulk actions', 'shaversclub-store' ),
            'ss_order' => __( 'Order', 'shaversclub-store' ),
            'customer' => __( 'Customer', 'shaversclub-store' ),
            'subscription' => __( 'Subscription', 'shaversclub-store' ),
            'products' => __( 'Products', 'shaversclub-store' ),
            'price' => __( 'Price', 'shaversclub-store' ),
            'status' => __( 'Status', 'shaversclub-store' ),
            'datum' => __( 'Date', 'shaversclub-store' ),
            'order_type' => __( 'Order type', 'shaversclub-store' ),
            'actions' => __( 'Actions', 'shaversclub-store' ),
        );
    }

    public static function sortable_columns( $columns ) {
        global $typenow;

        if( $typenow != static::$post_type ) {
            return $columns;
        }

        return array(
            'ss_order' => 'ss_order',
            'customer' => 'author',
            'subscription' => 'subscription',
            'price' => 'price',
            'datum' => 'datum',
//			'products' => 'products',
//			'status' => 'status',
        );
    }
    public static function request_filter( $vars ) {
        global $typenow;

        if( $typenow != static::$post_type ) {
            return $vars;
        }

        wp_enqueue_style( 'edit_order_css', SS_URL . 'css/order_edit.css' );
        wp_enqueue_style( 'select2', SS_URL . 'js/select2-4.0.3/dist/css/select2.min.css' );
        wp_enqueue_script( 'select2', SS_URL . 'js/select2-4.0.3/dist/js/select2.min.js', array( 'jquery' ) );
        wp_enqueue_script( 'edit_order_js', SS_URL . 'js/order_edit.js', array( 'select2' ) );

        $args;
        parse_str( $_SERVER['QUERY_STRING'], $args );

        if( isset( $args['download_pdf'] ) && is_numeric( $args['download_pdf'] ) ) {

            $mp = get_option( 'woocommerce_myparcel_general_settings' );
            $api = new MyParcel_API( $mp['api_key'] );
            $response = $api->get_shipment_labels( array( intval( $args['download_pdf'] ) ), array(), 'json' );
            if( isset( $response, $response['body'] ) ) {
                if( $url = HelperFunctions::get_pdf_url( $response['body'] ) ) {
                    header( 'location:' . trim( $api->APIURL, '/' ) . $url );
                    die();
                }

                //HelperFunctions::output_pdf( $response['body'] );
            }
        }

        if( isset( $vars[ 'orderby' ] ) ) {
            switch ( $vars[ 'orderby' ] ) {
                case 'subscription':
                    $vars['orderby'] = 'meta_value';
                    $vars['meta_key'] = 'subscription';
                    break;

                case 'ss_order':
                    $vars['orderby'] = 'ID';
                    break;

                case 'datum':
                    $vars['orderby'] = 'meta_value';
                    $vars['meta_key'] = 'date';
                    break;

                case 'price':
                    $vars['orderby'] = 'meta_value_num';
                    $vars['meta_key'] = '_filter_price';
                    break;

                default:
                    break;
            }
        }

        if( isset( $vars[ 's' ] ) && ! empty( $vars[ 's' ] ) ) {
//			add_filter( 'posts_search', array( 'HelperFunctions', 'custom_search' ) );
            $user_ids = array();
            $users = new WP_User_Query( array(
                'search' => $vars[ 's' ],
            ) );
            if( $users->results ) {
                $user_ids = array_map( function( $u ) { return $u->ID; }, $users->results );
            }
            $users = new WP_User_Query( array(
                'meta_query' => array(
                    'relation' => 'OR',
                    array( 'key' => 'first_name', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'last_name', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),

//					array( 'key' => 'billing_house_number', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
//					array( 'key' => 'billing_house_number_suffix', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'billing_postcode', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'billing_street_name', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'billing_city', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
//					array( 'key' => 'billing_country', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'billing_phone', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),

//					array( 'key' => 'shipping_house_number', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
//					array( 'key' => 'shipping_house_number_suffix', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'shipping_postcode', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'shipping_street_name', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                    array( 'key' => 'shipping_city', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
//					array( 'key' => 'shipping_country', 'value' => $vars[ 's' ], 'compare' => 'LIKE' ),
                ),
            ) );

            if( $users->results ) {
                $user_ids = array_merge( $user_ids, array_map( function( $u ) { return $u->ID; }, $users->results ) );
            }

            $vars[ 'author__in' ] = $user_ids;
            /*
                        if( ! isset( $vars['meta_query'] ) ) {
                            $vars['meta_query'] = array(
                                'relation' => 'AND',
                            );
                        }
                        $user_ids = array_unique( $user_ids );
                        $vars['meta_query'][] = array(
                            'value' => $user_ids,
                            'key' => 'customer',
                            'compare' => 'IN',
                        );
            */
            unset( $vars[ 's' ] );
        }

        if( isset( $vars[ 'subscription' ]  ) ) {
            if( ! empty( $vars[ 'subscription' ] ) ) {

                if( ! isset( $vars['meta_query'] ) ) {
                    $vars['meta_query'] = array(
                        'relation' => 'AND',
                    );
                }
                $vars['meta_query'][] = array(
                    'value' => $vars[ 'subscription' ],
                    'key' => 'subscription',
                );

            }
            unset( $vars[ 'subscription' ], $vars[ 'name' ] );
        }

        if( isset( $args[ 'campaign' ] ) && $args[ 'campaign' ] ) {

            if( ! isset( $vars['meta_query'] ) ) {
                $vars['meta_query'] = array(
                    'relation' => 'AND',
                );
            }

            $vars['meta_query'][] = array(
                'value' => intval( $args[ 'campaign' ] ),
                'key' => '_campaign',
            );
            /*
                        $vars['meta_query'][] = array(
                            'value' => ';s:1:"c";i:' . intval( $args[ 'campaign' ] ) . ';}',
                            'key' => 'campaign',
                            'compare' => 'LIKE',
                        );
            */
            unset( $vars[ 'campaign' ], $vars[ 'name' ] );
        }

        if( isset( $vars[ 'product' ] ) && is_array( $vars[ 'product' ] ) ) {
            $vars[ 'product' ] = array_filter( $vars[ 'product' ] );
            if( ! empty( $vars[ 'product' ] ) ) {

                if( ! isset( $vars['meta_query'] ) ) {
                    $vars['meta_query'] = array(
                        'relation' => 'AND',
                    );
                }
                foreach ( $vars[ 'product' ] as $pid ) {

                    $vars['meta_query'][] = array(
                        'value' => $pid,
                        'key' => '_product',
                    );

                    /*
                                        $vars['meta_query'][] = array(
                                            'value' => '(^|[^0-9])' . $pid . '([^0-9]|$)',
                                            'key' => 'products',
                                            'compare' => 'REGEXP',
                                        );
                    */
                }

                global $_ss_posts_where_order_filter, $_ss_posts_where_order_filter_count;

                $_ss_posts_where_order_filter_count = count( $vars[ 'product' ] );
                $_ss_posts_where_order_filter = function( $where ) {
                    global $_ss_posts_where_order_filter, $_ss_posts_where_order_filter_count, $wpdb;
                    if( strpos( $where, "$wpdb->posts.post_type = 'ss_order'" ) === false ) {
                        return $where;
                    }
                    return " AND ((SELECT COUNT(meta_id) FROM $wpdb->postmeta _mtc_ WHERE _mtc_.meta_key='_product' AND _mtc_.post_id=$wpdb->posts.ID) = $_ss_posts_where_order_filter_count) $where";
                };
                add_filter( 'posts_where', $_ss_posts_where_order_filter );

            }
            unset( $vars[ 'product' ], $vars[ 'name' ] );
        }

        if( isset( $args[ 'initial' ] ) ) {
            if( ! empty( $args[ 'initial' ] ) ) {

                if( ! isset( $vars['meta_query'] ) ) {
                    $vars['meta_query'] = array(
                        'relation' => 'AND',
                    );
                }
                $vars['meta_query'][] = array(
                    'value' => boolval( $args[ 'initial' ] === 'true' ),
                    'key' => 'initial',
                );
//				var_dump($vars);

            }
            unset( $vars[ 'initial' ], $vars[ 'name' ] );
        }

        return $vars;
    }

    public static function custom_column( $colname, $cptid ) {
        global $typenow, $wp;

        if( $typenow != static::$post_type ) {
            return;
        }

        $order = new SS_Order( $cptid );
        $customer = $order->get_customer();
        $args = array();
        parse_str( $_SERVER['QUERY_STRING'], $args );
        $current_url = home_url( add_query_arg( $args, $wp->request ) );

        switch( $colname ) {
            case 'ss_order':
                echo '<a href="post.php?post=' . $order->ID . '&action=edit">#' . $order->ID . '<br>' . $customer->user_email . '</a>';
                break;

            case 'customer':
                echo '<a href="edit.php?post_type=ss_order&author=' . $customer->ID . '">' . $customer->meta( 'first_name' ) . ' ' . $customer->meta( 'last_name' ) . '</a><br>';
                echo '<a href="mailto:' . $customer->user_email . '">' . $customer->user_email . '</a><br><br>';
                echo $customer->get_small_billing_address();
                if( $customer->uses_other_shipping_address() ) {
                    echo '<br>' . $customer->get_small_shipping_address();
                }
                break;

            case 'status':
                echo $order->post_status;
                break;

            case 'products':
            case 'subscription':
            case 'price':
            case 'order_type':
            case 'datum':
            case 'actions':
                echo '<div id="custom_column-' . $order->ID . '-' . $colname . '"></div>';
                break;
            /*
                        case 'products':
                            foreach ( $order->products as $p ) {
                                echo '<a title="' . __( 'Filter', 'shaversclub-store' ) . '" href="' . add_query_arg( 'product[0]', $p->ID, $current_url ) . '">' . $p->post_title . '</a> - <a title="' . __( 'Edit', 'shaversclub-store' ) . '" href="post.php?post=' . $p->ID . '&action=edit"><span class="dashicons dashicons-edit"></span></a><br>';
                            }
                            break;
                        case 'subscription':
                            if( $order->subscription ) {
                                echo '<a title="' . __( 'Filter', 'shaversclub-store' ) . '" href="' . add_query_arg( 'subscription', $order->subscription->ID, $current_url ) . '">' . __( 'Subscription', 'shaversclub-store' ) . ' #' . $order->subscription->ID . '</a> - <a title="' . __( 'Edit', 'shaversclub-store' ) . '" href="post.php?post=' . $order->subscription->ID . '&action=edit"><span class="dashicons dashicons-edit"></span></a><br>';
                            } else {
                                _e( 'One-off', 'shaversclub-store' );
                            }
                            break;

                        case 'price':
                            echo '<b>' . __( 'Price', 'shaversclub-store' ) . ': </b> ' . $order->get_price( true ) . '<br>';
                            echo '<b>' . __( 'Shipping price', 'shaversclub-store' ) . ': </b> ' . HelperFunctions::format_price( $order->shipping_price, null ) . '<br>';
                            echo $order->payment;
                            break;

                        case 'order_type':
                            echo '<span class="dashicons dashicons-' . ( $order->initial ? 'clock' : 'backup' ) . '"></span>';
                            break;

                        case 'status':
                            echo $order->post_status;
                            break;

                        case 'datum':
                            echo date_i18n( get_option( 'date_format' ), $order->date->getTimestamp() );
                            break;

                        case 'actions':
                                if( $order->post_status == 'completed' ) {
                                    echo '<span class="dashicons dashicons-yes"></span>';
                                } elseif( $order->post_status == 'processing' ) {
                                    echo '<a href="javascript:;" data-id="' . $order->ID . '" class="order-completed order-completed-' . $order->ID . '" title="' . __( 'Completed', 'shaversclub-store' ) . '" /><span class="dashicons dashicons-yes"></span></a>';
                                } elseif( $order->post_status == 'pending' ) {
                                    echo '<a href="javascript:;" data-id="' . $order->ID . '" class="order-processing order-processing-' . $order->ID . '" title="' . __( 'Processing', 'shaversclub-store' ) . '" /><span class="dashicons dashicons-hammer"></span></a>';
                                }
                                echo '<a href="javascript:;" data-id="' . $order->ID . '" class="mp-export mp-export-' . $order->ID . '" title="' . __( 'Export', 'shaversclub-store' ) . '" /><span class="dashicons dashicons-external"></span></a>';
                                $meta = $order->meta( '_myparcel_shipments' );
                                if ( ! empty( $meta ) ) {

                                    if( is_string( $meta ) ) {
                                        $meta = @unserialize( $meta );
                                    }

                                    if( is_array( $meta ) ) {
                                        $id = key( $meta );
                                        echo '<a target="_blank" href="' . add_query_arg( 'download_pdf', $id, $current_url ) . '" title="' . __( 'Download pdf', 'shaversclub-store' ) . '" /><span class="dashicons dashicons-media-document"></span></a>';

                                    }

                                }
                            break;
            */
            default:
                break;
        }
    }

    public static function filter_lists() {
        global $typenow, $wp;

        if( $typenow != static::$post_type ) {
            return '';
        }

        $subscriptions = Subscription::query( array( 'posts_per_page' => -1 ) );
        $products = Product::query( array( 'posts_per_page' => -1 ) );
        $campaigns = Campaign::query( array( 'posts_per_page' => -1 ) );

        $args = array();
        parse_str( $_SERVER['QUERY_STRING'], $args );
        $current_url = home_url( add_query_arg( $args, $wp->request ) );

        add_action( 'admin_footer', function() use ( $current_url ) { SS_Order::admin_script( $current_url ); }, 100 );

        $sid = isset( $args['subscription'] ) ? $args['subscription'] : '';
        $pid = isset( $args['product'] ) ? $args['product'] : '';
        $cid = isset( $args['campaign'] ) ? intval( $args['campaign'] ) : '';
        $ini = isset( $args['initial'] ) ? $args['initial'] : '';
        $post_status = isset( $args['post_status'] ) ? $args['post_status'] : '';
        ?>
        <select name="post_status">
            <option value=""><?php _e( 'Status', 'shaversclub-store' ); ?></option>
            <option<?php echo $post_status == 'upcoming' ? ' selected="selected"' : '' ; ?> value="upcoming"><?php _e( 'Upcoming', 'shaversclub-store' ) ?></option>
            <option<?php echo $post_status == 'pending' ? ' selected="selected"' : '' ; ?> value="pending"><?php _e( 'Pending', 'shaversclub-store' ) ?></option>
            <option<?php echo $post_status == 'processing' ? ' selected="selected"' : '' ; ?> value="processing"><?php _e( 'Processing', 'shaversclub-store' ) ?></option>
            <option<?php echo $post_status == 'processing_24' ? ' selected="selected"' : '' ; ?> value="processing_24"><?php _e( 'Processing (express)', 'shaversclub-store' ) ?></option>
            <option<?php echo $post_status == 'awaiting_shipping' ? ' selected="selected"' : '' ; ?> value="awaiting_shipping"><?php _e( 'Awaiting Shipping', 'shaversclub-store' ) ?></option>
            <option<?php echo $post_status == 'completed' ? ' selected="selected"' : '' ; ?> value="completed"><?php _e( 'Completed', 'shaversclub-store' ) ?></option>
            <option<?php echo $post_status == 'cancelled' ? ' selected="selected"' : '' ; ?> value="cancelled"><?php _e( 'Cancelled', 'shaversclub-store' ) ?></option>
            <option<?php echo $post_status == 'failed' ? ' selected="selected"' : '' ; ?> value="failed"><?php _e( 'Failed', 'shaversclub-store' ) ?></option>
        </select>

        <select name="initial">
            <option value=""><?php _e( 'Order type', 'shaversclub-store' ); ?></option>
            <option value="true"<?php echo $ini === 'true' ? ' selected="selected"' : '' ; ?>><?php _e( 'Initial', 'shaversclub-store' ) ?></option>
            <option value="false"<?php echo $ini === 'false' ? ' selected="selected"' : '' ; ?>><?php _e( 'Recurring', 'shaversclub-store' ) ?></option>
        </select>

        <?php
        /*
                echo '<select name="subscription"><option value="">' . __( 'Subscription', 'shaversclub-store' ) . '</option>';
                foreach ( $subscriptions as $s ) {
                    echo '<option value="' . $s->ID . '"' . ( $sid == $s->ID ? ' selected="selected"' : '' ) . '>' . $s->post_title . '</option>';
                }
                echo '</select>';
        */
        echo '<select name="product[]" multiple="multiple"><option value="">' . __( 'Products', 'shaversclub-store' ) . '</option>';
        foreach ( $products as $p ) {
            echo '<option value="' . $p->ID . '"' . ( $pid && in_array( $p->ID, $pid ) ? ' selected="selected"' : '' ) . '>' . $p->post_title . '</option>';
        }
        echo '</select>';
        echo '<select name="campaign"><option value="">' . __( 'All campaigns', 'shaversclub-store' ) . '</option>';
        foreach ( $campaigns as $c ) {
            echo '<option value="' . $c->ID . '"' . ( $c->ID == $cid ? ' selected="selected"' : '' ) . '>' . $c->post_title . '</option>';
        }
        echo '</select>';

    }

    public static function bulk_actions( $actions ) {
        global $typenow;

        if( $typenow != static::$post_type ) {
            return $actions;
        }

        $actions[ 'mark_processing' ] = __( 'Mark processing', 'shaversclub-store' );
        $actions[ 'mark_processing_24' ] = __( 'Mark processing (express)', 'shaversclub-store' );
        $actions[ 'mark_pending' ] = __( 'Mark pending', 'shaversclub-store' );
        $actions[ 'mark_awaiting_shipping' ] = __( 'Mark awaiting shipping', 'shaversclub-store' );
        $actions[ 'mark_complete' ] = __( 'Mark complete', 'shaversclub-store' );
        $actions[ 'mark_cancelled' ] = __( 'Mark cancelled', 'shaversclub-store' );
        $actions[ 'mark_failed' ] = __( 'Mark failed', 'shaversclub-store' );
        $actions[ 'myparcel_export' ] = __( 'MyParcel: export', 'shaversclub-store' );
        $actions[ 'myparcel_print' ] = __( 'MyParcel: print', 'shaversclub-store' );
        $actions[ 'myparcel_export_print' ] = __( 'MyParcel: export and print', 'shaversclub-store' );
        return $actions;
    }


    public static function handle_bulk_actions( $redirect_to, $doaction, $post_ids ){
        global $typenow, $wpdb;

        if( $typenow != static::$post_type ) {
            return $redirect_to;
        }

        $status = false;
        $ids = array_map( function( $id ) { return intval( $id ); }, $post_ids );

        if( $doaction == 'mark_pending' ) {
            $status = 'pending';
        } elseif( $doaction == 'mark_processing' ) {
            $status = 'processing';
        } elseif( $doaction == 'mark_processing_24' ) {
            $status = 'processing_24';
        } elseif( $doaction == 'mark_cancelled' ) {
            $status = 'cancelled';
        } elseif( $doaction == 'mark_failed' ) {
            $status = 'failed';
        } elseif( $doaction == 'mark_awaiting_shipping' ) {
            $status = 'awaiting_shipping';
        } elseif( $doaction == 'mark_complete' ) {
            $status = 'completed';
        }

        SS_Logger::write( '-- handle_bulk_actions --' );
        SS_Logger::write( "$doaction -- $status" );

        $timestamps = [];

        if( $status ) {

            $timestamps[] = ' -- before update -- ' . date( 'H:i:s' );
            $wpdb->query("UPDATE $wpdb->posts SET `post_status`='$status' WHERE ID IN(" . implode( ',', $ids ) . ")");
            $timestamps[] = ' -- after update -- ' . date( 'H:i:s' );

            if( $status == 'completed' ) {
                $orders = SS_Order::query( array(
                    'post__in' => $ids,
                    'posts_per_page' => -1,
                ) );

                $timestamps[] = ' -- after SS_Order::query -- ' . date( 'H:i:s' );

                foreach ( $orders as $o ) {

                    $timestamps[] = ' -- loop start -- ' . $o->ID . ' -- ' . date( 'H:i:s' );

                    $o->last_campaign();

                    $timestamps[] = ' -- after last_campaign -- ' . date( 'H:i:s' );

                    $o->mail('completed-order');

                    $timestamps[] = ' -- after mail -- ' . date( 'H:i:s' );

                    $feedback_company = SS_feedbackcompany::getInstance();
                    $feedback_company->ss_feedback( $o->ID, $o->get_post() );

                    $timestamps[] = ' -- after feedbackcompany -- ' . date( 'H:i:s' );

                    update_post_meta( $o->ID, 'was_completed', date( 'Y-m-d' ) );

                    $timestamps[] = ' -- loop end -- ' . date( 'H:i:s' );
                }
                $timestamps[] = ' -- after loop -- ' . date( 'H:i:s' );
            }
        }

        if( in_array( $doaction, array( 'myparcel_export', 'myparcel_print', 'myparcel_export_print' ) ) ) {

            ini_set('memory_limit', '-1');
            set_time_limit(0);

            $orders = SS_Order::query( array(
                'post__in' => $post_ids,
                'posts_per_page' => -1,
            ) );
            $timestamps[] = ' -- after MyParcel orders query -- ' . date( 'H:i:s' );

            $mp = get_option( 'woocommerce_myparcel_general_settings' );

            $ids = array();
            if( ( $doaction == 'myparcel_export' ) || ( $doaction == 'myparcel_export_print' ) ) {

                $errors = array();
                $mail_errors = array();
                foreach ( $orders as $o ) {
                    $timestamps[] = ' -- loop start -- ' . $o->ID . ' -- ' . date( 'H:i:s' );
                    try {

                        // $timestamps[] = ' -- before export -- ' . date( 'H:i:s' );
                        if( $id = $o->export() ) {
                            $timestamps[] = ' -- after export -- ' . date( 'H:i:s' );

                            if( empty( $o->meta( '_paid_date' ) ) ) {
                                $mail_errors[ $o->ID ] = $o->post_status;
                            }

                            $ids[] = $id;

                            if( $o->post_status != 'completed' ) {
                                $o->set_status('awaiting_shipping');
                            }

                            $o->save();
                            $timestamps[] = ' -- after save -- ' . date( 'H:i:s' );
                        }
                    } catch (Exception $e) {
                        $errors[ $o->ID ] = $e->getMessage();
                    }
                    $timestamps[] = ' -- loop end -- ' . date( 'H:i:s' );
                }

                $timestamps[] = ' -- before mail_errors -- ' . date( 'H:i:s' );
                if( $mail_errors ) {
                    $c = new Customer;
                    $mail_body = "\n bulk_action: $doaction - $c->ID \n"
                        . 'REQUEST_TIME_FLOAT: ' . $_SERVER['REQUEST_TIME_FLOAT'] . "\n"
                        . 'HTTP_USER_AGENT: ' . $_SERVER['HTTP_USER_AGENT'] . "\n"
                        . 'REMOTE_ADDR: ' . $_SERVER['REMOTE_ADDR'] . "\n"
                        . 'REMOTE_HOST: ' . $_SERVER['REMOTE_HOST'] . "\n"
                        . 'REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . "\n\n";

                    foreach( $mail_errors as $id => $status ) {
                        $mail_body .= "$id - $status\n";
                    }

                    HelperFunctions::mail( array( 'designs@dirlik.nl', 'marcokoershall@gmail.com' ), 'Export too soon', $mail_body );
                }
                $timestamps[] = ' -- after mail_errors -- ' . date( 'H:i:s' );

                if( $errors ) {
                    $sted = '';
                    foreach ($errors as $id => $message) {
                        $sted .= '<p><a href="post.php?post=' . $id . '&action=edit">#' . $id . '</a>: ' . $message . '</p>';
                    }
                    $_SESSION['__ss_admin_notices_div__'] = '<div class="error notice">' . $sted . '</div>';
                }
                $timestamps[] = ' -- after errors -- ' . date( 'H:i:s' );
            }

            if( ( $doaction == 'myparcel_print' ) || ( $doaction == 'myparcel_export_print' ) ) {

                if( empty( $ids ) ) {
                    foreach ( $orders as $o ) {
                        $meta = $o->meta( '_myparcel_shipments' );
                        if( is_string( $meta ) ) {
                            $meta = @unserialize( $meta );
                        }
                        if( is_array( $meta ) && ( ! empty( $meta ) ) && ( $id = key( $meta ) ) ) {
                            $ids[] = $id;
                        }
                    }
                }

                if( ! empty( $ids ) ) {

                    $timestamps[] = ' -- before get_shipment_labels -- ' . date( 'H:i:s' );
                    $api = new MyParcel_API( $mp['api_key'] );
                    $response = $api->get_shipment_labels( $ids, array(), 'json' );
                    SS_Logger::write( $response );
                    $timestamps[] = ' -- after get_shipment_labels -- ' . date( 'H:i:s' );
                    if( isset( $response, $response['body'] ) ) {
                        if( $url = HelperFunctions::get_pdf_url( $response['body'] ) ) {
                            header( 'location:' . trim( $api->APIURL, '/' ) . $url );
                            die();
                        } else {
                            $_SESSION['__ss_admin_notices_div__'] = '<div class="error notice">Geen pdf url gekregen.<br>' . $response['body'] . '</div>';
                        }
                        //HelperFunctions::output_pdf( $response['body'] );
                    }
                }
            }

            /*
                        $ids = array();
                        if( ( $doaction == 'myparcel_export' ) || ( $doaction == 'myparcel_export_print' ) ) {
                            $shipments = array();
                            foreach ( $orders as $o ) {
                                $shipments[] = $o->get_export_vars();
                            }
                            $api1 = new MyParcel_API( $mp['api_key'] );
                            $response = $api1->add_shipments( $shipments );
                            if( isset( $response, $response['body']['data'], $response['body']['data']['ids'] ) && ! empty( $response['body']['data']['ids'] ) ) {
                                $ids = array_map( function( $id_arr ) { return $id_arr['id']; }, $response['body']['data']['ids'] );
                                $api2 = new MyParcel_API( $mp['api_key'] );
                                $response2 = $api2->get_shipments( $ids );
                                if( isset( $response2, $response2['body']['data'], $response2['body']['data']['shipments'] ) && ! empty( $response2['body']['data']['shipments'] ) ) {
                                    foreach ( $response2['body']['data']['shipments'] as $i => $shipment ) {
                                        if( isset( $orders[ $i ] ) ) {
                                            $orders[ $i ]->set_shipment( $shipment, true );
                                        }
                                    }

                                }
                            }

                        }


                        if( ( $doaction == 'myparcel_print' ) || ( $doaction == 'myparcel_export_print' ) ) {

                            if( empty( $ids ) ) {
                                foreach ( $orders as $o ) {
                                    $meta = $o->meta( '_myparcel_shipments' );
                                    if( is_string( $meta ) ) {
                                        $meta = @unserialize( $meta );
                                    }
                                    if( is_array( $meta ) && ( ! empty( $meta ) ) && ( $id = key( $meta ) ) ) {
                                        $ids[] = $id;
                                    }
                                }
                            }

                            if( ! empty( $ids ) ) {
                                $api3 = new MyParcel_API( $mp['api_key'] );
                                $response = $api3->get_shipment_labels( $ids );
                                if( isset( $response, $response['body'] ) ) {
                                    HelperFunctions::output_pdf( $response['body'] );
                                }
                            }
                        }
            */
        }

        SS_Logger::write( implode( "\n", $timestamps ) );
        return $redirect_to;
    }

    public static function register_status() {
        register_post_status( 'completed', array(
            'label' => _x( 'Completed', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>' ),
        ) );
        register_post_status( 'awaiting_shipping', array(
            'label' => _x( 'Awaiting Shipping', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Awaiting Shipping <span class="count">(%s)</span>', 'Awaiting Shipping <span class="count">(%s)</span>' ),
        ) );
        register_post_status( 'processing_24', array(
            'label' => _x( 'Processing (express)', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Processing (express) <span class="count">(%s)</span>', 'Processing (express) <span class="count">(%s)</span>' ),
        ) );
        register_post_status( 'processing', array(
            'label' => _x( 'Processing', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Processing <span class="count">(%s)</span>', 'Processing <span class="count">(%s)</span>' ),
        ) );
        register_post_status( 'cancelled', array(
            'label' => _x( 'Cancelled', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>' ),
        ) );
        register_post_status( 'failed', array(
            'label' => _x( 'Failed', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Failed <span class="count">(%s)</span>', 'Failed <span class="count">(%s)</span>' ),
        ) );
        register_post_status( 'pending', array(
            'label' => _x( 'Pending', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>' ),
        ) );
        register_post_status( 'upcoming', array(
            'label' => _x( 'Upcoming', 'order', 'shaversclub-store' ),
            'show_in_admin_status_list' => true,
            'public'					=> true,
            'exclude_from_search'		=> false,
            'show_in_admin_all_list'	=> true,
            'label_count'				=> _n_noop( 'Upcoming <span class="count">(%s)</span>', 'Upcoming <span class="count">(%s)</span>' ),
        ) );

        parent::register_status();
    }

    /* DIT NOG VERPLAATSEN / OPSLITSEN */
    public function mail( $type, $other_email = false, $debug = false ) {
        global $ec_template_args, $ec_email_html, $ec_email_css;
        $EC_Settings = new EC_Settings;

        $order = $this;
        $customer = $order->get_customer();

        $ec_template_args['order'] = $this;
        $ec_template_args['user_login'] = $customer->user_login;

        $sent_to_admin = true;
        $plain_text = false;
        $show_purchase_note = false;
        $show_image = true;
        $show_sku = true;
        $show_download_links = false;
        $partial_refund = false;
        $email = $other_email ? $other_email : $customer->user_email;

        $subject = '[ShaversClub] ';
        switch ( $type ) {
            case 'new-order':
                $subject .= sprintf( __( 'New customer order (%d)', 'shaversclub-store' ), $this->ID );
                $file = "admin-new-order.php";
                break;

            case 'failed-order':
                $subject .= sprintf( __( 'Failed order (%d)', 'shaversclub-store' ), $this->ID );
                $file = "admin-failed-order.php";
                break;

            case 'cancelled-order':
                $subject .= sprintf( __( 'Cancelled order (%d)', 'shaversclub-store' ), $this->ID );
                $file = "admin-cancelled-order.php";
                break;

            case 'campaign-expired':
                $subject .= __( 'Campaign expired', 'shaversclub-store' );
                $file = "campaign-expired.php";
                break;

            case 'campaign-expired-groupon':
                $subject .= __( 'Campaign expired', 'shaversclub-store' );
                $file = "campaign-expired-groupon.php";
                break;

            case 'campaign-expired-123topdeal':
                $subject .= __( 'Campaign expired', 'shaversclub-store' );
                $file = "campaign-expired-123topdeal.php";
                break;

            case 'completed-order':
                $subject .= sprintf( __( 'Your order (%d) is complete', 'shaversclub-store' ), $this->ID );
                $file = "customer-completed-order.php";
                break;

            case 'invoice':
                $subject .= sprintf( __( 'Invoice for order (%d)', 'shaversclub-store' ), $this->ID );
                $file = "customer-invoice.php";
                break;

            case 'new-account':
                $subject .= __( 'Your new account', 'shaversclub-store' );
                $file = "customer-new-account.php";
                break;

            case 'note':
                $subject .= sprintf( __( 'Note added to your order (%d)', 'shaversclub-store' ), $this->ID );
                $file = "customer-note.php";
                break;

            case 'processing-order':
                $subject .= sprintf( __( 'Your order receipt (%d)', 'shaversclub-store' ), $this->ID );
                $file = "customer-processing-order.php";
                break;

            case 'partially-refunded-order':
                $partial_refund = true;
                $subject .= sprintf( __( 'Your order (%d) has been partially refunded', 'shaversclub-store' ), $this->ID );
                $file = "customer-refunded-order.php";
                break;

            case 'refunded-order':
                $subject .= sprintf( __( 'Your order (%d) has been refunded', 'shaversclub-store' ), $this->ID );
                $file = "customer-refunded-order.php";
                break;

            case 'reset-password':
                $subject .= __( 'Password Reset', 'shaversclub-store' );
                $file = "customer-reset-password.php";
                break;

            default:
                return false;
                break;
        }

        include SS_PATH . 'includes/email/' . $file;
        if ($debug) {
            echo $subject;
            echo $ec_email_html;die();
        }
        return HelperFunctions::mail( $email, $subject, $ec_email_html );
    }

    public function has_status( $status ) {
        return $this->post_status == $status;
    }

    public static function admin_script( $current_url ) {
        ?>
        <script type="text/javascript">
          ( function( $ ) {
            $( document ).ready( function( e ) {


              $( '.column-actions' ).on( 'click', function( e ) {
                var t = $( e.target );
                t = t.is('span') ? t.closest('a, td') : t ;

                if( ! t.is('a') ) {
                  return;
                }

                if( t.is('.mp-export') ) {

                  $.post( ajaxurl, { action: 'ss_mp_export', id: t.data( 'id' ) }, function( response ) {
                    if( response.status == 'success' ) {
                      var a1 = $( '.mp-export-' + response.id );
                      a1.next('a').remove();

                      a1.after(
                        $( '<a />' )
                          .attr( 'target', '_blank' )
                          .attr( 'href', '<?php echo add_query_arg( 'download_pdf', '[did]', $current_url ); ?>'.replace( '[did]', response.did ) )
                          .attr( 'title', '<?php _e( 'Download pdf', 'shaversclub-store' ); ?>' )
                          .html(
                            $( '<span />' )
                              .addClass( 'dashicons dashicons-media-document' )
                          )
                      );
                    }
                    alert( response.message );
                  }, 'json' );

                } else if( t.is('.order-completed') ) {

                  $.post( ajaxurl, { action: 'ss_order_completed', id: t.data( 'id' ) }, function( response ) {
                    if( response.status == 'success' ) {
                      var a1 = $( '.order-completed-' + response.id );
                      a1.before( a1.find('span') );
                      a1.remove();
                    }
                    alert( response.message );
                  }, 'json' );

                } else if( t.is('.order-processing') ) {

                  $.post( ajaxurl, { action: 'ss_order_processing', id: t.data( 'id' ) }, function( response ) {
                    if( response.status == 'success' ) {
                      var a1 = $( '.order-processing-' + response.id );
                      a1.before( a1.find('span') );
                      a1.remove();
                    }
                    alert( response.message );
                  }, 'json' );

                }

              } );

            } );
          } )( jQuery );
        </script>
        <?php
    }

    public static function check_for_shortcodes() {
        add_action( 'the_posts', function( $posts ) {
            if ( empty($posts) ) {
                return $posts;
            }

            foreach ($posts as $post) {
                if ( stripos($post->post_content, '[ss_tracking_pixel]') !== false ) {

                    global $gtm4wp_options;
                    $gtm4wp_options[ GTM4WP_OPTION_GTM_CODE ] = false;
                    /*
                                        $order = ( isset( $_GET['o'] ) && ( $oid = intval( $_GET['o'] ) ) ) ? new SS_Order( $oid ) : false ;

                                        if( ! $order || ! $order->has_post() || $order->meta('tracked') || in_array( $order->post_status, [ 'trash', 'failed', 'cancelled' ] ) ) {
                                            $gtm4wp_options[ GTM4WP_OPTION_GTM_CODE ] = false;
                                        }
                    */
                    break;
                }
            }

            return $posts;

        } );

    }

    public static function shortcodes() {
        add_shortcode( 'ss_tracking_pixel', array( 'SS_Order', 'tracking_pixel' ) );
    }

    public static function tracking_pixel() {
        $order = ( isset( $_GET['o'] ) && ( $oid = intval( $_GET['o'] ) ) ) ? new SS_Order( $oid ) : false ;
        if( ! $order || ! $order->has_post() || $order->meta('tracked') ) {
            add_filter( 'gadwp_analytics_script_path', function( $url ) { return SS_URL . 'js/dummy-analytics.js'; }, 0, 1 );
            return '';
        }

        //update_post_meta( $order->ID, 'tracked', true );
        $order->set_meta( 'tracked', true, true );
        ob_start();

        if( in_array( $order->post_status, [ 'trash', 'failed', 'cancelled' ] ) ):
            add_filter( 'gadwp_analytics_script_path', function( $url ) { return SS_URL . 'js/dummy-analytics.js'; }, 0, 1 ); ?>
            <script type="text/javascript">
              ( function( $ ) {
                jQuery( document ).ready( function() {
                  var div = $('.dynamic-message-div');
                  div.addClass('notification');
                  div.find('h2').html('Betaling afgebroken!');
                  div.find('p:first').before(
                    $('<div />')
                      .addClass('row')
                      .css( { marginTop: '15px' } )
                      .append( $('<div />').addClass('col--md-1 img') )
                      .append( $('<div />').addClass('col--md-11').html( 'Je IDEAL betaling is afgebroken. Dit kan je eigen keuze zijn maar ook een fout zijn geweest. Als er sprake is van een fout, probeer het dan later opnieuw. Neem gerust contact op voor hulp.' ) )
                  );

                  div.find('p, .vc_cta3-actions').remove();
                } );
              } ) ( jQuery );
            </script>
            <style type="text/css">
                .notification {
                    color: #ffffff !important;
                    background-color: #f9a939 !important;
                }
                .notification .img {
                    background-image: url(<?php echo SS_URL; ?>img/warning.png);
                    background-position: center;
                    background-repeat: no-repeat;
                }
            </style>
        <?php else:
            $cart = \Cart::_new();
            $cart->save_session();

            /* EVEN LOGGEN OM DUBBELEN TE HERLEIDEN */
            /*
                        $log_string = $order->ID . ' - ' . $order->meta('tracked') . ' - ' . $order->post_status . "\n";
                        $log_string .= 'REQUEST_TIME_FLOAT: ' . $_SERVER['REQUEST_TIME_FLOAT'] . "\n";
                        $log_string .= 'HTTP_USER_AGENT: ' . $_SERVER['HTTP_USER_AGENT'] . "\n";
                        $log_string .= 'REMOTE_ADDR: ' . $_SERVER['REMOTE_ADDR'] . "\n";
                        $log_string .= 'REMOTE_HOST: ' . $_SERVER['REMOTE_HOST'] . "\n";
                        $log_string .= 'REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . "\n";

                        SS_Logger::write( $log_string );*/ ?>
            <img id="1000153551_cpa_testing" src="https://ads.trafficjunky.net/tj_ads_pt?a=1000153551&member_id=1000986701&cb=<?php echo time() . mt_rand( 1000, 9999999 ); ?>&epu=<?php echo substr( $_SERVER["REQUEST_URI"], 0, 255 ); ?>&cti=<?php echo $order->ID; ?>&ctv=<?php echo $order->get_price(); ?>&ctd=<?php echo urlencode( $order->post_title ); ?>" width="1" height="1" border="0" />
            <script type="text/javascript">
              var ttConversionOptions = ttConversionOptions || [];
              ttConversionOptions.push({
                type: 'sales',
                campaignID: '<?php echo TT_CAMPAIGN_ID; ?>',
                productID: '<?php echo TT_PRODUCT_ID; ?>',
                transactionID: '<?php echo $order->ID; ?>',
                transactionAmount: '<?php echo $order->get_price(); ?>',
                quantity: '1',
                email: '',
                descrMerchant: '',
                descrAffiliate: '',
                currency: ''
              });
            </script>
            <noscript>
                <img src="//ts.tradetracker.net/?cid=<?php echo TT_CAMPAIGN_ID; ?>&pid=<?php echo TT_PRODUCT_ID; ?>&tid=<?php echo $order->ID; ?>&tam=<?php echo $order->get_price(); ?>&data=&qty=1&eml=&descrMerchant=&descrAffiliate=&event=sales&currency=EUR" alt="" />
            </noscript>
            <script type="text/javascript">
              // No editing needed below this line.
              (function(ttConversionOptions) {
                var campaignID = 'campaignID' in ttConversionOptions ? ttConversionOptions.campaignID : ('length' in ttConversionOptions && ttConversionOptions.length ? ttConversionOptions[0].campaignID : null);
                var tt = document.createElement('script'); tt.type = 'text/javascript'; tt.async = true; tt.src = '//tm.tradetracker.net/conversion?s=' + encodeURIComponent(campaignID) + '&t=m';
                var s = document.getElementsByTagName('script'); s = s[s.length - 1]; s.parentNode.insertBefore(tt, s);
              })(ttConversionOptions);
            </script>

        <?php endif;
        return ob_get_clean();
    }

    public static function ajax() {
        add_action( 'wp_ajax_ss_mp_export', array( 'SS_Order', 'ajax_mp_export' ) );
        add_action( 'wp_ajax_ss_order_completed', array( 'SS_Order', 'ajax_order_completed' ) );
        add_action( 'wp_ajax_ss_order_processing', array( 'SS_Order', 'ajax_order_processing' ) );
        add_action( 'wp_ajax_ss_change_order_date', array( 'SS_Order', 'ajax_change_order_date' ) );

        add_action( 'wp_ajax_ss_get_order_data', array( 'SS_Order', 'ajax_get_order_data' ) );

        add_action( 'wp_ajax_ss_mp_bulk_action', array( 'SS_Order', 'ajax_mp_bulk_action' ) );

        add_action( 'wp_ajax_ss_send_email', array( 'SS_Order', 'ajax_send_email' ) );
        add_action( 'wp_ajax_ss_get_custom_order_columns', array( 'SS_Order', 'ajax_get_custom_order_columns' ) );

        add_action( 'wp_ajax_nopriv_save_external_order_data', array( 'SS_Order', 'ajax_save_external_order_data' ) );
    }

    public static function ajax_save_external_order_data() {
        //die(var_dump(implode( ',',$_POST['products'])));
        ///die(var_dump($_POST));

        $order = new SS_Order();
        $order->meta[ 'date' ] = $order->date->format( 'Y-m-d H:i:s' );
        $order->meta[ 'price' ] =  $_POST['price'];
        $order->meta[ 'shipping_price' ] = $_POST['shipping_price'];
        $order->meta[ 'payment' ] = $_POST['payment']; //'ideal', 'sepadirectdebit', 'directdebit'
        $order->meta[ 'initial' ] = $_POST['initial'];
        $order->meta[ 'campaign' ] = $_POST['campaign'];
        $order->meta[ '_filter_price' ] = $order->get_price();
        if( $_POST['subscription']) {
            $order->meta[ 'subscription' ] = $_POST['subscription']; //id
        }

//        if( is_array( $this->campaign ) && isset( $this->campaign['c'] ) ) {
//            $this->meta[ '_campaign' ] = $this->campaign['c'];
//        }

        $ids = $_POST['products'];
        $order->meta[ 'products' ] = implode( ',', $ids );

        $meta = $order->meta( '_myparcel_shipments', true );
        if(!empty($meta)) {
            $order->meta[ '_myparcel_shipments' ] = array( $meta );
        }

        if(empty( $order->post ) ) {
            if(!isset($attr[ 'post_status' ])) {
                $attr[ 'post_status' ] = 'upcoming';
            }

            if( $order->meta[ 'subscription' ] ) {
                $attr[ 'post_author' ] = $order->subscription->post_author;//meta( 'customer' )
            }
            $attr[ 'post_title' ] = $order->make_def_title();
        }

        parent::save($attr);

        //if it is a new order, parent :: save must first generate an ID
        foreach ($ids as $id) {
            //add_post_meta( $order->ID, '_product', $id );
            //update_post_meta( $order->ID, '_quantity_' . $id, ( $q = $order->products[ $id ]->quantity ) ? $q : 1 );
        }
        wp_die( json_encode( ['okk'] ) );
    }

    public static function ajax_get_order_data() {

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : false;
        $out = [];

        $customer = new Customer;
        // $order = SS_Order::get( $id );

        // var_dump($customer->ID);
        if( $id && ( $order = SS_Order::get( $id ) ) ) {
            $c = $order->get_customer();

            if( ( ! $c->ID ) || ( $c->ID != $customer->ID ) ) {
                wp_die( json_encode( $out ) );
            }

            $out = [
                'title' => 'Bestelling van ' . date_i18n( 'j F Y', $order->date->getTimestamp() ),
                'delivered' => '',
                'id' => $order->ID,
                'products' => [],
                'price' => $order->get_total_price(),
                'address' => $c->get_formatted_billing_address(),
                'payment' => $order->payment,
            ];

            foreach( $order->products as $p ) {

                $src = '';
                if( $id = $p->meta('_thumbnail_id') ) {
                    $img = wp_get_attachment_image_src( $id, 'full' );
                    if( $img && isset( $img[0] ) ) {
                        $src = $img[0];
                    }
                }

                $out[ 'products' ][] = [
                    'title' => $p->meta( 'frontend_title' ),
                    'product_line' => $p->meta( 'product_line' ),
                    'price' => $p->get_price(),
                    'quantity' => $p->quantity,
                    'image' => $src,
                ];
            }
        }
        wp_die( json_encode( $out ) );
    }

    public static function ajax_get_custom_order_columns() {

        $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? $_POST['ids'] : [] ;

        if( ! $ids ) {
            wp_die( json_encode( [ ] ) );
        }

        $args = array();
        $components = parse_url( $_SERVER['HTTP_REFERER'] );
        parse_str( $components['query'], $args );
        $current_url = add_query_arg( $args, $components['scheme'] . '://' . $components['host'] . $components['path'] );

        $result = [];
        $orders = SS_Order::query( [ 'post__in' => $ids ] );
        foreach ( $orders as $order ) {
            $customer = $order->get_customer();
            $actions = '';

            if( $order->post_status == 'completed' ) {
                $actions .= '<span class="dashicons dashicons-yes"></span>';
            } elseif( $order->post_status == 'processing' ) {
                $actions .= '<a href="javascript:;" data-id="' . $order->ID . '" class="order-completed order-completed-' . $order->ID . '" title="' . __( 'Completed', 'shaversclub-store' ) . '" /><span class="dashicons dashicons-yes"></span></a>';
            } elseif( $order->post_status == 'pending' ) {
                $actions .= '<a href="javascript:;" data-id="' . $order->ID . '" class="order-processing order-processing-' . $order->ID . '" title="' . __( 'Processing', 'shaversclub-store' ) . '" /><span class="dashicons dashicons-hammer"></span></a>';
            }
            $actions .= '<a href="javascript:;" data-id="' . $order->ID . '" class="mp-export mp-export-' . $order->ID . '" title="' . __( 'Export', 'shaversclub-store' ) . '" /><span class="dashicons dashicons-external"></span></a>';
            $meta = $order->meta( '_myparcel_shipments' );
            if ( ! empty( $meta ) ) {

                if( is_string( $meta ) ) {
                    $meta = @unserialize( $meta );
                }

                if( is_array( $meta ) ) {
                    $id = key( $meta );
                    $actions .= '<a target="_blank" href="' . add_query_arg( 'download_pdf', $id, $current_url ) . '" title="' . __( 'Download pdf', 'shaversclub-store' ) . '" ><span class="dashicons dashicons-media-document"></span></a>';
                }
            }

            $result[ $order->ID ] = [
                'products' => '',
                'subscription' => $order->subscription ? '<a title="' . __( 'Filter', 'shaversclub-store' ) . '" href="' . add_query_arg( 'subscription', $order->subscription->ID, $current_url ) . '">' . __( 'Subscription', 'shaversclub-store' ) . ' #' . $order->subscription->ID . '</a> - <a title="' . __( 'Edit', 'shaversclub-store' ) . '" href="post.php?post=' . $order->subscription->ID . '&action=edit"><span class="dashicons dashicons-edit"></span></a><br>' : __( 'One-off', 'shaversclub-store' ),
                'price' => '<b>' . __( 'Price', 'shaversclub-store' ) . ': </b> ' . $order->get_price( true ) . '<br><b>' . __( 'Shipping price', 'shaversclub-store' ) . ': </b> ' . HelperFunctions::format_price( $order->shipping_price, null ) . '<br>' . $order->payment,
                'order_type' => '<span class="dashicons dashicons-' . ( $order->initial ? 'clock' : 'backup' ) . '"></span>',
                'datum' => date_i18n( get_option( 'date_format' ), $order->date->getTimestamp() ),
                'actions' => $actions,
            ];

            foreach ( $order->products as $p ) {
                $result[ $order->ID ]['products'] .= '<a title="' . __( 'Filter', 'shaversclub-store' ) . '" href="' . add_query_arg( 'product[0]', $p->ID, $current_url ) . '">' . $p->post_title . ' (' . $p->quantity . 'st.)</a> - <a title="' . __( 'Edit', 'shaversclub-store' ) . '" href="post.php?post=' . $p->ID . '&action=edit"><span class="dashicons dashicons-edit"></span></a><br>';
            }

        }
        wp_die( json_encode( $result ) );

    }

    public static function ajax_send_email() {

        $out = array(
            'message' => __( 'Could not send email', 'shaversclub-store' ),
        );

        $order = new SS_Order( $_POST[ 'id' ] );
        $email = HelperFunctions::verify_email( $_POST[ 'email' ] );

        if( $email && $order->has_post() && $order->mail( $_POST[ 'type' ], $email ) ) {
            $out = array(
                'message' => __( 'Email sent!', 'shaversclub-store' ),
            );
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_change_order_date() {

        $out = array(
            'status' => 'error',
            'message' => __( 'Could not change order date', 'shaversclub-store' ),
        );

        $order = new SS_Order( $_POST[ 'id' ] );
        $date = new DateTime( $_POST[ 'date' ] );
        if( $order->has_post() && ( $date instanceof DateTime ) ) {
            $order->date = $date;
            $order->save();
            $out = array(
                'status' => 'success',
                'id' => $order->ID,
                'date' => date_i18n( wc_date_format(), $date->getTimestamp() ),
            );
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_order_processing() {

        $out = array(
            'status' => 'error',
            'message' => __( 'Could not set order to processing', 'shaversclub-store' ),
        );

        $order = new SS_Order( $_POST[ 'id' ] );
        if( $order->has_post() ) {
            $order->set_status('processing');
            $order->save();
            //$order->mail('processing-order');
            $out = array(
                'status' => 'success',
                'message' => __( 'Order Processing, refresh to see changes', 'shaversclub-store' ),
                'id' => $order->ID,
            );
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_order_completed() {

        $out = array(
            'status' => 'error',
            'message' => __( 'Could not complete order', 'shaversclub-store' ),
        );

        $order = new SS_Order( $_POST[ 'id' ] );
        if( $order->has_post() ) {
            $order->complete();
            $out = array(
                'status' => 'success',
                'message' => __( 'Order completed', 'shaversclub-store' ),
                'id' => $order->ID,
            );
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_mp_export() {

        $out = array(
            'status' => 'error',
            'message' => __( 'Could not export order', 'shaversclub-store' ),
        );

        try {
            $order = new SS_Order( $_POST[ 'id' ] );

            if( $order->has_post() && ( $id = $order->export() ) ) {

                if( empty( $order->meta( '_paid_date' ) ) ) {
                    $c = new Customer;
                    $mail_body = "\n ajax_mp_export : $c->ID \n"
                        . 'REQUEST_TIME_FLOAT: ' . $_SERVER['REQUEST_TIME_FLOAT'] . "\n"
                        . 'HTTP_USER_AGENT: ' . $_SERVER['HTTP_USER_AGENT'] . "\n"
                        . 'REMOTE_ADDR: ' . $_SERVER['REMOTE_ADDR'] . "\n"
                        . 'REMOTE_HOST: ' . $_SERVER['REMOTE_HOST'] . "\n"
                        . 'REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . "\n\n"
                        . "$id - $order->post_status\n";

                    HelperFunctions::mail( array( 'designs@dirlik.nl', 'marcokoershall@gmail.com' ), 'Export too soon', $mail_body );
                }

                if( $order->post_status != 'completed' ) {
                    $order->set_status('awaiting_shipping');
                }

                $order->save();
                $out = array(
                    'status' => 'success',
                    'message' => __( 'Order exported', 'shaversclub-store' ),
                    'did' => $id,
                    'id' => $order->ID,
                );
            }
        } catch (Exception $e) {
            $out[ 'message' ] = $e->getMessage();
        }

        wp_die( json_encode( $out ) );
    }

    public static function ajax_mp_bulk_action() {

        $out = array();

        $doaction = $_POST['bulk_action'];
        $post_ids = $_POST['ids'];

        SS_Logger::write( '-- ajax_mp_bulk_action --' );
        SS_Logger::write( $doaction );

        $timestamps = [];

        if( in_array( $doaction, array( 'myparcel_export', 'myparcel_print', 'myparcel_export_print' ) ) ) {

            ini_set('memory_limit', '-1');
            set_time_limit(0);

            $orders = SS_Order::query( array(
                'post__in' => $post_ids,
                'posts_per_page' => -1,
            ) );
            $timestamps[] = ' -- after MyParcel orders query -- ' . date( 'H:i:s' );

            $mp = get_option( 'woocommerce_myparcel_general_settings' );

            $ids = array();
            if( ( $doaction == 'myparcel_export' ) || ( $doaction == 'myparcel_export_print' ) ) {

                $errors = array();
                $mail_errors = array();
                foreach ( $orders as $o ) {
                    $timestamps[] = ' -- loop start -- ' . $o->ID . ' -- ' . date( 'H:i:s' );
                    try {
                        if( $id = $o->export() ) {
                            $ids[] = $id;

                            if( empty( $o->meta( '_paid_date' ) ) ) {
                                $mail_errors[ $o->ID ] = $o->post_status;
                            }

                            if( $o->post_status != 'completed' ) {
                                $o->set_status('awaiting_shipping');
                            }

                            $o->save();
                            $timestamps[] = ' -- after save -- ' . date( 'H:i:s' );
                        }
                    } catch (Exception $e) {
                        $errors[ $o->ID ] = $e->getMessage();
                    }
                    $timestamps[] = ' -- loop end -- ' . date( 'H:i:s' );
                }

                $timestamps[] = ' -- before mail_errors -- ' . date( 'H:i:s' );
                if( $mail_errors ) {
                    $c = new Customer;
                    $mail_body = "\n ajax_mp_bulk_action: $doaction - $c->ID \n"
                        . 'REQUEST_TIME_FLOAT: ' . $_SERVER['REQUEST_TIME_FLOAT'] . "\n"
                        . 'HTTP_USER_AGENT: ' . $_SERVER['HTTP_USER_AGENT'] . "\n"
                        . 'REMOTE_ADDR: ' . $_SERVER['REMOTE_ADDR'] . "\n"
                        . 'REMOTE_HOST: ' . $_SERVER['REMOTE_HOST'] . "\n"
                        . 'REQUEST_URI: ' . $_SERVER['REQUEST_URI'] . "\n\n";

                    foreach( $mail_errors as $id => $status ) {
                        $mail_body .= "$id - $status\n";
                    }

                    HelperFunctions::mail( array( 'designs@dirlik.nl', 'marcokoershall@gmail.com' ), 'Export too soon', $mail_body );
                }
                $timestamps[] = ' -- after mail_errors -- ' . date( 'H:i:s' );

                if( $errors ) {
//					$out['errors'] = $errors;

                    $sted = '';
                    foreach ($errors as $id => $message) {
                        $sted .= '<p><a href="post.php?post=' . $id . '&action=edit">#' . $id . '</a>: ' . $message . '</p>';
                    }
                    $_SESSION['__ss_admin_notices_div__'] = '<div class="error notice">' . $sted . '</div>';
                }
                $timestamps[] = ' -- after errors -- ' . date( 'H:i:s' );
            }

            if( ( $doaction == 'myparcel_print' ) || ( $doaction == 'myparcel_export_print' ) ) {

                if( empty( $ids ) ) {
                    foreach ( $orders as $o ) {
                        $meta = $o->meta( '_myparcel_shipments' );
                        if( is_string( $meta ) ) {
                            $meta = @unserialize( $meta );
                        }
                        if( is_array( $meta ) && ( ! empty( $meta ) ) && ( $id = key( $meta ) ) ) {
                            $ids[] = $id;
                        }
                    }
                }

                if( ! empty( $ids ) ) {

                    $timestamps[] = ' -- before get_shipment_labels -- ' . date( 'H:i:s' );
                    $api = new MyParcel_API( $mp['api_key'] );
                    $response = $api->get_shipment_labels( $ids, array(), 'json' );
                    SS_Logger::write( $response );
                    if( isset( $response, $response['body'] ) ) {
                        if( $url = HelperFunctions::get_pdf_url( $response['body'] ) ) {
                            $out['url'] = trim( $api->APIURL, '/' ) . $url;
                        } else {
                            $_SESSION['__ss_admin_notices_div__'] = '<div class="error notice">Geen pdf url gekregen.<br>' . $response['body'] . '</div>';
                        }
                        //HelperFunctions::output_pdf( $response['body'] );
                    }
                }
            }
        }

        SS_Logger::write( implode( "\n", $timestamps ) );

        wp_die( json_encode( $out ) );
    }

    public static function meta_boxes() {
        global $post;

        wp_enqueue_script( 'edit_order_js', SS_URL . 'js/order_edit.js' );
        wp_enqueue_style( 'edit_order_css', SS_URL . 'css/order_edit.css' );

        wp_enqueue_script( 'products_editor_js', SS_URL . 'js/products_editor.js' );
        wp_enqueue_style( 'products_editor_css', SS_URL . 'css/products_editor.css' );

        $order = new SS_Order( $post );

        $options = json_decode( get_option( 'ss-' . Subscription::$post_type . '-options', '[]' ), true );
        if( ! isset( $options['interval_options'] ) ) {
            $options['interval_options'] = array();
        }

        add_meta_box( 'order_details', __('Order details', 'shaversclub-store'), function() use ( $order ) {

            $subscription = $order->subscription;
            $customer = $order->get_customer();

            $current_url = home_url( '/wp-admin/edit.php?post_type=' . static::$post_type );

            add_action( 'admin_footer', function () use ( $current_url ) { SS_Order::admin_script( $current_url ); } );

            $meta = $order->meta( '_myparcel_shipments' );
            if ( ! empty( $meta ) ) {
                if( is_string( $meta ) ) {
                    $meta = @unserialize( $meta );
                }
            }
            ?>
            <h2><?php printf( __( 'Order #%s details', 'shaversclub-store' ), $order->ID ); ?></h2>
            <h2>
                <?php echo date_i18n( wc_date_format(), $order->date->getTimestamp() ); ?> <span class="dashicons dashicons-edit edit-customer-link" style="float: right;"></span>
            </h2>
            <a href="javascript:;" class="change-order-date"><?php _e( 'Change date', 'shaversclub-store' ); ?></a>
            <div class="change-order-date-div" data-id="<?php echo $order->ID; ?>">
                <div class="side-row">
                    <input type="text" name="change_order_date" class="order_date datepicker" value="<?php echo $order->date->format('d-m-Y'); ?>" />
                    @
                </div>
                <div class="side-row">
                    <input type="text" name="change_order_hours" class="order_time" value="<?php echo $order->date->format('H'); ?>" />:
                    <input type="text" name="change_order_minutes" class="order_time" value="<?php echo $order->date->format('i'); ?>" />:
                    <input type="text" name="change_order_seconds" class="order_time" value="<?php echo $order->date->format('s'); ?>" />
                </div>
                <div class="side-row">
                    <button class="change_order_submit"><?php _e( 'Save', 'shaversclub-store' ); ?></button>
                </div>
            </div>
            <div class="order_data">


                <div class="order_data_column">
                    <p>
                        <?php echo $customer->meta('first_name') . ' ' . $customer->meta('last_name'); ?>
                    </p>
                    <p>
                        <select name="hidden_post_status">
                            <option<?php echo $order->post_status == 'upcoming' ? ' selected="selected"' : '' ; ?> value="upcoming"><?php _e( 'Upcoming', 'shaversclub-store' ) ?></option>
                            <option<?php echo $order->post_status == 'pending' ? ' selected="selected"' : '' ; ?> value="pending"><?php _e( 'Pending', 'shaversclub-store' ) ?></option>
                            <option<?php echo $order->post_status == 'processing' ? ' selected="selected"' : '' ; ?> value="processing"><?php _e( 'Processing', 'shaversclub-store' ) ?></option>
                            <option<?php echo $order->post_status == 'processing_24' ? ' selected="selected"' : '' ; ?> value="processing_24"><?php _e( 'Processing (express)', 'shaversclub-store' ) ?></option>
                            <option<?php echo $order->post_status == 'awaiting_shipping' ? ' selected="selected"' : '' ; ?> value="awaiting_shipping"><?php _e( 'Awaiting Shipping', 'shaversclub-store' ) ?></option>
                            <option<?php echo $order->post_status == 'completed' ? ' selected="selected"' : '' ; ?> value="completed"><?php _e( 'Completed', 'shaversclub-store' ) ?></option>
                            <option<?php echo $order->post_status == 'cancelled' ? ' selected="selected"' : '' ; ?> value="cancelled"><?php _e( 'Cancelled', 'shaversclub-store' ) ?></option>
                            <option<?php echo $order->post_status == 'failed' ? ' selected="selected"' : '' ; ?> value="failed"><?php _e( 'Failed', 'shaversclub-store' ) ?></option>
                        </select>
                    </p>
                </div>

                <div class="edit_customer">
                    <?php echo $customer->admin_edit_customer_form(); ?>
                </div>

                <div class="order_data_column">
                    <h4>Factuurgegevens</h4>

                    <div class="address">
                        <p>
                            <strong>Adres:</strong>
                            <?php echo $customer->meta('first_name') . ' ' . $customer->meta('last_name'); ?><br>
                            <?php echo $customer->get_small_billing_address(); ?>
                            <?php /* echo $customer->meta('billing_street_name') . ' ' . $customer->meta('billing_house_number') . ' ' . $customer->meta('billing_house_number_suffix'); ?><br>
							<?php echo $customer->meta('billing_postcode') . ' ' . $customer->meta('billing_city'); */ ?>
                        </p>
                        <p>
                            <strong>E-mail:</strong>
                            <a href="mailto:<?php echo $customer->user_email; ?>"><?php echo $customer->user_email; ?></a>
                        </p>
                        <p>
                            <strong>Telefoon:</strong>
                            <?php echo $customer->meta('billing_phone'); ?>
                        </p>
                        <p>
                            <strong>Straat:</strong>
                            <?php echo $customer->meta('billing_street_name'); ?>
                        </p>
                        <p>
                            <strong>Nummer:</strong>
                            <?php echo $customer->meta('billing_house_number') . ' ' . $customer->meta('billing_house_number_suffix'); ?>
                        </p>
                        <p>
                            <strong>Betaalmethode:</strong>
                            <?php echo $order->meta('payment'); ?>
                        </p>
                    </div>
                </div>

                <div class="order_data_column">
                    <h4>Verzendgegevens</h4>

                    <div class="address">
                        <p>
                            <strong>Adres:</strong>
                            <?php echo $customer->meta('first_name') . ' ' . $customer->meta('last_name'); ?><br>
                            <?php echo $customer->get_small_shipping_address(); ?>
                            <?php /* echo $customer->meta('shipping_street_name') . ' ' . $customer->meta('shipping_house_number') . ' ' . $customer->meta('shipping_house_number_suffix'); ?><br>
							<?php echo $customer->meta('shipping_postcode') . ' ' . $customer->meta('shipping_city'); */ ?>
                        </p>
                        <p>
                            <strong>Straat:</strong>
                            <?php echo $customer->meta('shipping_street_name'); ?>
                        </p>
                        <p>
                            <strong>Nummer:</strong>
                            <?php echo $customer->meta('shipping_house_number') . ' ' . $customer->meta('shipping_house_number_suffix'); ?>
                        </p>
                        <p>
                            <strong>MyParcel:</strong><br>
                            <a href="javascript:;" data-id="<?php echo $order->ID; ?>" class="mp-export mp-export-<?php echo $order->ID; ?>" title="<?php _e( 'Export', 'shaversclub-store' ); ?>" /><span class="dashicons dashicons-external"></span></a>
                            <?php if( is_array( $meta ) ): $id = key( $meta ); ?>
                                <a target="_blank" href="<?php echo add_query_arg( 'download_pdf', $id, $current_url ); ?>" title="<?php _e( 'Download pdf', 'shaversclub-store' ); ?>" /><span class="dashicons dashicons-media-document"></span></a>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

            </div>
            <div class="clear"></div>
            <?php
        }, static::$post_type, 'normal');


        add_meta_box( 'products', __('Products', 'shaversclub-store'), function() use ( $order ) {
            //$checked = is_null( $order->price ) ? '' : ' checked="checked"' ;
            //$products = $order->products;
            //$ids = array_keys( $products );
            //$ids = array_map( function( $p ) { return $p->ID; }, $products );

            $products = Product::query( array( 'numberposts' => -1 ) );
            foreach ( $products as $product ) {
                $price_map[ $product->ID ] = $product->get_price();
            }
            ?>
            <table class="proto">
                <tr>
                    <td>
                        <?php wp_dropdown_pages( array( 'post_type' => 'product', 'class' => 'products', 'name' => 'products[%d]' ) ); ?>
                    </td>
                    <td>
                        <input type="text" name="quantities[%d]" value="1" />
                    </td>
                    <td>
                        <span class="subtotal"></span>
                    </td>
                    <td>
                        <a class="remove-product dashicons dashicons-trash" href="javascript:;"></a>
                    </td>
                </tr>
            </table>
            <table class="form-table order_table" data-price-map="<?php echo str_replace( '"', '&quot;', json_encode( $price_map ) ); ?>">
                <tr class="first">
                    <td class="first">
                        <label>
                            <?php _e('Products', 'shaversclub-store'); ?>:
                        </label>
                    </td>
                    <td>
                        <a class="add-product" href="javascript:;"><?php _e('Add New Product', 'shaversclub-store'); ?></a>
                    </td>
                    <td></td>
                    <?php /*
					<td>
						<?php
							$selected = 'data-selected="[' . implode( ',', $ids ) . ']"';
							$select_box = wp_dropdown_pages( array( 'post_type' => 'product', 'class' => 'products', 'name' => 'products[]', 'echo' => 0  ) );
							echo str_replace( '<select', '<select multiple="multiple" ' . $selected, $select_box );
						?>
					</td>
					<td>
						<label for="custom_price"><?php _e( 'Custom price', 'shaversclub-store' ); ?>
							<input type="checkbox" id="custom_price" class="custom_price"<?php echo $checked; ?> />
							<input type="text" class="price" name="price" value="<?php echo $order->price; ?>" />
						</label>
					</td>
*/ ?>
                </tr>
                <?php foreach ($order->products as $id => $product): ?>
                    <tr>
                        <td>
                            <?php wp_dropdown_pages( array( 'post_type' => 'product', 'class' => 'products', 'name' => 'products[%d]', 'selected' => $id  ) ); ?>
                        </td>
                        <td>
                            <input type="text" name="quantities[%d]" value="<?php echo $product->quantity; ?>" />
                        </td>
                        <td>
                            <span class="subtotal"></span>
                        </td>
                        <td>
                            <a class="remove-product dashicons dashicons-trash" href="javascript:;"></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="last">
                    <td>
                        <label>
                            <?php _e('Total', 'shaversclub-store'); ?>:
                        </label>
                    </td>
                    <td></td>
                    <td>
                        <b class="total"></b>
                        <a class="edit-custom-price dashicons dashicons-edit" href="javascript:;"></a>
                        <a class="auto-custom-price dashicons dashicons-editor-ul" href="javascript:;"></a>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="price" value="<?php echo $order->price; ?>" />
            <?php
        }, static::$post_type, 'normal');

        add_meta_box( 'related_orders', __('Related orders', 'shaversclub-store'), function() use ( $order, $options ) {
            $subscription = $order->subscription;
            if( ! $subscription ) {
                echo '-';
                return;
            }

            $interval = $subscription->get_interval();
            $interval = HelperFunctions::format_date_interval( $interval );

            $iv_label = '';
            if( isset( $options, $options['interval_options'], $options['interval_options'][ $interval ] ) ) {
                if( is_string( $options['interval_options'][ $interval ] ) ) {
                    $iv_label = unserialize( $options['interval_options'][ $interval ] );
                    $iv_label = $iv_label['label'];
                } else {
                    $iv_label = $options['interval_options'][ $interval ]['label'];
                }
            }
            $orders = $subscription->get_orders(-1);
            ?>
            <table class="form-table">
                <tr>
                    <th>
                        <?php _e( 'Subscription', 'shaversclub-store' ); ?>:
                    </th>
                    <th>
                        <a title="<?php _e( 'Edit', 'shaversclub-store' ); ?>" href="post.php?post=<?php echo $subscription->ID; ?>&action=edit">
                            <?php _e( 'Subscription', 'shaversclub-store' ); ?> #<?php echo $subscription->ID; ?>
                        </a>
                    </th>
                    <th>
                        <?php echo date_i18n( wc_date_format(), ( new DateTime( $subscription->post_date ) )->getTimestamp() ); ?>
                    </th>
                    <th>
                        <?php echo $subscription->post_status; ?>
                    </th>
                    <th>
                        <?php echo $subscription->get_total_price( true ); ?><br>
                        <?php echo $iv_label; ?>
                    </th>
                </tr>
                <tr>
                    <th>
                        <?php _e( 'Order number', 'shaversclub-store' ); ?>
                    </th>
                    <th>
                        <?php _e( 'Initial', 'shaversclub-store' ); ?>
                    </th>
                    <th>
                        <?php _e( 'Date', 'shaversclub-store' ); ?>
                    </th>
                    <th>
                        <?php _e( 'Status', 'shaversclub-store' ); ?>
                    </th>
                    <th>
                        <?php _e( 'Total', 'shaversclub-store' ); ?>
                    </th>
                </tr>
                <?php foreach ( $orders as $o ): if( $o->ID == $order->ID ) continue; ?>
                    <tr>
                        <td>
                            <a title="<?php _e( 'Edit', 'shaversclub-store' ); ?>" href="post.php?post=<?php echo $o->ID; ?>&action=edit">#<?php echo $o->ID; ?></a>
                        </td>
                        <td>
                            <span class="dashicons dashicons-<?php echo $o->initial ? 'clock' : 'backup' ; ?>"></span>
                        </td>
                        <td>
                            <?php echo date_i18n( wc_date_format(), $o->date->getTimestamp() ); ?>
                        </td>
                        <td>
                            <?php echo $o->post_status; ?>
                        </td>
                        <td>
                            <?php echo $o->get_total_price( true ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php
        }, static::$post_type, 'normal');

        add_meta_box( 'email', __('Email', 'shaversclub-store'), function() use ( $order ) {
            ?>

            <select name="order-emails">
                <option value="new-order"><?php _e( 'New customer order (%d) - %s', 'shaversclub-store' ); ?></option>
                <option value="failed-order"><?php _e( 'Failed order (%d)', 'shaversclub-store' ); ?></option>
                <option value="cancelled-order"><?php _e( 'Cancelled order (%d)', 'shaversclub-store' ); ?></option>
                <option value="campaign-expired"><?php _e( 'Campaign expired', 'shaversclub-store' ); ?></option>
                <option value="completed-order"><?php _e( 'Your order from %s is complete', 'shaversclub-store' ); ?></option>
                <option value="invoice"><?php _e( 'Invoice for order (%d) from %s', 'shaversclub-store' ); ?></option>
                <option value="new-account"><?php _e( 'Your new account', 'shaversclub-store' ); ?></option>
                <option value="note"><?php _e( 'Note added to your order from %s', 'shaversclub-store' ); ?></option>
                <option value="processing-order"><?php _e( 'Your order receipt from %s', 'shaversclub-store' ); ?></option>
                <option value="partially-refunded-order"><?php _e( 'Your order from %s has been partially refunded', 'shaversclub-store' ); ?></option>
                <option value="refunded-order"><?php _e( 'Your order from %s has been refunded', 'shaversclub-store' ); ?></option>
                <option value="reset-password"><?php _e( 'Password Reset', 'shaversclub-store' ); ?></option>
            </select>
            <input type="email" name="email" value="<?php echo $order->get_customer()->user_email; ?>" />
            <button class="send_email button button-primary" data-id="<?php echo $order->ID; ?>"><?php _e( 'Send', 'shaversclub-store' ); ?></button>
            <span class="spinner"></span>

            <?php
        }, static::$post_type, 'side');

        add_meta_box( 'notifications', __('Notifications', 'shaversclub-store'), function() use ( $order ) {
            $notifications = AdyenNotification::query( array(
                'meta_key' => 'merchantReference',
                'meta_value' => $order->ID,
            ) );
            foreach ( $notifications as $not ): $date = new DateTime( $not->post_date ); ?>
                <div class="note">
                    <strong>Event: </strong><?php echo $not->eventCode; ?><br>
                    <strong>Success: </strong><?php echo $not->success; ?><br>
                    <small><?php echo $date ? $date->format( 'Y-m-d H:i:s' ) : ''; ?></small>
                </div>
            <?php		endforeach;

        }, static::$post_type, 'side');

    }

    public static function save_post( $post_id ) {
        $keys = array( '' );

        if( isset( $_POST[ 'products' ], $_POST[ 'price' ] ) ) {

            $order = new SS_Order( $post_id );

            if( $order->has_post() ) {

                $order->products = array();
                $quantities = isset( $_POST['quantities'] ) ? (array)$_POST['quantities'] : [];

                foreach ( $_POST[ 'products' ] as $i => $p ) {

                    $product = new Product( $p );
                    if( ! $product->has_post() ) {
                        continue;
                    }

                    $product->quantity = isset( $quantities[ $i ] ) && ( $q = intval( $quantities[ $i ] ) ) ? $q : 1;
                    $order->add_product( $product );
                    update_post_meta( $post_id, '_quantity_' . $product->ID, $product->quantity);

                }
                $order->price = null;
                if( isset( $_POST['price'] ) && is_numeric( $_POST['price'] ) ) {
                    $order->price = floatval( $_POST['price'] );
                }
                remove_action( 'save_post_ss_order', array( 'SS_Order', 'save_post') );
                if( ( $order->post_status != $_POST['hidden_post_status'] ) && ( $_POST['hidden_post_status'] == 'completed' ) ) {
                    $order->set_meta( 'was_completed', date( 'Y-m-d' ) );
                }
                $order->save( array( 'post_status' => $_POST['hidden_post_status'] ) );
                add_action( 'save_post_ss_order', array( 'SS_Order', 'save_post') );

            }

        }

        foreach ($keys as $key) {
            if( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, $_POST[ $key ] );
            }
        }
    }

}
