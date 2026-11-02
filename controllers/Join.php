<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use Entities\Forum\Attribute;

class Join extends CI_Controller {
	
	private $forum = null;

	public function index() {

		$this->load->model('user');
		$userId = $this->user->authCheck();
		if ($userId) {
			redirect('/');
			return;
		}
		
		$head['referrer'] = 'join';
		$data = array();

		// countries
		$this->db->select("*")->from('Country')->order_by('name', 'ASC');
		$data['countries'] = $this->db->get()->result_array();
		
		//build dob dropdown
		$this->load->helper('dob_dropdown');
		$data['dobDay'] = dobDayDropdown('dobDay', $this->input->post('dobDay'), 'class="form-control"');
		$data['dobMonth'] = dobMonthDropdown('dobMonth', $this->input->post('dobMonth'), 'class="form-control"');
		$data['dobYear'] = dobYearDropdown('dobYear', $this->input->post('dobYear'), 'class="form-control"');
		
		$this->load->model('group');
		
		// we're having a go
		if($this->input->post()) {

			$data['error'] = '';
			$this->load->model('globals');
			$rr = $this->globals->verifyRecaptcha($this->input->post('g-recaptcha-response'));

			if ($this->input->post('agree') && $this->input->post('agree') === 'on') $data['agree'] = $this->input->post('agree');
			if ($this->input->post('country')) $data['selectedCountry'] = $this->input->post('country');
					
			// If we are joining after an invite, need to validate the invite token, and get info about the forum it's from
			$inviteIncluded = false;
			$inviteFound = false;
			$inviteValid = false;
			$ramblurInvite = true;
			if ($this->input->post('inviteToken') && $this->input->post('inviteToken') !== 'false' && (!$this->input->post('skipInvite') || $this->input->post('skipInvite') === 'false')) {
				$inviteIncluded = true;
				$this->db->from('SubjectInvite')
					->where([
						'token' => $this->input->post('inviteToken'),
						'result' => NULL
					]);
				$invite = $this->db->get();
				if ($invite->num_rows() > 0) {
					$inviteFound = true;
					$invite = $invite->first_row('array');

					$this->forum = $this->getForumDetails($invite['subjectId']);
					
					// for non-ramblur invites, need further forum info for non-branded display purposes
					if ((int)$this->forum['type'] !== group::TYPE_RAMBLUR) {
						$ramblurInvite = false;
						$data['fId'] = $this->forum['subjectId'];
						$data['forumDescription'] = $this->forum['description'];
						$data['forumName'] = $this->forum['subjectName'];
						$data['forumPrivate'] = $this->forum['private'];
						$data['avatarPath'] = $this->getForumAvatar($invite['subjectId'], $this->forum['private']);
					}
					if (mb_strtolower($invite['email']) === mb_strtolower($this->input->post('email'))) {
						$inviteValid = true;
					} else {
						$data['error'] .= 'The email address you entered does not match the email address the forum invitation was sent to. Please use the email address the invitation was sent to.<br>';
					}
				} else {
					$data['error'] .= 'The invitation to join a forum is not valid. You can still join Ramblur and then can search for the forum to try and join it again.<br>';
				}
			}
			
			// if a specific forum has been requested and it's a non-ramblur forum, show the no-brand view
			$nrf = false;
			if ($this->input->post('fId') && $this->input->post('fId') !== 'false') {
				$this->forum = $this->getForumDetails($this->input->post('fId'));
				if ($this->forum && $this->forumIsJoinable($this->forum['subjectId'])) {

					// Store fId for all forum types
					$data['fId'] = $this->input->post('fId');
					
					// Keep rest of info only for nRF
					if ((int)$this->forum['type'] !== group::TYPE_RAMBLUR) {
						$nrf = true;
						$data['forumDescription'] = $this->forum['description'];
						$data['forumName'] = $this->forum['subjectName'];
						$data['forumPrivate'] = $this->forum['private'];
						$data['avatarPath'] = $this->getForumAvatar($this->input->post('fId'), false);
					}
				}
			}

			// first hurdle = verify recaptcha response
			if(!$rr['success']) {

				if($this->input->post('country')) $data['selectedCountry'] = $this->input->post('country');
				$data['error'] .= 'Invalid reCaptcha - please (re)solve the challenge!';
				$this->load->view($inviteIncluded && $inviteFound && !$ramblurInvite ? 'join-no-brand' : 'join', $data);

			} else {
			
				// load up some shit, yo
				$this->load->helper(array('form', 'url'));
				$this->load->library('form_validation');
				
				// FORM VALIDATION RULES
				$this->form_validation->set_rules('username', 'Username', 'trim|required|max_length[64]|mb_chars_dash_underscore|callback_username_check');
				$this->form_validation->set_rules('password', 'Password', 'trim|min_length[6]|required');
				$this->form_validation->set_rules('passwordCheck', 'Password confirmation', 'matches[password]|required');
				$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|callback_email_check');
				$this->form_validation->set_rules('dobDay', 'Date of Birth "Day"', 'required');
				$this->form_validation->set_rules('dobMonth', 'Date of Birth "Month"', 'required');
				$this->form_validation->set_rules('dobYear', 'Date of Birth "Year"', 'required|callback_check_valid_date');
				$this->form_validation->set_rules('country', 'Country', 'required');
				$this->form_validation->set_rules('agree', 'Agree', 'callback_accept_terms');
				$this->form_validation->set_rules('inviteToken', 'invitation code', 'alpha_dash');
				$this->form_validation->set_rules('skipInvite', 'skip invite', '');
				
				// FORM VALIDATION ERROR MESSAGES
				$this->form_validation->set_message('matches', 'The passwords do not match.');
				
				// bad form, naughty form
				if ($this->form_validation->run() === false || !$rr['success']) {
					$this->load->view(($inviteIncluded && $inviteFound && !$ramblurInvite) || $nrf ? 'join-no-brand' : 'join', $data);
				// good form!
				} elseif (!$this->input->post('agree')) {

					//NB. no check here as frontend is currently only handling the 'agree' thing

				} else {

					if ($inviteIncluded && !$inviteFound) {
						//$data['includeSkipInvite'] = true;
						$this->load->view('join', $data);
					} else if ($inviteIncluded && $inviteFound && !$inviteValid) {
						$this->load->view($ramblurInvite ? 'join' : 'join-no-brand', $data);
					} else {
	
						$data['userName'] = $this->input->post('username');
						$data['password'] = $this->input->post('password');
						$data['email'] = $this->input->post('email');
						$data['dobDay'] = $this->input->post('dobDay');
						$data['dobMonth'] = $this->input->post('dobMonth');
						$data['dobYear'] = $this->input->post('dobYear');
						$data['userJoinDOB'] = $data['dobYear'] . '-' . $data['dobMonth'] . '-' . $data['dobDay'];
						$data['country'] = $this->input->post('country');
						
						$data['thirdparty'] = ($inviteIncluded && $inviteValid && !$ramblurInvite) || $nrf;
						if ($data['thirdparty']) {
							$data['thirdpartyForum'] = $this->forum;
						}

						$uid = $this->user->create($data);
	
						if(is_numeric($uid)) {
							$successJoinData = [];
							
							// if the user is underage and it's a club18 forum, decline the invitation (if one was used), and don't join the forum
							$dob = new DateTime($data['userJoinDOB']);
							$now = new DateTime('today');
							$age = $dob->diff($now)->y;
							
							if ($this->forum && (int)$this->forum['type'] === group::TYPE_CLUB18 && $age < 18) {
								$successJoinData['underage'] = true;

								// Store the result of the invitation
								if ($inviteIncluded && $inviteValid) {
									$this->db->update('SubjectInvite', ['result' => group::SUBJECTINVITE_DECLINE], ['id' => $invite['id']]);
								}
							} else {
								// Store the result of the invitation
								if ($inviteIncluded && $inviteValid) {
									$this->db->update('SubjectInvite', ['result' => group::SUBJECTINVITE_ACCEPT], ['id' => $invite['id']]);
								}
								
								// auto subscribe to the requested forum if there was an invite, or there was a valid forum specified by fId (ramblur/non-ramblur irrelevant)
								if (($inviteIncluded && $inviteValid && !$ramblurInvite) || $this->forum) {
									// add user to forum
									$this->db->query('CALL addSubjectToUserList(?, ?, 0, 1)', [$uid, $this->forum['subjectId']]); // how can we tell if this was successful?
									if ($this->forum['private']) {
										$this->db->update('UserToSubject', ['privateAccess' => 1], ['userId' => $uid, 'subjectId' => $this->forum['subjectId']]);
									}
								}
							}
							
							if (($inviteIncluded && $inviteValid && !$ramblurInvite) || $nrf) {
								$successJoinData['forumDescription'] = $data['forumDescription'];
								$successJoinData['forumName'] = $data['forumName'];
								$successJoinData['forumPrivate'] = $data['forumPrivate'];
								$successJoinData['forumId'] = $this->forum['subjectId'];
								
								$this->load->view('joinsuccess-no-brand', $successJoinData);
							} else {
								if ($this->forum) {
									$successJoinData['forumName'] = $this->forum['subjectName'];
									$successJoinData['forumId'] = $this->forum['subjectId'];
								}
								$this->load->view('joinsuccess', $successJoinData);
							}
							unset($data);
						} else {
							$data['error'] = 'System error: Failed to create user. Please try again.';
							$this->load->view(($inviteIncluded && $inviteValid && !$ramblurInvite) || $nrf ? 'join-no-brand' : 'join', $data);
						}
					}
				}
			}

		// initial page load
		} else {
			// coming to the page from an invitation to join
			if ($this->input->get('invite')) {
				$inviteIncluded = true;
				$this->db->from('SubjectInvite')
					->where([
						'token' => $this->input->get('invite'),
						'result' => NULL
					]);
				$invite = $this->db->get();
				if ($invite->num_rows() > 0) {
					$invite = $invite->first_row('array');
					$data['inviteToken'] = $invite['token'];
				} else {
					$data['error'] = 'The invitation to join a forum is not valid. You can still join Ramblur and then can search for the forum to try and join it again.';
					$this->load->view('join', $data);
					return;
				}

				$this->forum = $this->getForumDetails($invite['subjectId']);
				
				if ((int)$this->forum['type'] === group::TYPE_RAMBLUR) {
					$this->load->view('join', $data);
				} else {
					$data['fId'] = $this->forum['subjectId'];
					$data['forumDescription'] = $this->forum['description'];
					$data['forumName'] = $this->forum['subjectName'];
					$data['forumPrivate'] = $this->forum['private'];
					$data['avatarPath'] = $this->getForumAvatar($invite['subjectId'], $this->forum['private']);
					$this->load->view('join-no-brand', $data);
				}
			// forumId has been provided
			} else if ($this->input->get('fId')) {
				
				$this->load->helper('not_found_response');

				$this->forum = $this->getForumDetails($this->input->get('fId'));
				if (!($this->forum && $this->forumIsJoinable($this->forum['subjectId']))) {
					not_found_response();
					return false;
				}
				
				// All types of forums pass through the fId
				$data['fId'] = $this->input->get('fId');
				
				// Ramblur forums get normal join page
				if ((int)$this->forum['type'] === group::TYPE_RAMBLUR) {
					$this->load->view('join', $data);
					
				// Non ramblur forums have a forum specific join page
				} else {
					$data['forumDescription'] = $this->forum['description'];
					$data['forumName'] = $this->forum['subjectName'];
					$data['forumPrivate'] = $this->forum['private'];
					$data['avatarPath'] = $this->getForumAvatar($this->input->get('fId'), false);
					$this->load->view('join-no-brand', $data);
				}
				
			} else {
				$this->load->view('join', $data);
			}
		}
	}

