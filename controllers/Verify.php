<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Verify extends CI_Controller {
	
	const CODE_INVALID = 0;
	const CODE_SUCCESS = 1;
	const CODE_ERROR = 2;
	const CODE_LOGGED_IN = 3;

	public function index() {
		
		$data = [];
		$head = [];
		$head['referrer'] = 'verify';

		// check the authentication
		$this->load->model('user');
		$user = $this->user->authCheck();
		
		if($user) {
			$head['loggedIn'] = true;
			$data['verified'] = self::CODE_LOGGED_IN;
		} else {
			$head['loggedIn'] = false;
	
			$data['verified'] = self::CODE_INVALID;
		}
		

		$this->load->view('generic-header', $head);
		$this->load->view('verify', $data);
		$this->load->view('generic-footer');
	}

	public function c() {
		
		$head = [];
		$data = [];
		$head['referrer'] = 'verify';
		$data['verified'] = 0;

		$headerView = 'generic-header';
		$mainView = 'verify';
		$footerView = 'generic-footer';
		$includeHeader = true;
		
		// check the authentication
		$this->load->model('user');
		$user = $this->user->authCheck();
		
		if($user) {
			$head['loggedIn'] = true;
			$data['verified'] = self::CODE_LOGGED_IN;
		} else {
			
			if ($verificationCode = $this->uri->segment(3) !== false) {
	
				$verificationCode = $this->uri->segment(3);
	
				$this->load->model('user');
				
				// If the user is only following one subject, we show them the non-branded view
				$this->db->select("
						COUNT(`s`.`subjectId`) AS 'subjectCount',
						ANY_VALUE(`su`.`subjectId`) AS 'subjectId',
						ANY_VALUE(`su`.`subjectName`) AS 'subjectName',
						ANY_VALUE(`su`.`description`) AS 'description',
						ANY_VALUE(`su`.`private`) AS 'private'
						", FALSE) // can use ANY_VALUE as we only use the values when there's a single forum
					->from('User AS u')
					->join('UserToSubject AS s', 's.userId = u.userId')
					->join('Subject AS su', 'su.subjectId = s.subjectId')
					->group_by('u.userId')
					->where('u.verificationCode', $verificationCode);
				
				$subjectCount = 0;
				$subjectCountResult = $this->db->get();
				if ($subjectCountResult->num_rows() > 0) {
					$subjectCountResult = $subjectCountResult->first_row('array');
					$subjectCount = $subjectCountResult['subjectCount'];
				}
				if ($subjectCount > 0) {
					// check for valid code
					$data['verified'] = ($verified = $this->user->verify(array("verificationCode" => $verificationCode))) ? 1 : 2;
					if ($subjectCount == 1) {
						$this->load->model('group');
						
						// private files have to be authenticated, but at this stage, there's no authentication available, so only show avatar
						// if it's a public forum
						if ($subjectCountResult['private']) {
							$avatarPath = '';
						} else {
							$avatarResult = $this->group->getThirdPartyAvatar($subjectCountResult['subjectId']);
							if (!$avatarResult || !$avatarResult['avatar']) {
								$avatarPath = '';
							} else {
								$this->load->model('file');
								$avatarPath = $this->file->getRelFilePath($avatarResult['avatar'], false);
							}
						}
						
						$data['forumName'] = $subjectCountResult['subjectName'];
						$data['forumDescription'] = $subjectCountResult['description'];
						$data['forumIsPrivate'] = (boolean)$subjectCountResult['private'];
						$data['forumId'] = $subjectCountResult['subjectId'];
						$data['avatarPath'] = $avatarPath;
						$includeHeader = false;
						$mainView .= '-no-brand';
						$footerView .= '-no-brand';
					}
				}
	
			}
		}

		if ($includeHeader) {
			$this->load->view($headerView, $head);
		}
		$this->load->view($mainView, $data);
		$this->load->view($footerView);
	}
}
