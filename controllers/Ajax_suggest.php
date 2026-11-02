<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax_suggest extends CI_Controller {

	
	function autocomplete () {
		$this->load->helper('search_helper');
		$ln_sph = get_sphinx_PDO();
		$ln = get_zenure_PDO();
		$indexes = 'simplecomplete';
	
		// Get user credentials
		$this->load->model('user');
		$userId = $this->user->authCheck();
		
		$arr = array();
		$query = $this->input->get('term');
		if (!$query || $query == '') {
			echo json_encode($arr);
			return;
		}
		$query = trim($query);
		$q = explode(' ',$query);
		if(isset($q[1]) && $q[1] == '**'){
			echo json_encode($arr);
			return;
		}
		// is the user an admin?
		$isAdmin = $this->user->adminCheck();

		$stmt = $ln_sph->prepare("SELECT * FROM ".$indexes." WHERE MATCH(:match)  LIMIT 0,100 OPTION ranker=sph04");
	
		$stmt->bindValue(':match', $query,PDO::PARAM_STR);
		$stmt->execute();
	
		foreach($stmt->fetchAll() as $r){
			
			// admin allowed to view all results
			if ($isAdmin) {
				$arr[] = array('id' => utf8_encode($r['title']),'term' =>utf8_encode($r['title']));
			// non-admins must check access to subject
			} else {
				$p = $ln->prepare("SELECT private,subjectId,type FROM Subject WHERE subjectName=:subject LIMIT 1");
				$p->bindValue(':subject', $r['title'],PDO::PARAM_STR);
				$p->execute();
				$subjectRow = $p->fetch();
				// shouldn't happen, but just in case
				if (!$subjectRow) {
					continue;
				}
				// anonymous users can only search for ramblur forums
				if (!$userId) {
					$this->load->model('group');
					if ((int)$subjectRow['type'] === group::TYPE_RAMBLUR) {
						$arr[] = array('id' => utf8_encode($r['title']),'term' =>utf8_encode($r['title']));
					}
				// logged in users
				} else {
					// if it's not private, everyone has access
					if ($subjectRow['private'] == false) {
						$arr[] = array('id' => utf8_encode($r['title']),'term' =>utf8_encode($r['title']));
					// if private, must have been granted access
					} else {
						$s = $ln->prepare('SELECT privateAccess from UserToSubject WHERE subjectId = :subject AND userId = :id LIMIT 1');
						$s->bindValue(':subject', $subjectRow['subjectId'],PDO::PARAM_STR);
						$s->bindValue(':id', $userId, PDO::PARAM_STR);
						$s->execute();
						$privateCheckRow = $s->fetch();
						if ($privateCheckRow && $privateCheckRow['privateAccess'] == true) {
							$arr[] = array('id' => utf8_encode($r['title']),'term' =>utf8_encode($r['title']));
						}
					}
				}
			}
		}
		echo json_encode($arr);
	}
}