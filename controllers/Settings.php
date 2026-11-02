<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Settings extends Tabbed_Controller {

    protected $title = 'settings';
    protected $indexRedirect = '/settings/user-profile';
        /*     * **************************** USER ******************************* */

    public function user() {
        redirect('/settings/user-profile');
    }

    function user_profile() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('node');
        $this->load->model('file');

        $data["user"] = array();
        $data["user"]["info"] = $this->user->info($user, true);
        $this->loadViews('settings/userProfile', $data);
    }

	function user_info() {
		if (!$user = $this->initialiseUser()) {
			//error_log("USER_INFO: Could not initialise user ".$referrer);
			return;
		}
		if ( isset($_SERVER['HTTP_REFERER']) ) {
			$referrer = $_SERVER['HTTP_REFERER'];
		} else {
			$referrer='referrer not known';
		}

		// load the other libraries we need
		$this->load->model('file');
		$this->load->model('node');
		$this->load->model('globals');

		$subValue = $this->input->post('submit');
		$componentData = array();
		$userData = $this->node->userInfo($user);
		
		// if a form was submitted
		if( $this->input->post('submit')) {
			
			if ($this->input->post('updateType') === 'profile') {
				
				$this->load->library('form_validation');
				$this->form_validation->set_rules('store_firstName', 'First Name', 'trim|min_length[2]|max_length[128]');
				$this->form_validation->set_rules('store_lastName', 'Last Name', 'trim|min_length[2]|max_length[128]');
				$this->form_validation->set_rules('store_skype', 'Skype', 'trim');
				$this->form_validation->set_rules('store_facetime', 'Facetime', 'trim');
				$this->form_validation->set_rules('store_postcode', 'Postcode', 'trim|alpha_numeric_spaces|min_length[2]|max_length[20]');
				$this->form_validation->set_rules('store_mobile', 'Mobile', 'trim|numeric|regex_match[/^[0-9]{10}$/]');
				
				if ($this->form_validation->run() === FALSE) {
					$componentData["error"] = '<span class="sttsErrorTxt">'.validation_errors().'</span>';
					//error_log("USER_INFO: Input failed form validation ".$referrer." ".$user." ".validation_errors());
				} else {
					$profileData = [];
					
					$storeVars = array();
					$postVars = $this->input->post(NULL, TRUE);
					foreach($postVars AS $k => $v) {
						// if we're meant to store it
						if (mb_substr($k, 0, 6) == 'store_') {
							$storeVars[mb_substr($k, 6)] = $v;
						}
					}

					// if there are any left to store
					if (count($storeVars) > 0) {
						$success = $this->user->updateUserInfo($user, $storeVars);
					}
					if ($success) {
						$componentData['message'] = '<span class="sttsSuccessTxt"><i class="fa fa-thumbs-up fa-lg"></i>Your details were successfully updated</span>';
					} else {
						$componentData["error"] = '<span class="sttsErrorTxt">Your details could not be updated. Try again.</span>';
					}
				}
			} else if ($this->input->post('updateType') === 'avatar') {
				if (isset($_FILES["store_avatar"]) && $_FILES["store_avatar"]["error"] <= 0) {
					// upload the avatar
					$error = false;
					$result = $this->user->uploadAvatar($user, $_FILES["store_avatar"]["tmp_name"], $error);
					
					if ($error !== false) {
						$componentData["avatarError"] = '<span class="sttsErrorTxt">'.$error.' Try again.</span>';
					} else {
						$componentData['avatarMessage'] = '<span class="sttsSuccessTxt"><i class="fa fa-thumbs-up fa-lg"></i>Your new avatar was successfully updated</span>';
					}
				}
			}
		}
		$componentData["user"] = $this->user->info($user);

		$componentData['completePercentage'] = $this->user->getProgressbarPercentage($componentData["user"]["properties"]); 

		$componentData["saveHandler"] = 'settings/user-info/' . $user;
		$userData["user"]["userEditView"] = $this->load->view('components/editableUserProfile', $componentData, TRUE);
		$this->loadViews('settings/userInfo', $userData);
	}

    function user_password() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $data = array();
        $storeVars = array();
        $storeVars['userId'] = $user;
        $storeVars['password'] = $this->input->post('store_password');
        $storeVars['newPassword'] = $this->input->post('store_newpassword');
        $storeVars['action'] = $this->input->post('submit');

        if (($this->input->post('submit'))) {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('store_password', 'Password', 'required');
            $this->form_validation->set_rules('store_newpassword', 'New Password', 'required|differs[store_password]');
            $this->form_validation->set_rules('store_newpassword1', 'Confirm New Password', 'required|matches[store_newpassword]');

            if ($this->form_validation->run() === FALSE) {
                $data["error"] = validation_errors();
            } else if ($this->input->post('submit') == 'save') {
                if ($this->input->post('store_newpassword1') != $this->input->post('store_newpassword')) {
                    $data['error'] = 'New Passwords not matched!';
                }
                /* else if(($this->input->post('store_newpassword1') == $this->input->post('store_newpassword'))&&($this->input->post('store_password') == $this->input->post('store_newpassword')))
                  {
                  $data['message'] = 'New Password must be different from Old password!';
                  } */ else {
                    if ($this->user->changePassword($storeVars)) {
                        $data['message'] = 'Password changed Successfully!';
                    } else {
                        $data['error'] = 'Wrong Password!';
                    }
                }
            }
        }
        $this->loadViews('settings/userPassword', $data);
    }

    function user_suspend() {

        if (!$user = $this->initialiseUser()) {
            return;
        }

        $data = array();

        if (($this->input->post('submit'))) {

            $this->load->library('form_validation');
            $this->form_validation->set_rules('store_password', 'Password', 'required');

            if ($this->form_validation->run() === FALSE) {

                $data["error"] = validation_errors();

            } else {

                $storeVars = array();
                $storeVars['userId'] = $user;
                $storeVars['password'] = $this->input->post('store_password');
                $storeVars['action'] = $this->input->post('submit');
                if ($this->input->post('submit') === 'suspend') {

                    if ($this->user->suspend($storeVars)) {

                        $data['message'] = 'Account suspended!';
                        $data['messageCode'] = 'S';

                    } else $data['message'] = 'Incorrect password!';

                } else if ($this->input->post('submit') === 'delete') {

                    if ($this->user->suspend($storeVars)) {

                        $data['message'] = 'Account deleted!';
                        $data['messageCode'] = 'D';

                    } else $data['message'] = 'Incorrect password!';

                }

            }

        }

        $this->loadViews('settings/userSuspend', $data);

    }

    //linked accounts.PhaseII
    function user_accounts() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->loadViews('settings/userLinkedAccounts');
    }

    function user_permissions() {
        if (!$user = $this->initialiseUser()) {
            return;
        }

        // load the other libraries we need
        $this->load->model('node');

        // if a form was submitted
        if (( $this->input->post('submit') ) && ( $this->input->post('submit') == 'save' )) {

            $postVars = $this->input->post(NULL, TRUE);
            $postKeys = array_keys($postVars);
            $storeVars = array();

            // go through all the posted vars
            for ($i = 0, $ilen = count($postKeys); $i < $ilen; $i++) {
                // if we're meant to store it
                if (mb_substr($postKeys[$i], 0, 6) == 'store_') {
                    if ($postVars[$postKeys[$i]] == 'yes') {
                        $storeVars[mb_substr($postKeys[$i], 6)] = true;
                    } else {
                        $storeVars[mb_substr($postKeys[$i], 6)] = false;
                    }
                }
            }

            // if there are any left to store
            if (count($storeVars) > 0) {
                // call the api to store it all
                $this->user->updatePermissions($storeVars);
            }
            $data['message'] = 'Permissions saved successfully.';
        }
        // show the page
        $data['user'] = $this->node->userInfo($user);
        $this->loadViews('settings/userPermissions', $data);
    }
	
	
	/* draft post admin tool */
	
	public function draftPosts_draftPosts() {
		$this->draftPosts();
	}

	public function draftPosts() {

		if ($this->uri->segment(3) == 'manage') {
		
			
			$this->load->model('user');
			$data["isAdmin"] = false;
			$data["userId"] = $this->user->authCheck();
			if (!$data["userId"] && !$this->user->isDraftMetaUser($data["user"])) {
				return false;
			}

			$this->load->model('group');

			$data["isAdmin"] = false;
			$data["action"] = 'index';
			$data["forums"] = $this->group->getDraftForumsNamesAndIds();
			$data["draftMetaUsers"] = $this->group->getDraftMetaUsers();
			$data["draftUsers"] = $this->group->getDraftUsers();
			$data["draftPriorityDates"] = $this->group->getDraftPriorityDates();
			$this->loadViews('admin/draftPosts', $data);
		} else if ($this->uri->segment(3) == 'messages') {
			$this->draftPostsMessages();
		} else {
			$this->draftPostsOverview();
		}
	}
	
	public function draftPostsOverview_draftPostsOverview() {
		$this->draftPostsOverView();
	}
	
	public function draftPostsOverview() {
		
		
		$this->load->model('group');
		$this->load->model('user');
		
		$data["userId"] = $this->user->authCheck();
		$data["isAdmin"] = false;
		$data["action"] = 'index';
		$data["forums"] = $this->group->getAdminDraftForumsNamesAndIds();
		$data["draftMetaUsers"] = $this->group->getDraftMetaUsers();
		$data["draftUsers"] = $this->group->getDraftUsers();
		$data["draftPriorityDates"] = $this->group->getDraftPriorityDates();

		$data["forumSelection"] = $this->input->post('forumSelection');
		$data["priorityDatesOnly"] = $this->input->post('priorityDatesOnly');
		
		/**
		 * Determines if the headers in the generated table are 
		 * grouped by forum or outputted for each post
		 * 
		 * When ordering by priority we don't want grouping
		 */
		$data['groupForumHeaders'] = true;
		if($data['priorityDatesOnly']){
			$data['groupForumHeaders'] = false;
		}
		
		
		$data["draftPostsOverview"] = $this->group->getDraftPostsOverview($data["forumSelection"],$data["priorityDatesOnly"]);
		
		$this->load->helper("date_utils_helper");
		$this->loadViews('admin/draftPostsOverview', $data);
	}
	
	public function draftPostsMessages() {
		
		$this->load->model('message');
		
		$data["isAdmin"] = false;
		$data["draftMessages"] = $this->message->getDraftMessages();
		
		$this->loadViews('admin/draftPostsMessages', $data);
	}
	

	/* end post admin tool */
	
	
	
	
	

    /*     * **************************** MEDIA ******************************* */

   // function media() {
     //   redirect('settings/media-pending');
    //}
    
   // function media_pending() {
    	//if (!$user = $this->initialiseUser()) {
    	//	return;
    	//}
    	//$this->loadViews('settings/mediaPending');
    //}

/*     function media_images() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('library');
        // if a submit was done
        if ($this->input->post('submit') !== false) {
            // the user is uploading something
            if ($this->input->post('submit') == 'upload') {
                // if media was uploaded...
                if ($_FILES["store_media"]["error"][0] <= 0) {
                    // if a group was specified
                    if (( $this->input->post('store_group') !== false ) && ( $this->input->post('store_group') != '' )) {
                        // upload the file related to the selected group
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"][0], 'group' => $this->input->post('store_group')));
                    } else {
                        // just upload the file to the users account
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"][0]));
                    }
                }
            } /* else if ( $this->input->post('submit') == 'add link' ) {
              $this->file->store(array('link' => $this->input->post('upLink'), 'group' => $this->input->post('imageMedia_group') ));
              // the user is wanting to 'remove' something from their gallery
              } else if ( $this->input->post('submit') == 'remove' ) {
              // unlink the specified file from the user account (stays linked to any groups/posts/etc)
              $this->node->unrelate($this->uri->segment(3), $user);
              } */
