<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Mod_application extends CI_Controller {

	public function index($subjectId = null) {
		$this->load->model('user');
		$this->load->model('globals');
		$this->load->model('group');
		
		// check the authentication
		$user = $this->user->authCheck();
		if(!$user) {
			redirect('/');
			return;
		}
		if($user && $this->input->post('submit') == 'save') {
			$this->load->helper('form');
			$this->load->library('form_validation');
			
			$formConfig = array(
				array(
					'field' => 'firstName',
					'label' => 'first name',
					'rules' => 'required'
				),
				array(
					'field' => 'lastName',
					'label' => 'last name',
					'rules' => 'required'
				),
				array(
					'field' => 'dob',
					'label' => 'date of birth',
					'rules' => 'required'
				),
				array(
					'field' => 'address',
					'label' => 'address',
					'rules' => 'required'
				),
				array(
					'field' => 'postalAddress',
					'label' => 'postal address',
					'rules' => 'required'
				),
				array(
					'field' => 'phone',
					'label' => 'phone number',
					'rules' => 'required'
				),
				array(
					'field' => 'mobile',
					'label' => 'mobile number',
					'rules' => 'required'
				),
				array(
					'field' => 'employment',
					'label' => 'employment status',
					'rules' => 'required'
				),
				array(
					'field' => 'profile',
					'label' => 'public profile',
					'rules' => 'required'
				),
				array(
					'field' => 'expertise',
					'label' => 'expertise',
					'rules' => 'required'
				),
				array(
					'field' => 'forum',
					'label' => 'forum',
					'rules' => 'required'
				)
			);
			$this->form_validation->set_rules($formConfig);
			if ($this->form_validation->run() !== false) {
				// Make sure subject exists, and is a ramblur forum
				$this->db->select('subjectId')
					->from('Subject')
					->where(array(
						'subjectName' => $this->input->post('forum'),
						'type' => group::TYPE_RAMBLUR
					));
				$existingSubject = $this->db->get();
				if($existingSubject->num_rows() === 0) {
					$resultMsg = 'The forum could not be found.';
					$result = false;
				} else {
					// Make sure the user isn't already an available moderator for the subject
					$existingSubject = $existingSubject->first_row('array');
					$subjectId = $existingSubject['subjectId'];
					$this->db->select('userId')
						->from('ModeratorToSubject')
						->where(array(
							'userId' => $user,
							'subjectId' => $subjectId
						));
					if($this->db->get()->num_rows() > 0) {
						$resultMsg = 'You have already been recorded as being available to moderate this forum.';
						$result = false;
					} else {
						// store application
						$this->db->select('userId')
							->from('Moderator')
							->where('userId', $user);
						$existingMod = $this->db->get();
						if($existingMod->num_rows() === 0) {
							$this->db->insert('Moderator',
								array(
									'userId' => $user,
									'text' => $this->input->post('expertise'),
									'publicProfile' => $this->input->post('profile'),
									'ramblurEnabled' => 1
							));
						}
						$this->db->insert('ModeratorToSubject',
							array(
								'userId' => $user,
								'subjectId' => $subjectId
						));
						$result = true;
						$resultMsg = 'Your application has been recorded, and you will be notified of the result in the coming week. Thankyou.';
					}
				}
			}
		}
		$subject = null;
		if(!is_null($subjectId)) {
			$subjectName = $this->group->getName($subjectId);
			if ($subjectName) {
				$subject = array('id' => $subjectId, 'name' => $subjectName);
			}
		}
		$viewData = compact('user', 'result', 'resultMsg', 'subject');
		$this->load->view('generic-header', array("referrer" => 'moderatorApplication', 'loggedIn' => true));
		$this->load->view('moderatorApplication', $viewData);
		$this->load->view('generic-footer');
	}
}