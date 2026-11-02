<?php

  if (!defined('BASEPATH'))
  exit('No direct script access allowed');

  class AuthenticateAssets extends CI_Controller {

  public function index() {

  }
  public function getFile(){
    // Checks access permission for a given user
    // and file combination.  Outputs an 'X-Accel-Redirect'
    // header when access is granted, 403 otherwise

    // NB.  If the requested file is actually a 'public'
    //      file, access will be granted but a bogus file path
    //      will be placed in the X-Accel-redirect header.

    $this->load->model('user');

    // Get user identifier from their session cookie
    $userId = $this->user->authCheck();
    
    // May be trying to retrieve a private forum avatar
    if ($this->input->get('invite')) {
    	$this->db->from('SubjectInvite')
    		->where('token', $this->input->get('invite'));
    	$invite = $this->db->get();
    	if ($invite->num_rows() > 0) {
    		$invite = $invite->first_row('array');
    		$data['subjectId'] = $invite['subjectId'];
    		$file = $this->uri->segment(8,"undefined");
    		$baseFileName = mb_substr($file,0,36);
    		$this->db->select('id')
    			->from('SubjectDisplay')
    			->where([
    				'fileName' => $baseFileName,
    				'subjectId' => $invite['subjectId'],
    				'type' => 'avatar'
    		]);
    		$result = $this->db->get();
    		if ($result->num_rows() === 0) {
		  $this->output->set_status_header('403');
    			return false;
    		}
    		
    		// allowed
    		
    	} else {
	  $this->output->set_status_header('403');
    		return false;
    	}
    } else {
	    // Bail early if they do not have a valid session
	    if($userId == false){
	      $this->output->set_status_header('403');
	      return(false);
	    }
	
	    // Take the requested file name from the 8th uri segment.
	    $file = $this->uri->segment(8,"undefined");
	
	    // Bail if the 8th uri segment was absent.
	    if($file == "undefined"){
	      $this->output->set_status_header('403');
	      return(false);
	    }
	
	    // Cut out the 36 characters that will equal that stored as PK 'fileName'
	    // in the table called 'Content'
	    $baseFileName = mb_substr($file,0,36);
	    
	    // Check access permission for this user to this file.
	
	    $query="CALL checkFileAccess(?,?,@valid,true);";
	    $params=array($baseFileName,$userId);
	    $query = $this->db->query($query, $params);
	    $tmp=$query->result_array(); 
	
	    if (!isset($tmp[0]["valid"]) || $tmp[0]["valid"] == false){
	      // access denied
	      $this->output->set_status_header('403');
	      return(false);
	    }
    }


    // Access granted so work out the correct path and let NGINX know about it.
    list($level1, $level2, $level3,$level4,$level5) = explode('-',$baseFileName);

    $path = $this->config->item('server_return_path') . $level1."/".$level2."/".$level3."/".$level4."/".$level5."/".$file;

    header("X-Accel-Redirect: ". $path);
	  
  }
}
?>

