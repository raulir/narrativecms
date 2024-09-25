<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists($GLOBALS['config']['base_path'] . 'vendor/autoload.php')){
	require_once($GLOBALS['config']['base_path'] . 'vendor/autoload.php');
}

require_once('system/vendor/phpmailer/Exception.php');
require_once('system/vendor/phpmailer/PHPMailer.php');
require_once('system/vendor/phpmailer/SMTP.php');

class form_model extends CI_Model {
	
	function create_form_data($cms_page_panel_id, $email, $data){

    	// check if table exists
		$this->create_table_form_data();
		
		$data['time'] = time();
		
		$code = '';
		if (!empty($data['confirmation_code'])){
			
			$code = $data['confirmation_code'];
			
			unset($data['confirmation_code']);
			unset($data['codeurl']);
			
		}
		
		// check if code field exists
		$sql = "select COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = database() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
		$query = $this->db->query($sql, ['form_data', 'code', ]);
		$check = $query->result_array();
		
		if (empty($check)){
			$sql = "ALTER TABLE `form_data` ADD `code` varchar(100) NOT NULL";
			$this->db->query($sql);
			$sql = "ALTER TABLE `form_data` ADD INDEX `code_idx` (`code`(3))";
			$this->db->query($sql);
		}
		
		$sql = "insert into form_data set cms_page_panel_id = ? , email = ? , code = ? , data = ? ";
		$this->db->query($sql, [$cms_page_panel_id, $email, $code, json_encode($data), ]);
		$return = $this->db->insert_id();
		
		return $return;
	
	}
	
	function delete_form_data($form_data_id){
		
		$sql = "delete from form_data where form_data_id = ? limit 1 ";
		$this->db->query($sql, [$form_data_id]);
	
		return true;
		
	}
	
