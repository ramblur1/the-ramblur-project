<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Handles reporting and retrieval of Ramblur error reporting through 
 * the JIRA REST API.
 * 
 * @author katharyn
 */
class Jira_issue extends CI_Model {
	
	const REQUEST_GET = "GET";
	const REQUEST_POST = "POST";
	const REQUEST_PUT = "PUT";
	const REQUEST_DELETE = "DELETE";
	
	function __construct() {
		$this->load->config('jira');
		// Call the Model constructor
		parent::__construct();
	}
	
	private function getBaseUri() {
		return $this->config->item('jira_host').'/rest/api/latest';
	}
	
	/**
	 * Creates a system error report in the relevant JIRA project.
	 * 
	 * @param string $summary
	 * @param string $description
	 * @param integer $userId
	 * @return stdClass
	 */
	function createIssue($userEmail, $summary, $description, $device, $browser, $os, $userId, $file = null, $issueType = 'bug') {

		$this->load->model('user');

	    $this->load->helper(array('htmlpurifier'));

		$data = new stdClass();
		$fields = new stdClass();
		$username = $this->user->getUserName($userId);
		$fields->project = (object)array("id" => $this->config->item('jira_project_id'));
		$fields->summary = html_purify($summary,'post');
		$fields->issuetype = $issueType === 'feedback' ? (object)array("id" => $this->config->item('jira_issue_type_feedback')) : (object)array("id" => $this->config->item('jira_issue_type_bug'));

		if ($issueType === 'bug') {
			$fields->description = 'Reported by: ' . html_purify($username, 'post')
					. PHP_EOL . 'User Email: ' . html_purify($userEmail, 'post')
					. PHP_EOL . 'Description: ' . html_purify($description, 'post')
					. PHP_EOL . 'Device: ' . html_purify($device, 'post')
					. PHP_EOL . 'Browser: ' . html_purify($browser, 'post')
					. PHP_EOL . 'Operating System: ' . html_purify($os, 'post');
		} else if ($issueType === 'feedback') {
			$fields->description = 'Reported by: ' . html_purify($username, 'post')
					. PHP_EOL . 'User Email: ' . html_purify($userEmail, 'post')
					. PHP_EOL . 'Description: ' . html_purify($description, 'post');
		}

		$data->fields = $fields;

		$dataString = json_encode($data);
		$curl = $this->initialiseCurl(self::REQUEST_POST, 'issue');
		
		curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . mb_strlen($dataString))
		);
		$result = $this->send($curl);
		if(!$result) {
			return false;
		}
		$issue = $this->processIssuePayload($result);
		
		if($issueType === 'bug') { //don't log feedback here
			$this->db->insert('UserToIssue', array('userId'=>$userId, 'issueId'=>$issue["key"]));
		}
		
		// @todo - find a way to not fail silently if the file fails to attach properly
		if (!is_null($file)) {
			$fileCurl = $this->initialiseCurl(self::REQUEST_POST, 'issue/'.$issue["key"].'/attachments');
			$fileData = [];
			$fileInfo =  new finfo(FILEINFO_MIME_TYPE);
			$fileData['file'] = new CURLFile($file['tmp_name'], $fileInfo->file($file['tmp_name']), $file['name']);
			curl_setopt($fileCurl, CURLOPT_POSTFIELDS, $fileData);
			curl_setopt($fileCurl, CURLOPT_HTTPHEADER, array("X-Atlassian-Token:nocheck"));
			$this->send($fileCurl);
		}
		
		return $issue;
	}
	
	/**
	 * Retrieves an issue from JIRA, returning the parsed object.
	 * @param string $issueKey
	 * @return stdClass
	 */
	function getIssue($issueKey) {
		$curl = $this->initialiseCurl(self::REQUEST_GET, 'issue/'.$issueKey);
		$issue = $this->send($curl);
		if(!$issue) {
			return false;
		}
		return $this->processIssuePayload($issue);
	}
	
	/**
	 * Retrieves all issues related to the user from JIRA, returning the parsed objects.
	 * @param string $userId
	 * @return stdClass
	 */
	function getIssuesByUser($userId) {
		$this->db->select('issueId')
			->from('UserToIssue')
			->where('userId', $userId);
		$userIssues = $this->db->get();
		if($userIssues->num_rows() > 0) {
			$ids = array();
			foreach($userIssues->result_array() as $row) {
				$ids[] = $row["issueId"];
			}

			// Possibly change to POST, since query could be very large
			$curl = $this->initialiseCurl(self::REQUEST_GET, $this->getSearchString($ids));
			$issues = $this->send($curl);
			if(!$issues) {
				return false;
			}
			return $this->processIssuesPayload($issues);
		} else {
			return false;
		}
	}
	
	/**
	 * Finds all unresolved issues reported by ramblur users
	 * @return array|boolean
	 */
	function getAllUnresolvedIssues() {
		// retrieve all unresolved issues.
		// status = active, progress name = todo or in progress
		// parse and return
		
	}
	
	/**
	 * Finds all issues which are resolved in JIRA, but yet to be marked as resolved in zenure db.
	 * 
	 * @return boolean|stdClass
	 */
	function getUnmarkedResolvedIssues() {
		$this->db->from('UserToIssue')
			->where('resolved', 0);
		$unmarkedIssues = $this->db->get();
		if($unmarkedIssues->num_rows() > 0) {
			$ids = array();
			foreach($unmarkedIssues->result_array() as $row) {
				$ids[] = $row["issueId"];
			}
			$curl = $this->initialiseCurl(self::REQUEST_GET, $this->getSearchString($ids, '%20and%20status=done'));
			$issues = $this->send($curl);
			if(!$issues) {
				return false;
			}
			return $this->processIssuesPayload($issues);
		} else {
			return false;
		}
	}
	
	/**
	 * Finds all issues reported by ramblur users
	 * @return array|boolean
	 */
	function getAllIssues() {
		$this->db->select('i.*, u.userName')
			->from('UserToIssue AS i')
			->join('User as u', 'u.userId = i.userId');
		$trackedIssues = $this->db->get();
		if($trackedIssues->num_rows() > 0) {
			$ids = array();
			$trackedIssues = $trackedIssues->result_array();
			foreach($trackedIssues as $row) {
				$ids[] = $row["issueId"];
			}
			// Possibly change to POST, since query could be very large
			$curl = $this->initialiseCurl(self::REQUEST_GET, $this->getSearchString($ids));
			$issues = $this->send($curl);
			if(!$issues) {
				return false;
			}
			$processedIssues = $this->processIssuesPayload($issues);
			for($i = 0; $i < count($processedIssues); $i++) {
				$issueKey = $processedIssues[$i]["key"];
				$trackedIssueKey = array_search($issueKey, array_column($trackedIssues, 'issueId'));
				$processedIssues[$i]["userId"] = $trackedIssues[$trackedIssueKey]["userId"];
				$processedIssues[$i]["userName"] = $trackedIssues[$trackedIssueKey]["userName"];
			}
			return $processedIssues;
		} else {
			return false;
		}
	}
	
	/**
	 * Edits an existing issue in JIRA, returning the parsed object.
	 * Allows for the addition of comments, and changing the description.
	 * 
	 * Unnecessary
	 * 
	 * @param string $issueKey
	 * @return stdClass
	 */