/*        }
        // get a list of groups
        $groups = $this->user->groups(false);
        //$categories = $this->group->getCategories();
        //$media = $this->group->extras(array('group' => $user, 'extra' => 'gallery'));
        $data1 = array();
        $data1["subjectId"] = '2'; //$this->uri->segment(3);
        $media["libs"] = $this->library->getLibNames($data1);
        $media["files"] = $this->library->getUserContent($data1);
        $media["no_images_warning"]="Ooops! No images in gallery!";
        $this->loadViews('settings/mediaImages', array('media' => $media, 'groups' => $groups));
    }

    function media_videos() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('library');
        // if a submit was done
        if ($this->input->post('submit') !== false) {
            // the user is uploading something
            if ($this->input->post('submit') == 'upload') {
                // if media was uploaded...
                if ($_FILES["store_media"]["error"] <= 0) {
                    // if a group was specified
                    if (( $this->input->post('store_group') !== false ) && ( $this->input->post('store_group') != '' )) {

                        // upload the file related to the selected group
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"], 'group' => $this->input->post('store_group')));
                    } else {
                        // just upload the file to the users account
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"]));
                    }
                }
            } else if ($this->input->post('submit') == 'add link') {
                $this->file->store(array('link' => $this->input->post('upLink'), 'group' => $this->input->post('store_group')));
                // the user is wanting to 'remove' something from their gallery
            }/* else if ( $this->input->post('submit') == 'remove' ) {
              // unlink the specified file from the user account (stays linked to any groups/posts/etc)
              $this->node->unrelate($this->uri->segment(3), $user);
              } */
/*        }
        // get a list of groups
        $groups = $this->user->groups();
        //$media = $this->group->extras(array('group' => $user, 'extra' => 'gallery'));
        $data1 = array();
        $data1["subjectId"] = '2'; //$this->uri->segment(3);
        $media["libs"] = $this->library->getLibNames($data1);
        $media["files"] = $this->library->getUserContent($data1);
        $this->loadViews('settings/mediaVideos', array('media' => $media, 'groups' => $groups));
    }

    function media_audio() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('library');
        // if a submit was done
        if ($this->input->post('submit') !== false) {
            // the user is uploading something
            if ($this->input->post('submit') == 'upload') {
                // if media was uploaded...
                if ($_FILES["store_media"]["error"] <= 0) {
                    // if a group was specified
                    if (( $this->input->post('soundMedia_group') !== false ) && ( $this->input->post('soundMedia_group') != '' )) {

                        // upload the file related to the selected group
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"], 'group' => $this->input->post('soundMedia_group')));
                    } else {
                        // just upload the file to the users account
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"]));
                    }
                }
            } else if ($this->input->post('submit') == 'add link') {
                $this->file->store(array('link' => $this->input->post('upLink'), 'group' => $this->input->post('soundMedia_group')));
                // the user is wanting to 'remove' something from their gallery
            } /* else if ( $this->input->post('submit') == 'remove' ) {
              // unlink the specified file from the user account (stays linked to any groups/posts/etc)
              $this->node->unrelate($this->uri->segment(3), $user);
              } */
/*        }
        // get a list of groups
        $groups = $this->user->groups();
        //$media = $this->group->extras(array('group' => $user, 'extra' => 'gallery'));
        $data1 = array();
        $data1["subjectId"] = '2'; //$this->uri->segment(3);
        $media["libs"] = $this->library->getLibNames($data1);
        $media["files"] = $this->library->getUserContent($data1);
        $this->loadViews('settings/mediaAudio', array('media' => $media, 'groups' => $groups));
    }

    function media_drawings() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('library');
        // if a submit was done
        if ($this->input->post('submit') !== false) {
            // the user is uploading something
            if ($this->input->post('submit') == 'upload') {
                // if media was uploaded...
                if ($_FILES["store_media"]["error"] <= 0) {
                    // if a group was specified
                    if (( $this->input->post('media_group1') !== false ) && ( $this->input->post('media_group1') != '' )) {

                        // upload the file related to the selected group
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"], 'group' => $this->input->post('media_group1')));
                    } else {
                        // just upload the file to the users account
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"]));
                    }
                }
            }
        }
        // get a list of groups
        $groups = $this->user->groups();
        //$media = $this->group->extras(array('group' => $user, 'extra' => 'gallery'));
        $data1 = array();
        $data1["subjectId"] = '2'; //$this->uri->segment(3);
        $media["libs"] = $this->library->getLibNames($data1);
        $media["files"] = $this->library->getUserContent($data1);
        $media["type"] = 'drawings';
        $this->loadViews('settings/mediaDrawings', array('media' => $media, 'groups' => $groups));
    }

    function media_maps() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('library');
        // if a submit was done
        if ($this->input->post('submit') !== false) {
            // the user is uploading something
            if ($this->input->post('submit') == 'add link') {
                $this->file->store(array('link' => $this->input->post('upLink'), 'group' => $this->input->post('store_group')));
                // the user is wanting to 'remove' something from their gallery
            } /* else if ( $this->input->post('submit') == 'remove' ) {
              // unlink the specified file from the user account (stays linked to any groups/posts/etc)
              $this->node->unrelate($this->uri->segment(3), $user);
              } */
/*        }
        // get a list of groups
        $groups = $this->user->groups();
        //$media = $this->group->extras(array('group' => $user, 'extra' => 'gallery'));
        $data1 = array();
        $data1["subjectId"] = '2'; //$this->uri->segment(3);
        $media["libs"] = $this->library->getLibNames($data1);
        $media["files"] = $this->library->getUserContent($data1);
        $this->loadViews('settings/mediaMaps', array('media' => $media, 'groups' => $groups));
    }

    /**
     * [media_files description]
     * @return [type] [description]
     */
/*    function media_files() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('library');
        // if a submit was done
        if ($this->input->post('submit') !== false) {//var_dump($this->input->post('store_group'));
            // the user is uploading something
            if ($this->input->post('submit') == 'upload') {
                // if media was uploaded...
                if ($_FILES["store_media"]["error"] <= 0) {
                    // if a group was specified
                    if (( $this->input->post('media_group1') !== false ) && ( $this->input->post('media_group1') != '' )) {

                        // upload the file related to the selected group
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"], 'group' => $this->input->post('media_group1')));
                    } else {
                        // just upload the file to the users account
                        $this->file->store(array('local' => $_FILES["store_media"]["tmp_name"]));
                    }
                }
            } else if ($this->input->post('submit') == 'add link') {
                $this->file->store(array('link' => $this->input->post('upLink'), 'group' => $this->input->post('media_group1')));
                // the user is wanting to 'remove' something from their gallery
            } /* else if ( $this->input->post('submit') == 'remove' ) {
              // unlink the specified file from the user account (stays linked to any groups/posts/etc)
              $this->node->unrelate($this->uri->segment(3), $user);
              } */
/*        }
        // get a list of groups
        $groups = $this->user->groups();
        //$media = $this->group->extras(array('group' => $user, 'extra' => 'gallery'));
        $data1 = array();
        $data1["subjectId"] = '2'; //$this->uri->segment(3);
        $media["libs"] = $this->library->getLibNames($data1);
        $media["files"] = $this->library->getUserContent($data1);
        $this->loadViews('settings/mediaAttachments', array('media' => $media, 'groups' => $groups));
    }

    function media_all() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('library');
        // if a submit was done
        if ($this->input->post('submit') !== false) {//var_dump($this->input->post('store_group'));
            if ($this->input->post('submit') == 'Add link') {
                $this->file->store(array('link' => $this->input->post('upLink'), 'group' => $this->input->post('store_group')));
                // the user is wanting to 'remove' something from their gallery
            } /* else if ( $this->input->post('submit') == 'remove' ) {
              // unlink the specified file from the user account (stays linked to any groups/posts/etc)
              $this->node->unrelate($this->uri->segment(3), $user);
              } */
