<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Controller to display the site blog
 * 
 * Currently pulls in a standalone "bludit" blog located under public_html/ramblur-blog
 * and displays it using ajax at ramblur.com/blog
 *  
 */
class Blog extends CI_Controller {

    public function index() {

		/* 
		 * We need to build the url that the ajax request will make to the ramblur-blog app
		 * Both the location of the ramblur-blog with correct scheme $domain (https://www.ramblur.com)
		 * and the url we want to call from the ramblur-blog which this controller receives as a get variable
		 * 
		*/ 		
		$this->load->helper('url');
		$domain = base_url();
		$baseUri = uri_string();		
		$desiredUri = strstr($baseUri,"/");
		if(!empty($this->input->get())){
			$desiredUri = $desiredUri."?".http_build_query($this->input->get());
		}
		
		//Final URL, no double slashes allowed
		$blogUrl = $domain.preg_replace('#/+#','/',"ramblur-blog/".$desiredUri) ;

		//Build the header and views
        $head['referrer'] = false;
        $this->load->model('user');
        $head['loggedIn'] = $this->user->authCheck() !== false;
        $this->load->view('generic-header', $head);

		$this->load->view('blog/ajaxRequest',["blogUrl"=>$blogUrl]);	
		
    } 


}
