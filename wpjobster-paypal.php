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

		public $_paypal_url, $_jb_action, $_oid;

		public static function init() {
			$class = __CLASS__; new $class;
		}

		public function __construct(){
			global $wpdb;

			$this->paypal_url = $this->submit_url();

			$this->site_url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];

			$this->title = 'just a test';
			$this->amount = 5;
			$this->oid = 1;
			$this->key = md5( date("Y-m-d:").rand() );
			$this->charset = get_bloginfo( 'charset' );
			$this->currency_code = get_option('wpjobster_currency_1');

			$this->business = get_option('wpjobster_paypal_email');
			if( empty( $this->business ) ){
				echo __("ERROR: please input your paypal address in backend",'wpjobster');
				exit;
			}

			$this->p = new paypal_class;
		}

		public function submit_url(){
			$sdb = get_option('wpjobster_paypal_enable_sdbx');
			$paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
			if($sdb == "yes")
				$paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

			return $paypal_url;
		}

		public function job_payment(){
			return;
		}

		public function featured_payment(){
			return;
		}

		public function topup_payment(){
			return;
		}

		public function subscription_payment(){
			return;
		}

		public function custom_extra_payment(){
			return;
		}

		public function badge_payment(){
			return;
		}

		public function success(){
			echo "<br/><p><b>Thank you for your Donation. </b><br /></p>";
			foreach ($_POST as $key => $value) { echo "$key: $value<br>"; }
		}

		public function cancel(){
			echo "<br/><p><b>The order was canceled!</b></p><br />";
			foreach ($_POST as $key => $value) { echo "$key: $value<br>"; }
		}

		public function ipn(){
			if ( $this->p->validate_ipn() ) {
				$dated = date("D, d M Y H:i:s", time());

				$subject = 'Instant Payment Notification - Recieved Payment';
				$to = 'aleximbir92@gmail.com';
				$body =  "An instant payment notification was successfully recieved\n";
				$body .= "from ".$p->ipn_data['payer_email']." on ".date('m/d/Y');
				$body .= " at ".date('g:i A')."\n\nDetails:\n";
				$headers = "";
				$headers .= "From: Test Paypal \r\n";
				$headers .= "Date: $dated \r\n";

				$PaymentStatus =  $p->ipn_data['payment_status'];
				$Email        =  $p->ipn_data['payer_email'];
				$id           =  $p->ipn_data['item_number'];

				if($PaymentStatus == 'Completed' or $PaymentStatus == 'Pending'){
						$PaymentStatus = '2';
				}else{
						$PaymentStatus = '1';
				}
				foreach ($p->ipn_data as $key => $value) { $body .= "\n$key: $value"; }

				wp_mail( $to, $subject, $body, $headers );
			}
		}

		public function paypal_action( $action='', $payment_type='' ){

			if( $payment_type ) {

				if ( empty( $action ) ) $action = 'process';

				$this->p->paypal_url = $this->paypal_url;

				switch ($action) {
					case 'process':
						$this->p->add_field( 'business', $this->business );
						$this->p->add_field( 'return', $this->site_url . '?action=success' );
						$this->p->add_field( 'cancel_return', $this->site_url . '?action=cancel' );
						$this->p->add_field( 'notify_url', $this->site_url . '?action=ipn' );
						$this->p->add_field( 'item_name', $this->title ) ;
						$this->p->add_field( 'amount', $this->amount );
						$this->p->add_field( 'key', $this->key );
						$this->p->add_field( 'item_number', $this->oid );
						$this->p->add_field( 'currency_code',$this->currency_code );
						$this->p->add_field( 'charset', $this->charset );
						$this->p->add_field( 'custom', $payment_type );

						$this->p->submit_paypal_post();
						break;
					case 'success':
						$this->success();
						break;

					case 'cancel':
						$this->cancel();
						break;

					case 'ipn':
						$this->ipn();
						break;
				}
			}
		}

	} // END CLASS

} // END IF CLASS EXIST
