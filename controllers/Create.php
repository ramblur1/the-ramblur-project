<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Create extends CI_Controller {


public function __construct() {

		parent::__construct();
		require_once APPPATH.'/third_party/Mobile_Detect.php';
		$this->detect = new Mobile_Detect;
		define("TABLET", $this->detect->isTablet() ? true : false);
	}

    public function index() {

        $head['referrer'] = 'create';
        $this->load->model('user');
        $head['loggedIn'] = $this->user->authCheck() !== false;

        $this->load->view('generic-header', $head);

        $this->load->view('create');

        $this->load->view('generic-footer');        

    } 


}
