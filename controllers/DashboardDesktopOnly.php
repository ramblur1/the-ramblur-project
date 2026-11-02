<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DashboardDesktopOnly extends CI_Controller {

	public function index() {
		$this->load->model('globals');
		$this->load->model('user');

		
		$this->load->view('dashboardDesktopOnly');

	}
	

}