<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax_suggest1 extends CI_Controller {
	
	function autocomplete () {
		$this->load->helper('search_helper');
		$ln_sph = get_sphinx_PDO();
		$ln = get_zenure_PDO();
		
		$arr =array();

		$this->load->model('user');
		$this->load->model('group');
		$id = $this->user->authCheck();
		
		// This search is only available to logged in users
		if (!$id) {
			echo json_encode($arr);
			exit();
		}

		$q = trim($this->input->get('term'));
		$searchType = $this->input->get('term2');
		$subject = trim($this->input->get('term3'));
		$cats = trim($this->input->get('term4'));
		//if (!preg_match('/^\d+(?:,\d+)*$/',$cats)){$cats='-1';} // only accept digits separated by commas.
		if (!preg_match('/^[\x30-\x39]+(?:,[\x30-\x39]+)*$/u',$cats)){$cats='-1';} // only accept ascii digits separated by commas, input may contain utf-8 chars which will be duly ignored.
		if($cats == ''){ $cats='-1';}
		
		// Check the subject exists
		$subjectName = $this->group->getName($subject);
		if ($subjectName == '') {
			echo json_encode($arr);
			exit();
		}
		
		// is the user an admin?
		$isAdmin = $this->user->adminCheck();
		
		// SubjectId=1 is an edge case, want entries for all the subjects followed by the requestor.

		if ($subject == 1) {
			$select_statement=" subjectId=:subject ";

			$p = $ln->prepare("SELECT subjectId FROM UserToSubject WHERE userId=:user AND banned<NOW() AND displayed=true and (active=true OR subjectId IN (select subjectId from subjectGroups where userId=:user and displayed=true))");
			$p->bindValue(':user', $id,PDO::PARAM_STR);
			$p->execute();
			$select_statement="subject IN (";
			foreach ($p->fetchAll() as $row) {
				$select_statement.= "'".$row['subjectId']."',";
			}
			$select_statement=mb_substr($select_statement,0,mb_strlen($select_statement)-1) .')';

		} else {
			$select_statement=" subject=:subject ";
			
			// admin users can always access any subject, so check access for others only
			if (!$isAdmin) {
				$p = $ln->prepare("SELECT private,subjectId FROM Subject WHERE subjectId=:subject LIMIT 1");
				$p->bindValue(':subject', $subject,PDO::PARAM_STR);
				$p->execute();
	
				// Check access control first.	Is this a private subject?	If yes, does the user have private access?
				// If no, is the user an administrator?
	
				$row = $p->fetch();
				// Subject should exist, as it's checked above, but check anyway
				if (!$row) {
					echo json_encode($arr);
					exit();
				}
	
				// have to be following private forum, and have access granted
				if($row['private'] == true) {

					$s = $ln->prepare('SELECT privateAccess FROM UserToSubject WHERE subjectId = :subject AND userId = :id LIMIT 1');
					$s->bindValue(':subject', $subject,PDO::PARAM_STR);
					$s->bindValue(':id', $id, PDO::PARAM_STR);
					$s->execute();
					
					$row1 = $s->fetch();
					if (!$row1 || $row1['privateAccess'] == false) {
						echo json_encode($arr);
						exit();
					}
				}
			}
		}

		// END check access control

		// $q = $this->input->post('term');
		//$stmt = $ln_sph->prepare("SELECT * FROM $indexes WHERE MATCH(:match) LIMIT 0,10 OPTION ranker=sph04");
		switch($searchType) {
			case "searchLib":
			default:
				// Look for matches to the supplied string both in libNames and photo comments.
				$stmt1 = $ln_sph->prepare("SELECT * FROM simplecompletelibrary WHERE MATCH(:name) AND ".$select_statement." LIMIT 0,20 OPTION ranker=matchany");
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompletecontent WHERE MATCH(:name) AND postmoddeleted = 0 AND  ".$select_statement." AND category NOT IN (".$cats.") LIMIT 0,20 OPTION ranker=matchany");

				$query = $this->prep_query($q);
	
				$stmt1->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt1->bindValue(':subject', $subject,PDO::PARAM_INT):'';
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt2->bindValue(':subject', $subject,PDO::PARAM_INT):'';
	
				break;
			case "searchVid":
				//$stmt1 = $ln->prepare("SELECT DISTINCT libName,subjectId FROM Library WHERE libName LIKE :name AND subjectId = :subject");
				$stmt1 = $ln_sph->prepare("SELECT * FROM simplecompletelibrary WHERE MATCH(:name) AND ".$select_statement." LIMIT 0,20 OPTION ranker=matchany");
				//$stmt2 = $ln->prepare("SELECT userTitle as 'comment',subjectId FROM Content WHERE (userTitle LIKE :name OR description LIKE :name) AND ".$select_statement." AND (fileType='video' OR fileType='link' AND URL LIKE '%youtub%')");
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompletevids WHERE MATCH(:name) AND postmoddeleted = 0 AND ".$select_statement." AND category NOT IN (".$cats.") LIMIT 0,20 OPTION ranker=matchany");
				//$aq = explode(' ',$q);
				//if(mb_strlen($aq[count($aq)-1])<2){
				//$query = '%' . $q . '%';
				//}else{
				//$query = '%' . $q . '%';
				//}
				$query = $this->prep_query($q);
				$stmt1->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt1->bindValue(':subject', $subject,PDO::PARAM_STR):'';
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt2->bindValue(':subject', $subject,PDO::PARAM_STR):'';
	
				break;
			case "searchArts":
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompletearticles WHERE MATCH(:name) AND ".$select_statement."  AND category NOT IN (".$cats.") LIMIT 0,20 OPTION ranker=matchany");
	
				$query = $this->prep_query($q);
	
				if( $query == ''){return false;}
	
				//file_put_contents('/home/tech/DATABASE/chk', $query);
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt2->bindValue(':subject', $subject,PDO::PARAM_STR):'';
				break;
			case "searchReviews":
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompletereviews WHERE MATCH(:name) AND ".$select_statement." AND category NOT IN (".$cats.") LIMIT 0,20 OPTION ranker=matchany");
	
				$query = $this->prep_query($q);
	
				if( $query == ''){return false;}
	
				//file_put_contents('/home/tech/DATABASE/chk', $query);
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt2->bindValue(':subject', $subject,PDO::PARAM_STR):'';
				break;
			case "searchEvents":
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompleteevents WHERE MATCH(:name) AND ".$select_statement." AND category NOT IN (".$cats.") LIMIT 0,20 OPTION ranker=matchany");
	
				$query = $this->prep_query($q);
	
				if( $query == ''){return false;}
	
				//file_put_contents('/home/tech/DATABASE/chk', $query);
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt2->bindValue(':subject', $subject,PDO::PARAM_STR):'';
				break;
			case "searchBlogs":
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompleteblogs WHERE MATCH(:name) AND ".$select_statement." AND category NOT IN (".$cats.") LIMIT 0,20 OPTION ranker=matchany");
	
				$query = $this->prep_query($q);
	
				if( $query == ''){return false;}
	
				//file_put_contents('/home/tech/DATABASE/chk', $query);
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt2->bindValue(':subject', $subject,PDO::PARAM_STR):'';
				break;
	
			case "searchVlogs":
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompletevlogs WHERE MATCH(:name) AND ".$select_statement." AND category NOT IN (".$cats.") LIMIT 0,20 OPTION ranker=matchany");
	
				$query = $this->prep_query($q);
	
				if( $query == ''){return false;}
	
				//file_put_contents('/home/tech/DATABASE/chk', $query);
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				$subject!=1?$stmt2->bindValue(':subject', $subject,PDO::PARAM_STR):'';
				break;
				
			case "searchUsers":
				$stmt2 = $ln_sph->prepare("SELECT * FROM simplecompleteusers WHERE MATCH(:name) LIMIT 0,20 OPTION ranker=matchany");
				$query = $this->prep_query($q);
			
				if ($query == '') {
					return false;
				}
			
				$stmt2->bindValue(':name', $query,PDO::PARAM_STR);
				break;

		}
		


		if (isset($stmt1)) {
			$stmt1->execute();
			
			foreach($stmt1->fetchAll() as $r) {
				$arr[] = array( 'id' => utf8_encode('folder'), 'label' => utf8_encode($r['libname']) );
			}
		}

		$stmt2->execute();
		
		foreach($stmt2->fetchAll() as $r){
			
			switch($_GET['term2']){
				case "searchLib":
				case "searchVid":
				default:
				  $numContentComments = $this->getNumContentComments($r['file']);
				  $arr[] = array('id' => utf8_encode('file'),
					  'label' =>utf8_encode($r['comment']),
					  'fileName' => utf8_encode($r['file']),
					  'subject' => utf8_encode($r['subject']), 
					  'numComments' => $numContentComments);
					break;
				case "searchArts":
				case "searchBlogs":
				case "searchReviews":
				case "searchEvents":
					if(!$r['postmoddeleted']){
						$abridged_text = $this->abridge_text($r['text'],$q);
						$label= $r['title'];
					}else{
						$abridged_text = "This content has been removed from the forum by a moderator";
						$label = "This content has been removed";
					}
				
					$numPostComments = $this->getNumPostComments($r['postid']);
					
					$arr[] = array('id' => utf8_encode($r['postid']),
									'nid' => utf8_encode($r['nodeid']),
									'label' =>utf8_encode($label), 
									'text' => utf8_encode($abridged_text),
									'subject' => utf8_encode($r['subject']),
									'postModDeleted' => utf8_encode($r['postmoddeleted']),
									'numComments' => $numPostComments);
					break;
				case "searchVlogs":
					$abridged_text = $this->abridge_text($r['text'],$q);
				
					$arr[] = array('id' => utf8_encode($r['postid']),'nid' => utf8_encode($r['nodeid']),'label' =>utf8_encode($r['title']), 'text' => utf8_encode($abridged_text),'fileName' => utf8_encode($r['file']),'subject' => utf8_encode($r['subject']));
					break;
				case "searchUsers":
					// don't include self
					$userid = $r['userid'];
					$email = $r['email'];
					$username = $r['username'];
					$firstName = $r['firstname'];
					$lastName = $r['lastname'];
					$avatar = $r['avatar'];
				
					// Prioritise name presentation - real name, username, email, last name, first name
					if($userid !== $id) {
						if($firstName !== '' && $lastName !== '') {
							$label = $firstName . ' ' . $lastName;
						} else if ($username !== '') {
							$label = $username;
						} else if ($email !== '') {
							$label = $email;
						} else if ($lastName !== '') {
							$label = $lastName;
						} else if ($firstName !== '') {
							$label = $firstName;
						}
						if($label !== '') {
							$userInfo = array('id' => utf8_encode($userid), 'term' => utf8_encode( $label));
							if($avatar) {
								$userInfo['userIcon'] = utf8_encode($r['avatar'].'-icon16');
							}
							$arr[] = $userInfo;
						}
					}
					break;
			}
		}

		echo json_encode($arr);
	}

	public function prep_query($q){
		$stopWords = array("and","or","the","an","if","a","the","of");
		$aq = explode(' ',$q);
		$query='';
		
		foreach ($aq as $word) {
			if (!in_array($word,$stopWords)){
				$query .= '*'.$word.'* | ';
			}
		}
		$query = trim($query,' | ');
		return ($query);
	}

	public function abridge_text($text,$target){

		$stopWords = array("and","or","the","an","if","a","the","of");

		$ab_text = '...';
		$posm = -1;
		$count =0;

		while (($posn = stripos(mb_substr($text,$posm+1), $target)) !== FALSE && $count < 6 ) {
			//file_put_contents('/home/tech/DATABASE/chk', $posn);

			if ($posn >= 20) {
				$start = $posn - 20;
			} else if ($posn<20) {
				$start=0;
			} else {
				return ('');
			}
			$ab_text = $ab_text . '...' . mb_substr(mb_substr($text,$posm+1),$start,40);
			$posm += $posn + 1;
			$count++;
		}
		if ($count == 0) {
			$aq = explode(' ',$target);
			foreach ($aq as $word) {
				$posm = -1;$count=0;
				if (!in_array($word,$stopWords)) {
					while (($posn = stripos(mb_substr($text,$posm+1), $word)) !== FALSE && $count < 6 ) {
			
						if ($posn >= 20){
							$start = $posn - 20;
						} else if ($posn<20) {
							$start=0;
						} else {
							return ('');
						}
					
						$ab_text = $ab_text . '...' . mb_substr(mb_substr($text,$posm+1),$start,40);
						$posm += $posn + 1;
						$count++;
					}
				}
			}
		}
		return ($ab_text. '...');
	}
	
	function getNumContentComments($fileName) {
        
        $this->db->select('numComments')
				->from('Content')
				->where('fileName', $fileName);

		$query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->row()->numComments;
        } else {
            return 0;
        }
    }
	
	function getNumPostComments($postId) {
        
        $this->db->select('numComments')
				->from('Post')
				->where('postId', $postId);

		$query = $this->db->get();

        if ($query->num_rows() > 0) {
			return $query->row()->numComments;
        } else {
            return 0;
        }
    }
    
}
