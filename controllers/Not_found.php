<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Not_found extends CI_Controller {

	public function index() {

	    $head['referrer'] = 'notfound';
	    $this->load->model('user');
	    $head['loggedIn'] = $this->user->authCheck() !== false;

        $this->load->view('generic-header', $head);

		$this->load->view('not_found');

        $this->load->view('generic-footer');

		$this->output->set_status_header('404');

	}

}