/*        }
        // get a list of groups
        $groups = $this->user->groups();
        //$media = $this->group->extras(array('group' => $user, 'extra' => 'gallery'));
        $data1 = array();
        $data1["subjectId"] = '2'; //$this->uri->segment(3);
        $media["libs"] = $this->library->getLibNames($data1);
        $media["files"] = $this->library->getUserContent($data1);
        $this->loadViews('settings/mediaAll', array('media' => $media, 'groups' => $groups));
    } */

    /*     * **************************** INVITE ******************************* */

    public function invite() {
        redirect('/settings/invite-invite');
    }

    function invite_invite() {
        if (!$user = $this->initialiseUser()) {
            return;
        }
        $data = array();
        $this->load->library('form_validation');
        $this->form_validation->set_rules('store_name', 'name', 'required');
        $this->form_validation->set_rules('store_email', 'email', 'required');
        if ($this->input->post('submit') !== false && $this->input->post('submit') == 'invite') {
            // the user is uploading something
            if ($this->form_validation->run() === FALSE) {
                $data["error"] = validation_errors();
            } else {
                $inviteMessage = strip_tags(trim($this->input->post('store_message')));
                $success = $this->user->sendFriendInvites([['email' => $this->input->post('store_email'), 'name' => $this->input->post('store_name')]], $inviteMessage);
                
                if ($success) {
                    $data['message'] = 'Invited successfully.';
                } else {
                    $data['error'] = 'Error.';
                }
            }
        }
        $this->loadViews('settings/inviteFriend', $data);
    }

    /*     * **************************** ERRORS ******************************* */

    public function errors() {
        redirect('/settings/errors-report');
    }


    function errors_report() {

        if (!$userId = $this->initialiseUser()) {
            return;
        }
        $data = array();
        // if a submit was done
        $this->load->library('form_validation');
        $this->form_validation->set_rules('store_Title', 'Title', 'required');
        $this->form_validation->set_rules('store_Description', 'Description', 'required');
		$this->form_validation->set_rules('store_Device', 'Device', 'required');
		$this->form_validation->set_rules('store_Browser', 'Browser', 'required');
		$this->form_validation->set_rules('store_OS', 'OS', 'required');

        if ($this->input->post('submit') !== false && $this->input->post('submit') == 'report') {
            // the user is uploading something
            if ($this->form_validation->run() === FALSE) {
                $data["error"] = validation_errors();
            } else {
                $this->load->model('jira_issue', 'issue');

				$data["userEmail"] = $this->user->getUserEmail($userId);
                $data["summary"] = $this->input->post('store_Title');
                $data["description"] = $this->input->post('store_Description');
				$data["device"] = $this->input->post('store_Device');
				$data["browser"] = $this->input->post('store_Browser');
				$data["os"] = $this->input->post('store_OS');


		//send email here instead jbmo
				$this->load->model("email");
						
						$template = new \Ramblur\Email\Builder\ErrorReportEmailTemplateBuilder(Logger::getLogger());
						$template->title($data['summary'])
								->description($data['description'])
								->device($data['device'])
								->browser($data['browser'])
								->os($data['os'])
								->userEmail($data["userEmail"])
								->userId($userId);
						
						$this->email->sendErrorReportEmail('admin@ramblur.com', $template);
						$this->email->sendErrorReportEmail('jonnybuchanan@hotmail.com', $template);
				
               /* jbmo $result = $this->issue->createIssue(
						$data["userEmail"],
						$data["summary"], 
						$data["description"], 
						$data["device"], 
						$data["browser"], 
						$data["os"], 
						$userId, 
						isset($_FILES["store_Screenshot"]) && $_FILES["store_Screenshot"]["size"] !== 0 ? $_FILES["store_Screenshot"] : NULL, 
						'bug');*/
                //if ($result != null) {
                    $data['message'] = 'Error reported successfully! You\'ll receive a Ramblur Message once resolved.';
               //} else {
                //    $data['error'] = "Error report failed. Please try again.";
                //}
            }
        }
        $this->loadViews('settings/systemErrorsReport', $data);
    }
	
	
	 public function feedback() {
        redirect('/settings/feedback-report');
    }
	
	function feedback_report() {

        if (!$userId = $this->initialiseUser()) {
            return;
        }
        $data = array();
        // if a submit was done
        $this->load->library('form_validation');
        $this->form_validation->set_rules('store_Title', 'Title', 'required');
        $this->form_validation->set_rules('store_Description', 'Description', 'required');

        if ($this->input->post('submit') !== false && $this->input->post('submit') == 'report') {
            // the user is uploading something
            if ($this->form_validation->run() === FALSE) {
                $data["error"] = validation_errors();
            } else {
                $this->load->model('jira_issue', 'issue');

				$data["userEmail"] = $this->user->getUserEmail($userId);
                $data["summary"] = $this->input->post('store_Title');
                $data["description"] = $this->input->post('store_Description');


//send email here instead jbmo
				$this->load->model("email");
						
						$template = new \Ramblur\Email\Builder\FeedbackReportEmailTemplateBuilder(Logger::getLogger());
						$template->title($data['summary'])
								->description($data['description'])
								->userEmail($data["userEmail"])
								->userId($userId);
						
						$this->email->sendFeedbackReportEmail('jonnybuchanan@hotmail.com', $template);
						$this->email->sendFeedbackReportEmail('admin@ramblur.com', $template);
						$this->email->sendFeedbackReportEmail('feedback@ramblur.com', $template);


                /*jbmo $result = $this->issue->createIssue(
						$data["userEmail"],
						$data["summary"], 
						$data["description"],
						NULL,
						NULL,
						NULL,
						$userId, 
						isset($_FILES["store_Screenshot"]) && $_FILES["store_Screenshot"]["size"] !== 0 ? $_FILES["store_Screenshot"] : NULL, 
						'feedback');*/
                //if ($result != null) {
                    $data['message'] = 'Feedback submitted successfully!';
                //} else {
                 //   $data['error'] = "Feedback submission failed. Please try again.";
                //}
            }
        }
        $this->loadViews('settings/systemFeedbackReport', $data);
    }

    function errors_previous() {

        if (!$user = $this->initialiseUser()) {
            return;
        }
        $data = array();
        $this->load->model('jira_issue', 'issue');
        $data['reportedErrors'] = $this->issue->getIssuesByUser($user);

        $this->load->model('node');
        $this->load->model('jira_issue', 'issue');
        $data["user"] = array();
        $data["user"]["info"] = $this->user->info($user, true);
        $data["user"]["issues"] = $this->issue->getIssuesByUser($user);
        if ($data["user"]["info"]["level"] === 'moderator') {
            $data["user"]["moderatorSubjects"] = $this->user->getModeratorSubjects($user);
        }
        $data["user"]["moderatorProfile"] = $this->user->getModeratorProfile($user);

        $this->loadViews('settings/systemErrorsOldReports', $data);
    }

    /*     * **************************** FORUMS ******************************* */

    public function forums() {
        redirect('/settings/forums-manage');
    }
	
    function forums_manage() {
        if (!$user = $this->initialiseUser()) {
            return;
        }

        $data = array();

        // load the other libraries we need
        $this->load->model('node');
        $this->load->model('group');
        $this->load->model('file');
        $this->load->model('subject');
        $this->load->model('subjectAttribute');
        
        //if user submitted
        if ($this->input->post('submit')) {
        	if ($this->input->post('submit') == 'unfollow') { // unfollow a group
        		$res = $this->subject->follow_subject($this->uri->segment(3), $user, 0, false);
        		if ($res === true) {
        			$data['message'] = 'unfollowed  ' . $this->uri->segment(3) . ' successfully';
				} else if ($res === "lastMod") {
					$data["displayLastModeratorModal"] = true;
        		} else {
        			$data['error'] = 'error while unfollow';
        		}
        	} else if ($this->input->post('submit') == 'unlink') {// we are unlinking a follower
        		// unlink the user from the group being modified
        		if ($this->node->unfollow($this->input->post('socialId'), $this->uri->segment(3))) {
        			$data['message'] = 'unlinked ';
        		} else {
        			$data['error'] = 'error while unlinking';
        		}
        	}
        }
        
        $data["user"] = array();
        $data["user"]["info"] = $this->user->info($user, true);
        $data["user"]["followedSubjects"] = $this->user->groups(true); //print"<pre>";print_r($data["user"]["followedSubjects"]);
        //$data["user"]["bannedSubjects"] = $this->user->getBannedSubjects($user);
        //$data["user"]["suggestedSubjects"] = $this->user->getSuggestedSubjects($user);
        $socials = array();
        $socials = $this->user->people($user); //get the followers and followed users
        //arrange social users according to subjects
        foreach ($data["user"]["followedSubjects"] as $userGroups => $rows) {
            if ($socials) {
                foreach ($socials as $socialUser) {
                    if ($rows["id"] == $socialUser["subjectId"]) {
                        //$data["user"]["followedSubjects"][$userGroups]["followers"]  = $socialUser["followers"];//no need to display followers
                        $data["user"]["followedSubjects"][$userGroups]["followers"] = count($socialUser["followers"]);
                        $data["user"]["followedSubjects"][$userGroups]["followed"] = $socialUser["followed"];
                    }
                }
            } else {
                $data["user"]["followedSubjects"][$userGroups]["followers"] = 0;
                $data["user"]["followedSubjects"][$userGroups]["followed"] = 0;
            }
        }
        $this->loadViews('settings/forumsManage', $data);
    }
    
  function forums_create() {
    if(!$user = $this->initialiseUser()) {
      return;
    }
    redirect('dashboard');
  }
	function forums_suggest() {
		if(!$user = $this->initialiseUser()) {
			return;
		}
		
		$this->load->library('form_validation');
		$this->load->model('group');
		$data = array();
		$data['forumCreateDiv']='none';
		$data['categoryList']='none';
		$data['categoryListTitle']='none';
		if ( ( $this->input->post('submit') ) && ( $this->input->post('submit') == 'suggest' ) ) {
            $data["groups"] = $this->user->groups(false,$user);

            $this->form_validation->set_rules('store_name1', 'name', 'required');
            $this->form_validation->set_rules('store_description1', 'description', 'required');

            if ($this->form_validation->run() === FALSE) {
                $data["forumSuggest"] = validation_errors();
            } else {
                /*if (isset($_FILES["forumSuggestFiles"])) {
                    $extension = array("jpeg", "jpg", "png", "gif");
                    foreach ($_FILES["forumSuggestFiles"]["tmp_name"] as $key => $tmp_name) {
                        $file_name = $_FILES["forumSuggestFiles"]["name"][$key];
                        $file_tmp = $_FILES["forumSuggestFiles"]["tmp_name"][$key];
                        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                        if (in_array($ext, $extension)) {
                            //array_push($data["suggestBackgrounds"],"$file_name, ");
                        } else {
                            $data["forumSuggest"] = "Please upload proper backgrounds.";
                        }
                    }
            	}*/
                $this->load->model('group');
                //todo. send report to admin??? store_category
                $error = false;
		// name and description sanitised in Group.php
                $result = $this->group->suggestForums($this->input->post('store_name1'), $this->input->post('store_description1'), $this->input->post('store_category1'), $this->input->post('store_subcategory1'), $error);
                
                if ($result === false) {
                    if ($error) {
                        $data['forumSuggest'] = $error;
                    } else {
                        $data['forumSuggest'] = 'Something went wrong. Please try again';
                    }
                } else {
	                $data["forumSuggest"] = "The forum ".$this->input->post('store_name1')." has been successfully suggested!";
                }
                // if forum is created send message to admin
                //$this->load->model('message');
                //$this->message->systemMessage($from, $to, $subjectId, $msgType, $data = array());
            }
            $data["handler"] = '#Suggest';
		}
		$this->loadViews('settings/forumsSuggest',$data);		
	}

	function forums_adopt() {
		if(!$user = $this->initialiseUser()) {
			return;
		}

		$this->load->library('form_validation');
		$this->load->model('group');
		$data = array();
		$data['forumCreateDiv']='none';
		$data['categoryList']='none';
		$data['categoryListTitle']='none';
		$data['store_createTerms']=""; 
		if ( ( $this->input->post('submit') ) && ( $this->input->post('submit') == 'adopt' ) ) 
		{
			$data['subjectName']=$this->input->post('store_name2');
			$data['url']=$this->input->post('store_website');
			$data['softwares']=$this->input->post('store_program');
			$data['activeUsers']=$this->input->post('store_NumUsers');

            $this->form_validation->set_rules('store_name2', 'name', 'required');
            $this->form_validation->set_rules('store_website', 'URL', 'required|valid_url');
           /* url validation function regex_check(){
              $this->get->post('username');
              // Note, if this ever gets uncommented, ereg was removed in PHP 7, so update to preg
              if(eregi('^[a-zA-Z0-9._-]+@[a-zA-Z0-9-] +\.[a-zA-Z.]{2,5}$', $username)){
                 return TRUE;}else{return FALSE;}
            }*/
            $this->form_validation->set_rules('store_program', 'description', 'required');
            $this->form_validation->set_rules('store_NumUsers', 'Active Users', 'required|numeric');

            if ($this->form_validation->run() === FALSE) {
                $data["adoptForums"] = validation_errors();
            } else {
                if ($this->input->post('store_adoptTerms')) {
                    //todo.insert into table & send report to admin???
                    $this->load->model('group');
		    // URL, subjectname and 'softwares' all sanitised in Group.php
                    $message = $this->group->adoptForums($data['subjectName'], $data['url'], $data['softwares'], $data['activeUsers']);
                    if ($message !== false) {
                        //if forum is created send message to admin
                        $data["adoptForums"] = $message;
                        //if forum is created send message to admin
                        $this->load->model('message');
                        //$this->message->systemMessage($from, $to, $subjectId, $msgType, $data = array());
                    }
                    //unset($data);
                } else {
                    $data["adoptForums"] = "Please read and accept our Terms & Conditions.";
                }
            }
            $data["handler"] = '#Adopt';
        }
		$this->loadViews('settings/forumsAdopt',$data);		
    }

    function forums_report() {
        if (!$authUser = $this->initialiseUser()) {
            return;
        }
        $data = array();
        $data["groups"] = $this->user->groups();
        if (( $this->input->post('submit') ) && ( $this->input->post('submit') == 'report' )) {
            $this->load->library('form_validation');

            $this->form_validation->set_rules('selected_forum', 'name', 'required');
            $this->form_validation->set_rules('forum_description', 'description', 'required');

            if ($this->form_validation->run() === FALSE) {
                $data["forumReport"] = validation_errors();
            } else {
                //todo.insert into table & send report to admin???
                $this->load->model('group');
		// forum_description sanitised in Group.php
                $data["forumReport"] = $this->group->reportForums($this->input->post('selected_forum'), $this->input->post('forum_description'));
                if ($data["forumReport"] !== false) {
                    //if forum is created send message to admin
                    $this->load->model('message');
                    //$this->message->systemMessage($from, $to, $subjectId, $msgType, $data = array());
                    //$data["message"] ="The forum ".$this->input->post('name')." has been successfully Reported.";
                }
                /* else
                  {
                  $data["error"] = "There was a problem Reporting the Forum!";
                  } */
            }
        }
        $this->loadViews('settings/forumsReport', $data);
    }

    /*     * **************************** HELP ******************************* */

    public function help() {
        redirect('/settings/help-help');
    }

    function help_help($nonSettings='') {
        if (!$authUser = $this->initialiseUser()) {
            return;
        }
        
        $data["nonSettings"] = FALSE;
        if($nonSettings) {
            $data["nonSettings"] = TRUE;
        }
        $this->loadViews('help/help', $data);
		//$this->loadViews('settings/helpFaq', $data);
    }

    function help_suggest() {
        if (!$authUser = $this->initialiseUser()) {
            return;
        }

        $data = array();
        if (( $this->input->post('submit') ) && ( $this->input->post('submit') == 'suggest' )) {
            $this->load->library('form_validation');

            $this->form_validation->set_rules('suggest_title', 'name', 'required');
            $this->form_validation->set_rules('suggest_description', 'description', 'required');

            if ($this->form_validation->run() === FALSE) {
                $data["helpSuggest"] = validation_errors();
            } else {
                //todo.insert into table & send suggest/feedback to admin???
                $this->load->model('group');
                $data["helpSuggest"] = $this->user->sendFeedback($this->input->post('suggest_title'), $this->input->post('suggest_description'));
                if ($data["helpSuggest"] !== false) {
                    //if forum is created send message to admin
                    $this->load->model('message');
                    //$this->message->systemMessage($from, $to, $subjectId, $msgType, $data = array());
                    //$data["message"] ="The forum ".$this->input->post('name')." has been successfully Reported.";
                }
                /* else
                  {
                  $data["error"] = "There was a problem Reporting the Forum!";
                  } */
            }
        }
        $this->loadViews('settings/helpSuggest', $data);
    }
    
    /* ***************************** ADVERTISING ******************************** */
    public function advertising_manage() {
    	if (!$authUser = $this->initialiseUser()) {
    		return;
    	}
    	$this->loadViews('settings/advertisingPending');
    }
