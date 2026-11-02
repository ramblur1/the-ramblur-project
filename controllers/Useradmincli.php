<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

/**
 * Rough and ready CLI class that allows various things to be run directly from the command line
 */
class UserAdminCli extends CI_Controller {

	public function __construct() {
		parent::__construct();

		if (!is_cli()) {
			return false;
		}

		$this->load->helper('date_utils');

		$this->load->model('globals');
		$this->load->helper('email');
		$this->load->model('geo');
		$this->load->model('subject');
		$this->load->model('user');
		$this->load->library("DataValidatorResult");
	}

	/**
	 * Create and verify a user directly from the command line
	 * Email must be urlencoded to get around CI's character whitelist
	 * If a postcode is supplied a random suburb from that postcode will be looked up and applied
	 * 
	 * 
	 * @param type $username
	 * @param type $password
	 * @param type $email
	 * @param type $dob
	 * @param type $countryISO2
	 * @param type $stateName
	 * @param type $postcode
	 * @return boolean
	 */
	public function createUser($username = '', $password = '', $email = '', $dob = '', $countryISO2 = '', $stateName = '', $postcode = false) {

		if (!is_cli()) {
			return false;
		}

		$output = "Creating user with details:\n";
		$output .= "Username: $username\n";
		$output .= "Password: $password\n";
		$output .= "Email: $email\n";
		$output .= "DOB: $dob\n";
		$output .= "ISO2: $countryISO2\n";
		$output .= "Postcode: $postcode\n";

		// Validate the given data
		$email = urldecode($email);
		$userValidationResult = $this->_validateUserDetails($username, $password, $email, $dob);
		$addressValidationResult = $this->_validateAddressDetails($countryISO2, $stateName, $postcode);
		if ($userValidationResult->isInvalid() || $addressValidationResult->isInvalid()) {
			$output .= "------- FAILED -------\n";
			$output .= "User Validation\n";
			$output .= "\t\t$userValidationResult\n";
			$output .= "Address Validation\n";
			$output .= "\n\n$addressValidationResult\n";
			//echo $output;
			echo ($this->load->view('cli/cliOutput', ['output' => $output], TRUE));
			exit(1);
		}

		$realDob = convert_date_to_ymd($dob);

		// The user->create function inexplicably requires an arbitrary array of data, build it
		$postData = array();
		$postData['userName'] = $username;
		$postData['password'] = $password;
		$postData['userJoinDOB'] = $realDob;
		$postData['email'] = $email;
		$postData['country'] = $countryISO2;

		//Create the user
		if (!$userId = $this->user->create($postData)) {
			$output .= "Could not create user: $username";
			echo ($this->load->view('cli/cliOutput', ['output' => $output], TRUE));
			exit(1);
		}

		//Verify the user
		if (!$this->user->verifyUserById($userId)) {
			$output .= "Could not verify user!: $userId=>$username";
		}

		$country = $this->geo->getCountryByIso2Code($countryISO2);

		if ($stateName) {
			$state = $this->geo->getBestStateByCountryIdAndStateName($country->countryId, $stateName);
			$stateId = $state->stateId;
		}
		if ($postcode) {
			$suburb = $this->geo->getRandomSuburbByPostcode($countryISO2, $postcode);
			$suburbName = $suburb['placeName'];
		}

		if (!$this->user->updateUserInfo($userId, ["country" => $country->countryId, "state" => $stateId, "postcode" => $postcode, "suburb" => $suburbName])) {
			$output .= "Could not save user profile data!";
			echo ($this->load->view('cli/cliOutput', ['output' => $output], TRUE));
			exit(1);
		}
		echo ($this->load->view('cli/cliOutput', ['output' => $output], TRUE));
		exit(0);
	}