	/* CALLBACKS */

	public function username_check($str) {
		return $this->user->username_check($str);
	}

	public function email_check($email) {

		$this->db->select("username")->from('User')->where('email', $email);
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			if ($this->forum && (int)$this->forum['type'] !== group::TYPE_RAMBLUR) {
				$this->form_validation->set_message('email_check', 'This email address has already been registered as an account with ramblur, the hosting platform for this forum. Go <a href="'.base_url(urlencode(str_replace(' ','-',$this->forum['subjectName'])).'-forum/'.$this->forum['subjectId']).'">back</a> to the forum home page and log in with your ramblur account.');
			} else {
				$this->form_validation->set_message('email_check', 'This email is already registered');
			}
			return false;
		}
		return true;
	}

	public function accept_terms() {
		if ($this->input->post('agree')) return true;
		$this->form_validation->set_message('accept_terms', 'Please read and accept our Terms &amp; Conditions');
		return false;
	}
	
	public function check_valid_date() {
		
		$dobMonth = $this->input->post('dobMonth');
		$dobDay = $this->input->post('dobDay');
		$dobYear = $this->input->post('dobYear');
		
		if ($dobMonth && $dobDay && $dobYear && checkdate($dobMonth, $dobDay, $dobYear)) {
			if (new DateTime($dobDay . '-' . $dobMonth . '-' . $dobYear) < new DateTime($this->config->item('ramblurAgeLimit') . ' years ago')) {
				return true;
			} else {
				$this->form_validation->set_message('check_valid_date', 'You have not met the minimum age requirements.');
				return false;
			}
		}
		$this->form_validation->set_message('check_valid_date', 'Please enter a valid date of birth.');
		return false;
	}
	
	private function getForumDetails ($forumId) {
		$this->db->select('subjectId, subjectName, private, type, description')
			->from('Subject')
			->where('subjectId', $forumId);
		$forumResults = $this->db->get();
		if ($forumResults->num_rows() === 0) {
			return false;
		}
		return $forumResults->first_row('array');
	}
	
	private function getForumAvatar($forumId, $private, $invite = false) {

		$avatarResult = $this->group->getThirdPartyAvatar($forumId);
		if (!$avatarResult || !$avatarResult['avatar']) {
			return '';
		} else {
			$this->load->model('file');
			$avatarPath = $this->file->getRelFilePath($avatarResult['avatar'], $private);
			if ($private) {
				$avatarPath .= '?invite='.$invite;
			}
			return $avatarPath;
		}
	}
	
	private function forumIsJoinable($forumId) {
		$this->load->model('subjectAttribute');
		
		$forumEntity = $this->subjectAttribute->getForumEntity($forumId);
		return !$forumEntity->is(Attribute::INVITE_ONLY());
	}
}
