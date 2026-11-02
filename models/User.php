<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use \Ramblur\CIUtils\ConfigHelper;

class User extends CI_Model {

	private $authUser = NULL;
	private $authIsAdmin = NULL;
	private $authIsManager = NULL;
	private $authIsSupervisor = NULL;
	private $authIsModerator = NULL;
	private $authUserName = NULL;
	
	const USER_ADMIN = 'administrator';
	const USER_MANAGER = 'manager';
	const USER_SUPERVISOR = 'supervisor';
	const USER_MODERATOR = 'moderator';
	const USER_STANDARD = 'standard';

	function __construct() {
		// Call the Model constructor
		parent::__construct();
		
		$this->load->helper('cookie');
	}
	
	/*
	 |--------------------------------------------------------------------------
	 | AUTHORISATION
	 |--------------------------------------------------------------------------
	 */
	
	/**
	 * Checks that the user is logged in. Also stores user properties for
	 * further authorisation eg. administrative privileges.
	 * Returns the user id if they are logged in, otherwise false.
	 * @return boolean|int
	 */
	public function authCheck($endSession = true) {
		// Only want to check once per script
		if(is_null($this->authUser)) {
			
			$this->authUser = $this->session->userdata('user_id');
			// above returns NULL if the data isn't set, so change to false here, as many
			// authCheck calls rely on false if there is no logged in user, and to avoid having to check again
			if (is_null($this->authUser)) {
				$this->authUser = false;
			}

			//auto-login the user if they are remembered
			if (!$this->authUser && get_cookie($this->config->item('auth_identity_cookie_name')) && get_cookie($this->config->item('auth_remember_cookie_name'))) {
				$this->authUser = $this->login_remembered_user();
			}
			if ($this->authUser) {
				$query = $this->db->select("
					userName,
					isAdministrator,
					isManager,
					canBeSupervisor,
					canBeModerator
				")
				->join('Permissions AS p', 'p.userId = u.userId')
				->where('u.userId', $this->authUser)
				->get('User AS u');
				
				if ($query->num_rows() === 1) {
					$result = $query->row();
					$this->authUserName = $result->userName;
					$this->authIsAdmin = $result->isAdministrator === '1';
					$this->authIsManager = $result->isManager === '1';
					$this->authIsSupervisor = $result->canBeSupervisor === '1';
					$this->authIsModerator = $result->canBeModerator === '1';
				}
			}
			
			if ($endSession) {
				session_write_close();
			}
		}
		return $this->authUser;
	}
	
	/**
	 * Retrieves the username of the logged in user, using User::AuthCheck
	 */
	function getAuthUserName() {
		if(is_null($this->authUserName)) {
			$this->authCheck();
		}
		return $this->authUserName;
	}
	
	/**
	 * Checks if the logged in user is authorised for administration purposes. 
	 * Uses User::authCheck() to retrieve user details.
	 * Returns true if authorised as an admin, otherwise false.
	 * @return boolean
	 */
	function adminCheck() {
		if(is_null($this->authIsAdmin)) {
			$user = $this->authCheck();
			if(!$user) {
				$this->authIsAdmin = false;
			}
		}
		return $this->authIsAdmin;
	}
	
	/**
	 * Checks if the logged in user is authorised for manager purposes.
	 * Uses User::authCheck() to retrieve user details.
	 * Returns true if authorised as a manager, otherwise false.
	 * @return boolean
	 */
	function managerCheck() {
		if(is_null($this->authIsManager)) {
			$user = $this->authCheck();
			if(!$user) {
				$this->authIsManager = false;
			}
		}
		return $this->authIsManager;
	}

	/**
	 * Checks if the logged in user is authorised for moderator supervision purposes.
	 * Uses User::authCheck to retrieve user details.
	 * Returns true if authorised as a supervisor (regardless of allowed subjects), otherwise false.
	 * @return boolean
	 */
	function supervisorCheck() {
		if(is_null($this->authIsSupervisor)) {
			$user = $this->authCheck();
			if(!$user) {
				$this->authIsSupervisor = false;
			}
		}
		return $this->authIsSupervisor;
	}
	
	/**
	 * Checks if the logged in user is authorised for moderator purposes. 
	 * Uses User::authCheck to retrieve user details.
	 * Returns true if authorised as a moderator (regardless of allowed subjects), otherwise false.
	 * @return boolean
	 */
	function moderatorCheck() {
		if(is_null($this->authIsModerator)) {
		$user = $this->authCheck();
			if(!$user) {
				$this->authIsModerator = false;
			}
		}
		return $this->authIsModerator;
	}

	/**
	 * login
	 * 
	 * @param string $identity
	 * @param string $password
	 * @param mixed $error - Passed by reference so the caller can inspect/pass the error
	 * @param boolean $remember
	 * @return boolean
	 */
	function auth($identity, $password, &$error, $remember = FALSE) {
		if (empty($identity) || empty($password)) {
			$error = 'Login unsuccessful';
			return false;
		}
		$query = $this->db->select(
				'u.userId,
				u.username,
				u.email,
				u.password,
				u.joinDate,
				p.verified,
				p.suspended,
				p.suspendedDate,
				p.deleted,
				u.lastLogon'
			)
			->where('u.username', $identity)
			//->or_where('email', $identity)  we want this eventually, but have to modify register so that a username can't be someones else email and vice versa
			->limit(1)
			->order_by('u.userId', 'desc')
			->join('Permissions AS p', 'p.userId = u.userId')
			->get('User AS u');
		
		if ($this->is_max_login_attempts_exceeded($identity)) {
			//$this->set_error('login_timeout');
			$error = 'There have been too many unsuccessful login attempts. Please wait five minutes before trying again.';
			return FALSE;
		}
		if ($query->num_rows() === 1) {
			$user = $query->row();
			$passwordMatch = password_verify($password, $user->password);
			if ($passwordMatch === TRUE) {
				if (intval($user->verified) == 0) {
					// allow for 24 hours
					if (strtotime($user->joinDate) > strtotime($this->config->item('auth_unverified_lockout_days') . ' days ago')) {
						$error = 'This account has not been verified. Check your email for the verification link, or request another.';
						return FALSE;
					}
				}
				
				if (intval($user->deleted) == 1) {
					$error = 'Login unsuccessful';
					return FALSE;
				}
				
				if (!$this->suspensionCheck($user, $error)) {
					return FALSE;
				}
				
				$this->set_session($user);
				$this->update_last_login($user->userId);
				$this->clear_login_attempts($identity);
				if ($remember && $this->config->item('auth_remember_users')) {
					$this->remember_user($user->userId);
				}
				//$this->set_message('login_successful');
				return TRUE;
			}
		}
		$this->increase_login_attempts($identity);
		$error = 'Login unsuccessful';
		
		session_write_close();
		return FALSE;
	}
	
	/**
	 * From ion_auth
	 * 
	 * set_session
	 *
	 * @return bool
	 * @author jrmadsen67
	 **/
	public function set_session($user) {
		// regenerate the session id to prevent session fixation
		// No need to destroy the old one as it shouldn't have sensitive information in it
		$this->session->sess_regenerate();
		
		$session_data = array(
			'identity'             => $user->username,
			'username'             => $user->username,
			'email'                => $user->email,
			'user_id'              => $user->userId, //everyone likes to overwrite id so we'll use user_id
			'old_last_login'       => $user->lastLogon,
			'last_check'           => time(),
			);
		$this->session->set_userdata($session_data);
		return TRUE;
	}
	
	/**
	 * Mostly from ion_auth, with loginCount and loginCountThisMonth additions
	 * 
	 * update_last_login
	 *
	 * @return bool
	 * @author Ben Edmunds
	 **/
	public function update_last_login($id) {
		$this->load->helper('date');
		$this->db->set('loginCount', 'loginCount + 1', FALSE);
		$this->db->set('loginCountThisMonth', 'loginCountThisMonth + 1', FALSE);
		$this->db->set('lastLogon', time());
		$this->db->where('userId', $id);
		$this->db->update('User');
		return $this->db->affected_rows() == 1;
	}
	
	/**
	 * From ion_auth
	 * 
	 * increase_login_attempts
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * Note: the current IP address will be used if track_login_ip_address config value is TRUE
	 *
	 * @param string $identity: user's identity
	 **/
	public function increase_login_attempts($identity) {
		if ($this->config->item('auth_track_login_attempts')) {
			$data = array('ip_address' => '', 'login' => $identity, 'time' => time());
			if ($this->config->item('auth_track_login_ip_address')) {
				$data['ip_address'] = $this->input->ip_address();
			}
			return $this->db->insert('loginAttempts', $data);
		}
		return FALSE;
	}
	
	
	/**
	 * From ion_auth
	 * 
	 * clear_login_attempts
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * @param string $identity: user's identity
	 * @param int $old_attempts_expire_period: in seconds, any attempts older than this value will be removed.
	 *                                         It is used for regularly purging the attempts table.
	 *                                         (for security reason, minimum value is lockout_time config value)
	 * @param string $ip_address: IP address
	 *                            Only used if track_login_ip_address set to TRUE.
	 *                            If NULL (default value), current IP address is used.
	 *                            Use get_last_attempt_ip($identity) to retrieve user's last IP
	 **/
	public function clear_login_attempts($identity, $old_attempts_expire_period = 86400, $ip_address = NULL) {
		if ($this->config->item('auth_track_login_attempts')) {
			// Make sure $old_attempts_expire_period is at least equals to lockout_time
			$old_attempts_expire_period = max($old_attempts_expire_period, $this->config->item('lockout_time', 'auth'));
			$this->db->where('login', $identity);
			if ($this->config->item('auth_track_login_ip_address')) {
				if (!isset($ip_address)) {
					$ip_address = $this->input->ip_address();
				}
				$this->db->where('ip_address', $ip_address);
			}
			// Purge obsolete login attempts
			$this->db->or_where('time <', time() - $old_attempts_expire_period, FALSE);
			return $this->db->delete('loginAttempts');
		}
		return FALSE;
	}
	
	/**
	 * From ion_auth
	 * 
	 * is_max_login_attempts_exceeded
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * @param string $identity: user's identity
	 * @param string $ip_address: IP address
	 *                            Only used if track_login_ip_address set to TRUE.
	 *                            If NULL (default value), current IP address is used.
	 *                            Use get_last_attempt_ip($identity) to retrieve user's last IP
	 * @return boolean
	 **/
	public function is_max_login_attempts_exceeded($identity, $ip_address = NULL) {
		if ($this->config->item('auth_track_login_attempts')) {
			$max_attempts = $this->config->item('auth_max_login_attempts');
			if ($max_attempts > 0) {
				$attempts = $this->get_attempts_num($identity, $ip_address);
				return $attempts >= $max_attempts;
			}
		}
		return FALSE;
	}
	
	/**
	 * From ion_auth
	 * 
	 * Get number of attempts to login occured from given IP-address or identity
	 * Based on code from Tank Auth, by Ilya Konyukhov (https://github.com/ilkon/Tank-Auth)
	 *
	 * @param string $identity: user's identity
	 * @param string $ip_address: IP address
	 *                            Only used if track_login_ip_address set to TRUE.
	 *                            If NULL (default value), current IP address is used.
	 * @return int
	 */
	public function get_attempts_num($identity, $ip_address = NULL) {
		if ($this->config->item('auth_track_login_attempts')) {
			$this->db->select('1', FALSE);
			$this->db->where('login', $identity);
			if ($this->config->item('auth_track_login_ip_address')) {
				if (!isset($ip_address)) {
					$ip_address = $this->input->ip_address();
				}
				$this->db->where('ip_address', $ip_address);
			}
			$this->db->where('time >', time() - $this->config->item('auth_lockout_time'), FALSE);
			$qres = $this->db->get('loginAttempts');
			return $qres->num_rows();
		}
		return 0;
	}
	
	/**
	 * From ion_auth
	 *  
	 * remember_user
	 *
	 * @param int|string $id
	 *
	 * @return bool
	 * @author Ben Edmunds
	 */
	public function remember_user($id) {
		if (!$id) {
			return FALSE;
		}
		
		$query = $this->db->select('u.userId, u.username, u.email, u.lastLogon')
			->join('Permissions AS p', 'p.userId = u.userId')
			->where([
				'u.userId' => $id,
				'p.verified' => 1,
				'p.deleted' => 0,
				'p.suspended' => 0
			])
			->limit(1)
			->order_by('u.userId', 'desc')
			->get('User AS u');
		
		// if the user was not found, don't remember them
		if ($query->num_rows() != 1) {
			return FALSE;
		}
		$user = $query->row();
		$salt = $this->salt();
		$this->db->update('User', array('rememberCode' => $salt), array('userId' => $id));
		if ($this->db->affected_rows() > -1) {
			// if the user_expire is set to zero we'll set the expiration two years from now.
			if($this->config->item('auth_user_expire') === 0) {
				$expire = (60*60*24*365*2);

			// otherwise use what is set
			} else {
				$expire = $this->config->item('auth_user_expire');
			}
			set_cookie(array(
				'name'   => $this->config->item('auth_identity_cookie_name'),
				'value'  => $user->userId,
				'expire' => $expire,
				'httponly' => true
			));
			set_cookie(array(
				'name'   => $this->config->item('auth_remember_cookie_name'),
				'value'  => $salt,
				'expire' => $expire,
				'httponly' => true
			));
			return TRUE;
		}
		return FALSE;
	}
	/**
	 * From ion_auth
	 * 
	 * login_remembered_user
	 *
	 * @return bool
	 * @author Ben Edmunds
	 */
	public function login_remembered_user() {
		// check for valid data
		if (!get_cookie($this->config->item('auth_identity_cookie_name'))
			|| !get_cookie($this->config->item('auth_remember_cookie_name')))
		{
			return FALSE;
		}
		// get the user
		$query = $this->db->select('u.userId, u.username, u.email, u.lastLogon')
			->join('Permissions AS p', 'p.userId = u.userId')
			->where('u.userId', urldecode(get_cookie($this->config->item('auth_identity_cookie_name'))))
			->where('rememberCode', get_cookie($this->config->item('auth_remember_cookie_name')))
			->where([
				'p.verified' => 1,
				'p.deleted' => 0,
				'p.suspended' => 0
			])
			->limit(1)
			->order_by('u.userId', 'desc')
			->get('User AS u');
		// if the user was found, sign them in
		if ($query->num_rows() == 1) {
			$user = $query->row();
			$this->update_last_login($user->userId);
			$this->set_session($user);
			// extend the users cookies if the option is enabled
			if ($this->config->item('auth_user_extend_on_login')) {
				$this->remember_user($user->userId);
			}
			return $user->userId;
		}
		return FALSE;
	}
	
	/**
	 * From ion_auth
	 * 
	 * Generates a random salt value.
	 *
	 * Salt generation code taken from https://github.com/ircmaxell/password_compat/blob/master/lib/password.php
	 *
	 * @return bool|string
	 * @author Anthony Ferrera
	 */
	public function salt() {
		$raw_salt_len = 16;
		$buffer = '';
		$buffer_valid = FALSE;
		if (function_exists('random_bytes')) {
			$buffer = random_bytes($raw_salt_len);
			if ($buffer) {
				$buffer_valid = TRUE;
			}
		}
		if (!$buffer_valid && function_exists('mcrypt_create_iv') && !defined('PHALANGER')) {
			$buffer = mcrypt_create_iv($raw_salt_len, MCRYPT_DEV_URANDOM);
			if ($buffer) {
				$buffer_valid = TRUE;
			}
		}
		if (!$buffer_valid && function_exists('openssl_random_pseudo_bytes')) {
			$buffer = openssl_random_pseudo_bytes($raw_salt_len);
			if ($buffer) {
				$buffer_valid = TRUE;
			}
		}
		if (!$buffer_valid && @is_readable('/dev/urandom')) {
			$f = fopen('/dev/urandom', 'r');
			$read = strlen($buffer);
			while ($read < $raw_salt_len) {
				$buffer .= fread($f, $raw_salt_len - $read);
				$read = strlen($buffer);
			}
			fclose($f);
			if ($read >= $raw_salt_len) {
				$buffer_valid = TRUE;
			}
		}
		if (!$buffer_valid || strlen($buffer) < $raw_salt_len) {
			$bl = strlen($buffer);
			for ($i = 0; $i < $raw_salt_len; $i++) {
				if ($i < $bl) {
					$buffer[$i] = $buffer[$i] ^ chr(mt_rand(0, 255));
				} else {
					$buffer .= chr(mt_rand(0, 255));
				}
			}
		}
		$salt = $buffer;
		// encode string with the Base64 variant used by crypt
		$base64_digits = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
		$bcrypt64_digits = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$base64_string = base64_encode($salt);
		$salt = strtr(rtrim($base64_string, '='), $base64_digits, $bcrypt64_digits);
		$salt = substr($salt, 0, 22);
		return $salt;
	}
	
	private function suspensionCheck($user, &$error) {
		// If the user is suspended, and they are logging in within the reactivation period (6 months),
		// allow the login, and unsuspend the account. If they are outside the reactivation period, don't allow login,
		// and permanently delete their account
		if(intval($user->suspended) === 1) {
			//get the user suspended time in months
			$date1 = new DateTime("now");
			$date2 = new DateTime($user->suspendedDate);
			$interval = $date1->diff($date2);
			$userSuspendedPeriod = ($interval->y * 12)+ $interval->m;
		
			if ($userSuspendedPeriod >= $this->config->item('auth_suspended_max_months')) {
				//delete(deactivate) user
				$query = "UPDATE Permissions SET deleted=1, suspended = 0, suspendedDate='' WHERE userId = ?";
				$params = array($user->userId);
				$query = $this->db->query($query,$params);
				$error = 'Login unsuccessful';
				return FALSE;
			} else {
				// unsuspend
				$query = "UPDATE Permissions SET suspended = 0, suspendedDate = NULL WHERE userId = ?";
				$params = array($user->userId);
				$query = $this->db->query($query,$params);
			}
		}
		
		return TRUE;
	}

	function profileUpdateRequired ($userId) {

		// check that certain columns contain data
		// false = data complete
		// true = data INCOMPLETE
		$this->db->select('gender, DOB, stateId, suburb, postcode');
		$query = $this->db->get_where('User', array('userId' => $userId));

		if ($query->num_rows() > 0) {

		   $row = $query->row(); 

		   if(
		   	!is_null($row->gender)&&
		    //dob now mandatory during signup
		   	//$row->DOB!=='1000-01-01'&&
		   	//$row->DOB!=='0000-00-00'&&
		   	//$row->DOB!==''&&
		   	//!is_null($row->DOB)&&
		   	!is_null($row->stateId)&&
		   	!is_null($row->suburb)&&
		   	!is_null($row->postcode)
		   ) {
		   	return false;
		   } else {
		   	return true;
		   }

		}
	}
	
	// logs off a user
	function unauth() {

		// No need to check if they are currently authenticated,
		// as there could be cases where the session has timed out but the user has requested to be logged out
		$this->session->unset_userdata(array('identity', 'username', 'email', 'id', 'user_id') );

		// delete the remember me cookies if they exist
		if (get_cookie($this->config->item('auth_identity_cookie_name'))) {
			delete_cookie($this->config->item('auth_identity_cookie_name'));
		}
		if (get_cookie($this->config->item('auth_remember_cookie_name'))) {
			delete_cookie($this->config->item('auth_remember_cookie_name'));
		}

		// If user logged in through social auth, need to unauth
		$this->load->library('HybridAuthLib');
		$hybridauth_session_data = $this->hybridauthlib->getSessionData();
		if($hybridauth_session_data !== null) {
			$this->socialUnauth();
		}

		// regenerate the session id to prevent session fixation
		// destroy the old one as it may have user data in it
		$this->session->sess_regenerate(TRUE);

		// Destroy the session
		$this->session->sess_destroy();
		
		return true;
	}
	
	/*
	 logout from hybridauth providers if any
	 */
	public function socialUnauth(){
		$service = $this->hybridauthlib->logoutAllProviders();
		return true;
	}
	
	/*
	 |--------------------------------------------------------------------------
	 | END AUTHORISATION
	 |--------------------------------------------------------------------------
	 */

        /**
        * Verifies a user
        * @param type $userId
        * @return boolean
        */
        public function verifyUserById($userId) {
            $verificationCode = $this->getCurrentVerificationCode($userId);
            if (!$verificationCode) {                
                return false;
            }

            return $this->verify(array("verificationCode" => $verificationCode));
        }

        
	function verifyUser($username, $password) {

		$query = "SELECT u.userId, u.password, p.verified FROM User as u, Permissions as p WHERE u.userId = p.userId AND u.userName = ?;";            
		$params = array($username);
		$query = $this->db->query($query, $params);

		if ($query->num_rows() > 0) {

			$result = $query->row();

			if($result->verified==1) {

				return $result->verified;

			} else {

				$matched = password_verify($password, $result->password);

				if($matched) {
					return $result->userId;
				}
				return false;
			}
		}
		return false;
	}
	
	/**
	 * Handles messages and bonuses applied to new users.
	 * 
	 * @param string|int $userId
	 */
	function welcomeUser($userId) {
		
		$this->load->model('message');
		$this->load->model('group');
		$this->load->model('ad');
		
		$this->ad->createJoinBonuses($userId);
		
		// Send welcome messages in reverse order, as they are presented as newest first
		// Sleep in between each is to force a different timestamp on the db so they are ordered correctly
		$this->message->systemMessage(1, $userId, 1, message::MSG_WELCOME_INVITE);
		sleep(1);
		$this->message->systemMessage(1, $userId, 1, message::MSG_BETAPHASE);
		sleep(1);
		$this->message->systemMessage(1, $userId, 1, message::MSG_WELCOME);
	}

	
	
	function updateEmailAddress ($newEmail, $userId) {
		$this->load->model("email");
		// gen new verification code
		$verificationCode = $this->globals->uuid();	  

		$query = "UPDATE User SET email = ?, verificationCode = ? WHERE userId = ?;";            
		$params = array($newEmail, $verificationCode, $userId);
		if($query = $this->db->query($query, $params)){
			return $this->email->sendVerificationEmail($newEmail, $verificationCode);			
		}
		return false;
	}
	
	/**
	 * change password user(settings)
	 * Uses User::authCheck to retrieve user details.
	 * @return boolean|integer
	 */
	function changePassword($data) {
		$this->load->helper(array('ramblurfiltervar'));
		if (!isset($data["password"]) || !isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
			return false;
		}
		$userId = $data["userId"];
		$password = $data["password"];
		$newPassword=password_hash($data["newPassword"],PASSWORD_DEFAULT);
	
		$query = "SELECT password FROM User WHERE userId = ? ;";
		$query = $this->db->query($query,array($userId));
		$result = $query->row();
	
		// if there's a password match
		if ( password_verify($password, $result->password))
		{
			//update user password
			$query1 = "UPDATE User SET password = ? WHERE userId = ? ;";
			$query1 = $this->db->query($query1,array($newPassword,$userId));
			return true;
		}
		return false; //if wrong password
	}
	
	/**
	 * suspend or delete user(settings)
	 * Uses User::authCheck to retrieve user details.
	 * @return boolean|integer
	 */
	function suspend($data) {
		$this->load->helper(array('ramblurfiltervar'));
		if (!isset($data["password"]) || !isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
			return false;
		}
		$userId =$data["userId"];
		$password = $data["password"];
		$query = "SELECT password FROM User WHERE userId = ? ;";
		$query = $this->db->query($query,array($userId));
		$result = $query->row();
	
		// if there's a password match
		if (password_verify($password, $result->password)) {
			if($data["action"] === "suspend") {
				//suspend user
				$query1 = "UPDATE Permissions SET suspended = 1, suspendedDate=NOW() WHERE userId = ? ;";
			} else if ($data["action"] === "delete") {
				//delete user
				$query1 = "UPDATE Permissions SET deleted = 1 WHERE userId = ? ;";
			}
			$updateResult = $this->db->query($query1, array($userId));
			return $this->db->affected_rows() > 0;
		}
		return false;
	}

	/**
	 * Retrieves the "center" coords for the user's country, or the "center" of earth on failure
	 * @param string $countryId
	 * @return string
	 */
	function getUserCenter($countryId) {

		$this->db->select('centerCoords')->from('Country')->where('countryId', $countryId);
		return ($result = $this->db->get()->row()) ? $result->centerCoords : '0.0,0.0';

	}

	function getUserName($userId) {
		$this->db->select('userName')
			->from('User')
			->where('userId', $userId);
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return $result->first_row('array')['userName'];
		}
		return '';
	}
        function getUserId($userName){
            $this->db->select('userId')
                    ->from('User')
                    ->where('userName', $userName);
            $result = $this->db->get();
            if($result->num_rows() > 0) {
                    return $result->first_row('array')['userId'];
            }
            return false;
        }
	
