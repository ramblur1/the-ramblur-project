<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use Entities\Forum\Attribute;

class Subjectc extends CI_Controller {

	public function index() { 

		$this->load->model('globals');
		$this->load->model('user');
		$this->load->model('Subject');
		$this->load->model('group');

		$exp = array('expanded' => true);
		$notexp = array('expanded' => false);

		$alldata['query'] = array();

		// Check auth, get userId here.
		$userId = $this->user->authCheck();
		if ($userId == false) {
			print("auth failed");
			return;
		}

		//$userId = 1;
		$data = $this->Subject->get_subject_branch(1, $userId, 1, false);
		$featured = $this->Subject->get_featured_group($userId);

		foreach ($data as $row) {
			$row[] = $notexp;

			// Add data for subject groupings here

			if($row["grouped"]){
				$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
			}else{
				$data1='';
			}
			$row[] = $data1;
			$row[] = $featured;
			$alldata['query'][] = $row;
		}
		$alldata['userDOB'] =  $this->user->getDOB($userId);
		$this->load->view('subjectv',$alldata);
	}

	public function displayedrows($result='') {
		// A slightly awkward function that handles retrieval of hierarchical data via the 'Subject' model and sets it up for the view.
		//$data = array();

		$this->load->model('globals');
		$this->load->model('user');
		$this->load->model('Subject');
		$this->load->model('group');
		$this->load->helper(array('ramblurfiltervar'));

		if (isset($result) && $result == "NULL") {
			$alldata['query'] = array();
			$alldata1 = array();
			$substring = array();
			$expandedSubjectId=0;
			$shrinkedSubjectId=0;
			$shrinkednv=0;
			$shrinkeddv=0;
			$shrinkedsnv=0;
			$shrinkedsdv=0;
			$parentId=0;
			$subjectIdm=array();
			$exp = array('expanded' => true);
			$notexp = array('expanded' => false);
			$followed = array('followed' => true);
		
			$this->load->model('globals');
			$this->load->model('user');
			$this->load->model('Subject');

			// Check auth, get userId here.
			$userId = $this->user->authCheck();
			//$userId = 1;  // For testing purposes

			if ($this->input->post('submit1')) {

				$storeVars = array();
				$postVars = $this->input->post(NULL, TRUE);
				$postKeys = array_keys($postVars);

				// go through all the posted vars, find the subjectId of subject to be expanded or shrunk
				// Set subjects as 'followed' or 'unfollowed', group subjects together where called upon
				for ( $i = 0, $ilen = count($postKeys); $i < $ilen; $i++ ) {
		
					$substring = mb_substr($postVars[$postKeys[$i]], 0, 7);
					
					switch($substring) {
		
						case "reqexp_":
							list($junk,$subjectId, $level) = explode('_',$postVars[$postKeys[$i]]);
							if (!isset($subjectId) || rfilter_var($subjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							if (!isset($level) || rfilter_var($level,FILTER_VALIDATE_INT,true)===false) {
								return false;
							}
							$data = $this->Subject->get_subject_branch($subjectId, $userId, 1,true);
							$featured = $this->Subject->get_featured_group($userId);
							$rootSubName1 = $this->Subject->get_root_subjectname_from_subjectId($userId,$subjectId);	
							foreach ($data as $row) {
								// Add data for subject groupings here
								if ($row["grouped"]) {
									$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
								}else{
									$data1='';
								}
								$exp = array('expanded' => mb_strtolower($rootSubName1[0]['subjectName'][0]));
								$row[] = $exp;
								$row[] = $data1;
								$row[] = $featured;
								$alldata1[] = $row;
							}
							//$data1=$this->Subject->get_subject_groups($userId, $subjectId);
							 header('Content-Type: application/json');
							print json_encode($alldata1);
							exit;
							break;
							
						case "trendL_":
							list($junk,$subjectType,$subjectId) = explode('_',$postVars[$postKeys[$i]]);
							if (!isset($subjectId) || rfilter_var($subjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							if (!isset($subjectType) || rfilter_var($subjectType,FILTER_VALIDATE_INT,true)===false) {
								return false;
							}
							$data = $this->Subject->get_top_500($userId,$subjectType);
							$featured = $this->Subject->get_featured_group($userId);
							$details = $this->Subject->get_subject($subjectId, $userId);
							if ($subjectType == 3) {
								$exp = array('expanded' => 'g');
								$row1 = array(
									'subjectName' => 'top 500 general',
									'level' => $details[0]['level'],
									'subjectId' => $subjectId,
									'hasOffSpring' => $details[0]['hasOffSpring'],
									'rf' => false,
									'private' => false,
									'description' => '',
									'type' => $subjectType,
									'active' => $details[0]['active'],
									'grouped' => $details[0]['grouped']
								);
							} else if ($subjectType == 4) {
								$exp = array('expanded' => 'c');
								$row1 = array(
									'subjectName' => 'top 500 club18',
									'level' => $details[0]['level'],
									'subjectId' => $subjectId,
									'hasOffSpring' => $details[0]['hasOffSpring'],
									'rf' => false,
									'private' => false,
									'description' => '',
									'type'=>$subjectType,
									'active' => $details[0]['active'],
									'grouped' => $details[0]['grouped']
								);
							}	
							
							$row1[] = $exp;
							$row1[] = '';
							$row1[] = $featured;
							$alldata1[] = $row1;
					
							foreach ($data as $row) {
								// Add data for subject groupings here
								if ($row["grouped"]) {
									$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
								}else{
									$data1='';
								}
								$row[] = $exp;
								$row[] = '';
								$row[] = $featured;
								$alldata1[] = $row;
							}
							print json_encode($alldata1);
							exit;
							break;
							
						case "expand_":
							list($junk,$expandedSubjectId, $expandedLevel) = explode('_',$postVars[$postKeys[$i]]);
							break;
		
						case "shrink":
		
							list($junk,$shrinkedSubjectId,$shrinkedLevel) = explode('_',$postVars[$postKeys[$i]]);
							break;
		
						case "follow_":
		
							list($junk,$followSubjectId,$groupWith) = explode('_',$postVars[$postKeys[$i]]);
		
							if (!isset($followSubjectId) || rfilter_var($followSubjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							if (!isset($groupWith) || rfilter_var($groupWith,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							
							// can't follow my feed or main feed
							if (($this->group->getName($followSubjectId) === 'main feed' && (int)$this->group->getType($followSubjectId)) === group::TYPE_RAMBLUR || $followSubjectId == 1) {
								return false;
							}
							$this->load->model('subjectAttribute');
							$forumEntity = $this->subjectAttribute->getForumEntity($followSubjectId);
							
							// Can't follow forums which have been marked as requiring an invitation to join
							if ($forumEntity->is(Attribute::INVITE_ONLY())) {
								return false;
							}
							
							$this->Subject->follow_subject($followSubjectId, $userId,$groupWith,true);
							print 'followed';
							exit;
							break;
				
						case "unfoll_":
				
							list($junk,$followSubjectId,$groupWith) = explode('_',$postVars[$postKeys[$i]]);
					
							if (!isset($followSubjectId) || rfilter_var($followSubjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							if (!isset($groupWith) || rfilter_var($groupWith,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							
							// groupWIth only valid if it's not grouped with self
							if ($groupWith == $followSubjectId) {
								$groupWith = 0;
							}
						
							// can't unfollow my feed or main feed
							if (!(($this->group->getName($followSubjectId) === 'main feed' && (int)$this->group->getType($followSubjectId)) === group::TYPE_RAMBLUR || $followSubjectId == 1)) {
								$this->Subject->follow_subject($followSubjectId, $userId,$groupWith,false);
								print "unfollowed";
								exit;
							}
							break;
		
						case "group1_":
		
							list($junk,$groupOwner,$groupSubject) = explode('_',$postVars[$postKeys[$i]]);
							
							if (!isset($groupOwner) || rfilter_var($groupOwner,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							if( !isset($groupSubject) || rfilter_var($groupSubject,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							$this->Subject->group_subjects($groupOwner,$groupSubject, $userId,TRUE);
							
							break;
		
						case "delete_":
		
							list($junk,$groupSubject,$groupOwner) = explode('_',$postVars[$postKeys[$i]]);
							
							if (!isset($groupOwner) || rfilter_var($groupOwner,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							if (!isset($groupSubject) || rfilter_var($groupSubject,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							$this->Subject->group_subjects($groupOwner,$groupSubject, $userId,FALSE);
					
							//list($junk,$subjectId, $level) = explode('_',$postVars[$postKeys[$i]]);
							$rootSubName1 = $this->Subject->get_root_subjectname_from_subjectId($userId,$groupSubject);	
					
							$data = $this->Subject->get_subject_branch($groupOwner, $userId, 1,true);
							$featured = $this->Subject->get_featured_group($userId);
							foreach ($data as $row) {
								// Add data for subject groupings here
								if($row["grouped"]){
									$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
								}else{
									$data1 = '';
								}
								$exp = array('expanded' => mb_strtolower($rootSubName1[0]['subjectName'][0]));
								$row[] = $exp;
								$row[] = $data1;
								$row[] = $featured;
								$alldata1[] = $row;
							}
							
							print json_encode($alldata1);
							// $featured = $this->Subject->get_featured_group($userId);
							//print json_encode($featured);
							exit;
							break;
		
						case "select_":
							
							list($junk,$grouping,$subjectIdm[0],$subjectIdm[1],$subjectIdm[2],$subjectIdm[3],$subjectIdm[4],$subjectIdm[5],$subjectIdm[6]) = explode('_',$postVars[$postKeys[$i]]);
					
							if (!isset($grouping) || rfilter_var($grouping,FILTER_VALIDATE_INT,true)===false) {
								return false;
							}
							$firstFlag = false;
							for ($m=0; $m<7; $m++) {
								if ($subjectIdm[$m] != null && rfilter_var($subjectIdm[$m],FILTER_VALIDATE_INT,false)!==false) {
									if ($firstFlag == false) {
										if ($grouping != '0') {
											$this->Subject->group_subject_branch($userId,$subjectIdm[$m]);
											#$this->Subject->follow_subject_group($userId,$subjectIdm[$m],false);
										}
										$firstFlag = true;
									}
									$data=$this->Subject->get_subject_branch($subjectIdm[$m], $userId, 1,true);
						
									$featured=$this->Subject->get_featured_group($userId);
									$rootSubName1 = $this->Subject->get_root_subjectname_from_subjectId($userId,$subjectIdm[$m]);	
									
									foreach ($data as $row) {
										// Add data for subject groupings here
										if ($row["grouped"]) {
											$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
										} else {
											$data1='';
										}
										$exp = array('expanded' => mb_strtolower($rootSubName1[0]['subjectName'][0]));
										$row[] = $exp;
										$row[] = $data1;
										$row[] = $featured;
										$alldata1[$m][] = $row;
									}
									continue;
								} else {
									$alldata1[$m][]=null;
								}
							}
							
							print json_encode($alldata1);
							exit;
							//print 'done';
							break;
							
						case "update_":
							$alphabet = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
							foreach ($alphabet as $letter) {
					
								if ($postVars[$letter] != "null") {
					
									list($base,$subjectIdm[0],$subjectIdm[1],$subjectIdm[2],$subjectIdm[3],$subjectIdm[4],$subjectIdm[5]) = explode('_',$postVars[$letter]);
									if (!isset($base) || rfilter_var($base,FILTER_VALIDATE_INT,true)===false) {
										return false;
									}
									if ($base == '1') {
										$alldata1[$letter]["base"] = $this->Subject->get_subject_base($userId,$letter);
										$featured = $this->Subject->get_featured_group($userId);
										$alldata1[$letter]["base"][0]['featuredGroup'] = $featured;
									}
									for ($m=0; $m<6; $m++) {
										if ($subjectIdm[$m] != null && rfilter_var($subjectIdm[$m],FILTER_VALIDATE_INT,false)!==false) {
							
											$data = $this->Subject->get_subject_branch($subjectIdm[$m], $userId, 1,true);
							
											$featured = $this->Subject->get_featured_group($userId);
											$rootSubName1 = $this->Subject->get_root_subjectname_from_subjectId($userId,$subjectIdm[$m]);	
											foreach ($data as $row) {
												// Add data for subject groupings here
												if ($row["grouped"]) {
													$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
												} else {
													$data1 = '';
												}
												$exp = array('expanded' => mb_strtolower($rootSubName1[0]['subjectName'][0]));
												$row[] = $exp;
												$row[] = $data1;
												$row[] = $featured;
												$alldata1[$letter][$m][] = $row;
											}
											continue;
										} else{
											$alldata1[$letter][$m][]=null;
										}
									}
								}
							}
		
							print json_encode($alldata1);
							exit;
							break;
							
						case "selec1_":
				
							list($junk,$subjectId) = explode('_',$postVars[$postKeys[$i]]);
							if (!isset($subjectId) || rfilter_var($subjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							
							$data = $this->Subject->get_subject_branch($subjectId, $userId, 1,true);
							$featured = $this->Subject->get_featured_group($userId);
							$rootSubName1 = $this->Subject->get_root_subjectname_from_subjectId($userId,$subjectId);	
					
							foreach ($data as $row) {
								// Add data for subject groupings here
								if ($row["grouped"]) {
									$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
								} else {
									$data1='';
								}
						
								$exp = array('expanded' => mb_strtolower($rootSubName1[0]['subjectName'][0]));
								$row[] = $exp;
								$row[] = $data1;
								$row[] = $featured;
								$alldata1[] = $row;
							}
							
							print json_encode($alldata1);
							exit;
							//print 'done';
							break;
							
						case "selec2_":
				
							list($junk,$subjectName) = explode('_',$postVars[$postKeys[$i]]);
							
							$groupSubject = $this->Subject->get_selected_subjectId($subjectName);
							
							$featured = $this->Subject->get_featured_group($userId);
							$rootSubName = $this->Subject->get_root_subjectname($subjectName,$userId);
					
							$this->Subject->group_subjects($featured[0]['subjectName'],$subjectName, $userId,TRUE);
					
							//## mn mn call addSubjectToUserList here?
							$data = $this->Subject->get_subject_branch($featured[0]['featuredGroup'], $userId, 1,true);
					
							foreach ($data as $row) {
								// Add data for subject groupings here
								if ($row["grouped"]) {
									$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
								} else {
									$data1='';
								}
						
								$exp = array('expanded' => mb_strtolower($rootSubName[count(array_keys($rootSubName))-1]['subjectName'][0]));
								$row[] = $exp;
								$row[] = $data1;
								$row[] = $featured;
								$alldata1[] = $row;
							}
							
							print json_encode($alldata1);
							exit;
							//print 'done';
							break;
							
						case "groups_":
		
							list($junk,$groupsSubjectId) = explode('_',$postVars[$postKeys[$i]]);
							if (!isset($groupsSubjectId) || rfilter_var($groupsSubjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							$this->Subject->group_subject_branch($userId,$groupsSubjectId);
							break;
		
						case "featur_":
		
							list($junk,$subjectId) = explode('_',$postVars[$postKeys[$i]]);
							if (!isset($subjectId) || rfilter_var($subjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							$this->Subject->set_featured_group($userId,$subjectId);print 'done';exit;
							break;
		
						case "unfeat_":
							if(!isset($subjectId) || rfilter_var($subjectId,FILTER_VALIDATE_INT,false)===false) {
								return false;
							}
							list($junk,$subjectId) = explode('_',$postVars[$postKeys[$i]]);
							$this->Subject->unset_featured_group($userId,$subjectId);print 'done';exit;
							break;
					}
				}
		
				// Go through the posted vars again, find the nv, dv, snv, sdv of any subject to be shrunk (see database design
				// for a description of nv, dv etc.)
				for ($i = 0, $ilen = count($postKeys); $i < $ilen; $i++) {
					$substring = mb_substr($postVars[$postKeys[$i]], 0, 7);
					$featured = $this->Subject->get_featured_group($userId);

					switch ($substring) {
						case "reqexp_":
							break;
						case "expand_":
							break;
						case "shrink_":
							break;
						case "follow_":
							break;
						case "unfoll_":
							break;
						case "delete_":
							break;
						case "group1_":
							break;
						case "select_":
							break;
						case "selec1_":
							break;
						case "selec2_":
							break;
						case "groups_":
							break;
						case "saveit_":
							break;
						case "featur_":
							break;
						case "unfeat_":
							break;
						default:

							list($parentId,$expstate, $subjectId, $letter, $level, $nv, $dv, $snv, $sdv)  = explode('_',$postVars[$postKeys[$i]]);
							if( isset($subjectId) && rfilter_var($subjectId,FILTER_VALIDATE_INT,false)===false){
								return false;
							}
							if( isset($parentId) && rfilter_var($parentId,FILTER_VALIDATE_INT,false)===false){
								return false;
							}
							if( isset($expstate) && ($expstate!='con' && $expstate != 'exp')){
								return false;
							}
							if( isset($letter) && !preg_match('/^[a-zA-Z]$/',$letter)){
								return false;
							}
							if( isset($level) && rfilter_var($level,FILTER_VALIDATE_INT,true)===false){
								return false;
							}
							if( !isset($nv) || rfilter_var($nv,FILTER_VALIDATE_FLOAT,true)===false){
								return false;
							}
							if( !isset($dv) || rfilter_var($dv,FILTER_VALIDATE_FLOAT,true)===false){
								return false;
							}
							if( !isset($snv) || rfilter_var($snv,FILTER_VALIDATE_FLOAT,true)===false){
								return false;
							}
							if( !isset($sdv) || rfilter_var($sdv,FILTER_VALIDATE_FLOAT,true)===false){
								return false;
							}
							if ( $subjectId == $shrinkedSubjectId){
								$shrinkednv = $nv; $shrinkeddv = $dv; $shrinkedsnv = $snv; $shrinkedsdv = $sdv;
							} else if ( isset($expandedLevel) && $level == $expandedLevel && $expstate == 'con') {
								$shrinkedSubjectId = $subjectId;
								$shrinkednv = $nv; $shrinkeddv = $dv; $shrinkedsnv = $snv; $shrinkedsdv = $sdv;
							}
					}
				}

				// Go through the posted vars a third time, add rows to the data array according to status of shrunk, expanded, followed, unfollowed etc.
				for ($i = 0, $ilen = count($postKeys); $i < $ilen; $i++) {
					$substring = mb_substr($postVars[$postKeys[$i]], 0, 7);
					
					switch ($substring) {
						case "reqexp_":
							break;
						case "expand_":
							break;
						case "shrink_":
							break;
						case "follow_":
							break;
						case "unfoll_":
							break;
						case "delete_":
							break;
						case "group1_":
							break;
						case "groups_":
							break;
						case "saveit_":
							break;
						case "select_":
							break;
						case "selec1_":
							break;
						case "selec2_":
							break;
						case "featur_":
							break;
						case "unfeat_":
						case "trendL_":
							break;
						default:
							list($parentId, $expstate, $subjectId, $letter, $level, $nv, $dv, $snv, $sdv)  = explode('_',$postVars[$postKeys[$i]]);
							
							$data = $this->Subject->get_subject($subjectId, $userId); 
							
							if ($data != NULL) {
								$result = $data[0];
								if ($result['dv'] != 0 && $result['sdv'] !=0) {
									$pre =  $result['nv']/$result['dv'];
									$next = $result['snv']/$result['sdv'];
								}
							}
					
							if ($subjectId == $expandedSubjectId && $expstate != 'con') {
								foreach ($data as $row) {
									// Add data for subject groupings here
									if ($row["grouped"]) {
										$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
									} else {
										$data1='';
									}
						
									$exp = array('expanded' => $letter);
									$row[] = $exp;
									$row[] = $data1;
									$row[] = $featured;
									$alldata['query'][] = $row;
								}
								if ($substring == 'trendL') {
									$data = $this->Subject->get_top_trending_500($subjectId, $userId);
								} else {	
									$data = $this->Subject->get_subject_branch($subjectId, $userId, 1,false);
								}
								foreach ($data as $row) {
						
									// Add data for subject groupings here
									$access = true;
									if ($row["private"] == true ) {
										$access = $this->Subject->check_access($row["subjectId"],$userId);
									}
									if ($access != false) {
										if ($row["grouped"]) {
											$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
										} else {
											$data1 = '';
										}
										$row[] = $notexp;
										$row[] = $data1;
										$row[] = $featured;
										$alldata['query'][] = $row;
									}
								}
							
							} else if ($subjectId == $shrinkedSubjectId) {
					
								// Add data for subject groupings here
								foreach ($data as $row) {
									if ($row["grouped"]) {
										$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
									} else {
										$data1 = '';
									}
						
									$row[] = $notexp;
									$row[] = $data1;
									$row[] = $featured;
									$alldata['query'][] = $row;
								}
							} else if ($shrinkeddv != 0 && $shrinkedsdv !=0 && $pre > $shrinkednv/$shrinkeddv && $pre < $shrinkedsnv/$shrinkedsdv) {
								;
							} else if ($parentId != $shrinkedSubjectId && $expstate == 'exp') {
								// Add data for subject groupings here
								foreach ($data as $row) {
									if ($row["grouped"]){
										$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
									} else {
										$data1 = '';
									}
						
									$row[] = $notexp;
									$row[] = $data1;
									$row[] = $featured;
									$alldata['query'][] = $row;
								}
							} else if ($parentId != $shrinkedSubjectId && $expstate == 'con') {
					
								// Add data for subject groupings here
								foreach ($data as $row) {
									if ($row["grouped"]) {
										$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
									} else {
										$data1 = '';
									}
						
									$exp = array('expanded' => $letter);
									$row[] = $exp;
									$row[] = $data1;
									$row[] = $featured;
									$alldata['query'][] = $row;
								}
							}
					}
				}
			}
			
			$alldata['userDOB'] =  $this->user->getDOB($userId);
			$this->load->view('subjectv',$alldata);
			
		} else { // The code formerly known as 'getRows'
			
			// Check auth, get userId here.
			$userId = $this->user->authCheck();
	
			$scrollTop = 0;
			$scrollLeft = 0;
			$result = rawurldecode($result);
			$this->load->model('globals');
			$this->load->model('user');
			$this->load->model('Subject');
			$this->load->model('group');
	
			if (is_numeric($result) ){
				$this->load->model('group');
				$subjectName = $this->group->getName($result);
				$selectedSubId = $result;
			} else {
				$subjectName = $result;
				$selectedSubId = $this->group->findByName($subjectName);
			}
			// Get the root subject name, use it to set the display page
			$rootSubName = $this->Subject->get_root_subjectname($subjectName,$userId);
			
			// Prompt user if $result not matched
			if (!isset($rootSubName[0]['subjectName']) || $rootSubName[0]['subjectName'] == '') {
				print '<p align="center" style="margin-top:300px;color:white;">Sorry, we could not find a match for your query "'.$subjectName.'".  Please try again.</p>';
				print '<p align="center" style="margin-top"350px;"><input type=\'button\' value=\'Search again\' class=\'button\' onclick=\'history.back()\'></p>';
				exit;
			}
			$rootIndex = array_keys($rootSubName);
			$rootIndex = count($rootIndex);
			if($rootIndex > 4) {
				$scrollLeft = ($rootIndex-4)*200;
			}
			
			$exp = array('expanded' => mb_substr($rootSubName[$rootIndex-1]['subjectName'],0,1));
			$notexp = array('expanded' => false);
		
			$alldata['query'] = array();
			$flag1 = false;
			$flag2 = false;
		
			// Get the subjectId associated with the selected subjectName
			//...
		
			// Get ALL subjects the user can view
			$data = $this->Subject->get_subject_branch(1,$userId,1,false);
			$featured = $this->Subject->get_featured_group($userId);
			$data1 = $this->Subject->get_subject_groups($userId, $featured[0]['featuredGroup']);
			$data2 = $this->Subject->get_subject_groups($userId, $selectedSubId);

			// Loop over ALL subjects
			foreach ($data as $row) {
				$access = true;
				if ($row["private"] == true) {
					$access = $this->Subject->check_access($row["subjectId"], $userId);
					if ($access == false) {
						continue;
					}
				}
				
				// Increment scrolltop if the first letter of selected forum matches the first letter of this forum and flag remains on
				if ($flag1 == false && mb_substr($row['subjectName'],0,1) == mb_substr($rootSubName[$rootIndex-1]['subjectName'],0,1)) {
					$scrollTop += 36;
				}
				
				// Turn off increment scrolltop flag if we've hit the selected forum
				if ($row['subjectName'] == $rootSubName[$rootIndex-1]['subjectName']) {
					$flag1 = true;
				}
				if ($row['subjectId'] == $selectedSubId) {
					$flag1 = true;
					$flag2 = true;
				}
				
				// Not in the root branch of the selected forum
				if ($row['subjectId'] != $rootSubName[$rootIndex-1]['subjectId']) {
		
					$row[] = $notexp;
					if ($row["grouped"]) {
						$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
					} else {
						$data1='';
					}
			
					$row[] = $data1;
					$row[] = $featured;
					$alldata['query'][] = $row;
				
				// In the root branch of the selected forum
				} else {
		
					// check expandable or not here
					if ($row['hasOffSpring'] == true) {
						$row[] = $exp;
					} else {
						$row[]=$notexp;
					}
					$data1 = $this->Subject->get_subject_groups($userId, $row['subjectId']);
					$row[] = $data1;
					$row[] = $featured;
					$alldata['query'][] = $row;
					
					$rf = $this->Subject->get_rf_status($selectedSubId);
					
					for ($i = $rootIndex-1; $i>=0; $i--) {
						$data3 = $this->Subject->get_subject_branch($rootSubName[$i]['subjectId'],$userId,1,false);
						$flag2 = false;
						foreach ($data3 as $row3) {
							
							$access = true;
							if ($row3["private"] == true ) {
								$access = $this->Subject->check_access($row3["subjectId"],$userId);
								if ($access == false) {
									continue;
								}
							}
							if ($row3['hasOffSpring'] == true && $i > 0 && $row3['subjectName'] == $rootSubName[$i-1]['subjectName']) {
					
								$flag2 = true;
								$row3[] = $exp;
							} else {
								$row3[] = $notexp;
							}
							// Add data for subject groupings here
							if ($row3["grouped"]) {
								$data1 = $this->Subject->get_subject_groups($userId, $row3['subjectId']);
							} else {
								$data1 = '';
							}
							
							$row3[] = $data1;
							$row3[] = $featured;
							$alldata['query'][] = $row3;
							
							// Need to turn off increment scrolltop flag if the selected forum is the parent forum of this one, as we need to stay at the top
							if ($row3['parentSubjectId'] === $selectedSubId) {
								$flag2 = true;
							}
							
							if ($flag2 == false) {
								$scrollTop += 36; 
								// If we've reached the selected forum, turn off increment scrolltop flag
								if ($i > 0 && $row3['subjectName'] == $rootSubName[$i-1]['subjectName']) {
									$flag2 = true;
								}
							}
							if ($row3['subjectId'] == $selectedSubId) {
								$flag = true;
							}
						}
					}
					
					if (!empty($rf)) {
						$data4 = $this->Subject->get_subject_branch($rf,$userId,1,false);
						?> <script>console.log('$data4', <? echo json_encode($data4, true);?>);</script> <?
						$myOffspring = $this->my_array_search($alldata['query'],$selectedSubId);
						
						$alldata['query'][$myOffspring][0] = $exp;
				
						foreach ($data4 as $row4) {
				
							$access = true;
							if ($row4["private"] == true ) {
								$access = $this->Subject->check_access($row4["subjectId"],$userId);
								if ($access == false) {
									continue;
								}
							}
				
							$row4[] = $notexp;
				
							// Add data for subject groupings here
							$data1 = $this->Subject->get_subject_groups($userId, $row4['subjectId']);
							
							$row4[] = $data1;
							$row4[] = $featured;
							$alldata['query'][] = $row4;
						}
					}
				}
			}
	
			$alldata['query']['location']['page'] = mb_substr($rootSubName[$rootIndex-1]['subjectName'],0,1);
		
			$alldata['query']['location']['subject'] = $rootSubName[0]['subjectName'];
			$alldata['query']['location']['scrollTop'] = $scrollTop === 0 ? $scrollTop : $scrollTop - 36;
		
			$alldata['query']['location']['scrollLeft'] = $scrollLeft;
		
			$alldata['userDOB'] =  $this->user->getDOB($userId);
			$this->load->view('subjectv', $alldata);
		}
	}
	
	private function my_array_search($myArray,$parent) {

		for ($z=count($myArray)-1; $z >= 0; $z--) {
			if (isset($myArray[$z]) && $myArray[$z]["parentSubjectId"] == $parent && $myArray[$z]["subjectName"]=="ramblur forums") {
				return($z);
			}
		}
		return false;
	}
}
?>