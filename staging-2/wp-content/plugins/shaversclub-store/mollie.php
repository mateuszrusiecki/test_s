<?php

    SS_Custom_Logger::write( '-- mollie.php --' );

	$dir = dirname( __FILE__ );
	while ( ! file_exists( $dir . '/wp-load.php' ) ) {
		$dir = dirname( $dir );
		if( trim( $dir, "/ \t\n\r\0\x0B" ) == '' ) {
			die( 'Could not find ABSPATH' );
		}
	}


	define('WP_USE_THEMES', false);

	require $dir . '/wp-load.php';

	if( session_status() == PHP_SESSION_ACTIVE ) {
		$_SESSION = [];
		if( ini_get('session.use_cookies') ) {
			$params = session_get_cookie_params();
			setcookie( session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly'] );
		}
		session_destroy();
		session_start();
	}

	SS_Logger::write( '-- mollie.php --' );

	try{
		$mollie = SS_Mollie::get_instance();

		$payment  = $mollie->payments->get( $_POST['id'] );
		$order = new SS_Order( $payment->metadata->order_id );
		$customer = $order->get_customer();
		$status = $payment->status;

		//SS_Logger::write( $status );
        SS_Custom_Logger::write( (array)$payment );
        SS_Custom_Logger::write( $order );
        SS_Custom_Logger::write( $customer );
        SS_Custom_Logger::write( $payment->status );

		if( $pd = $order->meta('_paid_date') ) {
            SS_Custom_Logger::write( 'already paid: ' . $pd );
			exit();
		}


		switch ( $payment->status ) {
			case 'open':
				//wp_update_post( array( 'ID' => $order->ID, 'post_status' => 'pending' ) );
				if( $order->post_status == 'upcoming' ) {
					$order->mail('new-order');
				}
				$order->set_status('pending');
				$order->save();
				break;

			case 'expired':
				$status = 'cancelled';

			case 'failed':
			case 'cancelled':

				$order->set_status( $status );
				$order->save();

				if( $order->subscription && $order->subscription->has_post() && ( $sid = $order->subscription->ID ) ) {

					global $wpdb;
					$id = $wpdb->get_var( "SELECT ID
											FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON ( ID = post_id )
											WHERE ID != '$order->ID'
												AND meta_key = 'subscription'
												AND meta_value = '$sid'
												AND post_date_gmt > '$order->post_date_gmt'" );

					if( empty( $id ) ) {

						$order->subscription->_fill_vars();
						$order->subscription->set_status('on-hold');
						$order->subscription->save();

					}

				}

				if( $order->meta( 'ref' ) ) {
					$customer->clear_used_ref( $order->ID, true );
				}

				$order->mail( "$status-order" );

				break;

			case 'paid':
				$order->lock();
				$order->paid();

				if( $payment->mandateId ) {
					update_user_meta( $customer->ID, 'mollie_mandate_id', $payment->mandateId );
				}
				if( $payment->customerId ) {
					update_user_meta( $customer->ID, 'mollie_id', $payment->customerId );
				}

				$order->unlock();
				break;

			default:
				break;
		}

        SS_Custom_Logger::write( $order->post_status );

	} catch ( Mollie_API_Exception $e ) {
        SS_Custom_Logger::write( '-- Exception -- ' . $e->getField() . ': ' . $e->getMessage() );
		$order->set_status( 'failed' );
		$order->save();

		HelperFunctions::mail_error( $order, "[payment webhook]\n" . $e->getField() . ': ' . $e->getMessage() );
	}
