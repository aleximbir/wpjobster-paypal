<?php
/*
	Plugin Name: WPJobster PayPal
	Plugin URL: http://wpjobster.com/
	Description: WPJobster PayPal Payment System.
	Version: 1.0.0
	Author: WPJobster
	Author URI: http://wpjobster.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( plugin_dir_path( __FILE__ ) . 'lib/paypal.class.php' );

if( ! class_exists("WPJobster_PayPal_Loader") ) {

	class WPJobster_PayPal_Loader {

		public $_paypal_url, $_jb_action, $_oid, $_unique_id;

		public static function init() {
			$class = __CLASS__; new $class;
		}

		public function __construct( $gateway='paypal' ){

			$this->_unique_id = 'paypal';
			add_action( 'show_paypal_form', array($this, 'paypal_form' ), 10, 2 );
			add_action( 'paypal_response', array($this, 'paypal_response' ), 10, 2 );

			$this->site_url = get_bloginfo( 'siteurl' );

			$this->key = md5( date("Y-m-d:").rand() );

			$this->business = get_option('wpjobster_paypal_email');
			if( empty( $this->business ) ){
				echo __("ERROR: please input your paypal address in backend",'wpjobster');
				exit;
			}

			$this->p = new paypal_class;
		}

		public function paypal_form( $payment_type, $common_details ){

			$title                          = $common_details['title'];
			$order_id                       = $common_details['order_id'];
			$pid                            = $common_details['pid'];
			$currency_code                  = $common_details['selected'];
			$wpjobster_final_payable_amount = $common_details['wpjobster_final_payable_amount'];

			$payment = wpj_get_payment( array(
				'payment_type'    => $payment_type,
				'payment_type_id' => $order_id,
			) );

			$notify_page  = get_bloginfo( 'siteurl' ) . '/?payment_response=paypal&payment_type=' . $payment_type . '&oid=' . $order_id;
			$success_page = get_bloginfo( 'siteurl' ) . '/?jb_action=loader_page&payment_type=' . $payment_type . '&oid=' . $order_id;
			$cancel_page  = get_bloginfo( 'siteurl' ) . '/?payment_response=paypal&payment_type=' . $payment_type . '&action=cancel&order_id=' . $order_id . '&jobid=' . $pid;

			if( $payment_type == 'subscription' && $common_details['sub_type'] == 'lifetime' ){
				$notify_page  = get_bloginfo( 'siteurl' ) . '/?payment_response=paypal&payment_type=' . $payment_type . '&oid=' . $order_id . '&sub_type=' . $common_details['sub_type'];
			}

			$this->p->add_field( 'business'     , $this->business );
			$this->p->add_field( 'currency_code', $currency_code );
			$this->p->add_field( 'return'       , $success_page );
			$this->p->add_field( 'cancel_return', $cancel_page );
			$this->p->add_field( 'notify_url'   , $notify_page );
			$this->p->add_field( 'item_name'    , $title ) ;
			$this->p->add_field( 'item_number'  , $pid );
			$this->p->add_field( 'charset'      , get_bloginfo( 'charset' ) );
			$this->p->add_field( 'amount'       , $wpjobster_final_payable_amount );
			$this->p->add_field( 'custom'       , $payment->id );
			$this->p->add_field( 'key'          , $this->key );

			$this->p->submit_paypal_post();
		}

		public function paypal_response( $payment_type, $wcf ){
			if ( $this->p->validate_ipn() ) {
				if ( $payment_type == '' ) {
					if( isset( $_POST['custom'] ) ) {
						$pid = $_POST['item_number'];
						$payment = wpj_get_payment( array(
							'id' => $_POST['custom'],
						) );
						$order_id        = $payment->payment_type_id;
						$payment_type    = $payment->payment_type;
					} else {
						$item_number     = $_POST['item_number'];
						$item_number_arr = explode( "|",$item_number );
						$pid             = $item_number_arr['0'];
						$payment_type    = $item_number_arr['1'];
					}
				}

				$payment_response = json_encode( $_REQUEST );

				if( ! isset( $order_id ) ){
					$order_id = isset( $_GET['order_id'] ) ? $_GET['order_id'] : "Unknown Order";
				}
				$transaction_id = isset( $_POST['txn_id'] ) ? $_POST['txn_id']                 : "No ID";
				$payment_status = isset( $_POST['payment_status'] ) ? $_POST['payment_status'] : "NO status";

				$payment['order_id']         = $order_id;
				$payment['payment_type']     = $payment_type;
				$payment['transaction_id']   = $transaction_id;
				$payment['payment_response'] = $payment_response;
				$payment['payment_status']   = $payment_status;
				$payment['gateway']          = $this->_unique_id;

				do_action( "wpjobster_store_payment_gateway_log", $payment );

				if ( isset( $_GET['action'] ) && $_GET['action'] == 'cancel' ) {
					$this->cancel();
				} elseif ( isset( $_POST['txn_id'] ) && isset( $_POST['payment_status'] ) && isset( $_POST['custom'] ) ) {
					$payment = wpj_get_payment( array(
						'id' => $_POST['custom'],
					) );
					$order_id       = $payment->payment_type_id;
					$payment_type   = $payment->payment_type;

					$tm             = time();
					$payment_status = $_POST['payment_status'];
					$transaction_id = $_POST['txn_id'];

					if ( ucfirst( $payment_status ) == ucfirst ( 'Completed' ) ) {
						$this->success( $payment_type, $order_id, $transaction_id, $payment_response );
					} else {
						$this->failed( $payment_type, $order_id, $transaction_id, $payment_response );
					}
				}
			}
		}

		public function success( $payment_type, $order_id, $transaction_id, $payment_response ){
			if ( $payment_type == 'subscription' && ( isset( $_GET['sub_type'] ) && $_GET['sub_type'] == 'lifetime' ) ) {
				do_action( "wpjobster_new_" . $payment_type . "_payment_success", $order_id, $this->_unique_id, $transaction_id, $payment_response );
			}
			do_action( "wpjobster_" . $payment_type . "_payment_success", $order_id, $this->_unique_id, $transaction_id, $payment_response );
		}

		public function cancel( $payment_type, $order_id, $transaction_id, $payment_response ){
			do_action( "wpjobster_".$payment_type."_payment_failed", $_GET['order_id'], $this->_unique_id, '', $payment_response );
		}

		public function failed( $payment_type, $order_id, $transaction_id, $payment_response ){
			do_action( "wpjobster_" . $payment_type . "_payment_failed", $order_id, $this->_unique_id, $transaction_id, $payment_response );
		}

	} // END CLASS

} // END IF CLASS EXIST
