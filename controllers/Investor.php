<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class Investor extends CI_Controller {

	public function index() {

		$this->load->model('user');
		$userId = $this->user->authCheck();
		$data = array();
		$data['referrer'] = 'investor';
		$data['loggedIn'] = $userId !== false;

		// we're having a go
		if ($this->input->post()) {
			$data['error'] = '';
			$this->load->model('globals');
			$rr = $this->globals->verifyRecaptcha($this->input->post('g-recaptcha-response'));

			// first hurdle = verify recaptcha response
			if (!$rr['success']) {

				$data['error'] .= 'Invalid reCaptcha - please (re)solve the challenge!';
				$this->load->view('investorView', $data);
			} else {

				// load up some shit, yo
				$this->load->helper(array('form', 'url'));
				$this->load->library('form_validation');

				// FORM VALIDATION RULES
				$this->form_validation->set_rules('firstname', 'first name', 'trim|required|max_length[64]|alpha_numeric_spaces');
				$this->form_validation->set_rules('surname', 'surname', 'trim|required|max_length[64]|alpha_numeric_spaces');

				$this->form_validation->set_rules('compOrInd', 'Company or Individual ', 'required');

				if ($this->input->post('compOrInd') === "We are a company") {
					$this->form_validation->set_rules('company', 'company', 'trim|required|max_length[64]', 'alpha_numeric_spaces');
					$this->form_validation->set_rules('abn', 'abn or company registration number', 'trim|required|max_length[64]|alpha_numeric_spaces');
					$this->form_validation->set_rules('address', 'address', 'trim|required|max_length[64]', 'regex_match[/^[a-z .,\-]+$/i]');
					$this->form_validation->set_rules('website', 'website', 'trim|required|valid_url');
				}

				//$this->form_validation->set_rules('phone', 'phone', 'trim|required|max_length[64]|numeric');
				//more flexible for phone numbers
				$this->form_validation->set_rules('phone', 'phone', 'trim|required|max_length[64]|regex_match[/^[- +()]*[0-9][- +()0-9]*$/]');
				$this->form_validation->set_rules('email', 'email', 'trim|required|valid_email');

				$this->form_validation->set_rules('comment', 'comment', 'trim|max_length[1024]|regex_match[/^[a-z .,\-]+$/i]');
				//$this->form_validation->set_rules('comment', 'comment', 'trim|max_length[1024]|alpha_numeric_spaces');

				// bad form, naughty form
				if ($this->form_validation->run() === false || !$rr['success']) {
					//$this->load->view(($inviteIncluded && $inviteFound && !$ramblurInvite) || $nrf ? 'join-no-brand' : 'join', $data);
					$this->load->view('investorView', $data);
					// good form!
				} else {
					$this->load->helper(array('htmlpurifier'));

					$data['firstname'] = html_purify($this->input->post('firstname'), 'post');
					$data['surname'] = html_purify($this->input->post('surname'), 'post');

					$data['company'] = html_purify($this->input->post('company'), 'post');
					$data['abn'] = html_purify($this->input->post('abn'), 'post');
					$data['address'] = html_purify($this->input->post('address'), 'post');
					$data['website'] = html_purify($this->input->post('website'), 'post');

					$data['phone'] = html_purify($this->input->post('phone'), 'post');
					$data['email'] = html_purify($this->input->post('email'), 'post');

					$data['comment'] = html_purify($this->input->post('comment'), 'post');

					$email = $this->globals->getInvestorEmail();
					
					if (!empty($email)) {

						$this->load->model("email");
						
						$template = new \Ramblur\Email\Builder\InvestorEmailTemplateBuilder(Logger::getLogger());
						$template->firstname($data['firstname'])
								->surname($data['surname'])
								->company($data['company'])
								->abn($data['abn'])
								->address($data['address'])
								->website($data['website'])
								->phone($data['phone'])
								->email($data['email'])
								->comment($data['comment']) ;
						
						$this->email->sendInvestorEmail($email, $template);
											
						$this->load->view('investorViewSuccess', $data);
					} else {
						$this->load->view('investorView', $data);
					}
				}
			}
		} else {
			$this->load->view('investorView', $data);
		}
	}

}
