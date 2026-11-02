<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Search extends CI_Model {

	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}
	
	function findString($srchStr,$userId)
	{
		$this->load->library('sphinxclient');
		
		$q = '==' . $srchStr;
		$this->sphinxclient->SetServer ( "localhost", 9312); 
		$this->sphinxclient->SetArrayResult ( true ); 
		$this->sphinxclient->Query ( $q, "*" ); 

		if ( isset($res['matches']) ){
			$i=0;
			foreach($res['matches'] as $match){
				$query = 'CALL getSubjectNode (?, ?)';
				$query = $this->db->query($query, array($match['id'],$userId) );
				$query = $query->row();
				
				if ( isset($query->subjectName) && $query->subjectName != NULL && $query->subjectName != '' ){
					$returnData['query'][$i]['id'] = $match['id'];
					$returnData['query'][$i]['weight'] = $match['weight'];		
					$returnData['query'][$i]['subjectName'] = $query->subjectName; 
					$returnData['query'][$i]['searchText'] = $srchStr;
					$i++;
				}
			}
		}else{
			$returnData['query'][0]['subjectName'] = "No Matches";
			$returnData['query'][0]['searchText'] = $srchStr;
		}
		return $returnData;
	}

	// User Logged In Auto Search - added cjt
	function autoSearchUli($search) {

		$userId = $this->user->authCheck();

		if($userId) {

			if(empty($search['string'])||!isset($search['string'])) {

				return 0;

			} else {

				//print_r($search);

				// setup an array of subscribed groups to compare against later on
				$groups = $this->group->getSubjects($data=false)["subs"];
				$active_groups = array();
				foreach($groups as $group) array_push($active_groups, $group['subjectId']);

				// figure out which forum types we shouldn't be looking in
				// 9999 = dummy value which will never be matched, and will be the only array item if all forum types are "on"
				// added to reduce the need for a condition to check if not2search was empty
				$not2search = array(9999);
				
				// If user is underage, club18 property won't be included, so add specifically here.
				if (!isset($search['forumsState']['club18'])) {
					$not2search[] = group::TYPE_CLUB18;
				}

				foreach($search['forumsState'] as $k => $v) {

					if($v === 0) {

						switch($k) {

							case 'related':
								array_push($not2search, group::TYPE_RELATED);
								break;

							case 'business':
								array_push($not2search, group::TYPE_BUSINESS);
								break;

							case 'general':
								array_push($not2search, group::TYPE_GENERAL);
								break;

							case 'club18':
								array_push($not2search, group::TYPE_CLUB18);
								break;

						}

					}

				}

				/*
				
				NB: removed "AND parentSubjectId = 1" to widen the search net

				*/

				//print_r($not2search);

				//print_r(implode(',', $not2search));
				
				$this->db->select("s1.subjectId as id, s1.subjectName as name, s1.description, s1.parentSubjectId as category, s1.hasOffSpring, s2.subjectName as sname, s1.type, s1.createdBy")
					->from('Subject AS s1')
					->join('Subject AS s2', 's2.subjectId = s1.parentSubjectId')
					->like('s1.subjectName', trim($search['string']))
					->where('s1.private', 0)
					->where('s1.visible', 1)
					->where_not_in('s1.type', $not2search)
					->order_by('s1.subjectName', 'ASC');
				$result = $this->db->get();
				if ($result->num_rows()>0){

					$groups = array();

					foreach ($result->result_array() as $row) {

						// pass back subscription info for status display purposes
						$row['isSubscribed'] = (in_array($row['id'], $active_groups)) ? '1' : '0';
						// Can't be private, as above query filters them out
						$row['visibility'] = $this->subjectAttribute->getForumVisibility($row['id'], false);
						$row['attributes'] = $this->subjectAttribute->getSubjectAttributesArray($row['id']);
						array_push($groups, $row);

					}

					return $groups;

				}
				return 0;

			}

		}
		return false;

	}

	// workarea type search
	function workarea($data){

		//print_r($data);

		if(!empty($data['srchWords'])&&$userId = $this->user->authCheck()){

			// reformat the array of words passed to suit REGEXP
			$srchWords = implode("|",$data['srchWords']);

			$groups = array();

			// populate my groups array if searching HOME FEED
			// kwik kludge to add single subjectId to array or fill up array if home feed
			if(intval($data['subjectId'])===1) {

				$this->db->select('subjectId'); 
				$this->db->from('UserToSubject');   
				$this->db->where( array('userId' => $userId, 'active' => 1));
				$result = $this->db->get()->result_array();

				foreach($result as $k => $v) array_push($groups, $v['subjectId']);

			} else array_push($groups, $data['subjectId']);

			// gallery now not being used in mobile...
			if($data['type']==='gallery') {

	  			$stopWords = array("and","or","the","an","if","the");

				$query = "SELECT DISTINCT l.libName, l.subjectId, l.userId, u.userName FROM Library as l, User as u WHERE l.userId = u.userId AND l.subjectId IN (".implode(',', $groups).") AND l.visibility = true AND ( ";

				foreach($data['srchWords'] as $word){

					if(!in_array($word, $stopWords) ){

						$query .= "l.libName LIKE ? OR ";
						$params = array(['%'.$this->db->escape_like_str($word).'%']);

					}

				}

				$query = mb_substr($query, 0, -4);
				$query .= ') ORDER BY l.libName;';

			} else {
				// allow for search by title or content/title
				if($data['srchParam']==='all'){
					$query = "SELECT s.subjectName, s.private as subjectPrivate, u.username, n.nodeId, n.text, p.* FROM Post AS p, Nodes AS n, User AS u, Subject as s WHERE u.userId = n.userId AND s.subjectId = p.subjectId AND p.postId = n.postId AND n.parentNodeId = 0 AND p.modDeleted = 0 AND p.postType = ? AND (p.title REGEXP ? || n.text REGEXP ?) AND s.subjectId IN (".implode(',', $groups).") AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) ORDER BY p.timeStamp DESC LIMIT 50;";
					$params = array($data['type'], $srchWords, $srchWords);

				} elseif ($data['srchParam']==='vidimg') {
					$type = ($data['type']==='image') ? 'photo' : 'link'; // <<<<<<<<<<<<<<<<<<<<<<< NB: searching for videos has to look for youtube or vimeo urls

					// PHOTO
					if($type==='photo') {

						$query = "SELECT c.*, s.private as subjectPrivate "
								. "FROM Content as c, "
								. "Subject as s, "
								. "ContentToNodes as cn "
								. "LEFT JOIN Nodes ON cn.nodeId = Nodes.nodeId "
								. "LEFT JOIN Post ON Nodes.postId = Post.postId "
								. "WHERE s.subjectId = c.subjectId AND c.description REGEXP ? "
								. "AND c.fileType = ? AND c.modDeleted = 0 "
								. "AND c.subjectId IN (".implode(',', $groups).") "
								. "AND (cn.fileName = c.fileName AND (cn.draftNode = 0 OR cn.draftNodeVisible = 1))"
								. "AND Post.modDeleted = 0 "
								
								. "ORDER BY c.timeStamp DESC LIMIT 50;";
						$params = array($srchWords, $type);

					// VIDEO
					} else {

						$query = "SELECT c.*, s.private as subjectPrivate "
								. "FROM Content as c, "
								. "Subject as s, "
								. "ContentToNodes as cn, "
								. "LEFT JOIN Nodes ON cn.nodeId = Nodes.nodeId"
								. "LEFT JOIN Post ON Nodes.postId = Post.postId"
								. "WHERE s.subjectId = c.subjectId "
								. "AND c.userTitle REGEXP ? "
								. "AND c.fileType = ? "
								. "AND c.modDeleted = 0 "
								. "AND c.subjectId IN (".implode(',', $groups).") "
								. "AND (c.URL LIKE '%www.youtube.com/embed%' OR c.URL LIKE '%player.vimeo.com%') "
								. "AND (cn.fileName = c.fileName AND (cn.draftNode = 0 OR cn.draftNodeVisible = 1)) "
								. "AND Post.modDeleted = 0 "
								. "ORDER BY c.timeStamp DESC LIMIT 50;";
						$params = array($srchWords, $type);

					}


				} else {
					$query = "SELECT s.subjectName, s.private as subjectPrivate, u.username, n.nodeId, n.text, p.* "
							. "FROM Post AS p, Nodes AS n, User AS u, Subject as s "
							. "WHERE u.userId = n.userId "
							. "AND s.subjectId = p.subjectId "
							. "AND p.postId = n.postId "
							. "AND n.parentNodeId = 0 "
							. "AND p.modDeleted = 0 "
							. "AND p.postType = ? "
							. "AND p.title REGEXP ? "
							. "AND s.subjectId IN (".implode(',', $groups).") "
							. "AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) "
							. "ORDER BY p.timeStamp DESC LIMIT 50;";
					$params = array($data['type'], $srchWords);

				}

			}

			$query = $this->db->query($query, $params);

			if($query->num_rows()>0){
				$result = $query->result_array(); 
				return $result;
			} else return '0';

		}
		return false;

	}

	// SITE WIDE SEARCH
	// forum title
	// forum desc
	// title
	// content
	// title  & content
	function site ($data) {

		//var_dump($data);

		// NLI - search forums/content !club18 & !private
		// ULI - search by settings & !private

		// figure out which forum types we shouldn't be looking in
		// 9999 = dummy value which will never be matched, and will be the only array item if all forum types are "on"
		// added to reduce the need for a condition to check if not2search was empty
		$not2search = array(9999);
		$userId = $this->user->authCheck();

		if($userId) {
			
			// If user is underage, club18 property won't be included, so add specifically here.
			if (!isset($data['forumsState']['club18'])) {
				$not2search[] = group::TYPE_CLUB18;
			}

			foreach($data['forumsState'] as $k => $v) {

				if($v === 0) {

					switch($k) {

						case 'related':
							array_push($not2search, group::TYPE_RELATED);
							break;

						case 'business':
							array_push($not2search, group::TYPE_BUSINESS);
							break;

						case 'general':
							array_push($not2search, group::TYPE_GENERAL);
							break;

						case 'club18':
							array_push($not2search, group::TYPE_CLUB18);
							break;

					}

				}

			}

		// not logged in searches ramblur forums only
		} else {

			array_push($not2search, 1, 2, 3, 4, 5);

		}

		// now used to indicate whether or not the search result is being followed by 'me'
		$userForums = array();

		// if we're logged in get a list of forums 'i'm' following
		if($userId) {

			$nonono = array('main feed', 'my feed', 'top 500 schools', 'top 500 general', 'top 500 club 18');
			$userForumsArr = $this->user->groups();

			// just need the ids of the forums
			foreach($userForumsArr as $forum) {

				if(!in_array($forum['name'], $nonono)) array_push($userForums, $forum['id']);

			}

			$userForumsFlat = implode(",",$userForums);

		}

		// whether or not to restrict the results to followed forums only
		$uf = (!empty($userForums)&&$data['searchbymfo']) ? ' AND s.subjectId IN ('.$userForumsFlat.')' : '';

		// things to consider:
		//	- type (club18, related, etc)????
		// 	- all post types? eg. blogs, etc

		$stopWords = array("and", "or", "the", "an", "if"); // convert to a CI constant/util model??
		//$stopWords = array("sdkcsdnk", "blah"); // temp array for testing

		$userWords = explode(',', mb_strtolower(str_replace(array('[', ']', '"'), '', $data['searchstr'])));

		// remove stopWords from userWords, to produce srchWords
		$srchWords = array_diff($userWords, $stopWords);

		// reformat the array of words passed to suit REGEXP
		$srchWords = implode("|",$srchWords);

		// AND s.type NOT IN (".implode(',', $not2search).")

		// what types of post not to search
		$typesno = "'message', 'edit', 'comment', 'subscription_msg'";

		switch ($data['searchby']) {

			case "fn":
				// for NLI - Ramblur forums only
				if(!$userId) {
					$query = "SELECT subjectId, subjectName, description, hasOffSpring, type FROM Subject WHERE subjectName REGEXP ? AND type = 0 AND private != 1 AND subjectName != 'ramblur forums' ORDER BY subjectName ASC;";
				} else {
					// added kwik kludge to avoid eg. 'business forums' displaying in forum searches for 'bus'
					$query = "SELECT subjectId, subjectName, description, hasOffSpring, secondaryColour, type FROM Subject WHERE subjectName REGEXP ? AND type NOT IN (".implode(',', $not2search).") AND subjectName NOT IN ('ramblur forums', 'business forums', 'general forums', 'club18 forums', 'related forums') AND private != 1 ORDER BY subjectName ASC;";
				}
				$params = array($srchWords);
				break;

			case "fd":
				// for NLI - Ramblur forums only
				if(!$userId) {
					$query = "SELECT subjectId, subjectName, description, hasOffSpring, type FROM Subject WHERE description REGEXP ? AND type = 0 AND private != 1 ORDER BY subjectName ASC;";
				} else {
					$query = "SELECT subjectId, subjectName, description, hasOffSpring, type FROM Subject WHERE description REGEXP ? AND type NOT IN (".implode(',', $not2search).") AND private != 1 ORDER BY subjectName ASC;";
				}
				$params = array($srchWords);
				break;

			case "pt":

				/* TODO: use CI query builder for these options below!! */

				// NB: EVENTS & dates?
				if(!$userId) {
					$query = "SELECT s.subjectName, u.username, n.nodeId, SUBSTRING(n.text, 1, 100) as text, p.*, DATE_FORMAT(p.timeStamp, '%d-%m-%Y') as postedDate, s.type FROM Post AS p, Nodes AS n, User AS u, Subject as s WHERE u.userId = n.userId AND s.subjectId = p.subjectId AND p.postId = n.postId AND n.parentNodeId = 0 AND p.title REGEXP ? AND p.postType NOT IN (".$typesno.") AND s.type != 4 AND s.private != 1 AND p.visible = 1  AND p.modDeleted = 0".$uf." AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) ORDER BY p.timeStamp DESC LIMIT ?, 50;";
				} else {
					$query = "SELECT s.subjectName, u.username, n.nodeId, SUBSTRING(n.text, 1, 100) as text, p.*, DATE_FORMAT(p.timeStamp, '%d-%m-%Y') as postedDate, s.type FROM Post AS p, Nodes AS n, User AS u, Subject as s WHERE u.userId = n.userId AND s.subjectId = p.subjectId AND p.postId = n.postId AND n.parentNodeId = 0 AND p.title REGEXP ? AND p.postType NOT IN (".$typesno.") AND s.type NOT IN (".implode(',', $not2search).") AND s.private != 1 AND p.visible = 1  AND p.modDeleted = 0".$uf." AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) ORDER BY p.timeStamp DESC LIMIT ?, 50;";
				}
				$params = array($srchWords, intval(trim($data['srlm'])));
				break;

			case "pc":
				if(!$userId) {
					$query = "SELECT s.subjectName, u.username, n.nodeId, SUBSTRING(n.text, 1, 100) as text, p.*, DATE_FORMAT(p.timeStamp, '%d-%m-%Y') as postedDate, s.type FROM Post AS p, Nodes AS n, User AS u, Subject as s WHERE u.userId = n.userId AND s.subjectId = p.subjectId AND p.postId = n.postId AND n.parentNodeId = 0 AND n.text REGEXP ? AND p.postType NOT IN (".$typesno.") AND s.type != 4 AND s.private != 1 AND p.visible = 1  AND p.modDeleted = 0".$uf." AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) ORDER BY p.timeStamp DESC LIMIT ?, 50;";
				} else {
					$query = "SELECT s.subjectName, u.username, n.nodeId, SUBSTRING(n.text, 1, 100) as text, p.*, DATE_FORMAT(p.timeStamp, '%d-%m-%Y') as postedDate, s.type FROM Post AS p, Nodes AS n, User AS u, Subject as s WHERE u.userId = n.userId AND s.subjectId = p.subjectId AND p.postId = n.postId AND n.parentNodeId = 0 AND n.text REGEXP ? AND p.postType NOT IN (".$typesno.") AND s.type NOT IN (".implode(',', $not2search).") AND s.private != 1 AND p.visible = 1  AND p.modDeleted = 0".$uf." AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) ORDER BY p.timeStamp DESC LIMIT ?, 50;";
				}	
				$params = array($srchWords, intval(trim($data['srlm'])));
				break;

			case "ptc":
				if(!$userId) {
					$query = "SELECT s.subjectName, u.username, n.nodeId, SUBSTRING(n.text, 1, 100) as text, p.*, DATE_FORMAT(p.timeStamp, '%d-%m-%Y') as postedDate, s.type FROM Post AS p, Nodes AS n, User AS u, Subject as s WHERE u.userId = n.userId AND s.subjectId = p.subjectId AND p.postId = n.postId AND n.parentNodeId = 0 AND (p.title REGEXP ? || n.text REGEXP ?) AND p.postType NOT IN (".$typesno.") AND s.type NOT IN (".implode(',', $not2search).") AND private != 1 AND p.visible = 1 AND p.modDeleted = 0".$uf." AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) ORDER BY p.timeStamp DESC LIMIT ?, 50;";
				} else {
					$query = "SELECT s.subjectName, u.username, n.nodeId, SUBSTRING(n.text, 1, 100) as text, p.*, DATE_FORMAT(p.timeStamp, '%d-%m-%Y') as postedDate, s.type FROM Post AS p, Nodes AS n, User AS u, Subject as s WHERE u.userId = n.userId AND s.subjectId = p.subjectId AND p.postId = n.postId AND n.parentNodeId = 0 AND (p.title REGEXP ? || n.text REGEXP ?) AND p.postType NOT IN (".$typesno.") AND s.type NOT IN (".implode(',', $not2search).") AND s.private != 1 AND p.visible = 1 AND p.modDeleted = 0".$uf." AND (p.draftPost = FALSE OR p.draftPostVisible = TRUE) ORDER BY p.timeStamp DESC LIMIT ?, 50;";
				}
				$params = array($srchWords, $srchWords, intval(trim($data['srlm'])));
				break;

		}

		if($query = $this->db->query($query, $params)) {

			if($query->num_rows()>0){

				// jiggerypokery to shoehorn in a flag for whether or not the forum is being followed
				$result = $query->result_array();
				$return = array();

				foreach($result as $row) {

					if($userId) {
						$row['following'] = (in_array($row['subjectId'], $userForums)) ? 1 : 0;
					} else {
						$row['following'] = 0; // nli = always not following
					}
					$row['attributes'] = $this->subjectAttribute->getSubjectAttributesArray($row['subjectId']);

					// if type != ramblur, fetch avatar for ULI !subd view of forum
					$row['avatar'] = null;
					if($row['type']!='0') {

						$where = array('type' => 'avatar', 'subjectId' => $row['subjectId']);
						$this->db->select('fileName')->from('SubjectDisplay')->where($where);
						$result = $this->db->get()->row();
						$row['avatar'] = $result->fileName;

					}
					array_push($return, $row);
				}
				return $return;
			} else return '0';
		}
		return false;
	}
}
?>
