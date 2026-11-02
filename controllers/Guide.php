<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Guide extends CI_Controller {
	
	public function index() {
		
		$head['referrer'] = 'how-ramblur-works';
		$this->load->model('user');
		$head['loggedIn'] = $this->user->authCheck() !== false;
		
		$this->load->view('generic-header', $head);
		
		$this->load->view('how-ramblur-works');
		
		$this->load->view('generic-footer');
	}
}
