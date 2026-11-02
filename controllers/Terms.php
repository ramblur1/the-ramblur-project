<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Terms extends CI_Controller {

    public function index() {
		
    	// check the authentication
    	$this->load->model('user');

        $head['referrer'] = 'terms';
        $head['loggedIn'] = $this->user->authCheck() !== false;

        $this->load->view('generic-header', $head);

        $this->load->view('terms');

        $this->load->view('generic-footer');

    } 


}
