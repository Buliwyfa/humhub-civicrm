<?php 
namespace commonspace\modules\user\controllers;

use Yii;

class CiviController {
	private $civi_user_key;
	private $civi_site_key;
	private $civi_uri;
	private $fields = array();

	public function __construct($civi_uri, $civi_site_key, $civi_user_key, $fields) {
		$this->civi_user_key = $civi_user_key;
		$this->civi_site_key = $civi_site_key;
		$this->civi_uri = $civi_uri;
		$this->fields = $fields;
	}

	/**
		1) Get civi contact ID by email address (or by username?)
			* GET /sites/all/modules/civicrm/extern/rest.php?entity=Email&action=get&api_key=userkey&key=sitekey&json={"email":"daniel@common.scot"}

		2) Get address ID by civi contact ID

		3) Post new address information using address ID

		4) Post new contact information using contact ID 
	*/
	public function sync_profile($user) {
		$contact_id = $this->get_user_id($this->fields['CIVI_HH_GUID_FIELD'], $user->guid);
		if (!$contact_id) {
			$contact_id = $this->get_user_id($this->fields['CIVI_HH_USERNAME_FIELD'], $user->username);
		}
		if (!$contact_id) {
			$contact_id = $this->get_user_id_by_email($user->email);
		}
		if (!$contact_id) {
			Yii::$app->getSession()->setFlash('error', 'Failed to find user in CRM, please contact admin@common.scot');
			return;
		}

		if ($user->profile->street || $user->profile->zip || $user->profile->city || $user->profile->state) {
			$this->update_address($contact_id, $user->profile);			
		}

		$params = array(
				'id'=>$contact_id,
				'first_name'=>$user->profile->firstname,
				'last_name'=>$user->profile->lastname,
				$this->fields['CIVI_HH_USERNAME_FIELD']=>$user->username,
				$this->fields['CIVI_HH_GUID_FIELD']=>$user->guid,
				'birth_date'=>$user->profile->birthday
		);
		$gender_opts = array(
			'male'=>'Male',
			'female'=>'Female',
			'custom'=>'Transgender'
		);
		if (isset($gender_opts[$user->profile->gender])) {
			$params['gender_id'] = $gender_opts[$user->profile->gender];
		}
		if (!$this->post_request(array(
			'entity'=>'Contact',
			'action'=>'create',
			'params'=> $params
		))) {
			Yii::$app->getSession()->setFlash('error', 'Failed to update user details in CRM, please contact admin@common.scot');	
		}

		foreach(array(
			'phone_private'=>'Phone',
			'phone_work'=>'Pager',
			'fax'=>'Fax',
			'mobile'=>'Mobile'
		) as $hh_field_id=>$civi_type_field) {
			if ($user->profile->$hh_field_id) {
				$this->update_multirecord('Phone', 'phone', 'phone_type_id', $contact_id, $civi_type_field, $user->profile->$hh_field_id);				
			}
		}

		foreach(array(
			'im_skype'=>'Skype',
			'im_msn'=>'MSN',
			'im_xmpp'=>'Jabber'
		) as $hh_field_id=>$civi_type_field) {
			if ($user->profile->$hh_field_id) {
				$this->update_multirecord('Im', 'name', 'provider_id', $contact_id, $civi_type_field, $user->profile->$hh_field_id);	
			}
		}

		foreach(array(
			'url_facebook'=>'Facebook',
			'url_linkedin'=>'LinkedIn',
			'url_myspace'=>'MySpace',
			'url_googleplus'=>'Google_',
			'url_myspace'=>'MySpace',
			'url_twitter'=>'Twitter',
			'url'=>'Main'
		) as $hh_field_id=>$civi_type_field) {
			if ($user->profile->$hh_field_id) {
				$this->update_multirecord('Website', 'url', 'website_type_id', $contact_id, $civi_type_field, $user->profile->$hh_field_id);
			}
		}
	}