// 	function editIssue($issueKey, $description = '', $comment = '') {
		
// 		// allow comment addition, and
// 		$dataString = '
// 			{"update":{
// 				"comment":[
// 					{
// 						"add":{
// 							"body":"test comment addition 2"
// 						}
// 					}
// 				]
// 			}
// 			}';
		
// 		//$dataString = json_encode($data);
// 		$curl = $this->initialiseCurl(self::REQUEST_PUT, 'issue/'.$issueKey);
		
// 		curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);
// 		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
// 				'Content-Type: application/json',
// 				'Content-Length: ' . mb_strlen($dataString))
// 				);
		
// 		$issue = $this->send($curl);
// 		// Check if successful (204). 400 if failed.
// 		return true;
// 	}
	
	/**
	 * Parses the JSON response data from JIRA. Keeps issue id, key, summary, escription and status.
	 * @param string $payload
	 * @return array
	 */
	private function processIssuePayload($payload, $decode = true) {
		//return $payload;
		if($decode) {
			$payload = json_decode($payload);
			// probably error message
			if(!$payload) {
				return $payload;
			}
		}
		$flatProperties = array('id', 'key');
		$fieldProperties = array('summary', 'description');
		$fieldNestedProperties = array('status'=>'name');
		$issue = array();
		foreach($flatProperties as $p) {
			if(property_exists($payload, $p)) {
				$issue[$p] = $payload->$p;
			}
		}
		if(isset($payload->fields)) {
			foreach($fieldProperties as $p) {
				if(property_exists($payload->fields, $p)) {
					$issue[$p] = $payload->fields->$p;
				}
			}
			foreach($fieldNestedProperties as $k => $v) {
				if(property_exists($payload->fields, $k) && property_exists($payload->fields->$k, $v)) {
					$issue[$k] = $payload->fields->$k->$v;
				}
			}
		}
		return $issue;
	}
	
	/**
	 * Parses JSON response data that returns an array of issues, instead of a single issue.
	 * @param string $payload
	 * @return array
	 */
	private function processIssuesPayload($payload) {
		$payload = json_decode($payload);
		// probably error message
		if(!$payload) {
			return $payload;
		}
		$issues = array();
		foreach($payload->issues as $issue) {
			$issues[] = $this->processIssuePayload($issue, false);
		}
		return $issues;
	}
	
	/**
	 * Creates the query string for a JIRA search in the configured project, 
	 * searching by provided issue ids. ALlows appending extra search queries
	 *  onto the end, with the optional $extra parameter
	 * 
	 * @param array $issueIds
	 * @return string
	 */
	private function getSearchString(array $issueIds, $extra = '') {
		if(!is_string($extra)) {
			$extra = '';
		}
		$searchString = 'search?jql=project='.$this->config->item('jira_project_id');
		$searchString .= '%20and%20issue%20in%20('.implode(',', $issueIds).')' . $extra;
		return $searchString;
	}
	
	/**
	 * Sets up cURL with the JIRA REST uri endpoint, JIRA credentials,
	 * and the desired method. Returns the resulting cURL handler.
	 * 
	 * @param string $method
	 * @param string $uriSuffix
	 * @return resource
	 */
	private function initialiseCurl($method, $uriSuffix = '') {
		// if provided without leading slash, add
		if($uriSuffix !== '' && $uriSuffix[0] !== '/') {
			$uriSuffix = '/'.$uriSuffix;
		}
		if($method !== self::REQUEST_GET && 
				$method !== self::REQUEST_DELETE &&
				$method !== self::REQUEST_POST &&
				$method !== self::REQUEST_PUT) {
			$method = self::REQUEST_GET;
		}
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $this->getBaseUri().$uriSuffix);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_USERPWD, $this->config->item('jira_credentials'));
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		return $curl;
	}
	
	/**
	 * Executes the request, returning the response body.
	 * 
	 * @param cURL handler $curl
	 * @return string
	 */
	private function send($curl) {
		$result = curl_exec($curl);
		// Check if any error occurred
		if(curl_errno($curl)) {
			return false;
		} else {
			$info = curl_getinfo($curl);
			// Check for successful status code (2xx)
			if(mb_substr($info["http_code"], 0, 1) !== '2') {
				return false;
			}
		}
		
		curl_close($curl);
		return $result;
	}
}