	/**
	 * Sets the users gender
	 * @param type $userName
	 * @param type $gender
	 */
	public function setUserGender($userName, $gender) {

		if (!is_cli()) {
			return false;
		}

		$usernameValidationResult = $this->_validateUserName($userName);
		if ($usernameValidationResult->isInvalid()) {
			echo ($this->load->view('cli/cliOutput', ['output' => $usernameValidationResult], TRUE));
			exit(1);
		}

		$userId = $this->user->getUserId($userName);
		
		if ($this->user->updateUserInfo($userId, ["gender" => $gender])) {
			exit(0);
		}
		echo ($this->load->view('cli/cliOutput', ['output' => "Could not update the user gender for user: $userName\n"], TRUE));

		exit(1);
	}

	/**
	 * Adds a forum to a user
	 * @param type $userName
	 * @param type $forumName
	 */
	public function addForumToUser($userName, $forumName) {

		if (!is_cli()) {
			return false;
		}

		//We can't pass in special chars from the CLI, so spaces are replaced 
		//by underscores, then re-replaced here.
		$repairedforumName = str_replace("_", " ", $forumName);

		$usernameValidationResult = $this->_validateUserName($userName);
		if ($usernameValidationResult->isInvalid()) {
			echo ($this->load->view('cli/cliOutput', ['output' => $usernameValidationResult], TRUE));
			exit(1);
		}

		$userId = $this->user->getUserId($userName);

		$subject = $this->subject->get_subjectid_from_subjectName($repairedforumName);
		if (!empty($subject)) {
			$subjectId = $subject[0]['subjectId'];
		}

		if (!$subjectId) {
			echo ($this->load->view('cli/cliOutput', ['output' => "Could not get subject id for subject name: $repairedforumName"], TRUE));
			exit(1);
		}

		if ($this->user->addUserToSubject($userId, $subjectId, true) <= 0) {
			echo ($this->load->view('cli/cliOutput', ['output' => "Could not add the subject name to the user: $userName - $repairedforumName"], TRUE));
			exit(1);
		}

		exit(0);
	}

	/**
	 * Turns of the tutorial buttons for a user
	 * @param type $userName
	 * @return boolean
	 */
	public function turnOffUserBubbles($userName) {

		if (!is_cli()) {
			return false;
		}

		$usernameValidationResult = $this->_validateUserName($userName);
		if ($usernameValidationResult->isInvalid()) {
			echo ($this->load->view('cli/cliOutput', ['output' => $usernameValidationResult], TRUE));
			exit(1);
		}

		$userId = $this->user->getUserId($userName);

		if ($this->user->updateUserInfo($userId, ["helpBubblesShown" => 1])) {
			exit(0);
		}
		echo ($this->load->view('cli/cliOutput', ['output' => "Could not update the user bubble status for user: $userName\n"], TRUE));
		exit(1);
	}

	/**
	 * Sets the user as a draft user
	 * @param type $userName
	 * @return boolean
	 */
	public function setUserToDraftUser($userName) {

		if (!is_cli()) {
			return false;
		}

		$usernameValidationResult = $this->_validateUserName($userName);
		if ($usernameValidationResult->isInvalid()) {
			echo ($this->load->view('cli/cliOutput', ['output' => $usernameValidationResult], TRUE));
			exit(1);
		}

		$userId = $this->user->getUserId($userName);

		if ($this->user->updateUserType($userId, 1)) {
			exit(0);
		}
		echo ($this->load->view('cli/cliOutput', ['output' => "Could not update the user type to '1' (draft) for user: $userName\n"], TRUE));
		exit(1);
	}

	/**
	 * Sets the users joindate
	 * @param type $userName
	 * @param type $timestamp unixtimestamp
	 * @return boolean
	 */
	public function setJoinDate($userName, $timeStamp) {

		if (!is_cli()) {
			return false;
		}

		$usernameValidationResult = $this->_validateUserName($userName);
		if ($usernameValidationResult->isInvalid()) {
			echo ($this->load->view('cli/cliOutput', ['output' => $usernameValidationResult], TRUE));
			exit(1);
		}

		$userId = $this->user->getUserId($userName);
		$date = convert_timestamp_to_ymdhis($timeStamp);
		if ($date) {
			if ($this->user->updateUserInfo($userId, ["joinDate" => $date])) {
				exit(0);
			}
		}

		echo ($this->load->view('cli/cliOutput', ['output' => "Could not update the user join date for user: $userName, date given was: " . $timeStamp], TRUE));
		exit(1);
	}