	public function update_multirecord($civi_entity, $record_id, $civi_field_id, $contact_id, $civi_field_data, $record_data) {
		$params = array(
			'contact_id'=>$contact_id,
			$civi_field_id=>$civi_field_data
		);
		$civi_record = $this->get_request(array(
			'action'=>'getsingle',
			'entity'=>$civi_entity,
			'params'=>$params
		));
		if ($civi_record) {
			$params['id'] = $civi_record->id;
		}
		$params[$record_id] = $record_data;

		if (!$this->post_request(array(
			'entity'=>$civi_entity,
			'action'=>'create',
			'params'=>$params
		))) {
			Yii::$app->getSession()->setFlash('error', "Failed to update CRM $civi_field_data $civi_entity record, please contact admin@common.scot");	
		}
	}

	public function update_address($contact_id, $user) {
		$address_record = $this->get_request(array(
			'action'=>'getsingle',
			'entity'=>'Address',
			'params'=>array(
				'contact_id'=>$contact_id
			)
		));
		$params = array(
			'contact_id'=>$contact_id,
			'location_type_id'=>'Home',
			'street_address'=>$user->street,
			'postal_code'=>$user->zip,
			'city'=>$user->city,
			//'country'=>$user->country,// TODO SCOTLAND!
			'state_province_id'=>$user->state 
		);
		if ($address_record) {
			$params['id'] = $address_record->id;
		}
		if (!$this->post_request(array(
			'entity'=>'Address',
			'action'=>'create',
			'params'=>$params
		))) {
			Yii::$app->getSession()->setFlash('error', 'Are you sure that address is valid?');
		};
	}

	public function get_user_id_by_email($email) {
		$email_record = $this->get_request(array(
			'action'=>'getsingle',
			'entity'=>'Email',
			'params'=>array(
				'email'=>urlencode($email)
			)
		));
		if (!$email_record) {
			return null;
		}
		return $email_record->contact_id;
	}

	public function get_user_id($field, $id) {
		$user_record = $this->get_request(array(
			'action'=>'getsingle',
			'entity'=>'Contact',
			'params'=>array(
				$field=>$id
			)
		));
		if (!$user_record) {
			return null;
		}
		return $user_record->id;
	}

	public function get_request($details = array()) {
		$params = isset($details['params']) ? json_encode($details['params']) : "{}";
		$uri = sprintf("%s?entity=%s&action=%s&api_key=%s&key=%s&json=%s", 
					$this->civi_uri, 
					$details['entity'],
					$details['action'],
					$this->civi_user_key, 
					$this->civi_site_key, 
					$params);
		$result = file_get_contents($uri);
		$response = json_decode($result);
		if (isset($response->is_error) && $response->is_error) {
			file_put_contents('civi-rest-log.txt', 
				"Date: " . date("Y-m-d H:i:s") . 
				"\nURI: $uri\nResult: $result\n\n", 
				FILE_APPEND);
			return null;
		}
		return $response;
	}

	public function post_request($details = array()) {
		$details['api_key'] = $this->civi_user_key;
		$details['key'] = $this->civi_site_key;
		$details['json']  = "{}";
		if (isset($details['params'])) {
			$details['json']  = json_encode($details['params']);
			unset($details['params']);
		}

		$context = stream_context_create(
			array('http' =>
				array(
					'method' => 'POST',
					'header' => 'Content-type: application/x-www-form-urlencoded',
					'content'=> http_build_query($details)
				)
			)
		);

		$result = file_get_contents($this->civi_uri, false, $context);

		$response = json_decode($result);
		if (isset($response->is_error) && $response->is_error) {
			file_put_contents('civi-rest-log.txt', 
				"Date: " . date("Y-m-d H:i:s") . 
				"\nURI: " . var_export($details) . "\nResult: $result\n\n", 
				FILE_APPEND);
			return null;
		}
		return $response;
	}
}