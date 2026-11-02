<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Website extends CI_Controller {
	
	public function index() {
		
		$head['referrer'] = 'website';
		$this->load->model('user');
		$head['loggedIn'] = $this->user->authCheck() !== false;
		
		$this->load->view('generic-header', $head);
		
		$this->load->view('website');
		
		$this->load->view('generic-footer');
	}
}