    function create_table_form_data(){
    	
    	$db_debug = $this->db->db_debug; //save setting
    	$this->db->db_debug = false; //disable debugging for queries
    	
		$sql = "select cms_page_panel_id from form_data limit 1 ";
		$query = $this->db->query($sql);

		$this->db->db_debug = $db_debug; //restore setting
		
		if($this->db->_error_number() == 1146){
    	
    		$sql = "CREATE TABLE `form_data` (
    					`form_data_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    					`cms_page_panel_id` int(10) UNSIGNED NOT NULL,
    					`email` varchar(100) NOT NULL, 
    					`code` varchar(100) NOT NULL, 
    					`data` text NOT NULL,
    					PRIMARY KEY (`form_data_id`)
    				) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    		$this->db->query($sql);
    	
    		$sql = "ALTER TABLE `form_data` ADD KEY `cms_page_panel_idx` (`cms_page_panel_id`)";
    		$this->db->query($sql);
    		$sql = "ALTER TABLE `form_data` ADD INDEX `code_idx` (`code`(3))";
    		$this->db->query($sql);
    	
    	}

    }
    
    function get_forms(){
    	
    	// check if table exists
		$this->create_table_form_data();
    		
		$sql = "select a.cms_page_panel_id, b.title from form_data a join cms_page_panel b on a.cms_page_panel_id = b.cms_page_panel_id group by a.cms_page_panel_id ";
		$query = $this->db->query($sql);
     		
     	$return = $query->result_array();
     	     	
 		return $return;
    	
    }
    
    /**
     * 
     * @param unknown $cms_page_panel_id
     * 
     * @param number $return_data 
     * 0 both data
     * 1 fields only
     * 2 data only 
     * 
     * @return string[][]|unknown[][]
     */
    function get_form_data($cms_page_panel_id, $return_data = 0){

    	$sql = "select * from form_data where cms_page_panel_id = ? ";
    	$query = $this->db->query($sql, array($cms_page_panel_id, ));
    	$data = $query->result_array();
    	 
    	// get possible fields
    	if ($return_data != 2){
    		$fields = ['time'];
    	}
    	if ($return_data != 1){
	    	$table = [];
    	}
    	
    	foreach($data as $row){
    		
    		$row_unpacked = !empty($row['data']) ? json_decode($row['data'], true) : array('email' => $row['email'], );
    		if (isset($row_unpacked['id'])){
    			unset($row_unpacked['id']);
    		}
    		
    		if ($return_data != 2){
    			$fields = array_unique(array_merge($fields, array_keys($row_unpacked)));
    		}
    		
    		if ($return_data != 1){
	    		$row_unpacked['time'] = date('Y-m-d H:i', !empty($row_unpacked['time']) ? $row_unpacked['time'] : 0);
	    		$row_unpacked['form_data_id'] = $row['form_data_id'] ?? '';
	    		$row_unpacked['item_id'] = $row['form_data_id'] ?? 0;
	    		$row_unpacked['id'] = $row['form_data_id'] ?? 0;
	    		$table[] = $row_unpacked;
    		}
    		
    	}
    	
    	$return = [];
    	
        if ($return_data == 0 || $return_data == 1){
    		$return['fields'] = $fields;
    	}
    	
    	if ($return_data == 0 || $return_data == 2){
    		$return['table'] = $table;
    	}
    	
    	return $return;
    	
    }
    
    function file_form_data($cms_page_panel_id, $filename){
    	
    	$data = $this->get_form_data($cms_page_panel_id);
    	$table = $data['table'];
    	$fields = $data['fields']; 

    	// create csv file
		header('Content-Type: application/CSV; charset=utf-16');
		header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
	
		// start file with bom and use utf16le, which both mac and windows should be able to autodetect
    	print("\xFF\xFE");

		// heading
		print(mb_convert_encoding('"'.implode('"'."\t".'"', $fields).'"'."\n", 'UTF-16LE','UTF-8'));

		// use tabs
		foreach($table as $row){
			$row_print = array();
			unset($row['form_data_id']);
			foreach($fields as $field){
				$row_print[] = !empty($row[$field]) ? $row[$field] : '';
			}
			print(mb_convert_encoding('"'.implode('"'."\t".'"', $row_print).'"'."\n", 'UTF-16LE','UTF-8'));
		}
	
		die();
    	
    }
    
    function create_cm_subscriber($data, $params){
    	
        $postdata = [
            'EmailAddress' => $data['email'],
            'Name' => !empty($data['name']) ? $data['name'] : '',
            'Resubscribe' => true,
            'RestartSubscriptionBasedAutoresponders' => true,
        	'ConsentToTrack' => 'Yes',
        ];

        $context = stream_context_create(array (
            'http' => array (
                'method'  => 'POST',
            	'ignore_errors' => true,
                'header'  =>
                    'Content-Type: application/json'."\r\n".
                    'Accept: application/json'."\r\n".
                    'Authorization: Basic ' . base64_encode($params['cm_api_key']) . "\r\n",
                    'content' => json_encode($postdata),
            ),
        ));
        
        if (empty($params['cm_api_url'])){
        	$params['cm_api_url'] = 'https://api.createsend.com/api/v3.2/';
        }

        $url = $params['cm_api_url'].'subscribers/'.$params['cm_list_id'].'.json';
        $result = file_get_contents($url, false, $context);

        return $result;
    
    }
    
    function create_mailchimp_subscriber($data, $params){
    	
    	$district = '';
    	
    	if(stristr($params['mailchimp_api_key'], '-')){
    		list($rest, $district) = explode('-', $params['mailchimp_api_key']);
    	}
    	
        $postdata = [
            'email_address' => $data['email'],
            'status' => 'subscribed',
        ];
        
        $ip = '';
        if(!empty($_SERVER['HTTP_CLIENT_IP'])) {  
        	$ip = $_SERVER['HTTP_CLIENT_IP'];  
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {  
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];  
     	} else {  
            $ip = $_SERVER['REMOTE_ADDR'];  
     	}
     	
     	if (!empty($ip)){
     		$postdata['ip_signup'] = $ip;
     	}
     	
        if (!empty($data['location'])){
        	
        	$url_extra = '?skip_merge_validation=true';
        	
        	$postdata['merge_fields']['ADDRESS']['addr1'] = $data['location'];
        	$postdata['merge_fields']['ADDRESS']['city'] = '';
        	$postdata['merge_fields']['ADDRESS']['state'] = '';
        	$postdata['merge_fields']['ADDRESS']['zip'] = '';
        	
            if (!empty($data['name'])){
        		$postdata['merge_fields']['FNAME'] = $data['name'];
        	}

        }

		$context = stream_context_create(array (
            'http' => array (
                'method'  => 'POST',
                'header'  =>
                    'Content-Type: application/json'."\r\n".
                    'Accept: application/json'."\r\n".
                    'Authorization: Basic ' . base64_encode('anystring:'.$params['mailchimp_api_key']) . "\r\n",
                    'content' => json_encode($postdata),
            ),
        ));

        $result = @file_get_contents(
        		'https://'.$district.'.api.mailchimp.com/3.0/lists/'.$params['mailchimp_list_id'].'/members/'.($url_extra ?? ''), 
        		false, 
        		$context
        );

        return $result;
    
    }
    
    function create_sendgrid_subscriber($data, $params){
    	
    	foreach($data as $key => $value){
    		$data[$key] = str_replace([',','"'], [';', "'"], $value);
    	}
    	
    	$sendgrid = new \SendGrid($params['sendgrid_api_key']);
    	
    	if (!empty($data['company'])){
    		
   			$response = $sendgrid->client->marketing()->field_definitions()->get();
//   			_print_r(json_decode($response->body(), true));
   			
   			$custom_fields_definitions = json_decode($response->body(), true)['custom_fields'];
   			
   			$custom_fields = '';
   			foreach($custom_fields_definitions as $def){
   				if (!empty($data[$def['name']])){

   					$custom_fields .= ',"'.$def['id'].'":"'.$data[$def['name']].'"';

   				}
   			}
    		
    		$custom_fields = trim($custom_fields, ',');
    	}

    	$request_body = json_decode('{
            	'.(!empty($params['sendgrid_list_id']) ? ('"list_ids": ["'.$params['sendgrid_list_id'].'"], ') : '').'
            	"contacts": [
                	{
                    	"email": "'.$data['email'].'",
                    	"first_name": "'.($data['first_name'] ?? ($data['name'] ?? '')).'",
    					"last_name": "'.($data['last_name'] ?? '').'"
   						'.(!empty($data['phone']) ? (',"phone_number":"'.$data['phone'].'"') : '').'
   						'.(!empty($custom_fields) ? (',"custom_fields": {'.$custom_fields.'}') : '').'
    				}
            	]
        	}');
    	
    	$response = $sendgrid->client->marketing()->contacts()->put($request_body);
    	
//    	_print_r($response);
    	
    }
    
    function confirm_code($code){
// _print_r($code);    	
    	$return = 0;
    	
    	if (empty($code)){
    		return $return;
    	}
    	
    	// check if exists
    	$sql = "select form_data_id, code from form_data where code = ? ";
    	$query = $this->db->query($sql, [$code]);
    	
    	$data = $query->result_array();
 
    	foreach($data as $row){
    	
    		if ($row['code']){
    			$return = 1;
    			$sql = "update form_data set code = '' where form_data_id = ? ";
    			$this->db->query($sql, [$row['form_data_id']]);
    			
    			$this->send_confirmation_success($row['form_data_id']);
    			
    		}

    	}

    	return $return;

    }
    
    function send_autoreply($data, $params){ // $autoreply_text, $autoreply_email, $autoreply_name, $autoreply_subject){
    	
    	$this->load->model('cms/cms_page_panel_model');

    	$autoreply_text = $params['autoreply_text'];
    	$autoreply_subject = $params['autoreply_subject'];
    	$autoreply_html = false;
    	
    	$plaintext = $autoreply_text;
    	if (!empty($params['autoreply_html']['target_id'])){
    		
    		$autoreply_html = true;
    		
    		$link = 'http'.($_SERVER['SERVER_PORT'] == 80 ? '' : 's').'://'.$_SERVER['SERVER_NAME']._l($params['autoreply_html']['url'], false);

			$autoreply_text = file_get_contents($link);
			
			$emailer = $this->cms_page_panel_model->get_cms_page_panel($params['autoreply_html']['target_id']);
			if (!empty($emailer['plain_text'])){
				$plaintext = $emailer['plain_text'];
			}
			
    	}
    	
    	foreach($data as $key => $val){
    		
    		$autoreply_text = str_replace('['.$key.']', $val, $autoreply_text);
    		
    		$plaintext = str_replace('['.$key.']', $val, $plaintext);
    	
    	}
    	
    	if (!empty($data['email'])){
    		
    		if(empty($GLOBALS['config']['smtp_server'])){
    			
		   		// send email
		    	@mail($data['email'], $autoreply_subject, $autoreply_text, 
						'From: '.$GLOBALS['config']['from_name'].'<'.$GLOBALS['config']['email'].'>'."\r\n".
		    			'Reply-to: '.$GLOBALS['config']['reply_name'].'<'.$GLOBALS['config']['reply_email'].'>'."\r\n");
		    	
    		} else {
    			    			
    			$autoreply_text = str_replace(["\n", "\r\n"], '<br>', $autoreply_text);
    			
    			$from = $GLOBALS['config']['email'];

    			$mail = new PHPMailer(true);
    			 
    			$mail->isSMTP();
    			$mail->Host = $GLOBALS['config']['smtp_server'];
    			$mail->SMTPAuth = true;
    			$mail->Username = $GLOBALS['config']['smtp_username'];
    			$mail->Password = $GLOBALS['config']['smtp_password'];
    			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    			$mail->Port = $GLOBALS['config']['smtp_port'];
    			
    			$mail->setFrom($GLOBALS['config']['email'], $GLOBALS['config']['from_name']);
    			$mail->addAddress($data['email']);
    			
   				$mail->addReplyTo($GLOBALS['config']['reply_email'], $GLOBALS['config']['reply_name']);
    			
				$mail->CharSet = 'utf-8';
				
				$mail->addCustomHeader('Auto-Submitted', 'auto-generated');
				
				$mail->Subject = $autoreply_subject;
    			$mail->Body = '<html><body>'.$autoreply_text.'</body></html>';
    			$mail->IsHTML(true); // $autoreply_html);
    			
//    			if (!empty($plaintext)){
    				$mail->AltBody = $plaintext;
//    			}
    			
    			// $mail->XMailer = 'Narrative CMS';
    			
    			$mail->send();
    			
    		}
    		
    	}

    }

    function send_confirmation_success($form_data_id){
    	
    	$this->load->model('cms/cms_page_panel_model');
    	
    	$sql = "select * from form_data where form_data_id = ? ";
    	$query = $this->db->query($sql, [$form_data_id]);
    	$data = $query->result_array();

    	foreach($data as $row){
    	
    		$row_unpacked = !empty($row['data']) ? json_decode($row['data'], true) : ['email' => $row['email']];
    	
    	}

    	// get form panel data
    	$panel = $this->cms_page_panel_model->get_cms_page_panel($row['cms_page_panel_id']);
    	
    	$email_html = false;
    	 
    	$plaintext = $panel['confirm_success_text'];
    	if (!empty($panel['confirm_html']['target_id'])){
    		
    		$email_html = true;
    		$email_text = file_get_contents('http'.($_SERVER['SERVER_PORT'] == 80 ? '' : 's').'://'.
							$_SERVER['SERVER_NAME']._l($panel['confirm_html']['url'], false));
    		
    		$emailer = $this->cms_page_panel_model->get_cms_page_panel($panel['confirm_html']['target_id']);
			if (!empty($emailer['plain_text'])){
				$plaintext = $emailer['plain_text'];
			}
    		
    	} else {
    		$email_text = $panel['confirm_success_text'];
    	}

    	foreach($row_unpacked as $key => $val){
	    	$email_text = str_replace('['.$key.']', $val, $email_text);
    		$plaintext = str_replace('['.$key.']', $val, $plaintext);
    	}
    	
    	if(empty($GLOBALS['config']['smtp_server'])){
    			
	   		// send email
	    	@mail($row_unpacked['email'], $panel['autoreply_subject'], $email_text, 
					'From: '.$GLOBALS['config']['from_name'].'<'.$GLOBALS['config']['email'].'>'."\r\n".
	    			'Reply-to: '.$GLOBALS['config']['reply_name'].'<'.$GLOBALS['config']['reply_email'].'>'."\r\n");
		    	
   		} else {

   			$mail = new PHPMailer(true);
    			 
    		$mail->isSMTP();
    		$mail->Host = $GLOBALS['config']['smtp_server'];
   			$mail->SMTPAuth = true;
   			$mail->Username = $GLOBALS['config']['smtp_username'];
   			$mail->Password = $GLOBALS['config']['smtp_password'];
   			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
   			$mail->Port = $GLOBALS['config']['smtp_port'];
    			
   			$mail->setFrom($GLOBALS['config']['email'], $GLOBALS['config']['from_name']);
   			$mail->addAddress($row_unpacked['email']);
    			
			$mail->addReplyTo($GLOBALS['config']['reply_email'], $GLOBALS['config']['reply_name']);
			
			$mail->CharSet = 'utf-8';
			
			$mail->addCustomHeader('Auto-Submitted', 'auto-generated');
				
    		$mail->Subject = $panel['confirm_subject'];
   			$mail->Body = $email_text;
   			$mail->IsHTML($email_html);
   			
   		    if (!empty($plaintext)){
    			$mail->AltBody = $plaintext;
    		}
   			
    		$mail->XMailer = 'Narrative CMS';
    	
    		$mail->send();
    			
   		}
   		
   		$this->send_info_confirmation($panel['emails'], $row_unpacked['email'], 
   				['reply_to' => ['email' => $row_unpacked['email'], ['name' => $row_unpacked['name'] ?? $row_unpacked['email']]]]);
    		
   	}
   	
   	function send_info_contact($emails, $data, $title, $params){
   	
   		$content = $title.(!empty($data['_page']) ? ("\n".'Page title: '.$data['_page']) : '')."\n\n";
   			
   		if (!empty($data['_page'])) {
   			unset($data['_page']);
   		}
   	
   		foreach($data as $key => $value){
   			$content .= $key . ': ' . $value . "\n";
   		}
   	
   		$content .= "\n\n".'This email is sent from the server address to reduce chance of this email being marked as spam.'."\n\n".
   				'Please, check recipient email when replying to the website visitor. If needed replace this with the email in the submitted data.';
   	
   		$content .= "\n\n".'You received this email because this email address is included as recipient for notifications at site '.$_SERVER['SERVER_NAME'].
   		"\n\n".'UNSUBSCRIBE: Please contact site webmaster, developer or your IT-support to unsubscribe. Do not mark this email as a spam, '.
   		'because you or other recipients may not receive any website notifications after that.';
   	
   		foreach($emails as $email){
   	
   			// send email
   			if(empty($GLOBALS['config']['smtp_server'])){
   			  
   				@mail($email['email'], $title, $content, 'From: '.$GLOBALS['config']['email']."\r\n".'Auto-Submitted: auto-generated'."\r\n");
   	
   			} else {
   		   
   				$mail = new PHPMailer(true);
   		   
   				if (!empty($GLOBALS['config']['smtp_debug'])){
	   				$GLOBALS['smtp_debug'] = [];
	   				$mail->SMTPDebug = 2;
	   				$mail->Debugoutput = function($line, $level) {
	   					$GLOBALS['smtp_debug'][] = $line;
	   				};
   				}
   	
   				$mail->isSMTP();
   				$mail->Host = $GLOBALS['config']['smtp_server'];
   				$mail->SMTPAuth = true;
   				$mail->Username = $GLOBALS['config']['smtp_username'];
   				$mail->Password = $GLOBALS['config']['smtp_password'];
   				$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
   				$mail->Port = $GLOBALS['config']['smtp_port'];
   		   
   				$mail->setFrom($GLOBALS['config']['email'], $GLOBALS['config']['from_name']);
   				$mail->addAddress($email['email']);
   		   
   				if(!empty($params['reply_to']['email'])){
   					$mail->addReplyTo($params['reply_to']['email'], $params['reply_to']['name']);
   				}
   		   
   				$mail->addCustomHeader('Auto-Submitted', 'auto-generated');
   		   
   				$mail->Subject = $title;
   				$mail->Body = $content;
   		   
   				try {
	   				$mail->send();
   				} catch (Exception $e) {
   					
   					
   					
   				}
   				
   				if (isset($GLOBALS['smtp_debug'])){
   					$debug_output = implode("\r\n", $GLOBALS['smtp_debug']);
   					file_put_contents($GLOBALS['config']['base_path'].'/cache/smtp_debug_'.$GLOBALS['config']['smtp_server'].'_'.time().'.txt', $debug_output);
   					unset($GLOBALS['smtp_debug']);
   				}
   		   
   			}
   	
   		}
   		 
   	}
   	
   	function send_info_confirmation($emails, $confirmed_email, $params){
   		
   		$title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').
   				'Visitor email address confirmed on "'.
   				trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ').'"';

   		$content = 'Visitor has confirmed the email address'."\r\n\r\n";
   		$content .= 'email: '.$confirmed_email."\r\n";
   		
   		$content .= "\r\n\r\n".'This email is sent from the server address to reduce chance of this email being marked as spam.'."\n\n".
   				'Please, check recipient email when replying to the website visitor. If needed replace this with the email in the submitted data.';
   		
   		$content .= "\n\n".'You received this email because this email address is included as recipient for notifications at site '.$_SERVER['SERVER_NAME'].
   		"\n\n".'UNSUBSCRIBE: Please contact site webmaster, developer or your IT-support to unsubscribe. Do not mark this email as a spam, '.
   		'because you or other recipients may not receive any website notifications after that.';
   		
   		foreach($emails as $email){
   		
   			// send email
   			if(empty($GLOBALS['config']['smtp_server'])){
   		
   				@mail($email['email'], $title, $content, 'From: '.$GLOBALS['config']['email']."\r\n".'Auto-Submitted: auto-generated'."\r\n");
   		
   			} else {
   		
   				$mail = new PHPMailer(true);

   				$mail->isSMTP();
   				$mail->Host = $GLOBALS['config']['smtp_server'];
   				$mail->SMTPAuth = true;
   				$mail->Username = $GLOBALS['config']['smtp_username'];
   				$mail->Password = $GLOBALS['config']['smtp_password'];
   				$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
   				$mail->Port = $GLOBALS['config']['smtp_port'];
   		
   				$mail->setFrom($GLOBALS['config']['email'], $GLOBALS['config']['from_name']);
   				$mail->addAddress($email['email']);
   		
   				if(!empty($params['reply_to']['email'])){
   					$mail->addReplyTo($params['reply_to']['email'], ($params['reply_to']['name'] ?? $params['reply_to']['email']));
   				}
   		
   				$mail->addCustomHeader('Auto-Submitted', 'auto-generated');
   		
   				$mail->Subject = $title;
   				$mail->Body = $content;
   		
   				$mail->send();
   		
   			}
   		
   		}
   		
   		 
   	}

}
