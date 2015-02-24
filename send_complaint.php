<?php

class Complaint {

	private $config;

	public function __construct($config){
		$this->config = $config;
		$this->user = $this->getUser($config);
	}

	/**
	 * Create the required URL to use the non-agent vicidial API
	 *
	 */
	public function initializeAPI($fields){
		
		$API['url'] = 'http://'.$_SERVER['SERVER_ADDR'].'/vicidial/non_agent_api.php?';
		$API['source'] = 'ComplaintScript';
		$API['user'] = 'c4tech';
		$API['pass'] = $this->user;
		$API['function'] = 'update_lead';
		$API['lead_id'] = $fields['lead_id'];
		$API['comments'] = urlencode($fields['comments'] . '  ' . $fields['complaint']);
		$API['custom_fields'] = 'Y';
		$API['complaint'] = ' ';
		
		$fullURL = $API['url'];
		foreach($API as $key => $value)
			if($key != 'url')
				$fullURL .= "$key=$value&";

		return $fullURL;	
	}

	/**
	 * Execute the cURL in order to call the non-agent API in order to update the required lead.
	 *
	 */
	public function updateField($url){
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$curl_response = curl_exec($curl);
		if ($curl_response === false) {
			$info = curl_getinfo($curl);
			curl_close($curl);
			die('error occured during curl exec. Additioanl info: ' . var_export($info));
		}
		curl_close($curl);

		$log = print_r($curl_response);
		error_log($log);
	}


        /**
         *  Send an email to the specified account in configuration.php reporting the complaint
         *
         */
        public function sendEmail($fields){
                require 'lib/PHPMailerAutoload.php';

                $mail = new PHPMailer(true);
                try {
                        $mail->isSMTP();
			//$mail->SMTPDebug = 2;  //uncomment for debugging email problems
                        $mail->Host = $this->config['smtphost'];
                        $mail->SMTPAuth = true;                        // Enable SMTP authentication
                        $mail->Username = $this->config['smtpuser'];             // SMTP username
                        $mail->Password = $this->config['smtppass'];               // SMTP password
                        $mail->SMTPSecure = 'ssl';                          // Enable TLS encryption, `ssl` also accepted
                        $mail->Port = 465;                                  // TCP port to connect to

                        $mail->From = $this->config['smtpuser'];
                        $mail->FromName = 'Vicidial Mailer';
                        $mail->addAddress($this->config['email'], $this->config['emailName']);     // Add a recipient

                        $mail->isHTML(true);                              // Set email format to HTML

                        $mail->Subject = "Complaint received for Lead ID {$fields['lead_id']} from User {$fields['user']}";

			$mail->Body  = "Name: ${fields['first_name']} ${fields['last_name']} <BR/>";
			$mail->Body .= "Company: ${fields['address1']} <BR/>";
                        $mail->Body .= "Phone: ${fields['phone_number']} <BR/>";
                        $mail->Body .= "Comments: ${fields['comments']}  ${fields['complaint']} <BR/>";



                        $mail->send();
                } catch (phpMailerException $e) {
                        echo $e->errorMessage();
                } catch (Exception $e) {
                        echo $e->getMessage();
                }
        }

	/**
	 * Make a request to the database in order to get the user password for the api call
	 *
	 */
	private function getUser($config){
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

                $sql = "select pass from vicidial_users WHERE user=\"{$this->config['user']}\"";
                $mysqli = new mysqli($config['dbhost'], $config['dbuser'], $config['dbpass'], $config['dbname'], $config['dbport']);
                $result = $mysqli->query($sql);

		$fetch = $result->fetch_row();

		return $fetch[0];


	}


}
if($_GET['complaint']) {
	include 'config/configuration.php';

	$complaint = new Complaint($config);
	$apiURL = $complaint->initializeAPI($_GET);
	$complaint->updateField($apiURL);
	$complaint->sendEmail($_GET);
}
?>