/*     public function advertising_manage() {
    	redirect('/advertising/manage');
    }
    public function advertising_create() {
    	redirect('/advertising/adCreateMenu');
    } */

    /*     * **************************** AD ADMIN ******************************** */
    
    public function advertisingAdmin() {
    	redirect('/settings/advertisingAdmin-adAdmin');
    }

    public function advertisingAdmin_adAdmin($tier1='') {
        $this->load->model('user');
        $this->load->model('globals');
        
        if($tier1 === 'adStatsMenu') {
            $this->adStats($tier1);
        }

        $data = array();
        
        // check the authentication
        if (!$data["user"] = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($data["user"])) {
            return;
        }

        
        
        $this->loadViews('adAdmin/adAdminMenu', $data);
    }

    function in_array_r($item, $array) {
        return preg_match('/"' . $item . '"/i', json_encode($array));
    }

    function in_array_r2($item, $array) {
        //echo json_encode($array);
        if (in_array($item, $array)) {
            return true;
        } else {
            return false;
        }
    }
    
    function orderArrayByMonth($raw) {
        usort($raw, function ($item1, $item2) {
            if (date('m', strtotime($item1['month'])) == date('m', strtotime($item2['month'])))
                return 0;
            return date('m', strtotime($item1['month'])) < date('m', strtotime($item2['month'])) ? -1 : 1;
        });
        return $raw;
    }

    function orderArrayByDay($raw) {
        usort($raw, function ($item1, $item2) {
            if (date('d', strtotime($item1['month'])) == date('d', strtotime($item2['month'])))
                return 0;
            return date('d', strtotime($item1['month'])) < date('d', strtotime($item2['month'])) ? -1 : 1;
        });
        return $raw;
    }

    //unused
    function _orderArrayByForumId($raw) {
        usort($raw, function ($item1, $item2) {
            if ($this->adadminmodel->getForumId($item1['forumName'])->subjectId == $this->adadminmodel->getForumId($item2['forumName'])->subjectId)
                return 0;
            return $this->adadminmodel->getForumId($item1['forumName'])->subjectId < $this->adadminmodel->getForumId($item2['forumName'])->subjectId ? -1 : 1;
        });
        return $raw;
    }

    function orderArray($raw, $col) {

        usort($raw, function ($item1, $item2) use ($col) {
            if ($item1[$col] === $item2[$col])
                return false; //was 0
            return $item1[$col] < $item2[$col] ? -1 : true; //was 1
        });
        return $raw;
    }

    function orderArrayDesc($raw, $col) {

        usort($raw, function ($item1, $item2) use ($col) {
            if ($item1[$col] === $item2[$col])
                return false; //was 0
            return $item1[$col] > $item2[$col] ? -1 : true; //was 1
        });
        return $raw;
    }

    function addMissingPlots($raw, $col1, $col2, $arr) {

        foreach ($arr as $val) {
            if (!$this->in_array_r($val, $raw)) {
                $raw[count($raw)][$col1] = $val;
                $raw[count($raw) - 1][$col2] = '0';
            }
        }
        return $raw;
    }

    public function adStats($tier1 = '', $tier2 = '', $tier3 = '') {
        
        $this->load->model('user');
        $this->load->model('globals');
        $this->load->model('adadminmodel');
        
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }


        $yAxisChartData = array();
        $charData = array();
        $data['xTitleRotation'] = 0;
        $data['chartWidth'] = '800px';
        $data['sectionTitle'] = $tier1;
        $data['sectionTitle2'] = $tier2;
        $data['sectionTitle3'] = $tier3;
        $data['yAxisTitle'] = $tier1 === 'revenue' ? 'Revenue (AUD)' : 'Number of Ads';
        $data["years"] = $this->adadminmodel->getAdYears();
        $adStyles = $this->adadminmodel->getAllAdStyles();
        $adCostModels = $this->adadminmodel->getAllCostModels();
        $adCats = $this->adadminmodel->getAllAdCategories();


        /* @todo span years
          if ($date1 && $date2) {
          then get months between dates and tag year on them for range that spans yeasrs
          }
         * */

        //if year not sent then set to current year
        if (!$year = $this->input->post('year')) {
            $year = date('Y');
        }
        if (!$data["years"]) {
            $data["years"][0]["year"] = $year;
        }

        $date1 = $this->input->post('adDate1Period');
        $date2 = $this->input->post('adDate2Period');
        $data["date1"] = $date1;
        $data["date2"] = $date2;

        if ($date1 && $date2) {
            $data["dateRangeVisibility"] = 'visibility:visible';
        } else {
            $data["dateRangeVisibility"] = 'visibility:hidden';
        }

        $month = null;
        for ($i = 1; $i <= 14; $i++) {
            $data["adMonthRadioStatus" . $i] = null;
        }
        if ($month = $this->input->post('adMonthRadioPeriod')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioForum')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioInternational')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioCountry')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioState')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioPostcode')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioAgeGroup')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioGender')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioUpdate')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        } else
        if ($month = $this->input->post('adMonthRadioYear')) {
            $data["adMonthRadioStatus" . $month] = 'checked="checked"';
        }

        if ($month && $month < 10) {
            $month = '0' . $month;
        }

        $days[] = null;
        $daysMax = 31;
        $Days29 = array('02');
        $Days30 = array('04', '06', '09', '11');

        if (in_array($month, $Days29)) {
            $daysMax = 29;
        }
        if (in_array($month, $Days30)) {
            $daysMax = 30;
        }

        for ($i = 1; $i <= $daysMax; $i++) {
            if ($i < 10) {
                $days[] = $year . '-' . $month . '-' . '0' . $i;
            } else {
                $days[] = $year . '-' . $month . '-' . $i;
            }
        }

        $months = array('January', 'February', 'March', 'April',
            'May', 'June', 'July', 'August',
            'September', 'October', 'November', 'December');

        $i = 0;

        $allTime = FALSE;
        //convert month number into name
        if ($month) {
            if ($month === '13') { //by year
                $month = null;
            } else if ($month === '14') { //All Time
                $month = null;
                $allTime = TRUE;
            } else {
                $month = date('F', mktime(0, 0, 0, $month));
            }
        }
        $data["currentYear"] = $year;

        $data["adTypeShowRadioStatus"] = 'checked="checked"';
        ;
        $data["adTypeGroupRadioStatus"] = null;
        $data["adTypeCatRadioStatus"] = null;
        $data["adTypeCostModelRadioStatus"] = null;
        $data["groupedBy"] = null;
        if ($this->input->post('adTypeRadio') === 'groupAdTypes') {
            $data["adTypeGroupRadioStatus"] = 'checked="checked"';
            $data["adTypeShowRadioStatus"] = null;
            $data["adTypeCatRadioStatus"] = null;
            $data["adTypeCostModelRadioStatus"] = null;
        } else if ($this->input->post('adTypeRadio') === 'showAdTypes') {
            $data["adTypeGroupRadioStatus"] = null;
            $data["adTypeShowRadioStatus"] = 'checked="checked"';
            $data["adTypeCatRadioStatus"] = null;
            $data["adTypeCostModelRadioStatus"] = null;
            $data["groupedBy"] = 'styles';
        } else if ($this->input->post('adTypeRadio') === 'showAdCats') {
            $data["adTypeGroupRadioStatus"] = null;
            $data["adTypeShowRadioStatus"] = null;
            $data["adTypeCostModelRadioStatus"] = null;
            $data["adTypeCatRadioStatus"] = 'checked="checked"';
            $data["groupedBy"] = 'cats';
        } else if ($this->input->post('adTypeRadio') === 'showAdCostModels') {
            $data["adTypeGroupRadioStatus"] = null;
            $data["adTypeShowRadioStatus"] = null;
            $data["adTypeCatRadioStatus"] = null;
            $data["adTypeCostModelRadioStatus"] = 'checked="checked"';
            $data["groupedBy"] = 'costModels';
        }
        $data["adTypeRadio"] = $this->input->post('adTypeRadio');

        //Active Status checked initialy
        $data["adStatusRadioStatus1"] = 'checked="checked"';
        for ($i = 2; $i <= 8; $i++) {
            $data["adStatusRadioStatus" . $i] = null;
        }
        $statusId = 1;
        for ($i = 1; $i <= 8; $i++) {
            if ($this->input->post('adStatusRadio') == $i) { //Active
                $data["adStatusRadioStatus" . $i] = 'checked="checked"';
                $statusId = $i;
            }
        }
        $data["adLimitRadioStatus1"] = 'checked="checked"';
        for ($i = 2; $i <= 4; $i++) {
            $data["adLimitRadioStatus" . $i] = null;
        }
        for ($i = 1; $i <= 4; $i++) {
            if ($this->input->post('adLimitRadio') == $i) {
                $data["adLimitRadioStatus" . $i] = 'checked="checked"';
            }
        }
        $limit = null;
        switch ($this->input->post('adLimitRadio')) {
            case 1: $limit = 10;
                break;
            case 2: $limit = 20;
                break;
            case 3: $limit = 50;
                break;
            case 4: $limit = null;
                break;
        }

        $data["adFreeRadioStatus1"] = null;
        $data["adFreeRadioStatus0"] = 'checked="checked"';

        $freeAds = 0;
        if ($this->input->post('adFreeRadio') == 1) {

            $data["adFreeRadioStatus1"] = 'checked="checked"';
            $data["adFreeRadioStatus0"] = null;
            $freeAds = 1;
        } else {
            $data["adFreeRadioStatus0"] = 'checked="checked"';
            $data["adFreeRadioStatus1"] = null;
            $freeAds = 0;
        }

        $title = 'All Ads';
        $data["allRevenue"] = FALSE;
        $data["allNumbers"] = FALSE;
        switch ($tier1) {
            case 'adStatsMenu':
                $this->loadviews('adAdmin/adStats');
                break;
            case 'adDestiniesMenu':
                $this->loadviews('adAdmin/adDestinies');
                break;
            case 'allRevenue':
                $data["allRevenue"] = TRUE;
                $data['sectionTitle'] = 'revenue';
                $data['sectionTitle2'] = 'period';
                $data["numberOfDataArrays"] = 0;
                $groupBy = 'style';
                $title = "Ad Type";
                if ($data["groupedBy"] === 'styles') {
                    $groupBy = 'style';
                } else if ($data["groupedBy"] === 'costModels') {
                    $groupBy = 'costModel';
                    $title = "Cost Model";
                } else if ($data["groupedBy"] === 'cats') {
                    $groupBy = 'mainAdCategory';
                    $title = "Main Ad Category";
                }
                $raw = $this->adadminmodel->getAllStats('revenue', $groupBy);
                if (!empty($raw)) {
                    foreach ($raw as $stat) {
                        $data["yAxisChartData"][] = (double) $stat["total"];
                        $data["xAxisChartData"][] = $stat["groupedBy"];
                    }
                }
                $data['xAxisTitle'] = $title;
                $data['yAxisTitle'] = 'Revenue';
                $data["pieChartNode"] = "pieChartNode";
                $data["chartTitle"] = "Ad Revenue for All Time Grouped by " . $title;
                $this->loadviews('adAdmin/adStatCharts', $data);
                break;
            case 'allNumbers':
                $data["allNumbers"] = TRUE;
                $data['sectionTitle'] = 'numbers';
                $data['sectionTitle2'] = 'period';
                $data["numberOfDataArrays"] = 0;
                $groupBy = 'style';
                $title = "Ad Type";
                if ($data["groupedBy"] === 'styles') {
                    $groupBy = 'style';
                } else if ($data["groupedBy"] === 'costModels') {
                    $groupBy = 'costModel';
                    $title = "Cost Model";
                } else if ($data["groupedBy"] === 'cats') {
                    $groupBy = 'mainAdCategory';
                    $title = "Main Ad Category";
                }
                $raw = $this->adadminmodel->getAllStats('numbers', $groupBy);
                if (!empty($raw)) {
                    foreach ($raw as $stat) {
                        $data["yAxisChartData"][] = (double) $stat["total"];
                        $data["xAxisChartData"][] = $stat["groupedBy"];
                    }
                }
                $data['xAxisTitle'] = $title;
                $data['yAxisTitle'] = 'Number of Ads';
                $data["pieChartNode"] = "pieChartNode";
                $data["chartTitle"] = "Ad Numbers for All Time Grouped by " . $title;
                $this->loadviews('adAdmin/adStatCharts', $data);
                break;
            case 'revenue' || 'numbers' :
                switch ($tier2) {
                    case 'period' :
                        if ($date1 && $date2) {
                            $fn = 'getStatsByDateRangePeriod';
                            $xAxisTitle = 'Month';

                            //$year = 
                        } else if (!empty($month) && $month != 'alltime') {
                            $fn = 'getStatsByMonthPeriod';
                            $xAxisTitle = 'Day';
                        } else {
                            $fn = 'getStatsByYearPeriod';
                            $xAxisTitle = 'Month';
                        }

                        $raw = $this->adadminmodel->$fn($year, $tier1, $month, null, null, $date1, $date2, $statusId, $freeAds);
                        if ($month) {
                            //change month below to period then cover day and month!!!!!
                            $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $days);
                            $raw = $this->orderArray($raw, 'month');
                        } else {
                            $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $months);
                            $raw = $this->orderArrayByMonth($raw);
                        }
                        if (!empty($raw)) {
                            foreach ($raw as $stat) {
                                $data["yAxisChartData"][] = (double) $stat["monthTotal"];
                                $data["xAxisChartData"][] = $stat["month"];
                            }
                        }

                        if ($data["groupedBy"] === 'styles') {
                            $title = "Ad Type";
                            //get data for ad styles (currently do not use group by as causes problems with charts!)
                            $i = 1;
                            foreach ($adStyles as $adStyle) {
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'style', $adStyle["style"], $date1, $date2, $statusId, $freeAds);
                                $data["series" . $i] = $adStyle["style"];

                                if ($month) {
                                    $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $days);
                                    $raw = $this->orderArray($raw, 'month');
                                } else {
                                    $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $months);
                                    $raw = $this->orderArrayByMonth($raw);
                                }

                                foreach ($raw as $stat) {
                                    $data["yAxisChartData" . $i][] = (double) $stat["monthTotal"];
                                }
                                $i++;
                            }
                        } else if ($data["groupedBy"] === 'costModels') {
                            $title = "Ad Cost Model";
                            //get data for ad styles (currently do not use group by as causes problems with charts!)
                            $i = 1;

                            foreach ($adCostModels as $adcostModel) {
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'costModel', $adcostModel["costModel"], $date1, $date2, $statusId, $freeAds);

                                $data["series" . $i] = $adcostModel["costModel"];

                                if ($month) {
                                    $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $days);
                                    $raw = $this->orderArray($raw, 'month');
                                } else {
                                    $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $months);
                                    $raw = $this->orderArrayByMonth($raw);
                                }

                                foreach ($raw as $stat) {
                                    $data["yAxisChartData" . $i][] = (double) $stat["monthTotal"];
                                }
                                $i++;
                            }

                            //StylesAndcostModels not used yet
                        } else if ($data["groupedBy"] === 'StylesAndcostModels') {
                            //get data for ad styles (currently do not use group by as causes problems with charts!)
                            $i = 1;

                            foreach ($adStyles as $adStyle) {
                                foreach ($adCostModels as $adcostModel) {
                                    $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'style', $adStyle["style"], $adcostModel["costModel"], $date1, $date2, $statusId, $freeAds);

                                    $data["series" . $i] = $adStyle["style"] . ' ' . $adcostModel["costModel"];

                                    if ($month) {
                                        $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $days);
                                        $raw = $this->orderArray($raw, 'month');
                                    } else {
                                        $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $months);
                                        $raw = $this->orderArrayByMonth($raw);
                                    }

                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["monthTotal"];
                                    }
                                    $i++;
                                }
                            }
                        } else if ($data["groupedBy"] === 'cats') {
                            $title = "Ad Categories";
                            $i = 1;
                            foreach ($adCats as $adCat) {
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'mainAdCategory', $adCat["mainAdCategory"], $date1, $date2, $statusId, $freeAds);
                                $data["series" . $i] = $adCat["mainAdCategory"];
                                if ($month) {
                                    $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $days);
                                    $raw = $this->orderArray($raw, 'month');
                                } else {
                                    $raw = $this->addMissingPlots($raw, 'month', 'monthTotal', $months);
                                    $raw = $this->orderArrayByMonth($raw);
                                }
                                foreach ($raw as $stat) {
                                    $data["yAxisChartData" . $i][] = (double) $stat["monthTotal"];
                                }
                                $i++;
                            }
                        }
                        if ($month) {
                            $data['xTitleRotation'] = -90;
                        }
                        $data["numberOfDataArrays"] = $i;
                        if ($date1 && $date2) {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $xAxisTitle . " from " . $date1 . ' to ' . $date2 . '<br/> Grouped by ' . $title . '<br/>';
                        } else {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $xAxisTitle . " in " . $month . ' ' . $year . '<br/> Grouped by ' . $title . '<br/>';
                        }
                        $data['xAxisTitle'] = $xAxisTitle;
                        $data["chartNode"] = "chartNode";
                        $this->loadviews('adAdmin/adStatCharts', $data);
                        break;
                    case 'forum' :
                        $xAxisTitle = 'Forum';
                        if ($date1 && $date2) {
                            $fn = 'getStatsByDateRangeForum';
                        } else if ($month) {
                            $fn = 'getStatsByMonthForum';
                        } else {
                            $fn = 'getStatsByYearForum';
                        }

                        $raw = $this->adadminmodel->$fn($year, $limit, $tier1, $month, null, null, $date1, $date2, $statusId, $freeAds);

                        // @todo - ordering by total currently only implemented for all grouped ads
                        if (!empty($raw)) {
                            if (empty($data["groupedBy"])) {
                                $raw = $this->orderArrayDesc($raw, 'total');
                            } else {
                                $raw = $this->orderArray($raw, 'forumName');
                            }
                        }

                        if (!empty($raw)) {
                            foreach ($raw as $stat) {
                                $data["yAxisChartData"][] = (double) $stat["total"];
                                $data["xAxisChartData"][] = $stat["forumName"];
                                $forumNames[] = $stat["forumName"];
                            }
                        }

                        //if ad styles are wanted
                        if ($data["groupedBy"] === 'styles') {
                            $title = "Ad Type";
                            //get data for ad styles (currently do not use group by as causes problems with charts!)
                            $i = 1;
                            foreach ($adStyles as $adStyle) {
                                $raw = $this->adadminmodel->$fn($year, $limit, $tier1, $month, 'style', $adStyle["style"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {

                                    $raw = $this->addMissingPlots($raw, 'forumName', 'total', $forumNames);
                                    //array_multisort($raw2, $raw);
                                    $raw = $this->orderArray($raw, 'forumName');
                                    $data["series" . $i] = $adStyle["style"];

                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        }
                        //if ad cost models are wanted
                        else if ($data["groupedBy"] === 'costModels') {
                            $title = "Ad Cost Models";
                            //get data for ad styles (currently do not use group by as causes problems with charts!)
                            $i = 1;
                            foreach ($adCostModels as $adCostModel) {
                                $raw = $this->adadminmodel->$fn($year, $limit, $tier1, $month, 'costModel', $adCostModel["costModel"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'forumName', 'total', $forumNames);
                                    $raw = $this->orderArray($raw, 'forumName');
                                    $data["series" . $i] = $adCostModel["costModel"];

                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                            /*
                              array_multisort($data["yAxisChartData"],
                              $data["yAxisChartData1"],
                              $data["yAxisChartData2"],
                              $data["yAxisChartData3"],
                              $data["yAxisChartData4"],
                              );
                             * 
                             */
                        } else if ($data["groupedBy"] === 'cats') {
                            $title = "Ad Categories";
                            //get data for ad styles (currently do not use group by as causes problems with charts!)
                            $i = 1;
                            foreach ($adCats as $adCat) {
                                $data["series" . $i] = $adCat["mainAdCategory"];
                                $raw = $this->adadminmodel->$fn($year, $limit, $tier1, $month, 'mainAdCategory', $adCat["mainAdCategory"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'forumName', 'total', $forumNames);
                                    $raw = $this->orderArray($raw, 'forumName');
                                    $data["series" . $i] = $adCat["mainAdCategory"];
                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        }

                        if ($date1 && $date2) {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $xAxisTitle . "<br/>from " . $date1 . ' to ' . $date2 . '<br/> Grouped by ' . $title . '<br/>';
                        } else {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $xAxisTitle . " in " . $month . ' ' . $year . '<br/> Grouped by ' . $title . '<br/>';
                        }
                        $data['xAxisTitle'] = $xAxisTitle;
                        $data['xTitleRotation'] = -90;    // may put a condition here!
                        $data["numberOfDataArrays"] = $i;
                        if (count($raw) < 10) {
                            $data["chartWidth"] = '450px';
                        }
                        $data["chartNode"] = "chartNode";
                        $this->loadviews('adAdmin/adStatCharts', $data);
                        break;
                    case 'region' :
                        if ($date1 && $date2) {
                            $fn = 'getStatsByDateRangeRegion';
                        } else if ($month) {
                            $fn = 'getStatsByMonthRegion';
                        } else {
                            $fn = 'getStatsByYearRegion';
                        }
                        $raw = $this->adadminmodel->$fn($year, $tier1, $limit, $month, $tier3, null, null, $date1, $date2, $statusId, $freeAds);
                        $i = 0;
                        if (!empty($raw)) {
                            // @todo - ordering by total currently only implemented for all grouped ads
                            if (empty($data["groupedBy"])) {
                                $raw = $this->orderArrayDesc($raw, 'total');
                            } else {
                                $raw = $this->orderArray($raw, $tier3);
                            }
                            foreach ($raw as $stat) {
                                if ($stat[$tier3] !== 'None') {
                                    $data["yAxisChartData"][] = (double) $stat["total"];
                                    $data["xAxisChartData"][] = $stat[$tier3];
                                    $regions[] = $stat[$tier3];
                                }
                            }
                        }

                        //if ad styles are wanted
                        if ($data["groupedBy"] === 'styles') {
                            $title = "Ad Type";
                            $i = 1;
                            foreach ($adStyles as $adStyle) {
                                $data["series" . $i] = $adStyle["style"];
                                $raw = $this->adadminmodel->$fn($year, $tier1, $limit, $month, $tier3, 'style', $adStyle["style"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, $tier3, 'total', $regions);
                                    $raw = $this->orderArray($raw, $tier3);

                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        }
                        //if ad cost models are wanted
                        else if ($data["groupedBy"] === 'costModels') {
                            $title = "Ad Cost Models";
                            $i = 1;
                            foreach ($adCostModels as $adCostModel) {
                                $data["series" . $i] = $adCostModel["costModel"];
                                $raw = $this->adadminmodel->$fn($year, $tier1, $limit, $month, $tier3, 'costModel', $adCostModel["costModel"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, $tier3, 'total', $regions);
                                    $raw = $this->orderArray($raw, $tier3);

                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                            //if main ad categories are wanted
                        } else if ($data["groupedBy"] === 'cats') {
                            $title = "Ad Categories";
                            $i = 1;
                            foreach ($adCats as $adCat) {
                                $data["series" . $i] = $adCat["mainAdCategory"];
                                $raw = $this->adadminmodel->$fn($year, $tier1, $limit, $month, $tier3, 'mainAdCategory', $adCat["mainAdCategory"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, $tier3, 'total', $regions);
                                    $raw = $this->orderArray($raw, $tier3);

                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        }

                        $data['xAxisTitle'] = ucfirst($tier3);
                        if ($date1 && $date2) {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $data['xAxisTitle'] . "<br/>from " . $date1 . ' to ' . $date2 . '<br/> Grouped by ' . $title . '<br/>';
                        } else {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $data['xAxisTitle'] . " in " . $month . ' ' . $year . '<br/> Grouped by ' . $title . '<br/>';
                        }
                        if (count($raw) > 3) {
                            $data['xTitleRotation'] = -90;    // may put a condition here!
                        }
                        $data["numberOfDataArrays"] = $i;
                        if (count($raw) < 10) {
                            $data["chartWidth"] = '450px';
                        }
                        if ($tier3 === 'international') {
                            $data["chartWidth"] = '380px';
                        }
                        $data["chartNode"] = "chartNode";
                        $this->loadviews('adAdmin/adStatCharts', $data);
                        break;
                    case 'ageGroup' :
                        $xAxisTitle = 'Age Group';
                        $data['xAxisTitle'] = 'Age Group';
                        if ($date1 && $date2) {
                            $fn = 'getStatsByDateRangeAgeGroup';
                        } else if ($month) {
                            $fn = 'getStatsByMonthAgeGroup';
                        } else {
                            $fn = 'getStatsByYearAgeGroup';
                        }

                        $raw = $this->adadminmodel->$fn($year, $tier1, $month, null, null, $date1, $date2, $statusId, $freeAds);
                        $i = 0;

                        if (!empty($raw)) {
                            foreach ($raw as $stat) {
                                $data["yAxisChartData"][] = (double) $stat["total"];
                                $data["xAxisChartData"][] = $stat["agegroup"];
                                $adAgeGroups[] = $stat["agegroup"];
                            }
                        }

                        //if ad styles are wanted
                        if ($data["groupedBy"] === 'styles') {
                            $title = "Ad Type";
                            $i = 1;
                            foreach ($adStyles as $adStyle) {
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'style', $adStyle["style"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'agegroup', 'total', $adAgeGroups);
                                    $raw = $this->orderArray($raw, 'agegroup');
                                    $data["series" . $i] = $adStyle["style"];
                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        }
                        //if ad cost models are wanted
                        else if ($data["groupedBy"] === 'costModels') {
                            $title = "Ad Cost Models";
                            $i = 1;
                            foreach ($adCostModels as $adCostModel) {
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'costModel', $adCostModel["costModel"], $date1, $date2, $statusId);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'agegroup', 'total', $adAgeGroups);
                                    $raw = $this->orderArray($raw, 'agegroup');
                                    $data["series" . $i] = $adCostModel["costModel"];
                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        } else if ($data["groupedBy"] === 'cats') {
                            $title = "Ad Categories";
                            $i = 1;
                            foreach ($adCats as $adCat) {
                                $data["series" . $i] = $adCat["mainAdCategory"];
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'mainAdCategory', $adCat["mainAdCategory"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'agegroup', 'total', $adAgeGroups);
                                    $raw = $this->orderArray($raw, 'agegroup');
                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        }

                        if ($date1 && $date2) {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $xAxisTitle . "<br/>from " . $date1 . ' to ' . $date2 . '<br/> Grouped by ' . $title . '<br/>';
                        } else {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $data['xAxisTitle'] . " in " . $month . ' ' . $year . '<br/> Grouped by ' . $title . '<br/>';
                        }
                        if (count($raw) < 10) {
                            $data["chartWidth"] = '450px';
                        }
                        $data["numberOfDataArrays"] = $i;
                        $data["chartNode"] = "chartNode";
                        //get x axis data ready for presentation
                        if (!empty($data["xAxisChartData"])) {
                            for ($i = 0; $i < count($data["xAxisChartData"]); $i++) {
                                $data["xAxisChartData"][$i] = $this->mapAgeGroupData($data["xAxisChartData"][$i]);
                            }
                        }
                        $this->loadviews('adAdmin/adStatCharts', $data);
                        break;
                    case 'gender' :
                        $xAxisTitle = 'Gender';
                        $data['xAxisTitle'] = 'Gender'; //is this used? Can it be set above to tier??????
                        $data["chartWidth"] = '400px';
                        if ($date1 && $date2) {
                            $fn = 'getStatsByDateRangeGender';
                        } else if ($month) {
                            $fn = 'getStatsByMonthAgeGroup';
                            $fn = 'getStatsByMonthGender';
                        } else {
                            $fn = 'getStatsByYearGender';
                        }

                        $genders = array('Both', 'Male', 'Female');
                        $data["stats"] = $this->adadminmodel->$fn($year, $tier1, $month, null, null, $date1, $date2, $statusId, $freeAds);
                        $i = 0;
                        if (!empty($data["stats"])) {
                            $data["stats"] = $this->orderArray($data["stats"], 'gender');
                            foreach ($data["stats"] as $stat) {
                                $data["stats"] = $this->orderArray($data["stats"], 'gender');
                                $data["yAxisChartData"][] = (double) $stat["total"];
                                $data["xAxisChartData"][] = $stat["gender"];
                            }
                        }

                        //if ad styles are wanted
                        if ($data["groupedBy"] === 'styles') {
                            $title = "Ad Styles";
                            $i = 1;
                            foreach ($adStyles as $adStyle) {
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'style', $adStyle["style"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'gender', 'total', $genders);
                                    $raw = $this->orderArray($raw, 'gender');
                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                        $data["series" . $i] = $adStyle["style"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        } else if ($data["groupedBy"] === 'costModels') {
                            $title = "Cost Models";
                            $i = 1;
                            foreach ($adCostModels as $adCostModel) {
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'costModel', $adCostModel["costModel"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'gender', 'total', $genders);
                                    $raw = $this->orderArray($raw, 'gender');
                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                        $data["series" . $i] = $adCostModel["costModel"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        } else if ($data["groupedBy"] === 'cats') {
                            $title = "Ad Categories";
                            $i = 1;
                            foreach ($adCats as $adCat) {
                                $data["series" . $i] = $adCat["mainAdCategory"];
                                $raw = $this->adadminmodel->$fn($year, $tier1, $month, 'mainAdCategory', $adCat["mainAdCategory"], $date1, $date2, $statusId, $freeAds);
                                if (!empty($raw)) {
                                    $raw = $this->addMissingPlots($raw, 'gender', 'total', $genders);
                                    $raw = $this->orderArray($raw, 'gender');
                                    foreach ($raw as $stat) {
                                        $data["yAxisChartData" . $i][] = (double) $stat["total"];
                                    }
                                } else {
                                    $data["yAxisChartData" . $i][] = null;
                                }
                                $i++;
                            }
                        }
                        if ($date1 && $date2) {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $xAxisTitle . "<br/>from " . $date1 . ' to ' . $date2 . '<br/> Grouped by ' . $title . '<br/>';
                        } else {
                            $data["chartTitle"] = "Ad " . ucfirst($tier1) . " by " . $data['xAxisTitle'] . " in " . $month . ' ' . $year . '<br/> Grouped by ' . $title . '<br/>';
                        }
                        $data["numberOfDataArrays"] = $i;
                        $data["chartNode"] = "chartNode";
                        $this->loadViews('adAdmin/adStatCharts', $data);
                        break;
                }
        }
    }

    public function adConfigure() {

         $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }

        $this->loadViews('adAdmin/adConfigure');
    }
    
    /*
     public function adFree() {

        $this->loadviews('adAdmin/adFree');
    }
    * 
    */

    function mapAgeGroupData($ageGroupId) {
        switch ($ageGroupId) {
            case '1' :
                return 'All';
            case '2':
                return '12 to 17';
            case '3':
                return '18 to 25';
            case '4':
                return '26 to 34';
            case '5':
                return '35 to 42';
            case '6':
                return '43 to 48';
            case '7':
                return '49 to 54';
            case '8':
                return '55 to 64';
            case '9':
                return '65 to 74';
            case '10':
                return '75 to 84';
            case 'default':
                return null;
        }
    }
    
    /* 
     * Turns ads on or off for specific forums
     */
    public function advertisingAdmin_adToggle() {
        
        if (!$userId = $data["user"] = $this->initialiseUser()) {
            return;
        }
        
        /*
         * @ todo - take this out when this function
         * is called by the dashboard (once dashboard is
         * complete), as all users should be able to toggle
         * ads on their own forums. Only adAdmin will be able
         * to toggle ads on Ramblur forums.
         * 
         */
        //if (!$this->user->isAdAdminUser($userId)) {
        //    return;
        //}
        
        $this->load->model('user');
        $this->load->model('adadminmodel');
        
        if ($this->user->isAdAdminUser($userId)) {
            $createUser = 0;
        } else {
            $createUser = $userId;
        }
        
        $adsToggledAdd = NULL;
        $adsToggledDel = NULL;
        if ($this->input->post('submit') && $adsToggledAdd = json_decode($this->input->post('forumsAdsToggledAdd'))) {
            $this->adadminmodel->updateAdsDisabled($createUser, $adsToggledAdd);
        }
        if ($this->input->post('submit') && $adsToggledDel = json_decode($this->input->post('forumsAdsToggledDel'))) {
            $this->adadminmodel->removeAdsDisabled($createUser, $adsToggledDel);
        }
        $data["forums"] = $this->adadminmodel->getForums($createUser);
        $data["adsDisabled"] = $this->adadminmodel->getAdsDisabled($createUser);
        $this->loadViews('ads/adToggle', $data);
    }
    
    
    
    /*** ad Payments ***/

    public function adPayments() {
        
        
        $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }

        $this->loadViews('adAdmin/adPayments');
    }
    
    
    
    function automateMonthlyDistributedPayments() {
        
        $this->load->model('adadminmodel');
        $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }
        
        $data = array();
        
        if ($this->adadminmodel->automatedDistributedPayments()) {
            $this->adadminmodel->disableAutomatedDistributedPaymentStatus();
        } else {
            $this->adadminmodel->enableAutomatedDistributedPaymentStatus();
        }
        
        $data = $this->getButtonStatus($data);
        
        $data["processing"] = $this->input->post('processing');
        //may as well show something when returning - so show payments waiting for auth!
        $data["showDistributedPayments"] = TRUE;
        $data["paid"] = 0;
        $data["authorize"] = 1;
        $data["authTitle"] = " Waiting Authorization ";
        $data["authButton"] = 'Authorize';
        $data["stage"] = 'Pending ';
        $data["detail"] = 'Summary ';
        $data["DistributedPayments"] = $this->adadminmodel->getDistributedPaymentsSummary($data["paid"], $data["authorize"]);
        
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/adDistributedPayments', $data);
    }
    
    /* 
     * The automated version of this function is in the cron controller
     * 
     */
    public function calculateMonthlyDistributedPayments() {

        $this->load->model('adadminmodel');
        $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
          
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }
        
        $data = array();
        
        $month = $this->input->post('month');
        $year = $this->input->post('year');
        $data["calcMessage"] = NULL;
        if ($this->input->post('submit') === 'calculateMonthlyDistributedPayments') {
            if (date('M') === mb_substr($month,0,3) && date('Y') === $year) {
                $data["calcMessage"] = "The selected period is part way through $month $year. Please wait until the<br/>
                        month is over to process this period (recommend waiting until the 2nd of next month,<br>
                        or Enable Automated Moderator Distributed Payments (via Cron))!";
            } else {
                if ($this->adadminmodel->paymentsNotAlreadyCalculated($month, $year, 0)) {
                    $this->db->trans_start();
                    $this->adadminmodel->setProcessLock('currentlyCalculating', 1);
                        $moderatorsForMonth = $data["moderatorsForMonth"] = $this->adadminmodel->getModeratorsForMonth($month, $year);
                        $this->adadminmodel->calculateMonthlyDistributedPayments($month, $year, $moderatorsForMonth);
                        //if ($this->adadminmodel->distributedPaymentsRequireAuth()) { //turned off initially - may be used later
                        $this->adadminmodel->sendPaymentAuthMessage($month, $year);
                        $this->adadminmodel->setProcessLock('currentlyCalculating', 0);
                    $this->db->trans_complete();
                    //}
                } else {
                    $data["calcMessage"] = "The Distributed Moderator Payments have previously been calculated for $month $year";
                }
            }
        }
        
        $data["processing"] = $this->input->post('processing');
        $data["scheduleButton"] = "Enable Automated Moderator Distributed Payments (via Cron)";
        $data["paymentButtonStatus"] = NULL;
        $data["paymentButtonColor"] = 'flat-primary-butt';
        
        //may as well show something when returning - so show payments waiting for auth!
        $data["showDistributedPayments"] = TRUE; 
        $data["paid"] = 0;
        $data["authorize"] = 1;
        $data["authTitle"] = " Waiting Authorization ";
        $data["authButton"] = 'Authorize';
        $data["stage"] = 'Pending ';
        $data["detail"] = 'Summary ';
        $data["DistributedPayments"] = $this->adadminmodel->getDistributedPaymentsSummary($data["paid"], $data["authorize"]);
        
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/adDistributedPayments', $data);
    }
    
    public function calculateMonthlyDistributedPaymentsCal() {
        
        $this->load->model('adadminmodel');
         $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }
        
        
        $data = array();
        $data["scheduleButton"] = "Enable Automated Moderator Distributed Payments (via Cron)";
        $data["paymentButtonStatus"] = NULL;
        $data["paymentButtonColor"] = 'flat-primary-butt';
        
        $data["months"] = $this->adadminmodel->getDistributedPaymentMonths();
        $data["years"] = $this->adadminmodel->getDistributedPaymentYears();
        
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/adDistributedPaymentsCal', $data);
    }
    

    /* 
     * 
     * This is run before processMonthlyDistributedPayments() 
     * when running manually only (i.e. not called by cron)
     * 
     * once authed then this can be run - if auth is on
     * 
     */
    
    public function preProcessMonthlyDistributedPayments() {

        $this->load->model('adadminmodel');
         $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }
        
        
        $data = array();
        
        $data["scheduleButton"] = "Enable Automated Moderator Distributed Payments (via Cron)";
        $data["paymentButtonStatus"] = NULL;
        $data["paymentButtonColor"] = 'flat-primary-butt';
        
        //show completed payment summary when returning
        $data["showDistributedPayments"] = TRUE;
        $data["paid"] = 'Processing';
        $data["authorize"] = 0;
        $data["authTitle"] = NULL;
        $data["stage"] = 'Processing ';
        $data["detail"] = 'Summary ';
        $data["processing"] = 'Processing';
        $data["DistributedPayments"] = $this->adadminmodel->getDistributedPaymentsSummary($data["paid"], $data["authorize"], 1);
        
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/adDistributedPayments', $data);
    }
    
    
    /* 
     * 
     * This is run after preProcessMonthlyDistributedPayments() 
     * when running manually only (i.e. not called by cron)
     * 
     * once authed then this can be run - if auth is on
     * 
     */
    
    public function processMonthlyDistributedPayments() {

        $this->load->model('adadminmodel');
         $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }
        
        
        $data = array();
        
        $this->db->trans_start();
            $data["monthlyPayments"] = $this->adadminmodel->processMonthlyDistributedPayments();
        $this->db->trans_complete();
        
        $data["scheduleButton"] = "Enable Automated Moderator Distributed Payments (via Cron)";
        $data["paymentButtonStatus"] = NULL;
        $data["paymentButtonColor"] = 'flat-primary-butt';
        
        //show completed payment summary when returning
        $data["showDistributedPayments"] = TRUE;
        $data["paid"] = 1;
        $data["authorize"] = 0;
        $data["authTitle"] = NULL;
        $data["stage"] = 'Completed ';
        $data["detail"] = 'Summary ';
        $data["processing"] = NULL; //don't need this here - only for pre above
        $data["DistributedPayments"] = $this->adadminmodel->getDistributedPaymentsSummary($data["paid"], $data["authorize"]);
        
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/adDistributedPayments', $data);
    }
    
    
    function getDistributedPayments() {
        
        $this->load->model('adadminmodel');
        $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
          
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }
        
        $data["showDistributedPayments"] = TRUE;
        
        if ($data["processing"] = $this->input->post('processing')) {
            $data["stage"] = $data["processing"];
        } else {
            $data["stage"] = 'Pending ';
        }
        $data["authButton"] = 'De-authorize';
        if ($paid = $this->input->post('stage') === '1') {
            $data["stage"] = 'Completed ';
        }

        $data["authTitle"] = NULL;
        $data["authorize"] = NULL;
        if($data["authorize"] = $this->input->post('authorize')) {
            $data["authTitle"] = " Waiting Authorization ";
            $data["authButton"] = 'Authorize';
        }
        
        if ($this->input->post('commitAuthorize')) {
            if (!$this->adadminmodel->getProcessLock('currentlyCalculating') && !$this->adadminmodel->getProcessLock('currentlySending')) {
                $this->db->trans_start();
                    $this->adadminmodel->setProcessLock('currentlyAuthorizing', 1);
                    $this->adadminmodel->authPayments();
                    $this->adadminmodel->setProcessLock('currentlyAuthorizing', 0);
                    $data["authorize"] = NULL;
                    $data["stage"] = 'Pending ';
                    $data["authTitle"] = NULL;
                $this->db->trans_complete();
            } else {
                log_message('error', 'getDistributedPayments() auth locked');
            }
        }

        $data["paid"] = $paid;
        
        $data["detail"] = $this->input->post('detail');
        if($data["detail"] === 'detail') {
            $data["DistributedPayments"] = $this->adadminmodel->getDistributedPayments($paid, $data["authorize"], $data["processing"]);
        } else {
            $data["DistributedPayments"] = $this->adadminmodel->getDistributedPaymentsSummary($paid, $data["authorize"], $data["processing"]);
        }
        $data = $this->getButtonStatus($data);
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/adDistributedPayments', $data);
    }
    
    
    function getButtonStatus($data) {
        
        $this->load->model('adadminmodel');
        
        if ($this->adadminmodel->automatedDistributedPayments()) {
            $data["scheduleButton"] = "Disable Automated Moderator Distributed Payments (via Cron)";
            $data["paymentButtonStatus"] = 'disabled="disabled"';
            $data["paymentButtonColor"] = 'gray';
        } else {
            $data["scheduleButton"] = "Enable Automated Moderator Distributed Payments (via Cron)";
            $data["paymentButtonStatus"] = NULL;
            $data["paymentButtonColor"] = 'flat-primary-butt';
        }
        return $data;
        
    }
    

    public function manageModeratorPaymentSettings() {
        
        $this->load->model('adadminmodel');
         $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
          
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }
        
        if ($post = $this->input->post()) {
            if($this->input->post('submit') === 'updateDistributedPaymentsConfig') {
                unset($post['submit']);
                //$post["distributedPaymentsRequireAuth"] = mb_strtolower($post["distributedPaymentsRequireAuth"]) === 'yes' ? '1' : '0';
                //note turning off payment authorization is currently disabled
                $post["distributedPaymentsRequireAuth"] = 1;
                $this->adadminmodel->updateDistributedPaymentsConfig($post);
            }
        }
            
        $data["paymentConfig"] = $this->adadminmodel->getDistributedPaymentConfig();
        $data = $this->getButtonStatus($data);
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/adDistributedPaymentsConfig', $data);
    }
    
    
    public function manageModeratorPayments() {

        $this->load->model('adadminmodel');
         $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
          
        if (!$this->user->isAdAdminUser($userId)) {
            return;
        }

        if ($post = $this->input->post()) {
            if ($this->input->post('submit') === 'updateModeratorPayments') {
                unset($post['submit']);
                $post["distributedPayments"] = mb_strtolower($post["distributedPayments"]) === 'yes' ? '1' : '0';
                $this->adadminmodel->updateDistributedPaymentInfo($post);
            }
        }

        $data["moderatorPaymentInfo"] = $this->adadminmodel->getDistributedPaymentInfo();
        $data = $this->getButtonStatus($data);
        $data['adAdminPaymentsButtonWell'] = $this->load->view('components/ads/adAdminPaymentsButtonWell', $data, TRUE);
        $this->loadViews('adAdmin/manageModeratorPayments', $data);
    }
    
    // not used
    public function _getMonthlyAdRevenue() {

        $this->load->model('adadminmodel');
         $this->load->model('user');
        // check the authentication
        if (!$this->user->authCheck()) {
            return;
        }
        
        $data = array();
        
        if ($this->input->post('submit') === 'revenue') {
            $monthlyAdRevenue = $data["monthlyAdRevenue"] = $this->adadminmodel->calculateMonthlyAdRevenue('October', '2016');
            $this->adadminmodel->insertMonthlyAdRevenue($monthlyAdRevenue);
        }
      
        $data = $this->getButtonStatus($data);
        $this->loadViews('adAdmin/adDistributedPayments', $data);
    }
    
    

    /*     * **************************** MODERATOR ******************************* */

    public function moderator() {
        redirect('/settings/moderator-terms');
    }

    function moderator_terms() {
        if (!$authUser = $this->initialiseModerator()) {
            return;
        }
        // @todo - different ts and cs for ramblur and non-ramblur mods?
        $this->loadViews('settings/moderatorTerms');
    }

    function moderator_resign() {
        if (!$authUser = $this->initialiseModerator()) {
            return;
        }
        if ($this->user->isModerator($authUser)) {
            $this->load->model('group');
            if ($this->input->post('submit') == 'resign' && $this->input->post('subject_resign')) {
                $subjectId = $this->input->post('subject_resign');
                $this->db->select("subjectName AS 'name', type")
                        ->from('Subject')
                        ->where('subjectId', $subjectId);
                $subjectResults = $this->db->get();
                if ($subjectResults->num_rows() === 0) {
                    $data['error'] = 'The subject could not be found.';
                } else {
                    $subject = $subjectResults->first_row('array');
                    $subjectName = $subject['name'];
                    $subjectType = $subject['type'];
                    if (!$this->user->canManageSubject($authUser, $subjectId)) {
                        $data['error'] = 'You cannot resign from that subject as you are not moderating it.';
                    } else {
                        // If no other users are moderating the forum, send a message to the supervisor (if ramblur) or block (if non-ramblur)
                        $currentModerators = $this->group->getModerators($subjectId);
                        if (count($currentModerators) === 1) {
                            if ($subjectType == group::TYPE_RAMBLUR) {
                                $supervisors = $this->group->getSupervisors($subjectId);
                                $this->load->model('message');
                                if ($this->user->setModeratorAssignedToSubject($authUser, $subjectId, FALSE)) {
                                    $data['message'] = 'You have successfully resigned from ' . $subjectName . ' and can no longer moderate it.';
                                    foreach ($supervisors as $super) {
                                        $this->message->systemMessage($this->user->authCheck(), $super['userId'], $subjectId, message::MSG_MODERATOR_NEEDED, array('moderatorName' => $this->user->getAuthUserName()));
                                    }
                                } else {
                                    $data['error'] = 'The attempt to resign from ' . $subjectName . ' failed. Please try again later.';
                                }
                            } else {
                                $data['error'] = 'This forum must be moderated, and so you cannot resign. Find someone to replace you, and then try again.';
                            }
                        } else {
                            if ($this->user->setModeratorAssignedToSubject($authUser, $subjectId, FALSE)) {
                                $data['message'] = 'You have successfully resigned from ' . $subjectName . ' and can no longer moderate it.';
                            } else {
                                $data['error'] = 'The attempt to resign from ' . $subjectName . ' failed. Please try again later.';
                            }
                        }
                    }
                }
            }
            $data['isModerator'] = TRUE;
            $moderatorSubjects = $this->user->getModeratorSubjects($authUser);
            $data['moderatedSubjects'] = array();
            foreach ($moderatorSubjects as $s) {
                if ($s['approved'] && $s['currentModerator']) {
                    switch ($s['type']) {
                        case group::TYPE_BUSINESS:
                            $s['icon'] = 'business';
                            break;
                        case group::TYPE_CLUB18:
                            $s['icon'] = '18plus';
                            break;
                        case group::TYPE_GENERAL:
                            $s['icon'] = 'general';
                            break;
                        case group::TYPE_RAMBLUR:
                            $s['icon'] = 'ram';
                            break;
                        case group::TYPE_RELATED:
                            $s['icon'] = 'related';
                            break;
                        case group::TYPE_SCHOOLS:
                            $s['icon'] = 'school';
                            break;
                        default:
                            $s['icon'] = '';
                    }
                    $s['type'] = $this->group->getTypeLabel($s['type']);
                    $data['moderatedSubjects'][] = $s;
                }
            }
        } else {
            $data['isModerator'] = FALSE;
        }
        $this->loadViews('settings/moderatorResign', $data);
    }

/*     function moderator_profile() {
        if (!$authUser = $this->initialiseModerator()) {
            return;
        }
        $data['user'] = $this->user->info($authUser);
        $this->loadViews('settings/moderatorProfile');
    } */

    /*     * **************************** SUPERVISOR ******************************* */

    public function supervisor() {
        redirect('/settings/supervisor-portal');
    }

    public function supervisor_portal() {
        if (!$authUser = $this->initialiseSupervisor()) {
            return;
        }
        // supervisors can only see their own portal
        if ($this->user->isSupervisor($authUser)) {
            $data['moderatorData'] = $this->reported_content->getSupervisorSummary($authUser);
            // admins and managers can choose a supervisor to view
        } else {
            $supervisorId = $this->input->post('supervisor_id') ? $this->input->post('supervisor_id') : $this->input->get('supervisor_id');
            if (!$supervisorId) {
                $data['supervisors'] = $this->user->findSupervisors();
            } else {
                if (!$this->user->isSupervisor($supervisorId)) {
                    $data['error'] = 'The user provided is not a supervisor.';
                } else {
                    $data['supervisor'] = ['id' => $supervisorId, 'name' => $this->user->getUserName($supervisorId)];
                    $data['moderatorData'] = $this->reported_content->getSupervisorSummary($supervisorId);
                }
            }
        }
        $this->loadViews('settings/supervisorPortal', $data);
    }

    public function supervisor_terms() {
        if (!$authUser = $this->initialiseSupervisor()) {
            return;
        }
        $this->loadViews('settings/supervisorTerms');
    }

    /*     * **************************** MANAGER ******************************* */

    public function manager() {
        redirect('/settings/manager-portal');
    }

    public function manager_terms() {
        if (!$authUser = $this->initialiseManager()) {
            return;
        }
        $this->loadViews('settings/managerTerms');
    }

    public function manager_portal() {
        if (!$authUser = $this->initialiseManager()) {
            return;
        }
        $data = array();
        // managers can only see their own portal
        if ($this->user->isManager($authUser)) {
            $data = $this->reported_content->getManagerSummary();
            // admins and managers can choose a supervisor to view
        } else {
            $managerId = $this->input->post('manager_id');
            if (!$managerId) {
                $data['managers'] = $this->user->findManagers();
            } else {
                if (!$this->user->managerCheck($managerId)) {
                    $data['error'] = 'The user provided is not a manager.';
                } else {
                    $data['manager'] = ['id' => $managerId, 'name' => $this->user->getUserName($managerId)];
                    $data = array_merge($data, $this->reported_content->getManagerSummary());
                }
            }
        }
        $this->loadViews('settings/managerPortal', $data);
    }
    
    
  

    /*     * **************************** ADMIN ******************************* */

    public function admin_portal() {
        if (!$this->initialiseAdmin()) {
            return;
        }
        redirect('/admin');
    }

    /*     * *************************** HELPERS ********************************** */

    protected function getTabData($userId) {
        $data = [];
        $data['user'] = $userId;
        $data["tool"] = "settings";
        // set up which tabs to use
        $data["tabs"] = array();
        $data["tabs"][] = array(
            "name" => "user",
            "label" => "USER DETAILS",
            "subTabs" => array(
                array("name" => "profile", "label" => "PROFILE"),
                array("name" => "info", "label" => "PERSONAL INFO"),
                array("name" => "password", "label" => "CHANGE PASSWORD"),
                //array("name" => "accounts", "label" => "LINKED ACCOUNTS"),// phaseII
                array("name" => "suspend", "label" => "SUSPEND"),
                array("name" => "permissions", "label" => "PERMISSIONS")
        ));
/*         $data["tabs"][] = array(
            "name" => "messages",
            "label" => "MESSAGES",
            "subTabs" => array(
                array("name" => "inbox", "label" => "INBOX"),
        )); */
        //$data["tabs"][] = array(
           // "name" => "media",
           // "label" => "MEDIA",
            //"subTabs" => array(
                /* array("name" => "images", "label" => "IMAGES"),
                array("name" => "videos", "label" => "VIDEOS"),
                array("name" => "audio", "label" => "AUDIO"),
                array("name" => "drawings", "label" => "DRAWINGS"),
                array("name" => "maps", "label" => "MAPS"),
                array("name" => "files", "label" => "ATTACHMENTS"),
                array("name" => "all", "label" => "ALL") */
            	//array("name" => "pending", "label" => "COMING SOON")
        //));
        $data["tabs"][] = array(
            "name" => "invite",
            "label" => "INVITE A FRIEND",
            "subTabs" => array(
                array("name" => "invite", "label" => "INVITE A FRIEND")
        ));
		/* jbmo2ndDec19 $data["tabs"][] = array(
            "name" => "forums",
            "label" => "FORUMS",
            "subTabs" => array(
            	//array("name" => "dashboard", "label" => "FORUM DASHBOARD"),
                array("name" => "manage", "label" => "MANAGE FORUMS"),
                array("name" => "create", "label" => "CREATE NEW FORUM", "redirect" => base_url('dashboard')),
				array("name" => "suggest", "label" => "SUGGEST NEW FORUM"),
				array("name" => "adopt", "label" => "ADOPT NEW FORUM"),
                array("name" => "report", "label" => "REPORT A FORUM")
        ));*/
		$data["tabs"][] = array(
            "name" => "feedback",
            "label" => "SUBMIT FEEDBACK",
            "subTabs" => array(
                array("name" => "report", "label" => "SUBMIT FEEDBACK"),
        ));
        $data["tabs"][] = array(
            "name" => "errors",
            "label" => "SYSTEM ERRORS",
            "subTabs" => array(
                array("name" => "report", "label" => "REPORT SYSTEM ERROR"),
                array("name" => "previous", "label" => "PREVIOUS REPORTS")
        ));
        $data["tabs"][] = array(
            "name" => "help",
            "label" => "HELP",
            "subTabs" => array(
                array("name" => "help", "label" => "FAQ"),
                array("name" => "suggest", "label" => "SUGGEST")
        ));
         $data["tabs"][] = array(
            "name" => "advertising",
            "label" => "ADVERTISING",
            "subTabs" => array(
                // Advertising controller temporarily disabled
                //array("name" => "manage", "label" => "MANAGE MY ADS", "redirect" => base_url('advertising/manage'))
            	//array("name" => "manage", "label" => "MANAGE MY ADS")
				array("name" => "manage", "label" => "COMING SOON")
        ));
		 
		if ($this->user->isDraftMetaUser($data["user"])) {
			$data["tabs"][] = [
				"name" => "draftPosts",
				"label" => "APPROVAL",
				"subTabs" => [
					["name" => "draftPosts", "label" => "DRAFT POSTS"]
				]
			];
		}

		/*
         * If user = moderator, show moderator tab, with T & Cs, resign, profile and social links subtabs
         * if user = supervisor, show supervisor tab, with T & Cs and portal subtabs
         * manager tab, with T & Cs, portal, possibly graphs, reports etc.
         * admin tab -> goes to /admin page
         */
        if ($this->user->moderatorCheck($data["user"])) {
            $data["tabs"][] = array(
                "name" => "moderator",
                "label" => "MODERATOR",
                "subTabs" => array(
                    array("name" => "terms", "label" => "TERMS & CONDITIONS"),
                    array("name" => "resign", "label" => "RESIGN"),
                    //array("name" => "profile", "label" => "PROFILE")
                )
            );
        }
        if ($this->user->supervisorCheck($data["user"])) {
            $data["tabs"][] = array(
                "name" => "supervisor",
                "label" => "SUPERVISOR",
                "subTabs" => array(
                    array("name" => "portal", "label" => "PORTAL"),
                    array("name" => "terms", "label" => "TERMS & CONDITIONS")
                )
            );
        }
        if ($this->user->managerCheck($data["user"])) {
            $data["tabs"][] = array(
                "name" => "manager",
                "label" => "MANAGER",
                "subTabs" => array(
                    array("name" => "portal", "label" => "PORTAL"),
                    array("name" => "terms", "label" => "TERMS & CONDITIONS")
                )
            );
        }
        if ($this->user->adminCheck($data["user"])) {
            $data["tabs"][] = array(
                "name" => "admin",
                "label" => "ADMIN",
                "subTabs" => array(
                    array("name" => "portal", "label" => "PORTAL", "redirect" => base_url('admin'))
                )
            );
        }
		if ($this->user->isAdAdminUser($userId)) {
        	$data["tabs"][] = array(
        		"name" => "advertisingAdmin",
        		"label" => "AD ADMIN",
        		"subTabs" => array(
        			array("name" => "adAdmin", "label" => "AD ADMIN")
        		));
        }
        return $data;
    }

	// Stores the tab id with the view, so that using back/forward browser buttons can work
    // nb. advertising and admin are redirects, so they don't need to be mapped
    protected $viewToTabMap = array(
        //'settings/forumsManage' => 'forums-manage',
        'settings/forumsReport' => 'forums-report',
        'settings/forumsSuggest' => 'forums-suggest',
    	'settings/forumsAdopt' => 'forums-adopt',
        'settings/inviteFriend' => 'invite-invite',
        'settings/managerPortal' => 'manager-portal',
        'settings/managerTerms' => 'manager-terms',
       // 'settings/mediaAttachments' => 'media-files',
       // 'settings/mediaAudio' => 'media-audio',
        //'settings/mediaImages' => 'media-images',
        //'settings/mediaMaps' => 'media-maps',
       // 'settings/mediaAll' => 'media-all',
       // 'settings/mediaVideos' => 'media-videos',
       // 'settings/mediaDrawings' => 'media-drawings',
    	//'settings/mediaPending' => 'media-pending',
        'settings/helpFaq' => 'help-help',
        'settings/helpSuggest' => 'help-suggest',
        'settings/systemErrorsOldReports' => 'errors-previous',
		'settings/systemFeedbackReport' => 'feedback-report',
        'settings/systemErrorsReport' => 'errors-report',
        'settings/userInfo' => 'user-info',
        'settings/userPassword' => 'user-password',
        'settings/userPermissions' => 'user-permissions',
        'settings/userProfile' => 'user-profile',
        'settings/userSuspend' => 'user-suspend',
        //'advertisingManage' => 'advertising-manage',
        'settings/advertisingPending' => 'advertising-manage',
        'advertisingOverview' => 'advertising-manage',
        'advertisingAdMenuScreen1' => 'advertising-createAdMenu',
        'ads/adToggle' => 'advertising-adToggle',
        'settings/moderatorTerms' => 'moderator-terms',
        'settings/moderatorResign' => 'moderator-resign',
        //'settings/moderatorProfile' => 'moderator-profile',
        'settings/supervisorPortal' => 'supervisor-portal',
        'settings/supervisorTerms' => 'supervisor-terms',
        'settings/managerPortal' => 'manager-portal',
        'settings/managerTerms' => 'manager-terms',
        'adAdmin/adAdminMenu' => 'advertisingAdmin-adAdmin',
		'admin/draftPosts' => 'draftPosts-draftPosts',
		'admin/draftPostsOverview' => 'draftPosts-draftPosts',
		'admin/draftPostMessages' => 'draftPosts-draftPosts'
    );
}
