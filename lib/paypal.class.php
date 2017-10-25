<?php
class paypal_class {
	var $last_error;                 // holds the last error encountered
	var $ipn_log;                    // bool: log IPN results to text file?
	var $ipn_log_file;               // filename of the IPN log
	var $ipn_response;               // holds the IPN response from paypal
	var $ipn_data = array();         // array contains the POST values for IPN
	var $fields = array();           // array holds the fields to submit to paypal
	var $sandbox;

	function paypal_class($sandbox = TRUE) {
		$this->sandbox = $sandbox;

		if( $this->sandbox ){
			$this->paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		} else {
			$this->paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
		}

		$this->last_error = '';
		$this->ipn_log_file = '.ipn_results.log';
		$this->ipn_log = true;
		$this->ipn_response = '';

		$this->add_field('rm','2');
		$this->add_field('cmd','_xclick');
	}

	function add_field($field, $value) {
		$this->fields["$field"] = $value;
	}

	function submit_paypal_post() {
		echo "<html>\n";
		echo "<head><title>Processing Payment...</title></head>\n";
		echo "<body onLoad=\"document.forms['paypal_form'].submit();\">\n";
		echo "<center><h2>Please wait, your order is being processed and you";
		echo " will be redirected to the paypal website.</h2></center>\n";
		echo "<form method=\"post\" name=\"paypal_form\" ";
		echo "action=\"".$this->paypal_url."\">\n";

		foreach ($this->fields as $name => $value) {
			echo "<input type=\"hidden\" name=\"$name\" value=\"$value\"/>\n";
		}

		echo "<center><br/><br/>If you are not automatically redirected to ";
		echo "paypal within 5 seconds...<br/><br/>\n";
		echo "<input type=\"submit\" value=\"Click Here\"></center>\n";

		echo "</form>\n";
		echo "</body></html>\n";
	}

	function validate_ipn() {

		$req = 'cmd=_notify-validate';

		foreach($_POST as $key => $value) {
			$value = urlencode($value);
			$req .= "&{$key}={$value}";
		}

		$res = '';
		$ch = curl_init($this->paypal_url);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
		$res = curl_exec($ch);
		curl_close($ch);

		if(strcmp($res, "VERIFIED") == 0){
			// Valid IPN transaction.
			$this->log_ipn_results(true);
			return true;
		}else{
			// Invalid IPN transaction.  Check the log for details.
			$this->last_error = 'IPN Validation Failed.';
			$this->log_ipn_results(false);
			return false;
		}
	}

	function log_ipn_results($success) {
		if (!$this->ipn_log) return;

		// Timestamp
		$text = '['.date('m/d/Y g:i A').'] - ';

		// Success or failure being logged?
		if ($success) $text .= "SUCCESS!\n";
		else $text .= 'FAIL: '.$this->last_error."\n";

		// Log the POST variables
		$text .= "IPN POST Vars from Paypal:\n";
		foreach ($this->ipn_data as $key=>$value) {
			$text .= "$key=$value, ";
		}

		// Log the response from the paypal server
		$text .= "\nIPN Response from Paypal Server:\n ".$this->ipn_response;

		// Write to log
		$fp=fopen($this->ipn_log_file,'a');
		fwrite($fp, $text . "\n\n");

		fclose($fp);
	}

	function dump_fields() {
		echo "<h3>paypal_class->dump_fields() Output:</h3>";
		echo "<table width=\"95%\" border=\"1\" cellpadding=\"2\" cellspacing=\"0\">
			<tr>
				<td bgcolor=\"black\"><b><font color=\"white\">Field Name</font></b></td>
				<td bgcolor=\"black\"><b><font color=\"white\">Value</font></b></td>
			</tr>";

		ksort($this->fields);
		foreach ($this->fields as $key => $value) {
			echo "<tr><td>$key</td><td>".urldecode($value)."&nbsp;</td></tr>";
		}

		echo "</table><br>";
	}
}
