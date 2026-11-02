<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * 
 * 
 * 
 */

class adAdmin extends CI_Controller {

    function __construct() {
	parent::__construct();
    }

    function __destruct() {
	
    }

    public function index() {
	$this->load->model('user');
	$this->load->model('globals');

	$data = array();

	$data["user"] = $this->user->authCheck();

	//$this->load->view('tabsHeader');
	$this->load->view('toolHeader');
	$this->load->view('advertisingHeader');
	$this->load->view('toolTabs', $data);
	$this->load->view('toolFooter');
	$this->load->view('adAdminMenu', $data);
    }

    public function adStats($param = '') {
	
	$this->load->model('user');
	$this->load->model('globals');
	$this->load->model('adadminmodel');
	
	//if ($this->input->post('submit') === 'adStats') {
	$this->load->view('toolHeader'); //why does not dojo.js get loaded in the index?
	
	switch ($param) {
	    case 'menu':
		$this->load->view('adStats');
		break;
	    case 'byDate' :
		
		
		$monthlyTotals = $this->adadminmodel->getStats(); 
		$data["monthlyTotals"] = $monthlyTotals;
		$data["chartTitle"] = "Top Ten Advertising Revene Earning Forums 2015";
		$data["chartTitle"] = "Total Advertising Revenue by Month in 2016";
		$data["chartData"] = array(121, 223, 433, 534, 634, 753, 837, 938, 800, 1000, 1200, 1300);
		$data["chartNode"] = "chartNode";
		$this->load->view('adAdmin/adStatCharts', $data);
		break;
	}

	// $this->load->view('chart1', $data);
    }

    public function adToggle() {

	$this->load->view('adToggle');
    }

    public function adPayments() {

	$this->load->view('adPayments');
    }

    public function adGeneralStats() {
	$data["chartTitle"] = "Top Ten Advertising Revene Earning Forums 2016";

	$data["chartTitle"] = "Top Ten Advertising Revene Earning Forums 2015";
	$data["chartData"] = array(1, 22, 43, 34, 34, 55, 77, 88, 33, 10);
	$data["chartNode"] = "chartNode2";
	$this->load->view('adMenuScreen1-charts', $data);
    }

}