	function getDisplayName($userId) {
		$fromUserDetails = $this->info($userId, true);
		if (!$fromUserDetails) {
			return '';
		}
		$fromName = '';
		if($fromUserDetails['firstName'] !== '' && $fromUserDetails['lastName'] !== '' && $fromUserDetails['firstNameSearch'] && $fromUserDetails['lastNameSearch']) {
			$fromName = $fromUserDetails['firstName'] . ' ' . $fromUserDetails['lastName'];
		} else {
			$fromName = $fromUserDetails['userName'];
		}
		return $fromName;
	}
	
	/**
	 * Retrieves the date of birth for the specified user as a DateTime object.
	 * 
	 * @param int|string $userId
	 * @return DateTime|NULL
	 */
	function getDOB($userId) {
		$this->db->select('DOB')
			->from('User')
			->where('userId', $userId);
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return new DateTime($result->first_row('array')['DOB']);
		}
		return null;
	}
	
	function isUnder18($userId) {
		$DOB = $this->getDOB($userId);
		if (!$DOB) {
			return false;
		}
		return $DOB > new DateTime('18 years ago');
		
	}

	/**
	 * Retrieves the highest level the given user is authorised as. Currently can be either standard, administrator, manager, supervisor or moderator.
	 * @param integer $userId
	 * @return string
	 */
	function getUserLevel($userId) {
		$this->db->select('isAdministrator, isManager, canBeSupervisor, canBeModerator')
			->from('Permissions')
			->where('userId', $userId);
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			$row = $result->result_array()[0];
			if($row['isAdministrator'] === '1') {
				return self::USER_ADMIN;
			} else if($row['isManager'] === '1') {
				return self::USER_MANAGER;
			} else if ($row['canBeSupervisor'] === '1') {
				return self::USER_SUPERVISOR;
			} else if ($row['canBeModerator'] === '1') {
				return self::USER_MODERATOR;
			} else {
				return self::USER_STANDARD;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Returns whether or not the user is an administrator.
	 * @param int $userId
	 * @return boolean
	 */
	function isAdmin($userId) {
		return $this->getUserLevel($userId) === self::USER_ADMIN;
	}
	
	/**
	 * Returns whether or not the user is an administrator.
	 * @param int $userId
	 * @return boolean
	 */
	function isManager($userId) {
		return $this->getUserLevel($userId) === self::USER_MANAGER;
	}
	
	/**
	 *  Returns whether or not the user is strictly a supervisor. ie. returns false if the user is an admin.
	 * @param unknown $userId
	 * @return boolean
	 */
	function isSupervisor($userId) {
		return $this->getUserLevel($userId) === self::USER_SUPERVISOR;
	}
	
	/**
	 *  Returns whether or not the user is strictly a moderator. ie. returns false if the user is an admin.
	 * @param unknown $userId
	 * @return boolean
	 */
	function isModerator($userId) {
		return $this->getUserLevel($userId) === self::USER_MODERATOR;
	}


	// changes a users level
	// @todo - check what this needs to handle
	function levelChange($userId, $newLevel) {
		// First check if user is allowed to to this at all
		if(!$this->moderatorCheck()) {
			return 'You are not allowed to change permissions.';
		}
		
		$currentLevel = $this->getUserLevel($userId);
		// Already at that level
		if($currentLevel === $newLevel) {
			return 'The user already has ' . $newLevel . ' permissions.';
		}
		
		// Admin can set user from admin to anything and to admin from anything
		// Moderator has already been checked and can set remaining options (standard to mod and mod to standard)
		if($newLevel === self::USER_ADMIN || $currentLevel === self::USER_ADMIN) {
			if(!$this->adminCheck()) {
				return 'You cannot give that user ' . $newLevel . '.';
			}
		}
		$data = array(
			'canBeModerator' => $newLevel === self::USER_MODERATOR || $newLevel === self::USER_SUPERVISOR || $newLevel === self::USER_ADMIN,
			'canBeSupervisor' => $newLevel === self::USER_SUPERVISOR || $newLevel === self::USER_ADMIN,
			'isAdministrator' => $newLevel === self::USER_ADMIN
		);
		$this->db->update('Permissions', $data, array('userId' => $userId));
		return true;
	}
	
	/**
	 * Gets info about the user.
	 * Use $direct = true to retrieve direct results ie. without being inside property array, 
	 * otherwise just provide $sourceId to retrieve user info the old way.
	 * 
	 * @param string $sourceId
	 * @param boolean $direct
	 * @return array|boolean
	 */

	//NB do not call user->info with an unchecked, user provided value for the first parameter
	function info($sourceId = false, $direct = false) {

		// if no source specified, just use the logged in user
		if (!isset($sourceId) || !$sourceId) {

			// check if the user is logged in
			$sourceId = $this->authCheck();

		}
		 
		// if we're still good...
		if ($sourceId !== false) {
			// Avoid using nested $properties array
			if($direct) {
				$sql = "CALL getUserDetails(?)";
				$query = $this->db->query($sql, array($sourceId));
				
				if($query->num_rows() > 0) {
					$result = $query->result_array()[0];
					$hiddenProps = array('password', 'session');
					foreach($hiddenProps as $p) {
						// remove it from the results
						unset($result[$p]);
					}
					if($result['isAdministrator']) {
						$result['level'] = 'administrator';
					} else if($result['isManager']) {
						$result['level'] = 'manager';
					} else if($result['canBeSupervisor']) {
						$result['level'] = 'supervisor';
					} else if($result['canBeModerator']) {
						$result['level'] = 'moderator';
					} else {
						$result['level'] = 'standard';
					}
					if(!is_null($result['avatar'])) {
						$result['avatar'] = $result['avatar'].'-avatar';
					}
					$result['id'] = $sourceId;
					$result['userCenter'] = $this->getUserCenter($result['countryId']);
				} else {
					$result = false;
				}
				//mysqli_next_result($this->db->conn_id);
			} else {
				// fetch all the data
				// mn $result = $this->node->info($sourceId);
				$this->load->model('node');
				$result = $this->node->userInfo($sourceId);
				// this is how to work out their rough age
				// $query = "select *, DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), `dob`)), '%y') as 'age' from `accounts` where `id` = ? limit 1";

				// go through all the properties
				$i = 0;
				while($i < count($result["properties"])) {
					// if its something that shouldn't be widely known
					if(in_array($result["properties"][$i]["name"], array("password", "session")) !== false) {
						// remove it from the results
						array_splice($result["properties"], $i, 1);
					} else {
						// if we found the timezone
						if($result["properties"][$i]["name"] == "timezone") {
							// set a systemwide timezone so all dates/times match
							@date_default_timezone_set($result["properties"][$i]["value"]);
						}
					$i++;
					}
				}
			}
			return $result;
		} else {
			return false;
		}
	}



	// fetch some more comprehensive data about the current user
	function profile($data) {

		$profile = array();

		$userId = $this->authCheck();

		if(!$userId) {
			return false;
		}

		$params = array($data["user"], $data['user'], $data['user'], $data['user']);

		$query = "select COUNT(DISTINCT IF(userId = ?, socialId, null)) AS 'following', COUNT(DISTINCT IF(socialID = ?, userId, null)) AS 'followers' from UserToSocial where userId = ? or socialId = ?;"; 
 
		$query = $this->db->query( $query, $params );
 
		$result = $query->result_array(); 

		$profile["user"]["following"] = $result[0]["following"];
		$profile["user"]["followers"] = $result[0]["followers"];

		$query = "select max(dateTime) as lastEntry,joinDate from Nodes JOIN User ON Nodes.userId = User.userId where Nodes.userId = ?";

		$query = $this->db->query( $query, [$data['user']]);

		$result = $query->result_array();  
 
		$profile["user"]["lastEntry"] = $result[0]["lastEntry"];
		$profile["user"]["joinDate"] = $result[0]["joinDate"];

		$query = "CALL getUserDetails(?);";

		$query = $this->db->query($query, [$data['user']]);   

		$result = $query->result_array();

		$profile['user']['avatar'] = is_null($result[0]['avatar']) ? null : $result[0]['avatar'].'-avatar';

		if ( $result[0]["userNameSearch"] == TRUE ){
		$profile["user"]["userName"] = $result[0]["userName"];
		}

		if ( $result[0]["firstNameSearch"] == TRUE ){
		$profile["user"]["firstName"] = $result[0]["firstName"];
		}

		if ( $result[0]["lastNameSearch"] == TRUE ){
			$profile["user"]["lastName"] = $result[0]["lastName"];
		}

		if ( $result[0]["ageVisible"] == TRUE ){
			$profile["user"]["age"] = $result[0]["age"];
		}

		if ( $result[0]["genderVisible"] == TRUE ){
			$profile["user"]["gender"] = $result[0]["gender"];
		}

		if ( $result[0]["locationVisible"] == TRUE ){
			$profile["user"]["country"] = $result[0]["country"];
			$profile["user"]["state"] = $result[0]["state"];
			$profile["user"]["postcode"] = $result[0]["postcode"];
		}

		if ( $result[0]["emailSearch"] == TRUE ){
		$profile["user"]["email"] = $result[0]["email"];
		}

		if ( $result[0]["subjectsVisible"] == FALSE ){
			return $profile;
		}

		$query = "CALL getUserGroups(?);";

		$query = $this->db->query( $query, [$data['user']]);

		$result = $query->result_array();
		$profile["groups"] = array();
		$i=0;
		foreach ( $query->result_array() as $row ) {

			if ( ($row["private"] == FALSE || ( $row["private"] == TRUE && $userId == $data['user'])) 
				&& ($row["type"] !== group::TYPE_RAMBLUR && $row["subjectName"] !== 'my feed')
				&& ($row["type"] !== group::TYPE_RAMBLUR && $row["subjectName"] !== 'main feed') )
			{
				$profile["groups"][$i]["subjectName"] = $row["subjectName"];
				$profile["groups"][$i]["subjectId"] = $row["subjectId"];
				$i++;
			}
		}

		//check if the current group is being followed by the user
		$profile["showIcons"] = FALSE; 

		if(!empty($data['forumPostId'])) {
			$profile['forumPostId'] = $data['forumPostId'];
			$forumIds = $this->getUserForumIds($userId);
			foreach($forumIds as $forumId) {
				if($data['forumPostId'] == $forumId["forumId"]) {
					$profile["showIcons"] = TRUE;
				}
			}
		}
		return $profile;
	}
		
	// Retrieves all forum id's followed by a user
	function getUserForumIds($userId) {
		
		$this->db->select('subjectId AS forumId')
			->from('UserToSubject')
			->where([
				'userId' => $userId,
				'active'=> 1
			]);
							
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$forums = $query->result_array();
			return $forums;
		} else {
			return false;
		}
	}
	
	// Retrieves all forum id's and names followed by a user
	function getUserForumIdsAndNames($userId) {
		
		$authId = $this->authCheck();
		if (!$authId || $authId != $userId) {
			return false;
		}
		
		$this->db->select('UserToSubject.subjectId AS forumId')
			->select('Subject.subjectName')	
			->select('Subject.type')
			//->select('Subject.private')
			->from('UserToSubject')
			->join('Subject', 'UserToSubject.subjectId = Subject.subjectId')
			->where([
				'userId' => $userId,
				'active'=> 1
			]);
							
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$forums = $query->result_array();
			return $forums;
		} else {
			return false;
		}
	}

	function profileUpdate($data) {

		$userId = $this->authCheck();

		if (!$userId || $userId != $data['id']) {
			return false;
		}
		$this->load->helper(array('htmlpurifier'));
		
		$allowed = array('id','stateId','suburb','postcode','DOB','gender');
		$input = array();

		$id = $data['id'];

		array_shift($data);

		foreach($data as $key => $value) {
			if(in_array($key,$allowed)){
				$input[$key]=html_purify($value,'post');
			}
		}

		// ALSO WORK OUT AGE!
		$data['age'] = date_diff(date_create($data['DOB']), date_create('today'))->y;

		$this->db->where('userId', $id);
		$this->db->update('User', $input); 

		// update user profile with new data
		if($this->db->affected_rows() === 1){

			// on success also increment loginCount
			$this->db->where('userId', $id);
			$this->db->set('loginCount', 'loginCount + 1', FALSE);
			$this->db->update('User');

			return true;
		}
		return false;
	}

	function resetPassword ($email, $code, $password) {

		// hash pwd
		$hash = password_hash($password, PASSWORD_DEFAULT);

		$data = array(
			'password' => $hash
		);

		$this->db->where(array('email' => $email, 'verificationCode' => $code));
		$this->db->update('User', $data);

		if ($this->db->affected_rows() === 1) {

			// null the verification code
			$this->db->set('verificationCode', NULL);
			$this->db->where(array('email' => $email, 'verificationCode' => $code));
			$this->db->update('User');

			return true;

		}
		return false;

	}

	function sendUsernameReminder ($email) {
		$this->load->model("email");
		// get username for email
	    $this->db->select('userName')->from('User')->where('email', $email);
	    $res = $this->db->get()->row();

		if ($this->db->affected_rows() === 1) {
	        $resetUsername = $res->userName;
			return $this->email->sendUsernameReminder($email, $resetUsername);
		}
		return false;

	}

	function resendVerificationEmail ($email) {
		$this->load->model("email");
		// get username for email
	    $this->db->select('verificationCode')->from('User')->where('email', $email);
	    $res = $this->db->get()->row();

		if ($this->db->affected_rows() === 1) {
	        $verificationCode = $res->verificationCode;
			return $this->email->sendVerificationReminder($email, $verificationCode) ;
		}
		return false;

	}

	function sendPasswordReset ($email) {
		$this->load->model("email");
		// generate a code and insert it into the "verificationCode" column
		$resetCode = $this->globals->uuid();	  

		$this->db->set('verificationCode', $resetCode);
		$this->db->where('email', $email);
		$this->db->update('User');

		// if ok, send email
		if ($this->db->affected_rows() === 1) {

			// get username for email
            $this->db->select('userName')->from('User')->where('email', $email);
            $res = $this->db->get()->row();
            $resetUsername = $res->userName;
			return $this->email->sendPasswordReset($email, $resetUsername, $resetCode);			

		}
		return false;

	}

        
        /**
         * Gets the user's current verification code. 
         * Verification code is mutable and is cleared after various actions 
         * @param type $userId
         * @return boolean
         */
        public function getCurrentVerificationCode($userId){
            
            $this->db->select('verificationCode')->from('User')->where('userId', $userId);  
            $query = $this->db->get();
            if ($query->num_rows() === 1) {
                $results = $query->first_row('array');                
                return $results['verificationCode'];
            }
            return false;
        }
        
	function resetCodeExists ($code) {

		$this->db->select('*');
		$query = $this->db->get_where('User', array('verificationCode' => $code));

		return ($query->num_rows() === 1) ? true : false;

	}        

	function updateUserInfo($userId, $data) {
		
		$allowedFields = [
			'firstName',
			'lastName',
			'skype',
			'facetime',
			'postcode',
			'mobile',
			'gender',
			'dob',
			'timezone',
			'country',
			'state',
			'suburb',
			'helpBubblesShown',
			'joinDate'
		];
		$this->db->select('firstName, lastName, skype, facetime, postcode, mobile, gender, dob, timezone, countryId, stateId, suburb, helpBubblesShown, joinDate')
			->from('User')
			->where('userId', $userId);
		$userData = $this->db->get();
		if ($userData->num_rows() === 0) {
			return false;
		}
		
		$userData = $userData->first_row('array');
		$this->load->helper(array('htmlpurifier'));
		$updateData = [];
		foreach($allowedFields AS $f) {
			if (isset($data[$f])) {
				if ($f === 'country') {
					if (!($data[$f] === $userData['countryId'] || ($data[$f] === '' && is_null($userData['countryId'])))) {
						$updateData['countryId'] = $data[$f];
					}
				} else 	if ($f === 'state') {
					if (!($data[$f] === $userData['stateId'] || ($data[$f] === '' && is_null($userData['stateId'])))) {
						$updateData['stateId'] = $data[$f];
					}
				} else {
					if (!($data[$f] === $userData[$f] || ($data[$f] === '' && is_null($userData[$f])))) {
					  $updateData[$f] = html_purify($data[$f],'post');
					  //$updateData[$f] = $data[$f];
					}
				}
			}
		}
		
		// Check state is valid
		if (isset($updateData['state'])) {
			$this->db->select('stateId')
				->from('State')
				->where('stateId', $updateData['state']);
			$stateExists = $this->db->get()->num_rows() > 0;
			if (!$stateExists) {
				return false;
			}
		}
		// Check country is valid
		if (isset($updateData['country'])) {
			$this->db->select('countryId')
				->from('Country')
				->where('countryId', $updateData['country']);
			$countryExists = $this->db->get()->num_rows() > 0;
			if (!$countryExists) {
				return false;
			}
		}
		
		// Check gender is valid
		if (isset($updateData['gender'])) {
			$updateData['gender'] = mb_strtoupper($updateData['gender']);
			if (!($updateData['gender'] == 'M' || $updateData['gender'] == 'F')) {
				return false;
			}
		}
		
		// Check dob is valid
		if (isset($updateData['dob'])) {
			$dob = new DateTime($updateData['dob']);
			if (!$dob) {
				return false;
			}
			$updateData['dob'] = $dob->format('Y-m-d');
		}
		
		if (count($updateData) > 0) {
			$this->db->update('User', $updateData, ['userId' => $userId]);
		} else {
			return false;
		}
		return $this->db->affected_rows() > 0;
	}
	
	function getProgressbarPercentage($userdata)
	{
		$completePercentage =0;//used to display profile completeness in progressbar
		foreach ($userdata as $key =>$value ) {
			if($value['name'] == "userName" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "email" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "country" && $value['value'] !=""){
				$completePercentage += 10;
			}
	
			if($value["name"] == "gender" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "dob" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "areacode" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "mobile" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "skype" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "facetime" && $value['value'] !=""){
				$completePercentage += 10;
			}if($value["name"] == "timezone" && $value['value'] !=""){
				$completePercentage += 10;
			}
		}
		return $completePercentage;
	}
	
	/**
	 *  Upload a file and set it as the user's avatar.
	 *  
	 * @param int|string $userId
	 * @param string $fileName
	 * @param boolean $error - Allows an error message to be read by the calling function.
	 */ 
	function uploadAvatar($userId, $file, &$error) {
		// upload the avatar
		$result = $this->file->store(array('local' => $file, 'group' => 1)); // set subjectId so that it doesn't get deleted
		if (!$result) {
			$error = 'The avatar could not be uploaded.';
			return false;
			
		}
		$filename = $result[0];
		// temp fix for subjectId not getting set by addUserContent, even though it should
		$this->db->update('Content', ['subjectId' => 1], ['fileName' => $filename]);
		$this->db->update('User', ['avatar' => $filename], ['userId' => $userId]);
		
		if ($this->db->affected_rows() > 0) {
			return true;
		} else {
			$error = 'The avatar could not be updated.';
			return false;
		}
	}
	
	function updateDisplayInfo($data, $userId = false) {

		if ( $userId === false ) {
			$userId = $this->authCheck();
		}
		
		// Make sure theme is set to valid values
		$this->db->select('id, value')
			->from('ThemeColourOptions')
			->where('value', $data['theme']);
		$themeOption = $this->db->get();
		if ($themeOption->num_rows() > 0) {
			$theme = $themeOption->first_row('array');
		} else {
			$theme = $this->group->getDefaultThemeColourOption();
		}
		
		// Make sure font is set to valid values
		$this->db->select('id, value')
			->from('ThemeColourOptions')
			->where('value', $data['primaryFontColour']);
		$fontOption = $this->db->get();
		if ($fontOption->num_rows() > 0) {
			$primaryFontColour = $fontOption->first_row('array');
		} else {
			$primaryFontColour = $this->group->getDefaultThemeFontOption();
		}
		
		$this->db->select('id, value')
			->from('ThemeColourOptions')
			->where('value', $data['secondaryFontColour']);
		$fontOption = $this->db->get();
		if ($fontOption->num_rows() > 0) {
			$secondaryFontColour = $fontOption->first_row('array');
		} else {
			$secondaryFontColour =  $this->group->getDefaultThemeFontOption();
		}
		$this->load->helper('colourvalidate');
		$this->db->update('User',[
					  'primaryColour' => colourvalidate($data['primaryColour']),
					  'secondaryColour' => colourvalidate($data['secondaryColour']),
					  'primaryThemeFontOptionId' => $primaryFontColour['id'],
					  'secondaryThemeFontOptionId' => $secondaryFontColour['id'],
					  'themeColourOptionId' => $theme['id'],
					  'opacity' => $data['opacity']
					  ],
				  ['userId' => $userId]);

		return $this->db->affected_rows() > 0;
	}


	function updatePermissions($data) {

		$userId = $this->authCheck();
		if (!$userId) {
			return false;
		}
		$this->load->helper(array('ramblurfiltervar'));
		if(!isset($data["ageVisible"]) || rfilter_var($data["ageVisible"],FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))===null ||
		   !isset($data["genderVisible"]) || rfilter_var($data["genderVisible"],FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))===null ||
		   !isset($data["locationVisible"]) || rfilter_var($data["locationVisible"],FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))===null ||
		   !isset($data["lastNameSearch"]) || rfilter_var($data["lastNameSearch"],FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))===null ||
		   !isset($data["firstNameSearch"]) || rfilter_var($data["firstNameSearch"],FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))===null ||
		   !isset($data["emailSearch"]) || rfilter_var($data["emailSearch"],FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))===null ||
		   !isset($data["subjectsVisible"]) || rfilter_var($data["subjectsVisible"],FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))===null)
		  {
		    return false;
		  }

		$query = "CALL updatePermissions(?,?,?,?,?,?,?,?);";

		$params = array($userId,$data["ageVisible"],$data["genderVisible"],$data["locationVisible"],$data["lastNameSearch"],$data["firstNameSearch"],$data["emailSearch"],$data["subjectsVisible"]);

		$query = $this->db->query($query, $params);

		return true;
	}

	// last minute neccessity for trying to establish if a user who logs in only has 1 forum followed
	// which means they're a 3rd party user who has logged in via the ramblur homepage
	function groupsQuickCount() {

		if ($userId = $this->authCheck()) {

			$this->db->select("s.subjectId, s.subjectName, s.type")
				->from('UserToSubject as uts, Subject as s')
				->where('userId', $userId)
				->where('active', 1)
				->where('uts.subjectId = s.subjectId');

			$result = $this->db->get();

			if($result->num_rows() > 0) {
				return $result->result_array();
			}
			return false;

		}
		return false;

	}


	// return the groups the user is subscribed to 
	// DOES NOT FILTER FAVOURITES (groupbar) YET - RETURNS ALL SUBSCRIBED GROUPS
	function groups($getHiddenGroups = false) {
		$this->load->model('group');
		$this->load->model('subjectAttribute');
		
		// check if the user is logged in
		$userId = $this->authCheck();
		
		$isAdmin = $this->isAdmin($userId);
		
		$groups = [];

		if ($userId === false) {
			// If not logged in, return random ones?? Shouldn't be necessary now we can view forum landing pages
			//$groups = $this->group->random();
			return $groups;
		}

		// find out what groups the user has starred?
		$groups = array();
		
		$this->db->select("
				s.subjectName,
				s.description,
				s.subjectId,
				s.private,
				s.type,
				User.userName AS forumOwner,
				u.displayed,
				t.value AS 'theme',
				u.opacity,
				u.primaryColour,
				u.secondaryColour,
				fp.value AS 'primaryFontColour',
				fs.value AS 'secondaryFontColour'
			")
			->from('Subject AS s')
			->join('User', 'User.userId = s.createdBy', 'LEFT')	
			->join('UserToSubject AS u', 's.subjectId = u.subjectId')
			->join('ThemeColourOptions AS t', 't.id = u.themeColourOptionId')
			->join('ThemeFontOptions AS fp', 'fp.id = u.primaryThemeFontOptionId')
			->join('ThemeFontOptions AS fs', 'fs.id = u.secondaryThemeFontOptionId')
			->where('u.userId', $userId)
			->where('(u.active = 1 OR u.grouped = 1)')
			->where('(s.private = 0 OR u.privateAccess = 1 OR '.($isAdmin ? 'true' : 'false').')')
			->order_by('s.subjectName', 'ASC');
		
		if (!$getHiddenGroups) {
			$this->db->where('u.displayed', 1);
		}

		$groupResults = $this->db->get();
		
		if ($groupResults->num_rows() === 0) {
			return $groups;
		}

		foreach ($groupResults->result_array() as $row ) {

			$private = $row['private'] ? 'private' : 'public';
			$forumType = $this->group->getTypeString($row['type']);
			
			if($forumType === 'Ramblur') {
				$row["forumOwner"] = null;
			}
			
			$groups[] = array(
				"id" => $row["subjectId"],
				"name" => $row["subjectName"],
				"description" => $row["description"],
				"type" => $row["type"],
				"displayed" => (boolean)$row['displayed'],
				"privacy" => $private,
				"forumtype" => $forumType,
				"theme" => $row["theme"],
				"opacity" => $row["opacity"],
				"primaryColour" => $row["primaryColour"],
				"secondaryColour" => $row["secondaryColour"],
				"primaryFontColour" => $row["primaryFontColour"],
				"secondaryFontColour" => $row["secondaryFontColour"],
				"forumOwner" => $row["forumOwner"],
				"attributes" => $this->subjectAttribute->getSubjectAttributesArray($row['subjectId'])
			);
		}

		// Order is 'my feed', 'main feed', 'randoms', the rest in alphabetical order

		// Do 'randoms' first
		//$randoms = array_splice($groups, array_search('randoms', array_column($groups, 'name')), 1);
		//array_splice($groups, 0, 0, $randoms);

		// Move home and main feed to the beginning
		$home = array_splice($groups, array_search('my feed', array_column($groups, 'name')), 1);
		array_splice($groups, 0, 0, $home);
		$mainFeedIndex = array_search('main feed', array_column($groups, 'name'));
		if ($mainFeedIndex && (int)$groups[$mainFeedIndex]['type'] === group::TYPE_RAMBLUR) {
			$main = array_splice($groups, $mainFeedIndex, 1);
			array_splice($groups,0, 0, $main); // main feed always first for initial release
		}
		return $groups;
	}
	
	/**
	 * Adds a UserToSubject row. Doesn't allow for grouping with other subjects as the stored procedure
	 * addSubjectToUserList does, but does allow for inactive rows to be inserted, which the sproc doesn't.
	 * Necessary for warning and banning users that may not be following the subject.
	 * 
	 * We now return something so there's some way of knowing if this worked.
	 *
	 * @param int $userId
	 * @param int $subjectId
	 * @param boolean $active
	 */
	function addUserToSubject($userId, $subjectId, $active) {
		$this->db->select("nv, dv, primaryColour, secondaryColour, primaryThemeFontOptionId, secondaryThemeFontOptionId, themeColourOptionId, opacity")
			->from('Subject')
			->where('subjectId', $subjectId);
		$subjectRow = $this->db->get()->first_row('array');
		$rootNv = floor($subjectRow['nv']/$subjectRow['dv']);
		$this->db->select('subjectId')
			->from('Subject')
			->where(array(
				'nv' => $rootNv,
				'dv' => 1
		));
		$rootSubjectId = $this->db->get()->first_row('array')['subjectId'];
	
		$this->db->insert('UserToSubject',
			array(
				'active' => $active,
				'userId' => $userId,
				'subjectId' => $subjectId,
				'rootSubjectId' => $rootSubjectId,
				'banned' => '1000-01-01 00:00:00',
				'moderator' => 0,
				'privateAccess' => 0,
				'primaryColour' => $subjectRow['primaryColour'],
				'secondaryColour' => $subjectRow['secondaryColour'],
				'primaryThemeFontOptionId' => $subjectRow['primaryThemeFontOptionId'],
				'secondaryThemeFontOptionId' => $subjectRow['secondaryThemeFontOptionId'],
				'themeColourOptionId' => $subjectRow['themeColourOptionId'],
				'opacity' => $subjectRow['opacity'],
		));
		
		$this->addUserToSubjectHistory($userId, $subjectId, true);
		
		return $this->db->affected_rows() ;

	}
	
	/**
	 * Adds a record of a user joining or leaving a forum.
	 * @param int|string $userId
	 * @param int|string $subjectId
	 * @param boolean $joining
	 */
	function addUserToSubjectHistory($userId, $subjectId, $joining) {
		// Insert new row, or update the existing one
		if ($joining) {
			$this->db->query("INSERT INTO UserToSubjectHistory (userId, subjectId)
				VALUES ($userId, $subjectId)
				ON DUPLICATE KEY UPDATE joinDate = UTC_TIMESTAMP(), leaveDate = NULL");
			
		// Update existing one
		} else {
			$this->db->set('leaveDate', 'UTC_TIMESTAMP()', FALSE)
				->where(['userId' => $userId, 'subjectId' => $subjectId])
				->update('UserToSubjectHistory');
		}
	}
	
	/**
	 * Gets all subjects a user is banned from
	 * @param int $userId
	 */
	function getBannedSubjects($userId) {
		$this->db->select("s.subjectId AS 'id', u.banned AS 'bannedUntil'")
			->from('UserToSubject AS u')
			->join('Subject AS s', 'u.subjectId = s.subjectId')
			->where(array('u.banned >' => 'NOW()', 'u.userId' => $userId), NULL, FALSE);
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return $result->result_array();
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Gets all subjects a user has suggested
	 * @param int $userId
	 */
	function getSuggestedSubjects($userId) {
		$this->db->select("s.id, s.subjectName AS 'name', s.description, IF(s.checkedResult IS NULL, 'No', 'Yes') AS 'checked', IF(s.checkedResult = 1, 'Yes', 'No') AS 'approved'")
			->from('SuggestForums AS s')
			->where('s.userId', $userId);
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return $result->result_array();
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Gets all subjects a user is following. Used for administrative purposes, not for retrieval of groups for a user display.
	 * @param int $userId
	 */
	function getFollowedSubjects($userId) {
		$sql = "
			SELECT s.subjectName AS 'name', s.subjectId AS 'id', s.private, s.type, u.active, u.displayed
			FROM UserToSubject AS u
			JOIN Subject AS s ON s.subjectId = u.subjectId
			WHERE u.userId = ?
			AND (s.private = 0 OR u.privateAccess = 1)
			ORDER BY IF(s.subjectName = 'main feed' AND s.type = " . group::TYPE_RAMBLUR . ", 1000, IF(s.subjectId = 1, 1000, s.subjectName))
		";
		
		$query = $this->db->query($sql, [$userId]);
			
		if ($query->num_rows() === 0) {
			return FALSE;
		}
		
		return $query->result_array();
		
		// order by override through MY_DB_active_record isn't working. Investigate, then re-enable the below query instead of above
/* 		$this->db->select(' s.name, s.subjectId, s.private, s.type, u.active, u.displayed')
			->from('UserToSubject AS u')
			->join('Subject AS s', 'u.subjectId = s.subjectId')
			->where('u.userId', $userId)
			->where('(s.private = 0 OR u.privateAccess = 1)')
			->order_by("IF(s.subjectName = 'main feed' AND s.type = 0, 1000, IF(s.subjectId = 1, 1000, s.subjectName))", 'DESC', false);
		$result = $this->db->get(); */
/* 		if($result->num_rows() > 0) {
			return $result->result_array();
		} else {
			return FALSE;
		} */
	}
	
	public function getPrivateFollowedSubjectIds($userId) {
		$followed = $this->getFollowedSubjects($userId);
		$privateFollowed = [];
		
		if ($followed) {
			foreach($followed as $f) {
				if ($f['private'] == 1) {
					$privateFollowed[] = $f['id'];
				}
			}
		}
		return $privateFollowed;
	}
	
	/**
	 * Bans the given user from the given subject for a predetermined amount of time.
	 * 
	 * @param int $userId
	 * @param int $subjectId
	 */
	function banFromSubject($userId, $subjectId, $banTimePeriod = 7) {
		$this->db->select('userId')
			->select("IF(banned > NOW(), banned, 0) AS 'bannedUntil'")
			->from('UserToSubject')
			->where(array(
				'userId' => $userId,
				'subjectId' => $subjectId
			));
		$result = $this->db->get();
		
		$banStart = 'NOW()';
		if($result->num_rows() == 0) {
			// Insert inactive, banned, new userToSubject row. Prevents user unfollowing the group and being unbanned.
			// Does it? @todo Check what happens when you unfollow a group
			// Doesn't - need to update store procedures to check if the user is banned, and either block unfollow, or set as inactive, or something else
			$this->addUserToSubject($userId, $subjectId, false);
		} else {
			$currentBan = $result->first_row()->bannedUntil;
			if ($currentBan !== '0') {
				$banStart = "'".$currentBan."'";
			}
		}
		
		// To use mysql functions, need to tell CI to NOT escape the string, using set.
		$this->db->set('banned', "DATE_ADD(".$banStart.",INTERVAL $banTimePeriod DAY)", FALSE);
		
		// Update existing userToSubject row. If already banned, will be extended
		$this->db->set('banCount', 'banCount + 1', FALSE);
		$this->db->set('active', 0); //unfollow the user from the forum
		$this->db->set('currentlyBanned', 1); 
		$this->db->update('UserToSubject',
				array(),
				array('userId' => $userId, 'subjectId' => $subjectId));
		
		return TRUE;
	}

	/**
	 * Removes a ban from a user for the given subject.
	 * @todo - remove a certain number of days from a ban, instead of the whole lot
	 * 
	 * @param int $userId
	 * @param int $subjectId
	 */
	function unbanFromSubject($userId, $subjectId) {
		$this->db->update('UserToSubject', 
				array('banned' => '1000-01-01 00:00:00', 'active' => 1, 'currentlyBanned' => 0),
				array('userId' => $userId,
					'subjectId' => $subjectId
				));
		return TRUE;
	}
	
	/**
	 * Gets the number of bans recorded for the user in the given subject.
	 * 
	 * @param int $userId
	 * @param int $subjectId
	 * @return int
	 */
	function getBanCount($userId, $subjectId) {
		$this->db->select('banCount')
			->from('UserToSubject')
			->where(array(
				'userId' => $userId,
				'subjectId' => $subjectId
			));
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return 0;
		} else {
			return (int)$result->result_array()[0]["banCount"];
		}
	}
	
	/**
	 * Sends a message to the user letting them know they are on good behaviour for their posts.
	 * Increments warning count for that subject 
	 * 
	 * @param int $userId
	 * @param int $subjectId
	 * @return string|boolean
	 */
	function warnUser($userId, $subjectId) {
		
		$this->db->select('userId')
			->from('UserToSubject')
			->where(array(
					'userId' => $userId,
					'subjectId' => $subjectId
			));
		$result = $this->db->get();
	
		if($result->num_rows() == 0) {
			$this->addUserToSubject($userId, $subjectId, false);
		}
		$this->db->set('warningCount', 'warningCount + 1', FALSE);
		$this->db->update('UserToSubject',
			array(),
			array('userId' => $userId, 'subjectId' => $subjectId));
		
		return TRUE;
	}
	
	/**
	 * Gets the number of warnings recorded for the user in the given subject.
	 *
	 * @param int $userId
	 * @param int $subjectId
	 * @return int
	 */
	function getWarningCount($userId, $subjectId) {
		$this->db->select('warningCount')
			->from('UserToSubject')
			->where(array(
					'userId' => $userId,
					'subjectId' => $subjectId
			));
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return 0;
		} else {
			return (int)$result->result_array()[0]["warningCount"];
		}
	}
	
	function getEditedNodeCount($userId, $subjectId) {
		return $this->getActionedNodeCount($userId, $subjectId, 'edited');
	}
	
	function getDeletedNodeCount($userId, $subjectId) {
		return $this->getActionedNodeCount($userId, $subjectId, 'deleted');
	}
	
	private function getActionedNodeCount($userId, $subjectId, $action) {
		$this->db->select("COUNT(v.id) AS 'reviewCount'")
			->from('ReportedContentReviews AS v')
			->join('ReportedContent AS r', 'r.id = v.reportedContentId')
			->join('ReportedContentReviewsToUser AS u', 'u.id = v.latestPost'.ucfirst($action), 'left')
			->join('ReportedNodes AS rn', 'rn.reportedContentId = r.id', 'left')
			->join('Nodes AS n', 'n.nodeId = rn.nodeId', 'left')
			->join('Post AS p', 'p.postId = n.postId', 'left')
			->join('ReportedFiles AS rf', 'rf.reportedContentId = r.id', 'left')
			->join('Content AS f', 'f.fileName = rf.fileName')
			->join('Subject AS sub', 'sub.subjectId = p.subjectId OR sub.subjectId = f.subjectId')
			->where(array(
				'sub.subjectId' => $subjectId,
				'u.post'.ucfirst($action) => 1,
				'r.reportedUserId' => $userId
			));
		return (int)$this->db->get()->first_row('array')['reviewCount'];
	}
	
	/**
	 * Sets up the user as a moderator of the provided forum. To be used on creation of non-Ramblur forums only, as
	 * the process is different for Ramblur moderators.
	 * 
	 * @param int|string $userId
	 * @param int|string $subjectId
	 * @param string $text (optional)
	 * @param string $publicProfile (optional)
	 * @param string $postLink (optional)
	 * @return boolean
	 */
	function makeForumModerator($userId, $subjectId, $text = '', $publicProfile = '', $postLink = '') {
		if($this->group->getType($subjectId) === group::TYPE_RAMBLUR) {
			return false;
		}
		// Provide moderator permissions if not already
		$this->db->select('canBeModerator')
			->from('Permissions')
			->where('userId', $userId);
		if(!$this->db->get()->first_row('array')['canBeModerator']) {
			$this->db->update('Permissions',
				array('canBeModerator' => 1),
				array('userId' => $userId));
			
			if($this->db->affected_rows() == 0) {
				return false;
			}
		}
		
		// Insert row into Moderator table if not already
		$this->db->select('userId')
			->from('Moderator')
			->where('userId', $userId);
		
		if($this->db->get()->num_rows() === 0) {
			$this->db->insert('Moderator',
				array(
					'userId' => $userId,
					'text' => $text,//text for us, optional
					'publicProfile' => $publicProfile,//profile text for public viewing
					'postLink' => $postLink,//website link, optional
				));
			if($this->db->affected_rows() == 0) {
				return false;
			}
			
		}
		$this->db->insert('ModeratorToSubject',
			array(
				'userId' => $userId,
				'subjectId' => $subjectId,
				'approved' => 1,
				'moderator' => 1
		));
		if($this->db->affected_rows() == 0) {
			return false;
		}
		return true;
	}
	/**
	 * Handles assigning and unassigning moderators to/from subjects.
	 * 
	 * @param int $moderatorId
	 * @param int $subjectId
	 * @param boolean $assign
	 * @return boolean
	 */
	function setModeratorAssignedToSubject($moderatorId, $subjectId, $assign) {
		if(!is_bool($assign)) { // assign must be boolean
			return false;
		}
		if(!$this->moderatorCheck($moderatorId)) { // only moderators can be assigned/unassigned
			return false;
		}
		if(!$this->getModeratorSubjectApproved($moderatorId, $subjectId)) { // moderator must have been approved for that subject
			return false;
		}
		if($moderatorId == $this->authCheck() && $assign) { // if the moderator is self acting, they can only unassign, not assign
			return false;
		}
		/**
		 * @todo - if the forum is a ramblur forum, need to check if any other moderators are assigned to that subject. If not
		 * need to message supervisors of that subject to appoint a new moderator.
		 * Handled in controller for self acting ie. resignations
		 * What if the admin is doing it, and they appoint a new one straight after removing the previous one? Waste to notify supervisor.
		 * Do countries come into it? ie. if there is no other moderator for the country they were assigned to, block/notify supervisor?
		 */
		// If they are being assigned, check if moderator is following subject already. If not, add.
		if($assign) {
			$this->db->select('userId')
				->from('UserToSubject')
				->where(array(
					'userId' => $moderatorId,
					'subjectId' => $subjectId
				));
			$result = $this->db->get();
			if($result->num_rows() == 0) {
				$this->addUserToSubject($moderatorId, $subjectId, false);
			}
		}
		// Assign/unassign
		$this->db->update('ModeratorToSubject',
			array('moderator' => $assign),
			array('subjectId' => $subjectId, 'userId' => $moderatorId));
		
		if($this->db->affected_rows() == 0) {
			return false;
		}
		// Log action
		$this->db->insert('ModeratorToSubjectLog',
			array('agentId' => $this->authCheck(),
				'moderatorId' => $moderatorId,
				'subjectId' => $subjectId,
				'action' => $assign ? 'ASSIGN' : 'UNASSIGN'
			));
		
		// Send message if not self acting
		if($moderatorId !== $this->authCheck()) {
			$this->load->model('message');
			$msgType = $assign ? message::MSG_MODERATOR_ASSIGNED : message::MSG_MODERATOR_UNASSIGNED;
			$this->message->systemMessage($this->authCheck(), $moderatorId, $subjectId, $msgType);
		}
		return true;
	}

	// creates a new user
	function create($indata) {

		// validate data
		if (!is_array($indata) || count($indata) === 0) {
			return false;
		}

		// validate email
		if ($this->globals->emailVerify($indata['email']) === false) {
			return false;
		}
		$data = array();

		$verificationCode = $this->globals->uuid();
		
		// Hash the password so it's non-reversible
		$tmphash = password_hash($indata['password'], PASSWORD_DEFAULT);

		$this->load->helper(array('htmlpurifier'));
		
		$email = html_purify($indata['email'],'post');
		
		// If the user is joining through a 3rd party site, don't add them to main and my feed - we do now!!!!!!
		$followDefaultForums = true;
		if (isset($indata['thirdparty']) && $indata['thirdparty']) {
			// make sure forum details have been provided
			if (isset($indata['thirdpartyForum']) && isset($indata['thirdpartyForum']['type'])) {
				// check if the user is old enough to join the forum, if not they get added to main and my feed instead
				if ((int)$indata['thirdpartyForum']['type'] === Group::TYPE_CLUB18) {
					$dob = new DateTime($indata['userJoinDOB']);
					$now = new DateTime('today');
					$age = $dob->diff($now)->y;
					if ($age >= 18) {
						//$followDefaultForums = false;
						$followDefaultForums = true;
					}
				} else {
					//$followDefaultForums = false;
					$followDefaultForums = true;
				}
			}
		}

		$query = "CALL addUser(?,NULL,NULL,?,NULL,?,NULL,'','',0,?,NULL,NULL,?,NULL,NULL,?,?,@newuserId,NULL,NULL);";
		$params = array(
			html_purify($indata['userName'], 'post'),
			$email,
			$tmphash,
			date($indata['userJoinDOB']),
			html_purify($indata['country'],'post'),
			$verificationCode,
			$followDefaultForums
		);

		$query = $this->db->query( $query, $params );

		if ($query->num_rows() > 0 ) {
			$result = $query->row(); 
			$newUserId = $result->newUserId;
		} else {
			return false;
		}

		$to = $email;
		
		$this->load->model("email");
		
		if (isset($indata['thirdparty']) && $indata['thirdparty'] && isset($indata['thirdpartyForum']) && isset($indata['thirdpartyForum']['subjectName'])) {
			if(!$this->email->sendTPVerificationEmail($to,$verificationCode, $indata['thirdpartyForum']['subjectName'])){
				return false;
			}
		} else {
			if(!$this->email->sendVerificationEmail($to,$verificationCode)){
				return false;
			}			
		}
		
		$this->welcomeUser($newUserId);
		return $newUserId;
	}


	// set an account as verified
	function verify($data) {
		// if the verification code supplied seems ok
		if ((isset($data["verificationCode"])) && ($data["verificationCode"] !== false) && (mb_strlen($data["verificationCode"]) > 30 )) {

			// @todo - have requesting user and requested user both properly sent
			$query = "CALL verifyUser(NULL,1,?,TRUE);";
			$params = array($data["verificationCode"]);
			$query = $this->db->query($query, $params);

			if ($query->num_rows() > 0) {
				$result = $query->row(); 
				if($result->verified == TRUE) {
					return true;
				}
			}
		}
		return false;
	}


	// set an account as UNverified (from management console)
	function unverify($userId) {

		$query = "CALL verifyUser(?,?,NULL,FALSE);";

		$params = array($userId, $this->authCheck());
		$query = $this->db->query($query, $params);

		if ( $query->num_rows() > 0 ) {
			$result = $query->row();
			if($result->verified == FALSE){
				return true;
			}else{
				return false;
			}
		}
		// send a message to the user to let them know they need to re-verify
	}

	function storeSearchChoice($choice=15) {

		// check if the user is logged in
		$userId = $this->authCheck();
		if (!$userId) {
			return false;
		}

		if ($this->isUnder18($userId)) {
			// Don't allow under 18 users to activate club 18
			if ($choice >> 3) {
				return false;
			}
		}
		$query="UPDATE User SET searchChoice = ? WHERE userId = ?;";
		$params = array($choice,$userId);

		$query = $this->db->query($query, $params);
		return true;
	}
	
	// Retrieve details for a single button
	function button($data) {

		if (!isset($data['type']) || !isset($data['id']) || !isset($data['action'])) {
			return false;
		}
		$userId = $this->authCheck();
		if (!$userId) {
			return false;
		}
		if ($data['type'] === 'thread') {
			
			if ($data['action'] === 'get') {
				$this->load->config('censoring');
				$this->load->helper('htmlpurifier');
			
				$this->db->select("
						p.postId AS 'id',
						p.lastInput,
						us.primaryColour,
						us.secondaryColour,
						fp.value AS 'primaryFontColour',
						fs.value AS 'secondaryFontColour',
						p.title,
						n.text AS 'caption',
						p.postType AS 'type',
						n.nodeId AS 'headNodeId',
						n.visible,
						p.subjectId,
						t.value AS 'theme',
						u.viewed
					")
					->from('UserToPost AS u')
					->join('Post AS p', 'p.postId = u.postId')
					->join('Nodes AS n', 'n.postId = p.postId')
					->join('UserToSubject AS us', 'us.subjectId = p.subjectId AND us.userId = u.userId')
					->join('Subject AS s', 's.subjectId = p.subjectId')
					->join('ThemeColourOptions AS t', 't.id = us.themeColourOptionId')
					->join('ThemeFontOptions AS fp', 'fp.id = us.primaryThemeFontOptionId')
					->join('ThemeFontOptions AS fs', 'fs.id = us.secondaryThemeFontOptionId')
					->where([
							'u.userId' => $userId,
							'n.level' => 0,
							'p.visible' => 1,
							'u.postId' => $data['id']
					])
					->where('(us.privateAccess = 1 OR s.private = 0)');
		
				$result = $this->db->get();
				if ($result->num_rows() === 0) {
					return false;
				}
				$button = $result->first_row('array');
				// remove html except censor markup
				$button["caption"] = strip_tags($button["caption"], '<'.$this->config->item('censor_element').'>');
				if (mb_strlen($button["caption"]) > 12 ) {
					// truncate but preserve censor markup
					$button["caption"] = truncate_html($button["caption"], 48, '...', true, true);
				}
				$button['viewed'] = (boolean)$button['viewed'];
				return ["button" => $button];
			} else if ($data['action'] === 'updatexxxxxxxxxxxxxx') { //turned off for new notification screen
				if (!isset($data['viewed'])) {
					return false;
				}
				$this->db->update('UserToPost', ['viewed' => $data['viewed']], ['userId' => $userId, 'postId' => $data['id']]);
				return $this->db->affected_rows() > 0;
			} else if ($data['action'] === 'update') {
				$this->db->update('UserToPost', ['viewed' => 1], ['userId' => $userId, 'postId' => $data['id']]);
				return $this->db->affected_rows() > 0;
			}
		}
		return false;
	}

	// work out which buttons the user should have!
	function buttons() {

		$this->load->config('censoring');
		$this->load->helper('htmlpurifier');

		// check if the user is logged in

		$userId = $this->authCheck();
		
		if (!$userId) {
			return false;
		}

		// retrieve all posts user has subscribed to
		$this->db->select("
				p.postId AS 'id',
				p.lastInput,
				us.primaryColour,
				us.secondaryColour,
				fp.value AS 'primaryFontColour',
				fs.value AS 'secondaryFontColour',
				p.title,
				n.text AS 'caption',
				p.postType AS 'type',
				n.nodeId AS 'headNodeId',
				n.visible,
				p.subjectId,
				s.subjectName,
				t.value AS 'theme',
				u.viewed,
				p.timeStamp
			")
			->from('UserToPost AS u')
			->join('Post AS p', 'p.postId = u.postId')
			->join('Nodes AS n', 'n.postId = p.postId')
			->join('UserToSubject AS us', 'us.subjectId = p.subjectId AND us.userId = u.userId')
			->join('Subject AS s', 's.subjectId = p.subjectId')
			->join('ThemeColourOptions AS t', 't.id = us.themeColourOptionId')
			->join('ThemeFontOptions AS fp', 'fp.id = us.primaryThemeFontOptionId')
			->join('ThemeFontOptions AS fs', 'fs.id = us.secondaryThemeFontOptionId')
			->where([
				'u.userId' => $userId,
				'n.level' => 0,
				'p.visible' => 1,
				'p.modDeleted' => 0
			])
				//->where("n.nodeType != 'rootNode'")
			->where('(us.privateAccess = 1 OR s.private = 0)')
			->order_by('p.lastInput', 'DESC');

		$threads = $this->db->get()->result_array();
		for($i = 0; $i<count($threads); $i++) {
			// remove html except censor markup
			$threads[$i]["caption"] = strip_tags($threads[$i]["caption"], '<'.$this->config->item('censor_element').'>');
			if ( mb_strlen($threads[$i]["caption"]) > 12 ) {
				// truncate but preserve censor markup
				$threads[$i]["caption"] = truncate_html($threads[$i]["caption"], 48, '...', true, true);
			}
			$threads[$i]['viewed'] = (boolean)$threads[$i]['viewed'];
		}
		// if any buttons are there to be returned
		if (count($threads) > 0 ) {
			return ['thread' => $threads, 'user' => ['id' => $userId]];
		} else {
			return ['thread' => [], 'user' => ['id' => $userId]];
		}
	}

	// a skinnier version of user::buttons for devices - cjt
	function getThreadButtonIds() {

		// if we have a user
		if ($userId = $this->authCheck()) {

			// retrieve all post Ids user has subscribed to
			$this->db->select("
					p.postId AS 'id',
					u.viewed
				")
				->from('UserToPost AS u')
				->join('Post AS p', 'p.postId = u.postId')
				->join('Nodes AS n', 'n.postId = p.postId')
				->join('UserToSubject AS us', 'us.subjectId = p.subjectId AND us.userId = u.userId')
				->join('Subject AS s', 's.subjectId = p.subjectId')
				->where([
					'u.userId' => $userId,
					'n.level' => 0,
					'p.visible' => 1
				])
				->where('(us.privateAccess = 1 OR s.private = 0)');

			$threads = $this->db->get()->result_array();

			// if any buttons are there to be returned
			return (count($threads) > 0 ) ? $threads : false;

		}
		return false;
		
	}

	// return who we are following
	function people($userId) {
  
		$this->db->select("s.userID, s.socialId, s.subjectId, u.userName, u.avatar")
			->from('UserToSocial AS s')
			->join('User AS u', 'u.userId = IF(s.userID = '.$userId.', s.socialId, s.userID)')
			->where("(s.userID = " . $userId . " OR s.socialId = " . $userId . ")")
			->where('displayed', 1);

		$results = $this->db->get();
		if($results->num_rows() == 0) {
			return false;
		}
		$results = $results->result_array();
		$resultsBySubjectId = array();

		$followers = array();
		$followed = array();
		foreach($results as $r) {
			$subjectId = $r['subjectId'];
			if(!isset($resultsBySubjectId[$subjectId])) {
				$resultsBySubjectId[$subjectId] = array();
			}
			$resultsBySubjectId[$subjectId][] = $r;
		}

		$data = array();
		foreach($resultsBySubjectId as $subjectId => $rows) {
			$subArray = array();
			$followed = array();
			$followers = array();
			$subArray['subjectId'] = $subjectId;
			foreach($rows as $r) {
				if($r['userID'] == $userId) {
					$followed[] = array(
						'id' => $r['socialId'],
						'name' => $r['userName'],
						'avatar' => $r['avatar'] ? $r['avatar'] . '-avatar' : ''
					);

				} else {
					$followers[] = array(
						'id' =>$r['userID'],
						'name' => $r['userName'],
						'avatar' => $r['avatar'] ? $r['avatar'] . '-avatar' : ''
					);
				}
			}

			$subArray['followers'] = $followers;
			$subArray['followed'] = $followed;
			$data[] = $subArray;
		}
		return $data;
	}


// recycle this for the unique usernames?
/*
	function generateUserId() {

		$userId = false;
		$limit = 1000;
		$i = 1;

		while ( ( $userId === false ) && ( $i < $limit ) ) {

			$tmp = mt_rand(1111111111, 9999999999);

			$query = "select `value` from `properties` where `name` = 'userId' and `value` = ?";
			$query = $this->db->query($query, array($tmp));

			if ( $query->num_rows() <= 0 ) {

				$userId = $tmp;

			}
			
		}

		return $userId;
	}
*/
	/**
	 * Carries out a database query for a user based on the search string. Matches by username, email, firstName or lastName.
	 * Returns an array of user ids, or false if none found.
	 * 
	 * @param string $search
	 * @param number $page
	 * @param number $limit
	 * @return array|boolean
	 */
	function search($search, $page = 0, $limit = 50) {
		$limit = 1 * $limit;
		$page = 1 * $page;

		$this->db->select('userId')
			->from('User')
			->like('userName', $search)
			->or_like(array(
					'email' => $search,
					'firstName' => $search,
					'lastName' => $search,
			))->order_by('userId')
			->limit($limit, $page * $limit);
		$query = $this->db->get();
		if($query->num_rows() > 0) {
			foreach($query->result_array() as $row) {
				$userInfo = $this->info($row["userId"], true);
				if($userInfo) {
					$result[] = $userInfo;
				}
			}
			return $result;
		} else {
			return array();
		}
	}
	
	/**
	 * Returns the ids of all reported content written by the specified user that have been reviewed, ordered by most recent to least.
	 * Only reviewed reports are returned, as unreviewed reports may never be actually reported, and so shouldn't be counted.
	 * 
	 * @param int $userId
	 */
	function getPreviousReports($userId, $subjectId = null) {
		$this->db->select("`r`.`id`, MAX(`v`.`latestReviewDate`) AS 'mostRecentReport', ANY_VALUE(s.subjectId) AS 'subjectId', ANY_VALUE(s.subjectName) AS 'subjectName'", FALSE)
			->from('ReportedContent AS r')
			->join('ReportedContentReviews AS v', 'v.reportedContentId = r.id')
			->join('ReportedNodes AS rn', 'rn.reportedContentId = r.id', 'left')
			->join('Nodes AS n', 'n.nodeId = rn.nodeId', 'left')
			->join('Nodes AS h', 'h.postId = n.postId AND h.nodeType = "rootNode"', 'left')
			->join('Post AS p', 'p.postId = n.postId', 'left')
			->join('ReportedFiles AS rf', 'rf.reportedContentId = r.id', 'left')
			->join('Content AS f', 'f.fileName = rf.fileName', 'left')
			->join('Subject AS s', 's.subjectId = p.subjectId OR s.subjectId = f.subjectId')
			->where(array('reportedUserId' => $userId));
		if(!is_null($subjectId)) {
			$this->db->where('s.subjectId', $subjectId);
		}
		$this->db->group_by('r.id')
			->order_by('mostRecentReport', 'DESC');
		return $this->db->get()->result_array();
	}
	
	function findStandardUsers() {
		$this->db->select('u.userId, u.userName')
		->from('User AS u')
		->join('Moderator AS m', 'm.userId = u.userId', 'left')
		->join('Permissions AS p', 'p.userId = u.userId')
		->where('(m.ramblurEnabled = 0 OR m.ramblurEnabled IS NULL)')
		->where([
			'p.isAdministrator' => 0,
			'p.isManager' => 0,
			'p.canBeSupervisor' => 0
		])
		->order_by('u.joinDate', 'DESC');

		$results = $this->db->get();
		$returnData = array();
		if($results->num_rows() > 0) {
			$users = $results->result_array();
			foreach($users as $user) {
				$userId = $user["userId"];
				$userData = $this->info($userId, true);
				$userData["userId"] = $userId;
				$returnData[] = $userData;
			}
			return $returnData;
		} else {
			return [];
		}
	}

	/**
	 * Returns a list of all users who can be moderators (and no higher), along with what subjects they can/do moderate.
	 * 
	 * @return array
	 */
	function findRamblurModerators() {
		$this->load->model('group');
		$this->db->select('u.userId, u.userName, su.subjectId, su.subjectName, ms.approved, ms.moderator')
			->select("IF(SUM(IF(l.`action` = 'ASSIGN', 1, 0)) > 0, 1, 0) AS 'hasModerated'", FALSE)
			->from('User AS u')
			->join('Moderator AS m', 'm.userId = u.userId')
			->join('ModeratorToSubject AS ms', 'ms.userId = u.userId', 'left')
			->join('Subject AS su', 'su.subjectId = ms.subjectId', 'left')
			->join('ModeratorToSubjectLog AS l', 'l.moderatorId = u.userId AND l.subjectId = su.subjectId', 'left')
			->where(array(
				'm.ramblurEnabled' => '1',
				'su.type' => group::TYPE_RAMBLUR
			))
			->group_by('u.userId, su.subjectId');
		$results = $this->db->get();
		if($results->num_rows() > 0) {
			$results = $results->result_array();
			$resultsByUserId = array();
			foreach($results as $r) {
				$userId = $r["userId"];
				if(!isset($resultsByUserId[$userId])) {
					$resultsByUserId[$userId] = array();
				}
				$resultsByUserId[$userId][] = $r;
			}
			$data = array();
			foreach($resultsByUserId as $userId => $rows) {
				$userData = $this->info($userId, true);
				$userData["userId"] = $userId;
				$userData["subjects"] = array();
				foreach($rows as $row) {
					if(!is_null($row["subjectId"]) && $row["approved"] == 1) {
						$sub = array();
						$sub["id"] = $row["subjectId"];
						$sub["name"] = $row["subjectName"];
						$sub["moderator"] = $row["moderator"] === '1';
						$sub["hasModerated"] = $row["hasModerated"] === '1';
						$userData["subjects"][] = $sub;
					}
				}
				$data[] = $userData;
			}
			return $data;
		} else {
			return [];
		}
	}
	
	/**
	 * Returns a list of all users who can be supervisors, and no higher.
	 *
	 * @return array
	 */
	function findSupervisors() {
		$this->db->select('u.userId, u.userName')
		->from('User AS u')
		->join('Permissions AS p', 'p.userId = u.userId')
		->where(array(
			'p.canBeSupervisor' => 1,
			'p.isManager' => 0,
			'p.isAdministrator' => 0
		));
		$results = $this->db->get();
		$returnData = array();
		if($results->num_rows() > 0) {
			$users = $results->result_array();
			foreach($users as $user) {
				$userId = $user["userId"];
				$userData = $this->info($userId, true);
				$userData["userId"] = $userId;
				$returnData[] = $userData;
			}
			return $returnData;
		} else {
			return array();
		}
	}
	
	/**
	 * Returns a list of all users who have manager privileges (and no higher).
	 *
	 * @return array
	 */
	function findManagers() {
		$this->db->select('u.userId, u.userName')
		->from('User AS u')
		->join('Permissions AS p', 'p.userId = u.userId')
		->where(array(
			'p.isManager' => 1,
			'p.isAdministrator' => 0
		));
		$results = $this->db->get();
		$returnData = array();
		if($results->num_rows() > 0) {
			$users = $results->result_array();
			foreach($users as $user) {
				$userId = $user["userId"];
				$userData = $this->info($userId, true);
				$userData["userId"] = $userId;
				$returnData[] = $userData;
			}
			return $returnData;
		} else {
			return array();
		}
	}
	
	/**
	 * Returns a list of all users who have administrative privileges.
	 *
	 * @return array
	 */
	function findAdministrators() {
		$this->db->select('u.userId, u.userName')
		->from('User AS u')
		->join('Permissions AS p', 'p.userId = u.userId')
		->where(array('p.isAdministrator' => 1));
		$results = $this->db->get();
		$returnData = array();
		if($results->num_rows() > 0) {
			$users = $results->result_array();
			foreach($users as $user) {
				$userId = $user["userId"];
				$userData = $this->info($userId, true);
				$userData["userId"] = $userId;
				$returnData[] = $userData;
			}
			return $returnData;
		} else {
			return array();
		}
	}
	
	/**
	 * Finds all subjects the given user is a moderator of.
	 * 
	 * @param int $userId
	 * @return array
	 */
	function getModeratorSubjects($userId) {
		$this->db->select("su.subjectId AS 'id', su.subjectName AS 'name', su.type AS 'type', m.approved AS 'approved', m.moderator AS 'currentModerator', su.facebookLink")
			->from('ModeratorToSubject AS m')
			->join('Subject AS su', 'su.subjectId = m.subjectId')
			->where(array('m.userId' => $userId));
		$results = $this->db->get();
		return $results->result_array();
	}
	
	function getModeratorProfile($userId) {
		$this->db->select('text, publicProfile')
			->from('Moderator')
			->where('userId', $userId);
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return array();
		} else {
			return $result->first_row('array');
		}
	}
	
	/**
	 * Determines if the user is a moderator of the given subject.
	 * 
	 * @param int $userId
	 * @param int $subjectId
	 * @return boolean
	 */
	function isModeratorSubject($userId, $subjectId) {
		foreach($this->getModeratorSubjects($userId) as $s) {
			if($s["id"] == $subjectId) {
				return $s["currentModerator"] == 1 && $s['approved'] == 1;
			}
		}
		return false;
	}
	
	/**
	 * Finds ths number of moderators for a given subject
	 * 
	 * @param int $subjectId
	 * @return int
	 */
	function moderatorCount($subjectId) {
                 
		$this->db->select('moderator')
			->where(array(
				"subjectId" => $subjectId,
				"moderator" => 1,
				"approved" => 1
			));
		return $this->db->get('ModeratorToSubject')->num_rows();
	}
	
	/**
	 * Determines if the user is a supervisor of the given subject
	 * 
	 * @param int $userId
	 * @param int $subjectId
	 */
	function isSupervisorSubject($userId, $subjectId) {
	
		$this->db->from('SupervisorToSubject')
			->where(array(
				"userId" => $userId,
				"subjectId" => $subjectId
			));
		return $this->db->count_all_results() >= 1;
	}
	
	/**
	 * Finds all subjects the given user is a supervisor of.
	 *
	 * @param int $userId
	 * @return array
	 */
	function getSupervisorSubjects($userId) {
		$this->db->select("s.subjectId AS 'id', su.facebookLink")
			->from('SupervisorToSubject AS s')
			->join('Subject AS su', 'su.subjectId = s.subjectId')
			->where('userId', $userId);
		return $this->db->get()->result_array();
	}
	
	/**
	 * Determines if the user has access to the given subject.
	 * Note: permissions for actions still need to be given to different user levels. eg. moderators can't
	 * unban members, even if they moderate the subject.
	 * @param int $userId
	 * @param int $subjectId
	 * @return boolean
	 */
	function canManageSubject($userId, $subjectId) {
		$level = $this->getUserLevel($userId);
		switch ($level) {
			case self::USER_ADMIN:
				return true;
				break;
			case self:: USER_MANAGER:
				return true;
				break;
			case self::USER_SUPERVISOR:
				return $this->isSupervisorSubject($userId, $subjectId);
				break;
			case self::USER_MODERATOR:
				return $this->isModeratorSubject($userId, $subjectId);
			default:
				return false;
			break;
		}
	}
	
	/**
	 * Determines if the user provided as the manager is allowed to manage the other user ie. act for them when reviewing nodes,
	 * banning users etc.
	 * @param int|string $managerId
	 * @param int|string $userId
	 * @return boolean
	 */
	function canManageUser($managerId, $userId) {
		if($managerId == $userId) {
			return true;
		}
		$managerLevel = $this->getUserLevel($managerId);
		$userLevel = $this->getUserLevel($userId);
		switch($managerLevel) {
			case self::USER_ADMIN:
				return true;
				break;
			case self:: USER_MANAGER:
				
				return $userLevel === self::USER_SUPERVISOR || $userLevel === self::USER_MODERATOR;
				break;
			case self::USER_SUPERVISOR:
				if($userLevel == 'moderator') {
					$managerSubjects = array();
					foreach($this->getSupervisorSubjects($managerId) AS $m) {
						$managerSubjects[] = $m['id'];
					}
					$userSubjects = array();
					foreach($this->getModeratorSubjects($userId) AS $s) {
						$userSubjects[] = $s['id'];
					}
					return array_intersect($managerSubjects, $userSubjects) ? true : false;
				} else {
					return false;
				}
				break;
			case self::USER_MODERATOR:
				return false;
			default:
				return false;
				break;
		}
	}
	
	/**
	 * Returns id and username of a user the level above the given user (termed overseer, as supervisor is specific in the hierarchy).
	 * If more than one user is found, a random one is chosen.
	 * subjectId is required to find an overseer of a moderator, as they should only be overseen by specific subjects.
	 * eg. for a supervisor, this will attempt to find a manager.
	 * @todo - what about non-Ramblur forums?
	 * 
	 * @param int|string $userId
	 * @param int|string $subjectId
	 * @return boolean|array
	 */
	function getOverseer($userId, $subjectId = null) {
		$userLevel = $this->getUserLevel($userId);
		switch($userLevel) {
			case self::USER_ADMIN:
				return false;
				break;
			case self:: USER_MANAGER:
				// get an admin
				$this->db->select("u.userId AS 'id', u.userName")
					->from('User AS u')
					->join('Permissions AS p', 'p.userId = u.userId')
					->where('p.isAdministrator', '1')
					->order_by('RAND()')
					->limit(1);
				$admin = $this->db->get();
				if($admin->num_rows() === 0) {
					return false;
				} else {
					return $admin->first_row('array');
				}
				break;
			case self::USER_SUPERVISOR:
				// get a manager
				$this->db->select("u.userId AS 'id', u.userName")
					->from('User AS u')
					->join('Permissions AS p', 'p.userId = u.userId')
					->where(array(
						'p.isManager' => '1',
						'p.isAdministrator' => '0'
					))
					->order_by('RAND()')
					->limit(1);
				$manager = $this->db->get();
				if($manager->num_rows() === 0) {
					return false;
				} else {
					return $manager->first_row('array');
				}
				break;
			case self::USER_MODERATOR:
				// get a supervisor in their subject
				if(is_null($subjectId)) {
					return false;
				}
				$this->db->select("u.userId AS 'id', u.userName")
					->from('User AS u')
					->join('SupervisorToSubject AS s', 's.userId = u.userId')
					->where('s.subjectId', $subjectId)
					->order_by('RAND()')
					->limit(1);
				$supervisor = $this->db->get();
				if($supervisor->num_rows() === 0) {
					return false;
				} else {
					return $supervisor->first_row('array');
				}
			default:
				return false;
				break;
		}
	}
	
	/**
	 * Returns all subjects the user is allowed to manage. Administrators and managers can manage any subject. Supervisors
	 * and moderators can only manage subjects assigned to them.
	 * 
	 * @param int|string $userId
	 * @return boolean|array
	 */
	function getManageableSubjects($userId) {
		$level = $this->getUserLevel($userId);
		switch ($level) {
			case self::USER_ADMIN:
				return true;
				break;
			case self::USER_MANAGER:
				return true;
				break;
			case self::USER_SUPERVISOR:
				return $this->getSupervisorSubjects($userId);
				break;
			case self::USER_MODERATOR:
				$allSubjects = $this->getModeratorSubjects($userId);
				$currentlyModerating = array();
				$this->load->model('reported_content');
				foreach($allSubjects as $a) {
					if($a['currentModerator']) {
						$currentlyModerating[] = array(
							"id" => $a['id'],
							"name" => $a['name'],
							"facebookLink" => $a["facebookLink"]
							//"twitterLink" => $a['twitterLink']
						);
					}
				}
				return $currentlyModerating;
			default:
				return array();
				break;
		}
	}
	
	/**
	 * Returns all subjects the supervisor is assigned to that don't have a moderator.
	 *
	 * @param int|string $supervisorId
	 * @return array
	 */
	function getSupervisorUnmoderatedSubjects($supervisorId) {
		
		$this->db->select("s.subjectId AS 'id'")
			->from('SupervisorToSubject AS s')
			->join('ModeratorToSubject AS m', 'm.subjectId = s.subjectId AND m.approved = 1 AND m.moderator = 1', 'LEFT')
			->where([
				's.userId' => $supervisorId,
				'm.userId' => NULL
			]);
		return $this->db->get()->result_array();
	}
	
	/**
	 * Returns true if the user has been approved as a moderator of the given subject..
	 * 
	 * @param int|string $userId
	 * @param int|string $subjectId
	 * @return boolean
	 */
	function getModeratorSubjectApproved($userId, $subjectId) {
		$this->db->select('approved')
			->from('ModeratorToSubject')
			->where(array(
				'userId' => $userId,
				'subjectId' => $subjectId
			));
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return $result->first_row('array')["approved"] == 1;
		} else {
			return false;
		}
	}
	
	/**
	 * Marks a user as either approved or unapproved to moderate the given 
	 * subject.
	 * 
	 * @param int|string $userId
	 * @param int|string $subjectId
	 * @param boolean $approved
	 * @return boolean
	 */
	function approveModeratorSubject($userId, $subjectId, $approved) {
		if(!is_bool($approved)) {
			return false;
		}
		$this->db->update('ModeratorToSubject',
			array('approved' => $approved),
			array('userId' => $userId,
				'subjectId' => $subjectId
			)
		);
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Returns the number of admin posts the user has yet to mark as read.
	 * 
	 * @param int|string $userId
	 * @return int
	 */
	function getUnacknowledgedAdminPostCount($userId) {
		// unacknowledged admin posts
		$adminPostSql = "
			SELECT COUNT(`u`.`postId`) AS 'count'
			FROM `UserToAdminPost` AS `u`
			JOIN `Post` AS `p` ON `p`.`postId` = `u`.`postId`
			WHERE `u`.`userId` = ? AND `u`.`readIt` = 0 AND `p`.`visible` = 1
			";
		return (int)$this->db->query($adminPostSql, array($userId))->first_row('array')["count"];
	}

	/**
	 * Returns the number of reported posts needing review in any subject moderator controls.
	 * 
	 * @param int|string $userId
	 * @return int
	 */
	function getModeratorActiveReportedPostCount($userId) {
		$this->load->model('reported_content');
		return $this->reported_content->getActiveReportedPostCount($userId, null, 'moderator');
	}
	
	/**
	 * Returns the number of unread messages the moderator has in their management inbox.
	 * 
	 * @param int|string $userId
	 * @return int
	 */
	function getUnreadModeratorMessageCount($userId) {
		$this->load->model('message');
		return $this->message->moderatorMessages('unseen', $userId, null, FALSE, TRUE);
	}
	
	/**
	 * Returns the number of reported posts needing review in any subject moderator controls;
	 * @param int|string $userId
	 * @return int
	 */
	function getSupervisorActiveReportedPostCount($userId) {
		// reported posts needing review in any subject supervisor controls
		$this->load->model('reported_content');
		return $this->reported_content->getActiveReportedPostCount($userId, null, 'supervisor');
	}
	
	/**
	 *  Returns the number of reported posts needing review by any manager (managers share these).
	 *  
	 *  @return int
	 */
	function getManagerActiveReportedPostCount() {
		$this->load->model('reported_content');
		return $this->reported_content->getActiveReportedPostCount(null, null, 'manager');
	}
	
	/**
	 * Returns the number of messages the supervisor has in their management inbox.
	 * 
	 * @param int|string $userId
	 * @return int
	 */
	function getUnreadSupervisorMessageCount($userId) {
		$this->load->model('message');
		return $this->message->supervisorMessages('unseen', $userId, TRUE);
	}
	
	/**
	 * Returns the number of messages the manager has in their management inbox.
	 *
	 * @param int|string $userId
	 * @return int
	 */
	function getUnreadManagerMessageCount($userId) {
		$this->load->model('message');
		return $this->message->managerMessages('unseen', $userId, TRUE);
	}
	
	/**
	 * Returns the counts for outstanding moderator tasks.
	 *
	 * @param int|string $userId
	 * @return array
	 */
	function getModeratorPendingCount($userId) {
		$returnData = array();
		$returnData["adminPosts"] = $this->getUnacknowledgedAdminPostCount($userId);
		$returnData["reportedPosts"] = $this->getModeratorActiveReportedPostCount($userId);
		$returnData["messages"] = $this->getUnreadModeratorMessageCount($userId);
		return $returnData;
	}
	
	/**
	 * Returns the counts for outstanding supervisor tasks.
	 *
	 * @param int|string $userId
	 * @return array
	 */
	function getSupervisorPendingCount($userId) {
		$returnData = array();
		$returnData["adminPosts"] = $this->getUnacknowledgedAdminPostCount($userId);
		$returnData["reportedPosts"] = $this->getSupervisorActiveReportedPostCount($userId);
		$returnData["messages"] = $this->getUnreadSupervisorMessageCount($userId);
		return $returnData;
	}
	
	/**
	 * Returns the counts for outstanding manager tasks.
	 *
	 * @param int|string $userId
	 * @return array
	 */
	function getManagerPendingCount($userId) {
		$returnData = array();
		$returnData['adminPosts'] = $this->getUnacknowledgedAdminPostCount($userId);
		$returnData['messages'] = $this->getUnreadManagerMessageCount($userId);
		$returnData['reportedPosts'] = $this->getManagerActiveReportedPostCount();
		return $returnData;
	}
	
	/**
	 * Checks that a chat invite is allowed to be issued.
	 * @param int|string $toUserId
	 * @return boolean
	 */
	function checkChatInvite($toUserId, $fromUserId) {
	
		// check to user isn't blocking from user
		// check from user is not blocking to user
		$this->db->select('userId')
			->from('UserToBlock')
			->where("(userId = $toUserId AND blockId = $fromUserId)")
			->or_where("(userId = $fromUserId AND blockId = $toUserId)");
		return $this->db->get()->num_rows() === 0;
	}

    // Added - cjt

    function getSubjectColor($data) {

        $userId = $this->authCheck();

		$colors = array();

		if($userId && $data['subjectId']!==1) {

			$sql = "SELECT
						IF(u.userId IS NULL, s.primaryColour, u.primaryColour) AS 'primaryColour',
						IF(u.userId IS NULL, s.secondaryColour, u.secondaryColour) AS 'secondaryColour',
						fp.value AS 'primaryFontColour',
						fs.value AS 'secondaryFontColour',
						t.value AS 'theme',
						IF(u.userId IS NULL, s.opacity, u.opacity) AS 'opacity'
					FROM Subject AS s
					LEFT JOIN UserToSubject AS u ON u.subjectId = s.subjectId AND u.userId = ?
					JOIN ThemeColourOptions AS t ON t.id = IF(u.userId IS NULL, s.themeColourOptionId, u.themeColourOptionId)
					JOIN ThemeFontOptions AS fp ON fp.id = IF(u.userId IS NULL, s.primaryThemeFontOptionId, u.primaryThemeFontOptionId)
					JOIN ThemeFontOptions AS fs ON fs.id = IF(u.userId IS NULL, s.secondaryThemeFontOptionId, u.secondaryThemeFontOptionId)
					WHERE s.subjectId = ?";
			$params = array($userId, $data['subjectId']);

			if($result = $this->db->query($sql, $params)) {

				$subjectColors = $result->result_array();
				//var_dump($subjectColors);
				
				$colors['primary'] = $subjectColors[0]['primaryColour'];
				$colors['secondary'] = $subjectColors[0]['secondaryColour'];
				$colors['primaryFont'] = $subjectColors[0]['primaryFontColour'];
				$colors['secondaryFont'] = $subjectColors[0]['secondaryFontColour'];
				$colors['theme'] = $subjectColors[0]['theme'];
				$colors['themeTxtCol'] = ($colors['theme'] === 'light') ? '#000000' : '#FFFFFF';
				$colors['themeInputCol'] = ($colors['theme'] === 'dark' || $colors['themeTxtCol'] === '#000000') ? '#FFFFFF' : '#000000';
				$colors['themeHex'] = ($colors['theme'] === 'light') ? '#FFFFFF' : '#000000';
				$colors['themeRGB'] = $this->hex2rgb($colors['themeHex']);
				$colors['opacity'] = (string)$subjectColors[0]['opacity'] / 100;

		   		return $colors;

			}
			return false;

		} else {

			$colors['primary'] = group::DEFAULT_PRIMARYCOLOUR;
			$colors['secondary'] = group::DEFAULT_SECONDARYCOLOUR;
			$colors['primaryFont'] = $this->group->getDefaultThemeFontOption()['value'];
			$colors['secondaryFont'] = $this->group->getDefaultThemeFontOption()['value'];
			$colors['theme'] = $this->group->getDefaultThemeColourOption()['value'];
			$colors['themeTxtCol'] = '#000000';
			$colors['themeInputCol'] = '#000000';
			$colors['themeHex'] = '#FFFFFF';
			$colors['themeRGB'] = $this->hex2rgb($colors['themeHex']);
			$colors['opacity'] = group::DEFAULT_OPACITY / 100;

		}

		return $colors;

    }


    /* i think i restored this to use it within the "Device" Settings Lite area - cjt */
    function countryDD() {

		$sql = "SELECT * FROM Country ORDER BY name ASC";
		if($result = $this->db->query($sql)) {

			$countries = $result->result_array();
			return $countries;

		}
   		return false;

    }

    function getStatesByCountry($data) {

		$sql = "SELECT * FROM State WHERE countryId = ?";
		$params = array($data['countryId']);
		if($result = $this->db->query($sql, $params)) {

			$states = $result->result_array();
			return $states;

		}
   		return false;

    }

    // USES GEONAMES API
    function getSuburbsByPostcode($data) {

    	//print_r($data);
    	$ukpc = array('EN', 'WA', 'AB', 'UL'); // EIRE?
    	if(in_array($data['country'], $ukpc)) $data['country'] = 'GB';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://api.geonames.org/postalCodeLookupJSON?postalcode='.urlencode($data['postcode']).'&country='.$data['country'].'&username='.$this->config->item("geonamesUsername"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
		curl_setopt($ch, CURLOPT_PROXY, $this->config->item("ramblur_internal_proxy").$this->config->item("ramblur_internal_proxy_port"));
		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);

		return $response['postalcodes'];

    }


    // move to "utility" model or ???
	function hex2rgb($color) {

		$color = str_replace("#", "", $color);

	    list($r, $g, $b) = array($color[0].$color[1],
	                             $color[2].$color[3],
	                             $color[4].$color[5]);
	    $r = hexdec($r); $g = hexdec($g); $b = hexdec($b);
	    return array($r, $g, $b);

	}


	// get a list of people who you're following, and in which group
	function getSocialFollowing() {

		if($userId = $this->authCheck()) {

			$sql = "SELECT socialId as id, userName as user, subjectName as forum, UserToSocial.subjectId as forumId FROM UserToSocial JOIN User ON UserToSocial.socialId = User.userId JOIN Subject ON UserToSocial.subjectId = Subject.subjectId WHERE UserToSocial.userId = ?;";
			$params = array($userId);
			if($result = $this->db->query($sql, $params)) {

				if($result->num_rows()>0) {

					$rows = $result->result_array();
					return $rows;

				}
				return '0';

			}
			return false;

		}
		return false;

	}

	function following() { // perhaps add a 'type' arg in here to find out social following, not just forums?

		$userId = $this->authCheck();

		// something something also banned? privateAccess?
		$sql = "SELECT * FROM UserToSubject WHERE userId = ? AND active = 1 AND privateAccess = 0;";
		$params = array($userId);
		$result = $this->db->query($sql, $params);

		return $result->num_rows();

	}


	/* added cjt */

	function getUserTimezone($userId) {

		$this->db->select('timeZone as timezone')->from('User')->where('userId', $userId);
		return ($result = $this->db->get()->row()) ? $result->timezone : false;

	}	

	// serves a purpose for me! - very basic at this stage...!
	function getBasicDetails ($data) {

		if($userId = $this->authCheck()) {

			if ($data['blockCheck']===true) {

				// get the user's details, but not if they are blocking this user
				// this is a messaging specific scenario - perhaps pass a parameter in to "just get basic details" without the block check
				$query = $this->db->select('*')->get_where('UserToBlock', array('userId' => $data['user'], 'blockId' => $userId));

				// they have NOT blocked this userId
				if ($query->num_rows()===0) {

					//echo "here2: ".$data['user'];
					$sql = $this->db->select('userId, userName')->get_where('User', array('userId' => $data['user']));
					$details = $sql->row_array();

					return $details;

				}

			} else {

				// an auth'd user fetching another user's details (initially for classified ad - NOT YET IMPLEMENTED)
				// $userId = (isset($data['adUser'])) ? $data['adUser'] : $userId;

				$sql = $this->db->select('userId, userName, avatar')->get_where('User', array('userId' => $data['user']));
				$details = $sql->row_array();

				return $details;

			}

		}
		return false;

	}

	function featuredGroups($data) {

		$userId = $this->authCheck();
		if (!$userId) {
			return false;
		}

		// initialise FG array
		$inFG = array();
		// get a current list of groups in this featured group
		$query = "SELECT subjectId FROM subjectGroups WHERE groupWith = ? AND userId = ? AND subjectId != ?"; 
		$params = array($data['fgId'], $userId, $data['fgId']);
		$query = $this->db->query($query, $params);

		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			foreach ($result as $row) array_push($inFG, $row['subjectId']);
		}

		// initialised array for returning
		$groups = array();

		$query = "CALL getUserGroups(?);"; 
		$params = array($userId);
		$query = $this->db->query($query, $params); 
 
		if ($query->num_rows() > 0) {

			$result = $query->result_array();

			foreach ($result as $row) {

				if($row['subjectId'] !== $data['fgId']) {

					$group['id'] = $row['subjectId'];
					$group['name'] = $row["subjectName"];
					$group['type'] = $row['type'];
					$group["inFeaturedGroup"] = (in_array($row['subjectId'], $inFG)) ? 1 : 0;

					array_push($groups, $group);
				}
			}
		}
		return $groups;
	}

	// "mobile" version of groups - needed a bit more info up front
	function groups2() {

		$userId = $this->authCheck();
		
		if (!$userId) {
			return false;
		}
		
		// FEATURED GROUP - find out if there is one
		$sql = "SELECT featuredGroup FROM User WHERE userId = ?";
		$params = array($userId);
		if($result = $this->db->query($sql, $params)){

			$fg = $result->result_array();
			if($fg[0]['featuredGroup'] !== 0 ) $featuredGroup = $fg[0]['featuredGroup'];

		}

		// "GROUPED" topics - are there any?  and if so, record for later flag setting
		$sql = "SELECT DISTINCT(groupWith) FROM subjectGroups WHERE userId = ?";
		$params = array($userId);
		$result = $this->db->query($sql, $params);
		$g = $result->result_array();
		$groupedTopics = array();
		foreach ($g as $row) {
			// if there is a grouping and it's not the fg, record it
			if($row['groupWith'] != $featuredGroup) array_push($groupedTopics, $row['groupWith']);
		}

		// initialised array for returning
		$groups = array();

		$query = "CALL getUserGroups(?);"; 
		$params = array($userId);
		$query = $this->db->query($query, $params); 
 
		if ($query->num_rows() > 0) {

			$result = $query->result_array();

			foreach ($result as $row) {

				$group['id'] = $row['subjectId'];
				$group['name'] = $row["subjectName"];
				$group['description'] = $row['description'];
				$group['type'] = $row['type'];
				$group["grouped"] = (in_array($row['subjectId'], $groupedTopics)) ? 1 : 0;
				$group["featuredGroup"] = ($row['subjectId'] == $featuredGroup) ? 1 : 0;
				$group["private"] = $row['private'];

				array_push($groups, $group);
			}
		}

		if(!$userId) array_unshift($groups, array("name" => "overview", "id" => "-1"));

		return $groups;
	}

	function profileDetails () {

		// check if the user is logged in
		$userId = $this->user->authCheck();
		if ($userId === false) {
			return false;
		}

		if($userId === false){return false;}

		$this->db->select('
				userName,
				mobile,
				skype,
				facetime,
				email,
				gender,
				firstName,
				lastName,
				age,
				dob,
				avatar,
				timeZone,
				Country.name as country,
				Country.countryId,
				State.name as state,
				User.stateId,
				suburb,
				postcode')
			->from('User')
			->join('Country', 'Country.countryId = User.countryId')
			->join('State', 'State.stateId = User.stateId', 'left')
			->where('User.userId', $userId);
		$query = $this->db->get();

		if ($query->num_rows() === 1) {
			$result = $query->first_row('array');
			return $result;
		}
		return false;
	}

	function settingsProfileUpdate ($data) {

		// check if the user is logged in
		if($userId = $this->user->authCheck()){

			$result = array();
			$update = array();

			// DOB!!

			foreach($data as $k => $v){
				//if ($k!=='email'&&trim($v)!=='') $update[$k] = $v;
				if (trim($v)!=='') $update[$k] = $v;
			}

			/* decide what, if anything, to do with email - REMOVED pre-launch 2017 (gitlab #699)
			if (isset($data['email'])) {

				if ($this->globals->emailVerify($data['email']) === false) {

					$result['email'] = 'The email entered is invalid';

				} else {

					// check for existing email
					$this->db->select('*')->from('User')->where('email', $data['email']);
					$query = $this->db->get();

					if ($query->num_rows()>0) {
						$result['email'] = 'This email is already registered with Ramblur';
					} else $update['email'] = trim($data['email']);

				}

			}*/

			// OTHER DATA - Verify here - FORMAT - LENGTH?

			if(isset($update['firstname'])&&preg_match('/[^\p{L}\' -]/u', $update['firstname'])) $result['firstname'] = 'non-printable characters in your firstname';		    
			if(isset($update['lastname'])&&preg_match('/[^\p{L}\' -]/u', $update['lastname'])) $result['lastname'] = 'non-printable characters in your lastname';
			// Country & State are dropdowns?
			// Suburb/City & Postcode should be auto-gen dropdowns?

			if (empty($result)) { // no result, which means no errors

				$this->db->where('userId', $userId);
				$this->db->update('User', $update);

				$result['result'] = true;
			}
			return $result;
		}
		return false;
	}

	// is user 1 following user 2 in subject N?
	// implemented to add "following" flag to node info object to decide which icon to show
	function isFollowing($socialId, $subjectId) {

		// check if the user is logged in
		$userId = $this->user->authCheck();

		// if we have a user
		if ( $userId !== false ) {

			$query="SELECT * FROM UserToSocial AS uts WHERE userId = ? AND subjectId = ? AND socialId = ?;";
			$params = array($userId, $subjectId, $socialId);
			$query = $this->db->query($query, $params); 
			
			if($query->num_rows()==1){
				return '1';
			}
			return '0';

		}
		return '0';

	}

	function getUnreadMessagesCount() {

		// check if the user is logged in
		if($userId = $this->user->authCheck()) {

			$count = array();

			// MESSAGES - now comes from 'messages' method of message model
			$count['messages'] = $this->message->messages('unseen', true);

			return $count;

		}
		return false;
	}

	function getSearchChoice() {

		$userId = $this->user->authCheck();
		if ($userId === false) {
			return false;
		}
		$query = "SELECT searchChoice FROM User WHERE userId = ?";
		$params = array($userId); 
		
		if($query = $this->db->query($query, $params)){

			$result = $query->row_array();
			if (!isset($result['searchChoice'])) {
				return false;
			}
			$searchChoice = $result['searchChoice'];
			$statuses = array();

			$statuses['related'] = (decbin($searchChoice) & 1) ? 1 : 0;
			$statuses['business'] = (decbin($searchChoice >> 1) & 1) ? 1 : 0;
			$statuses['general'] = (decbin($searchChoice >> 2) & 1) ? 1 : 0;
			
			if (!$this->isUnder18($userId)) {
				$statuses['club18'] = (decbin($searchChoice >> 3) & 1) ? 1 : 0;
			}

			return $statuses;
			
		}
		return false;

	}

	function setSearchChoice($data) {

		$userId = $this->user->authCheck();

		if ($userId === false) {
			return false;
		}

		switch($data['forum']) {

			case "related":
				$forumInt = 1;
				break;

			case "business":
				$forumInt = 2;
				break;

			case "general":
				$forumInt = 4;
				break;

			case "club18":
				
				if ($this->isUnder18($userId)) {
					// Don't allow under 18 users to set club 18 values
					return false;
				}
				$forumInt = 8;
				break;		
			default:
				$forumInt = 0;
				break;

		}

		// NOT SURE HOW THE OPERATOR WILL GO BEING A STRING $operator = ($data['action'] === 'on') ? '+' : '-';

		if($data['action'] === 'on') {

			$query = "UPDATE User SET searchChoice = (searchChoice + ?)  WHERE userId = ?";

		} else {

			$query = "UPDATE User SET searchChoice = (searchChoice - ?)  WHERE userId = ?";

		}

		$params = array($forumInt, $userId); 
		
		if($query = $this->db->query($query, $params)){

			// also get updated statuses
			$statuses = $this->getSearchChoice();

			return $statuses;

		}
		return false;

	}
		
    // end additions cjt
        
    //find out if user has ad admin auth
    function isAdAdminUser($userId) {

        $this->db->select('UserId');
        $this->db->from('Permissions');
        $this->db->where('UserId', $userId)
                 ->where('adAdmin', 1);
        $query = $this->db->get();
        $auth = $query->row();

        if ($query->num_rows() > 0) {
            return ($auth);
        } else {
            return (NULL);
        }
    }
    
    /**
     * Check for $userId in the user table
     * @param type $userId
     * @return boolean
     */
    public function userIdExists($userId){
        $this->db->select('userId')
			->from('User')
			->where('userId', $userId);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        return false;        
    }
    
     /**
     * Check for $email in the db
     * @param type $email
     * @return boolean
     */
    public function emailExists($email) {
        if(trim($email) === ""){
            return false;
        }
        $this->db->select("username")->from('User')->where('email', $email);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        return false;
    }
	
	 /**     
     * Return $email from the db
     * @param type $userId
     * @return string
     */
    public function getUserEmail($userId): string {
		
		$authUserId = $this->user->authCheck();
		//if (!$authUserId || $authUserId !== $userId) {
		if (!$authUserId) {	
			return false;
		}

		$this->db->select("email")->from('User')->where('userId', $userId);
        $query = $this->db->get();
		
        if ($query->num_rows() > 0) {
            return $query->row()->email;
        }
        return null;
    }
	
	/**     
     * Return $email from the db
     * @param type $userId
     * @return string
     */
    public function getUserEmailBatch($userIds): array {
		
		$authUserId = $this->user->authCheck();
		//if (!$authUserId || $authUserId !== $userId) {
		if (!$authUserId) {	
			return false;
		}

		$this->db->select("email")->from('User')->where_in('userId', $userIds);
        $query = $this->db->get();
		
        if ($query->num_rows() > 0) {
            return $query->result_array();
        }
        return null;
    }
	
    /**     
     * Check for $username in the db
     * @param type $username
     * @return boolean
     */
    public function usernameExists($username) {
        if(trim($username) === ""){
            return false;
        }
        $this->db->select("username")->from('User')->where('userName', $username);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return true;
        }
        return false;
    }

    public function username_check($username) {
        if($this->usernameExists($username)){
            $this->form_validation->set_message('username_check', 'This username is already taken');
            return false;
        }
        return true;
    }
    
    /****************************** Social Login(FB,Twitter,google...HAUTH) ********************************/
	
	/*
	* get the user data from database by provider name and provider user id
	**/
	function create_new_hybridauth_user( $username,$mobile,$email,$hybridauth_session,$gender, $first_name, $last_name,$age,$DOB,$country,$region,$zip,$avatar, $provider_name, $provider_user_id )
	{
		try{
			$this->load->model('globals');
			$user_exists = $this->get_user_by_provider_and_id($provider_name, $provider_user_id );
			// if user didn't authenticate using the selected provider before,create a new entry on database.
			if (!$user_exists){

				// if a valid email was given
				if ($this->globals->emailVerify($email) !== false) {

					$verificationCode = $this->globals->uuid();
					
					// Call password_hash around here some place.
					$tmphash = password_hash(str_shuffle( "0123456789abcdefghijklmnoABCDEFGHIJ" ), PASSWORD_DEFAULT);
					/*convert country to country id */
					/*convert region to stateId */
					/*convert zip to areaCode */
					/*fetch and store avatar from avatar url($avatar) to ramblur folder as image*/
					$gender= mb_strtoupper($gender[0]); 
					if(!$DOB){$DOB=NULL;}
					if(!$age){$age='0';}
					if(!$region){$region=NULL;}
					if(!$avatar){$avatar=NULL;}

					$query = "CALL addUser(?,?,NULL,?,NULL,?,?,?,?,?,?,NULL,NULL,?,?,?,?,1,@newuserId,?,?);";
					$params = array($username,$mobile,$email,$tmphash,$gender, $first_name, $last_name,$age,$DOB,$country,$region,$zip,$verificationCode,$provider_name, $provider_user_id);

					$query = $this->db->query( $query, $params );

					if ( $query->num_rows() > 0 ) { 
						$result = $query->row(); 
						$newUserId = $result->newUserId;

						//verify user
						$this->verify(array('verificationCode' => $verificationCode));
						$this->welcomeUser($newUserId);
						return $newUserId;
					}
				}
			} else {
				$query1 = "UPDATE User SET userName = ?,country=? WHERE hybridauth_provider_name = ? AND hybridauth_provider_uid = ?;";
				$params = array($username,$country,$provider_name, $provider_user_id);
				$query1 = $this->db->query($query1,$params);
				if($this->db->affected_rows() === 1){
					return $user_exists[0]->userId;
				}
			}
		}
		catch(Exception $e) {
			//'user not added'
			console.log('error', 'model.User.create_new_hybridauth_user: '.$e);
		}
		return ;
	}
	
	 /**
	  * get the user session for application
	  * @todo - redo with new auth stuff
	  */
	function socialAuth(&$error) {
		try {
			$this->load->library('HybridAuthLib');

			$connectedProviders=$this->hybridauthlib->getConnectedProviders();
			foreach ($connectedProviders as $providers => $provider) {
				$service = $this->hybridauthlib->getAdapter($provider);
				$access_token = $service->getAccessToken();
				// add access token to session variables
				$_SESSION['access_token'] = $access_token['access_token'];
			}
			
			if ((isset($access_token) && $access_token !== null ) && $service->isUserConnected()) {
				$user_profile = $service->getUserProfile();
				$hybridauth_provider_uid=$user_profile->identifier;
				
				$query = $this->db->select(
					'u.userId,
					u.username,
					u.email,
					u.password,
					u.joinDate,
					p.verified,
					p.suspended,
					p.suspendedDate,
					p.deleted,
					u.lastLogon'
					)
					->where('u.hybridauth_provider_name', $provider)
					->where('u.hybridauth_provider_uid', $hybridauth_provider_uid)
					->limit(1)
					->order_by('u.userId', 'desc')
					->join('Permissions AS p', 'p.userId = u.userId')
					->get('User AS u');
				
				$params = array($provider, $hybridauth_provider_uid );

				if (!$query->num_rows() === 0) {
					$error = 'Login unsuccessful';
					return FALSE;
				}
				$result = $query->row();
				$userName = $result->username;
				
				if (intval($result->deleted) == 1) {
					$error = 'Login unsuccessful';
					return FALSE;
				}
				
				if (!$this->suspensionCheck($result, $error)) {
					return FALSE;
				}
				
				$this->set_session($result);
				$this->update_last_login($result->userId);
				$this->clear_login_attempts($userName);

				return TRUE;
			}
			
			$error = 'Login unsuccessful';
			
			session_write_close();
			return FALSE;
			
		} catch(Exception $e){
			console.log('error', 'model.User.socialAuth: '.$e);die();
		}
	} 
	 
	/*
	* get the user data from database by provider name and provider user id
	**/
	function get_user_by_provider_and_id( $provider_name, $provider_user_id )
	{
		$this->db->from('User')
			->where([
				'hybridauth_provider_name' => $provider_name,
				'hybridauth_provider_uid' => $provider_user_id
			]);
		$userResult = $this->db->get();
		if ($userResult->num_rows() === 0) {
			return false;
		}
		return $userResult->result();
	}

	/*
	http://stackoverflow.com/questions/36822735/publish-a-post-on-a-facebook-page-as-page-admin
	 */
	function update_socialStatus()
	{
		$this->load->library('HybridAuthLib');

		$service = $this->hybridauthlib->authenticate($provider);
		if ($service->isUserConnected())
		{
			// update user status for facebook
			$service->setUserStatus(
				array(
					"access_token" => $this->data['facebook_profile'][0]['access_token'],
				   "message" => "testing", // status or message content
				   "link"    => "www.google.com", // webpage link
				   "picture" => "", // a picture link
				    "name" => "Stack Overflow",
				     "caption" => "http://stackoverflow.com",
				     "description" => $this->input->post('postcontent')
				)
			);

			//$response=$adapter->getUserContacts();	var_dump($response);
		 // Post to user facebook wall, update user status
			/*  $response=$service->setUserStatus(
			    array(
			       "message" => "hi!!!!", // status or message content
			       "link"    => "www.test.com", // webpage link
			    ),"/me/feed"
			  );
			  var_dump($response);*/

			  $service->api()->api("/221320344636/feed", 'POST', $params );
			 // update user status for twitter
  			//$service->setUserStatus(array('mesage' => 'Your status text', 'picture' => 'path/to/picture.jpg'));
		}
	}
	/****************************** end Social Login(FB,Twitter,google...HAUTH) ********************************/
    
	/** IS USER SUB'D TO A FORUM? */

	function isForumSubscribed ($subjectId) {

		//print_r($data);
		if ($userId = $this->user->authCheck()) {

			// if MAIN FEED -> get that subjectId (ie not 0 as passed!)
			// do this also for Top 500 forums??? ---------------------------------------------->>> test
			if($subjectId==0) {

				$this->db->select('subjectId')
					->from('Subject')
					->where([
						'subjectName' => 'main feed',
						'type' => group::TYPE_RAMBLUR
					]);
				$subject = $this->db->get();

				$subjectId = $subject->first_row('array')["subjectId"];

			}

			$this->db->from('UserToSubject AS u')
				->join("Subject AS s", 's.subjectId = u.subjectId')
				->where([
					'u.userId' => $userId,
					'u.subjectId' => $subjectId,
					'u.active' => 1
				])
				->where('IF(s.private = 1, u.privateAccess, 1) = 1', NULL, FALSE);
			$query = $this->db->get();
			return ($query->num_rows()===1) ? '1' : '0';

		}
		return '0';

	}


	/** INVITE A FRIEND **/

	function sendFriendInvites($emails, $message, $inviteFromName, $inviteFromEmail) {
		
		$userId = $this->user->authCheck();
		if (!$userId) {
			return false;
		}
		
		$safeEmails = [];
		foreach ($emails as $email) {
			if (is_array($email)) {
				if (!isset($email['email'])) {
					continue;
				}
				$email = $email['email'];
				$toName = $email['name'];
			} else {
				$email = $email;
				$toName = '';
			}
			if ($this->globals->emailVerify($email) === false) {
				continue;
			}
			$safeEmails[$email]=$toName;				
		}
		if(empty($safeEmails)){
			return false;
		}
		$fromName = $this->getUserName($userId);
		$this->load->model("email");
		return $this->email->sendSiteInvite($safeEmails, $message, $fromName, $inviteFromName, $inviteFromEmail);		
		
	}
	
	function sendFeedback($title, $description)
	{
		// check if the user is logged in
		$userId = $this->user->authCheck();
		
		if ( $userId === false ) {
			return false;
		}
		if($title !== false) {
			$fields = array(
				'title' => $title,
				'userId' => $userId
			);
			$this->db->from('userfeedback')
				->where($fields);
			$existing = $this->db->get();
			if($existing->num_rows() > 0) {//check if user already suggested this
				$message = "Thanks ! You have already suggested this!";
				//return false;
			}
			else
			{
			  $this->load->helper(array('htmlpurifier'));
				$this->db->insert('userfeedback',
					array(
					      'title' => html_purify($title,'post'),
						'userId' => $userId,
					      'feedback' => html_purify($description,'post')
				));
				$message = "Thanks ! Your suggestion has been successfully submitted!";
				//return true;
			}
			return  $message;
		} else {
			return false;
		}
	}
	/* BEGIN Device based "mini" forum settings update */

	function updateForumSettingsDevice($data) {

		//print_r($data);
		$userId = $this->user->authCheck();
		if($userId && filter_var($data['forum'],FILTER_VALIDATE_INT)) {

			/* Make sure theme is set to valid values
			$this->db->select('id, value')
				->from('ThemeColourOptions')
				->where('value', $data['theme']);
			$themeOption = $this->db->get();
			if ($themeOption->num_rows() > 0) {
				$theme = $themeOption->first_row('array');
			} else {
				$theme =  $this->group->getDefaultThemeColourOption();
			}*/

		        $this->load->helper('colourvalidate');
			if(!colourvalidate('#'.$data['pricol'])|| !colourvalidate('#'.$data['seccol'])){
			  return false;
			}
			$update = array(
					'primaryColour' => '#'.$data['pricol'],
					'primaryThemeFontOptionId' => $data['pricolfont'],
					'secondaryColour' => '#'.$data['seccol'],
					'secondaryThemeFontOptionId' => $data['seccolfont'],
					'opacity' => $data['opacity'],
				        'themeColourOptionId' => $data['theme']	   
			);

			$this->db->where(array('userId' => $userId, 'subjectId' => $data['forum']));
			$this->db->update('UserToSubject', $update);

			return true;

		}
		return false;

	}

	/** BEGIN MAPS **/
	function addMapMarker($data) {

		$userId = $this->user->authCheck();
		if ($userId===false) {
			return false;
		}

		$pinData = array(
			'lat' => $data['lat'],
			'lng' => $data['lng'],
			'userId' =>  $userId,
			'nodeId' => $data['nodeId'],
			'subjectId' => $data['subjectId']
		);

		$this->db->insert('UserToMapMarker', $pinData); 

		if ($this->db->affected_rows() === 0) {
			return false;
		}

		$return = array();

		$return['popupId'] = $data['popupId'];
		$return['id'] = $this->db->insert_id(); // is this adequately reliable??? cjt
		$return['nodeId'] = $data['nodeId'];
		
		return $return;

	}

	function getMapMarkers($subjectId) {

		// TODO: Add a limit?

		$userId = $this->user->authCheck();
	
		// userId validity not related to query
		if($userId) {

			// A LIMIT WILL NEED TO BE ADDED TO THIS
			//	- either by bounding (?) js side or a query LIMIT

			// if Home, first get subscribed forums and then get markers from all of them
			if($subjectId==1) {

       			//if($sql = $this->db->select('Subject.subjectId')->from('Subject')->join('UserToSubject', 'Subject.subjectId = UserToSubject.subjectId')->where('(active = true || grouped = true)')->where('(Subject.private = false OR UserToSubject.privateAccess = true)')->where('displayed', true)->where('userId', $userId)) {

				$query = "SELECT Subject.subjectId
						FROM Subject
						JOIN UserToSubject ON Subject.subjectId = UserToSubject.subjectId
						WHERE (active = true || grouped = true) AND (Subject.private = false OR UserToSubject.privateAccess = true)
						AND displayed = TRUE
						AND userId = ?;";
				$params = array($userId); 
				
				if($query = $this->db->query($query, $params)){

					$result = $query->result_array();

					$forums = array();

					foreach($result as $forum) {

						if(intval($forum['subjectId'])!==1) array_push($forums, $forum['subjectId']);

					}
					if(count($forums)>0) {
						/*if($query = $this->db->select('*')->from('UserToMapMarker')->where_in('subjectId', $fora)) {

				            $markers = $query->result_array();
				            return $markers;

						}*/

						$sql = "SELECT *
								FROM UserToMapMarker
								WHERE subjectId IN (".implode(',', $forums).")
								AND UserToMapMarker.nodeId not in (select nodeId from Nodes where draftNode = 1 and draftNodeVisible = 0 and Nodes.userId != $userId)
								AND UserToMapMarker.nodeId NOT IN (SELECT n.nodeId FROM Nodes n JOIN Post p ON p.postId = n.postId WHERE p.modDeleted = 1)";
						
						if($sql = $this->db->query($sql)){

				            $markers = $sql->result_array();
				            //print_r($markers);
				            return $markers;

						}
				        return false;	

					}
					return false;
					
				}
				return false;

       		// specific forum markers
			} else {

				
				$sql = "SELECT *
					FROM UserToMapMarker
				WHERE subjectId = $subjectId
				AND UserToMapMarker.nodeId not in (select nodeId from Nodes where draftNode = 1 and draftNodeVisible = 0 and Nodes.userId != $userId)
				AND UserToMapMarker.nodeId NOT IN (SELECT n.nodeId FROM Nodes n JOIN Post p ON p.postId = n.postId WHERE p.modDeleted = 1)";
					
				//if($sql = $this->db->get_where('UserToMapMarker', array('subjectId' => $subjectId))) {
				//if($sql = $this->db->get_where('UserToMapMarker', array('subjectId' => $subjectId))) {
				if($sql = $this->db->query($sql)) {

		            $markers = $sql->result_array();
		            return $markers;

				}
		        return false;

			}


    	}
    	return false;

	}

	/** END MAPS **/

	function getBackgroundOpacity($data) {
		$this->load->helper(array('ramblurfiltervar'));
		if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false) === false) {
			return false;
		}
		$userId = $this->authCheck();
		if (!$userId) {
			return NULL;
		}
		
		$this->db->select('workareaBackgroundOpacity');
		$this->db->from('User');
		$this->db->where('userId', $userId);
		$this->db->where('userId', $data["userId"]);
		$query = $this->db->get();
		$user = $query->result_array();

		if ($query->num_rows() > 0) {
			return ($user[0]);
		} else {
			return (NULL);
		}

	}
	
	function setBackgroundOpacity($val) {
		
		$userId = $this->authCheck();
		if (!$userId) {
			return false;
		}
		
		$this->db->set('workareaBackgroundOpacity', $val["val"]);
		$this->db->where('userId', $userId);
		$this->db->update('User');
	}

	function submitDeviceErrorReport($data) {

		//print_r($data);

		if ($user = $this->authCheck()) {

			$this->load->model('jira_issue', 'issue');
			// Already sanitised in Jira_issue.php
			$result = $this->issue->createIssue($data["summary"], $data["description"], $user, NULL);

			return ($result !== null) ? true : false;
		}
		return false;
	}
	
	/**
	 *  It's useful to put the possible statuses
	 * @param type $userId
	 * @param type $status
	 * @return boolean
	 */
	function updateHelpBubbleStatus($userId, $status) {
		
		$user = $this->authCheck();
		
		if ($user && $user === $userId) {
			
			$this->db->set('helpBubblesShown', $status);
			$this->db->where('userId', $userId);
			$this->db->update('User');
			return true;
		}
		return false;
	}
	
	
	function isDraftUser($userId) {

		$user = $this->authCheck();

		if ($user && $user === $userId) {
			$this->db->select('userId')
					->from('User')
					->where('userId', $userId)
					->where('userType', 1);
			if ($this->db->get()->num_rows() > 0) {
				return true;
			}
			return false;
		}
	}
	
	function isDraftMetaUser($userId) {

		$user = $this->authCheck();

		if ($user && $user === $userId) {
			$this->db->select('userId')
					->from('draftMetaUsers')
					->where('userId', $userId);
			if ($this->db->get()->num_rows() > 0) {
				return true;
			}
		}
		return false;
	}
	
	function isDraftMetaUserSelected($userId) {

		$user = $this->authCheck();

		if ($user) {
			$this->db->select('userId')
					->from('draftMetaUsers')
					->where('userId', $userId);
			if ($this->db->get()->num_rows() > 0) {
				return true;
			}
		}
		return false;
	}
	
	function addDraftUser($metaUserId, $draftUserId) {
		
		if (!is_cli() && !$this->authCheck()) {
			return false;
		}

		$draftUserValues = array(
				'userId' => $metaUserId, 
				'draftUserId' => $draftUserId
			);
			
			$this->db->insert('draftUserMapping', $draftUserValues);	
			
			if($this->db->affected_rows()) {
				return TRUE;
			} else { 
				return FALSE;
			}
	
	}
	
	function updateUserType($userName, $userType) {
		
		if (!is_cli() && !$this->adminCheck()) {
			return false;
		}

		$userId = $this->getUserId($userName);
		
		$this->db->set('userType', $userType)
				->where('userId', $userId)
				->update('User');
		
		return $this->db->affected_rows();
	
	}
	
	function setWideView($userId, $wideView) {

		if (!$this->canManageUser($this->user->authCheck(), $userId)) {
			return false;
		}

		$this->db->select('id')
				->from('UserToWideView')
				->where('userId', $userId);

		if ($this->db->get()->num_rows() > 0) {
			$this->db->set('id', $wideView)
					->where('userId', $userId)
					->update('UserToWideView');
		} else {
			$this->db->insert('UserToWideView', array('userId' => $userId, 'id' => $wideView));
		}
		return true;
	}

	/**
	 * Returns the users wide view setting
	 * @param type $userId
	 * @return boolean
	 */
	function isUserInWideView($userId) {
		if (!$this->user->canManageUser($this->user->authCheck(), $userId)) {
			return false;
		}

		$this->db->select('id')
				->from('UserToWideView')
				->where('userId', $userId)
				->where('id', true);

		$q = $this->db->get();
		if ($q->num_rows() > 0) {
			return true;
		}
		return false;
	}
	
	function getTotalNotificationCount() {
		
		$userId = $this->user->authCheck();
		if (!$userId) {
			return false;
		}
		
		// retrieve all posts user has subscribed to
		$this->db->select("COUNT(p.postId) AS unreadThreadCount")
			->from('UserToPost AS u')
			->join('Post AS p', 'p.postId = u.postId')
			->join('Nodes AS n', 'n.postId = p.postId')
			->join('UserToSubject AS us', 'us.subjectId = p.subjectId AND us.userId = u.userId')
			->join('Subject AS s', 's.subjectId = p.subjectId')
			->join('ThemeColourOptions AS t', 't.id = us.themeColourOptionId')
			->join('ThemeFontOptions AS fp', 'fp.id = us.primaryThemeFontOptionId')
			->join('ThemeFontOptions AS fs', 'fs.id = us.secondaryThemeFontOptionId')
			->where([
				'u.userId' => $userId,
				'n.level' => 0,
				'p.visible' => 1,
				'p.modDeleted' => 0,
				'u.viewed' => 0
			])
				//->where("n.nodeType != 'rootNode'")
				->where('(us.privateAccess = 1 OR s.private = 0)');


		$query = $this->db->get();
		if ($query->num_rows() > 0) {
			return $query->row()->unreadThreadCount;
		}
		return false;
	}
	
	function setForumMode($userId, $forumMode, $copyOnly = false) {
		
		if(!$this->user->authCheck()) {
			return false;
		}
		
		$this->db->select('subjectId')
				->select('forumThemeSet')
				->select('primaryColour')
				->select('secondaryColour')
				->select('themeColourOptionId')
				->select('primaryThemeFontOptionId')
				->select('secondaryThemeFontOptionId')
				->select('opacity')
				->from('UserToSubject')
				->where('userId', $userId);
		$forums = $this->db->get();
		if ($forums->num_rows() > 0) {
			//if not forum theme set, copy colors
			foreach ($forums->result_array() as $forum) {
				if (!$forum["forumThemeSet"]) {
					$this->copyOriginalForumTheme($userId, 
							$forum["subjectId"], 
							$forum["primaryColour"], 
							$forum["secondaryColour"],
							$forum["themeColourOptionId"],
							$forum["primaryThemeFontOptionId"],
							$forum["secondaryThemeFontOptionId"],
							$forum["opacity"]
							);
				}
			}
			
			if($copyOnly) {
				return true;
			}
			
			if ($forumMode === "nightMode") {
				$this->setForumTheme($userId, $forumMode, '#818181', '#697389', 2);
			} else if ($forumMode === "defaultMode") {
				$this->setForumTheme($userId, $forumMode, '#787E87', '#738DA7', 1);
			} else if ($forumMode === "wallpaper") {
				$this->setWallpaperForumTheme($userId,$forumMode);
			}
		}
		return true;
	}
	
	function copyOriginalForumTheme($userId, 
			$forumId, 
			$primaryColour, 
			$secondaryColour, 
			$themeColourOptionId, 
			$primaryThemeFontOptionId,
			$secondaryThemeFontOptionId,
			$opacity
			) {
		if(!$this->user->authCheck()) {
			return false;
		}
		$this->db->set('primaryColourOriginal', $primaryColour)
					->set('secondaryColourOriginal', $secondaryColour)
					->set('forumThemeSet', 1)
					->set('themeColourOptionIdOriginal', $themeColourOptionId)
					->set('primaryThemeFontOptionIdOriginal', $primaryThemeFontOptionId)
					->set('secondaryThemeFontOptionIdOriginal', $secondaryThemeFontOptionId)
					->set('opacityOriginal', $opacity)
					->where('userId', $userId)
					->where('subjectId', $forumId)
					->update('UserToSubject');
		
	}

	function setForumTheme($userId, $forumMode, $primaryColour, $secondaryColour, $lightOrDark) {
		$this->db->set('forumTheme', $forumMode)
				->set('primaryColour', $primaryColour)
				->set('secondaryColour', $secondaryColour)
				->set('primaryThemeFontOptionId', 1)
				->set('themeColourOptionId', $lightOrDark)
				->set('secondaryThemeFontOptionId', 1)
				->set('opacity', 100)
				->where('userId', $userId)
				->where('subjectId !=', 714)
				->update('UserToSubject');
	}

	function _setEachWallpaperForumTheme($userId, 
			$forumId, 
			$forumMode, 
			$primaryColour, 
			$secondaryColour, 
			$lightOrDark, 
			$primaryThemeFontOptionId, 
			$secondaryThemeFontOptionId,
			$opacity
			) {
		$this->db->set('forumTheme', $forumMode)
					->set('primaryColour', $primaryColour)
					->set('secondaryColour', $secondaryColour)
					->set('themeColourOptionId', $lightOrDark)
					->set('primaryThemeFontOptionId', $primaryThemeFontOptionId)
					->set('secondaryThemeFontOptionId', $secondaryThemeFontOptionId)
					->set('opacity', $opacity)
					->where('userId', $userId)
					->where('subjectId', $forumId)
					->where('subjectId !=', 714)
					->update('UserToSubject');
	}
	
	function setWallpaperForumTheme($userId, $forumMode) {
		$this->db->select('subjectId')
				->select('primaryColourOriginal')
				->select('secondaryColourOriginal')
				->select('themeColourOptionIdOriginal')
				->select('primaryThemeFontOptionIdOriginal')
				->select('secondaryThemeFontOptionIdOriginal')
				->select('opacityOriginal')
				->from('UserToSubject')
				->where('userId', $userId)
				->where('subjectId !=', 714);
		$forums = $this->db->get();
		if ($forums->num_rows() > 0) {
			foreach ($forums->result_array() as $forum) {
				$this->db->set('forumTheme', $forumMode)
					->set('forumThemeSet', 1)
					->set('primaryColour', $forum["primaryColourOriginal"])
					->set('secondaryColour', $forum["secondaryColourOriginal"])
					->set('themeColourOptionId', $forum["themeColourOptionIdOriginal"])
					->set('primaryThemeFontOptionId', $forum["primaryThemeFontOptionIdOriginal"])
					->set('secondaryThemeFontOptionId', $forum["secondaryThemeFontOptionIdOriginal"])
					->set('opacity', $forum["opacityOriginal"])
					->where('userId', $userId)
					->where('subjectId', $forum["subjectId"])
					->where('subjectId !=', 714)	
					->update('UserToSubject');
			}
		}
	//	$this->db->set('forumTheme', $forumMode)
				//	->set('forumThemeSet', 1) ////////////////////////////////////////////**&**
				//	->where('userId', $userId)
				//	->update('UserToSubject');
	}
	
	function getWallpaperModeSettings($userId, $subjectId) {
		if(!$this->user->authCheck()) {
			return false;
		}
		$this->db->select('subjectId')
				->select('forumThemeSet')
				->select('primaryColourOriginal')
				->select('secondaryColourOriginal')
				->select('themeColourOptionIdOriginal')
				->select('primaryThemeFontOptionIdOriginal')
				->select('secondaryThemeFontOptionIdOriginal')
				->select('opacityOriginal')
				->from('UserToSubject')
				->where('userId', $userId)
				->where('subjectId', $subjectId);;
		$forums = $this->db->get();
		if ($forums->num_rows() > 0) {
			return $forums->result_array()[0];
		}
		return false;
	}

}
