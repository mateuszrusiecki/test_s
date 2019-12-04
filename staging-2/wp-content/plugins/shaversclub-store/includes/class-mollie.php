<?php

require_once SS_PATH . 'includes/libs/Mollie/API/Autoloader.php';


class SS_Mollie {

	// const API_KEY = 'test_n88U6JSKNb2tQjcUtA5gJKTSHCca8d'; private static $mollie_id_key = 'test_mollie_id'; private static $mollie_mandate_id_key = 'test_mollie_mandate_id';
	const API_KEY = 'live_tER36yyJBWzK2v4th7pFpAFJ6xQApm'; private static $mollie_id_key = 'mollie_id'; private static $mollie_mandate_id_key = 'mollie_mandate_id';

	private static $mollie = null;

	public static function get_instance() {
		if( ! self::$mollie ) {
			self::$mollie = new Mollie_API_Client;
			self::$mollie->setApiKey( self::API_KEY );
		}

		return self::$mollie;
	}

	public static function payment( $order ) {

		SS_Logger::write( 'SS_Mollie:payment' );

		$customer = $order->get_customer();
		$method = str_replace( 'sepa', '', $order->payment );
		$recurring = null;


		SS_Logger::write( $order );
		SS_Logger::write( $customer );

/*
$ids = [
	1451,
	2443,
//	5,
//	8,
//	11,
//	12,
	1465,
	1433,
	2773,
];

if(!in_array($customer->ID, $ids)) {
	return array( 'status' => 'error', 'message' => 'je bent niet ingelogd als marco' );
}
*/
		if( $method == 'ideal' ) {
			if( $order->subscription ) {
				if( ! $order->initial && $customer->is_recurring() ) {
					$recurring = 'recurring';
					$method = 'directdebit';
				} else {
					$recurring = 'first';
				}
			} else {
				$recurring = null;
			}
		} elseif( $method == 'directdebit' && $customer->is_recurring() ) {
			$recurring = 'recurring';
		} else {
			$order->set_status( 'failed' );
			$order->save();
			SS_Logger::write( 'Ongeldige combinatie van betaalmethode en ordergegevens' );
			return array( 'status' => 'error', 'message' => 'Ongeldige combinatie van betaalmethode en ordergegevens.' );
		}

		SS_Logger::write( $method );
		SS_Logger::write( $recurring );

		$error_message = false;

		try {

			$mollie_id = $customer->meta( self::$mollie_id_key );
			if( ! $mollie_id ) {
				$mollie_id = self::create_customer( $customer );
			}

			$mollie = self::get_instance();

			$payment = $mollie->customers_payments->withParentId( $mollie_id )->create( array(
				'amount'		=> $order->get_total_price(),
				'description'	=> sprintf( __( 'Order %d - %s', 'shaversclub-store' ), $order->ID, date_i18n( 'j F, Y @ H:i', $order->date->getTimestamp() ) ),
				'redirectUrl'	=> add_query_arg( 'o', $order->ID, get_permalink( 107503) ),//get_site_url( null, 'thank-you' )
				// 'redirectUrl'	=> 'http://shaver.nl/bedankpagina/?o=' . $order->ID,
				'recurringType'	=> $recurring,
				'webhookUrl'	=> SS_URL . 'mollie.php',
				'locale'		=> 'nl_NL',
				'method'		=> $method,
				'metadata'		=> array( 'order_id' => $order->ID ),
			) );

			SS_Logger::write( (array)$payment );

			update_post_meta( $order->ID, 'mollie_status', $payment->status );
			update_post_meta( $order->ID, self::$mollie_id_key, $payment->id );

			$url = $payment->getPaymentUrl();
			if( ! $url ) {
				$url = add_query_arg( 'o', $order->ID, get_permalink( 107503 ) );
				// $url = 'http://shaver.nl/bedankpagina/?o=' . $order->ID;
			}

			$out = array(
				'status' => 'success',
				'url' => $url,
			);
		} catch ( Mollie_API_Exception $e ) {
			$out = array(
				'status' => 'error',
				'message' => htmlspecialchars( $e->getField() ) . ': ' . htmlspecialchars( $e->getMessage() ),
			);

			$error_message = $e->getField() . ': ' . $e->getMessage();

		} catch ( Exception $e ) {
			$out = array(
				'status' => 'error',
				'message' => $e->getMessage(),
			);

			$error_message = '###: ' . $e->getMessage();
		}

		if( $error_message ) {

			$order->set_status( 'failed' );
			$order->save();

			HelperFunctions::mail_error( $order, "[payment function]\n$error_message" );
			$order->mail( 'failed-order' );

		}

		return $out;
	}

	public static function create_customer( $customer ) {

		$mollie = self::get_instance();
		$response = $mollie->customers->create([
			'name'		=> $customer->meta('first_name') . ' ' . $customer->meta('last_name'),
			'email'		=> $customer->user_email,
			'locale'	=> 'nl_NL',
		]);

		update_user_meta( $customer->ID, self::$mollie_id_key, $response->id );
		$customer->clear_meta();
		return $response->id;
	}

	public static function create_mandate( $customer, $iban = false ) {

		$mollie = self::get_instance();

		$mollie_id = $customer->meta( self::$mollie_id_key );
		if( ! $mollie_id ) {
			$mollie_id = self::create_customer( $customer );
		}

		if( ! $iban ) {
			$iban = $customer->meta('iban');
		}

		//$iban = HelperFunctions::check_iban( (string)$iban );
		if( ! $iban ) {
			return false;
		}

		$mandate = $mollie->customers_mandates->withParentId( $mollie_id )->create( array(
			'method' => 'directdebit',
			'consumerAccount' => $iban,
			'consumerName' => $customer->meta('first_name') . ' ' . $customer->meta('last_name'),
		) );

		update_user_meta( $customer->ID,  self::$mollie_mandate_id_key , $mandate->id );
		$customer->clear_meta();

		return $mandate->id;
	}

	public static function get_mandate( $customer ) {

		$mollie_id = $customer->meta( self::$mollie_id_key );

		if( ! $mollie_id ) {
			return false;
		}

		$mollie = self::get_instance();

		$mollie_mandate_id = $customer->meta( self::$mollie_mandate_id_key );
		if( $mollie_mandate_id ) {
			return $mollie->customers_mandates->withParentId( $mollie_id )->get( $mollie_mandate_id );
		}

		$mandates = $mollie->customers_mandates->withParentId( $mollie_id )->all( 0, 1 );

		if( $mandates && $mandates->data ) {
			return $mandates->data[0];
		}

		return false;
	}

	public static function revoke_mandate( $customer ) {

		$mollie_id = $customer->meta( self::$mollie_id_key );
		if( ! $mollie_id ) {
			return false;
		}

		//delete_user_meta( $customer->ID, self::$mollie_id_key );

		$mollie_mandate_id = $customer->meta( self::$mollie_mandate_id_key );
		if( ! $mollie_mandate_id ) {
			return false;
		}

		delete_user_meta( $customer->ID,  self::$mollie_mandate_id_key  );
		$customer->clear_meta();

		$mollie = self::get_instance();

		return $mollie->customers_mandates->withParentId( $mollie_id )->delete( $mollie_mandate_id );

	}
}