	/**
	 * Sets the user as a draft user
	 * @param type $userName
	 * @return boolean
	 */
	public function addDraftUserToMetaUser($draftUserName, $metaUserName) {

		if (!is_cli()) {
			return false;
		}

		$draftUsernameValidationResult = $this->_validateUserName($draftUserName);
		if ($draftUsernameValidationResult->isInvalid()) {
			echo ($this->load->view('cli/cliOutput', ['output' => $draftUsernameValidationResult], TRUE));
			exit(1);
		}

		$metaUsernameValidationResult = $this->_validateUserName($metaUserName);
		if ($metaUsernameValidationResult->isInvalid()) {
			echo ($this->load->view('cli/cliOutput', ['output' => $metaUsernameValidationResult], TRUE));
			exit(1);
		}

		$draftUserId = $this->user->getUserId($draftUserName);
		$metaUserId = $this->user->getUserId($metaUserName);

		if ($this->user->addDraftUser($metaUserId, $draftUserId)) {
			exit(0);
		}

		echo ($this->load->view('cli/cliOutput', ['output' => "Could not add the user $draftUserName to the meta user: $metaUserName"], TRUE));
		exit(1);
	}

	/**
	 * Validates a given usernames existence
	 * @param type $userName
	 * @return \DataValidatorResult
	 */
	private function _validateUserName($userName): DataValidatorResult {
		$validation = new DataValidatorResult("Username Validator");

		if (trim($userName) === "") {
			$validation->addMessage("No username provided")
					->setValid(false);
		}

		if (!$this->user->usernameExists($userName)) {
			$validation->addMessage("Username does not exist: $userName")
					->setValid(false);
		}

		if (!$userId = $this->user->getUserId($userName)) {
			$validation->addMessage("A user id does not exist for: $userName")
					->setValid(false);
		}

		return $validation;
	}

	/**
	 * Validate data for user creation, there should be classes for this stuff
	 * @param type $username
	 * @param type $password
	 * @param type $email
	 * @param type $dob
	 * @param type $realDob
	 * @param type $countryISO2
	 * @return boolean
	 */
	private function _validateUserDetails($username, $password, $email, $dob): DataValidatorResult {

		$validation = new DataValidatorResult();
		if ($this->user->usernameExists($username)) {
			$validation->addMessage("Username is already taken: $username")
					->setValid(false);
		}

		if ($this->user->emailExists($email)) {
			$validation->addMessage("Email address already exists: $email")
					->setValid(false);
		}

		if (!valid_email($email)) {
			$validation->addMessage("Email address is invalid: $email")
					->setValid(false);
		}

		if (!preg_match('/^[\p{L}\p{N}_.-]+$/u', $username) || strlen($username) > 64) {
			$validation->addMessage("Username validation failed: $username")
					->setValid(false);
		}

		if (strlen($password) < 6) {
			$validation->addMessage("Password too short")
					->setValid(false);
		}

		if (!convert_date_to_ymd($dob)) {
			$validation->addMessage("Could not convert given DOB to a system dob - check the format of your date: $dob")
					->setValid(false);
		}

		return $validation;
	}

	private function _validateAddressDetails($countryISO2, $stateName, $postcode = false): DataValidatorResult {

		$validation = new DataValidatorResult();
		$country = $this->geo->getCountryByIso2Code($countryISO2);

		if (strlen($countryISO2) !== 2 || !$country) {
			$validation->addMessage("Country code is invalid: $countryISO2")
					->setValid(false);
		}

		$state = $this->geo->getBestStateByCountryIdAndStateName($country->countryId, $stateName);
		if (!$state) {
			$validation->addMessage("State is invalid: $stateName")
					->setValid(false);
		}

		if ($postcode) {
			$suburbs = $this->geo->getSuburbsByPostcode($countryISO2, $postcode);
			if (empty($suburbs)) {
				$validation->addMessage("The given postcode '$postcode' returned no suburbs")
						->setValid(false);
			}
		}
		return $validation;
	}

}
