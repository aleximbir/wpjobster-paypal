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

		public function __construct( $gateway='paypal' ) {

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

		public function is_subscription_payment( $payment_type, $sub_type ) {
			if ( $payment_type == 'subscription' && $sub_type == 'lifetime' ){
				return 'lifetime';
			} elseif ( $payment_type == 'subscription' && $sub_type != 'lifetime' ){
				return 'recurring';
			} else {
				return false;
			}
		}

		public function paypal_form( $payment_type, $common_details ) {

			// Normal Purchase Vars
			$order_id                       = isset( $common_details['order_id'] ) ? $common_details['order_id'] : '';
			$pid                            = isset( $common_details['pid'] ) ? $common_details['pid'] : '';
			$currency_code                  = isset( $common_details['selected'] ) ? $common_details['selected'] : 'USD';
			$wpjobster_final_payable_amount = isset( $common_details['wpjobster_final_payable_amount'] ) ? $common_details['wpjobster_final_payable_amount'] : 0.1;

			// Recurring Purchase Vars
			$cycle     = isset( $common_details['cycle'] ) ? $common_details['cycle'] : '';
			$period    = isset( $common_details['period'] ) ? $common_details['period'] : '';
			$sub_type  = isset( $common_details['sub_type'] ) ? $common_details['sub_type'] : '';
			$sub_level = isset( $common_details['sub_level'] ) ? $common_details['sub_level'] : '';

			// Set Product Title
			if ( $this->is_subscription_payment( $payment_type, $sub_type ) == 'recurring' ) {
				$title = strtoupper( $sub_type . ' Plan' );
			} else {
				$title = isset( $common_details['title'] ) ? $common_details['title'] : '-';
			}

			// Get Order ID
			$payment = wpj_get_payment( array(
				'payment_type'    => $payment_type,
				'payment_type_id' => $order_id,
			) );

			// Redirection Pages
			$notify_page  = get_bloginfo( 'siteurl' ) . '/?payment_response=paypal&payment_type=' . $payment_type . '&oid=' . $order_id;
			$success_page = get_bloginfo( 'siteurl' ) . '/?jb_action=loader_page&payment_type=' . $payment_type . '&oid=' . $order_id;
			$cancel_page  = get_bloginfo( 'siteurl' ) . '/?payment_response=paypal&payment_type=' . $payment_type . '&action=cancel&order_id=' . $order_id . '&jobid=' . $pid;

			if ( $this->is_subscription_payment( $payment_type, $sub_type ) ) {
				$notify_page  = get_bloginfo( 'siteurl' ) . '/?payment_response=paypal&payment_type=' . $payment_type . '&oid=' . $order_id . '&sub_type=' . $common_details['sub_type'];
			}

			// Send data to PayPal
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

			if ( $this->is_subscription_payment( $payment_type, $sub_type ) == 'recurring' ) {
				$this->p->add_field( 'cmd', '_xclick-subscriptions' );
				$this->p->add_field( 'lc', 'IN' );
				$this->p->add_field( 'no_note', 1 );
				$this->p->add_field( 'src',1 );
				$this->p->add_field( 'a3', $wpjobster_final_payable_amount );
				$this->p->add_field( 'p3', $period );
				$this->p->add_field( 't3', $cycle );
				$this->p->add_field( 'bn', 'PP-SubscriptionsBF:btn_subscribeCC_LG.gif:NonHostedGuest' );
			}

			$this->p->submit_paypal_post();
		}

		public function paypal_response( $payment_type, $wcf ) {
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
				// job purchase, featured job, subscription lifetime, topup, custom extra

					$payment = wpj_get_payment( array(
						'id' => $_POST['custom'],
					) );
					$order_id       = $payment->payment_type_id;
					$payment_type   = $payment->payment_type;

					$payment_status = $_POST['payment_status'];
					$transaction_id = $_POST['txn_id'];
					$txn_type       = $_POST['txn_type'];

					if ( ucfirst( $payment_status ) == ucfirst ( 'Completed' ) ) {
						$this->success( $payment_type, $order_id, $transaction_id, $payment_response );
					} else {
						$this->failed( $payment_type, $order_id, $transaction_id, $payment_response );
					}
				} elseif ( isset( $_POST['txn_type'] ) && isset( $_POST['subscr_id'] ) && isset( $_POST['custom'] ) ) {
				// subscription recurring

					$payment = wpj_get_payment( array(
						'id' => $_POST['custom'],
					) );
					$order_id       = $payment->payment_type_id;
					$payment_type   = $payment->payment_type;

					$payment_status = isset( $_POST['payment_status'] ) ? $_POST['payment_status'] : 'pending';
					$transaction_id = $_POST['subscr_id'];
					$txn_type       = $_POST['txn_type'];

					if ( $this->is_subscription_payment( $payment_type, $_REQUEST['sub_type'] ) == 'recurring' ) {
						if ( $txn_type == 'subscr_signup' ) {
							do_action( "wpjobster_new_" . $payment_type . "_payment_success", $order_id, $this->_unique_id, $transaction_id, $payment_response );
						} elseif ( $txn_type == 'subscr_cancel' || $txn_type == 'subscr_failed' ) {
							do_action( "wpjobster_" . $payment_type . "_payment_failed", $order_id, $this->_unique_id, $transaction_id, $payment_response );
						} elseif ( $txn_type == 'subscr_payment' ) {
							do_action( "wpjobster_" . $payment_type . "_payment_success", $order_id, $this->_unique_id, $transaction_id, $payment_response );
						} else {
							$payment_details = "Your payment is under process and transaction id is " . $transaction_id ." and payment_status is $payment_status ";
							do_action( "wpjobster_" . $payment_type . "_payment_other", $order_id, $this->_unique_id, $payment_details, $payment_response, $payment_Status='processing' );
						}
					}
				}
			}
		}

		public function success( $payment_type, $order_id, $transaction_id, $payment_response ) {
			if ( $payment_type == 'subscription' && ( isset( $_GET['sub_type'] ) && $_GET['sub_type'] == 'lifetime' ) ) {
				do_action( "wpjobster_new_" . $payment_type . "_payment_success", $order_id, $this->_unique_id, $transaction_id, $payment_response );
			}
			do_action( "wpjobster_" . $payment_type . "_payment_success", $order_id, $this->_unique_id, $transaction_id, $payment_response );
		}

		public function cancel( $payment_type, $order_id, $transaction_id, $payment_response ) {
			do_action( "wpjobster_".$payment_type."_payment_failed", $_GET['order_id'], $this->_unique_id, '', $payment_response );
		}

		public function failed( $payment_type, $order_id, $transaction_id, $payment_response ) {
			do_action( "wpjobster_" . $payment_type . "_payment_failed", $order_id, $this->_unique_id, $transaction_id, $payment_response );
		}

	} // END CLASS

} // END IF CLASS EXIST
