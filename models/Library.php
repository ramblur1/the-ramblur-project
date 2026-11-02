<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Library extends CI_Model {

	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}

	// Takes an array of file names and adds or deletes one row per fileName in table 'library'.  Must include
	// a library name, subjectId and operation(ie add or delete) in the call

	function updateLibrary($data) {

		// check if the user is logged in
		$userId = $this->user->authCheck();

		if ($userId !== false) {
			if (isset($data['libName']) && isset($data['fileNames']) && isset($data['subjectId'])) {
				if (!is_numeric($data["subjectId"])) {
					$data["subjectId"] = $this->makeSubjectId($data["subjectId"], $userId);
				}
				$this->load->helper(array('htmlpurifier'));
				foreach ($data['fileNames'] as $fileName) {
					$query = "CALL addUserContentToLibrary(?,?,?,?,?,?)";
					$params = array($fileName, $userId, html_purify($data['libName']), '1', $data['subjectId'], $data['operation']);

					$query = $this->db->query($query, $params);
				}
			}
			return true;
		} else {
			return false;
		}
	}

	function updateComments($data) {

		// check if the user is logged in
		$userId = $this->user->authCheck();
		$i = 0;
		if ($userId !== false) {
			if (isset($data['comments']) && isset($data['fileNames'])) {
				$this->load->helper(array('stripper'));
				foreach ($data['fileNames'] as $fileName) {
					$query = "UPDATE Content SET description = ? WHERE fileName =?";
					$params = array(stripper($data["comments"][$i]), $fileName);

					$query = $this->db->query($query, $params);
					$i++;
				}
			}
			return true;
		} else {
			return false;
		}
	}

	function getLibNames($data) {

		// check if the user is logged in
		$userId = $this->user->authCheck();
		$result2 = array();

		if ($userId !== false && isset($data['subjectId'])) {
			if (!is_numeric($data["subjectId"])) {
				$data["subjectId"] = $this->makeSubjectId($data["subjectId"], $userId);
			}
			$query = "CALL getLibraryNames(?,?)";
			$params = array($userId, $data['subjectId']);

			$query = $this->db->query($query, $params);

			$result = $query->result_array();
			foreach ($result as $row) {
				$query2 = "CALL getLibraryFiles(?,?,?,?)";
				$params = array($userId, $data['subjectId'], $row["libName"], '');

				$query2 = $this->db->query($query2, $params);

				$result1 = $query2->result_array();

				$result2[]["libName"] = $row["libName"];
				$result2[count($result2) - 1]["fileNames"] = $result1;
			}
			if (!isset($result2)) {
				return false;
			} else {
				return($result2);
			}
		}
	}

	function getUserContent($data) {

		// check if the user is logged in
		/*  $userId = $this->user->authCheck();

		  if ( $userId !== false && isset($data['subjectId']) ){
		  if ( ! is_numeric($data["subjectId"]) ){
		  $data["subjectId"] = $this->makeSubjectId($data["subjectId"],$userId);
		  }
		  $query="CALL getUserContent(?,?)";
		  $params=array($userId,$data['subjectId']);

		  $query = $this->db->query($query,$params);

		  $result1 = $query->result_array();

		  $result2 = $this->getLibNames($data);

		  if (!isset($result1)){return false;}
		  else{
		  return(array_merge($result2,$result1));
		  }

		  } */

		// check if the user is logged in
		$userId = $this->user->authCheck();

		if ($userId === false || !isset($data['subjectId'])) {
			return false;
		}
		if (!is_numeric($data["subjectId"])) {
			$data["subjectId"] = $this->makeSubjectId($data["subjectId"], $userId);
		}
		if (!isset($data['type'])) {
			$data['type'] = 'all';
		}
		
		// Check user has access to subject. Content belongs more to the forum than the user, so even if there are files in the forum by the user, 
		// if the user doesn't have access to the forum any more, they can't access the content
		if(!$this->group->getUserAccess($data['subjectId'], $userId)) {
			return false;
		}

		$this->db->select("license,licensePhotoUrl, licenseUserurl, licenseTitle, licenseUsername,c.fileName,fileType,mimeType,fileSize,c.userId as 'owner',URL,numViews,timeStamp,duration,c.subjectId as 'subject', c.description as 'comment',userTitle,width,height,l.libName,s.private")
			->from("Content AS c")
			->join("Library AS l", 'l.fileName = c.fileName AND c.userId = l.userId AND l.visibility = 1', 'left')
			->join("Subject AS s", 's.subjectId = c.subjectId')
			->where(array(
				'c.userId' => $userId,
				'c.subjectId' => $data['subjectId'],
				'c.visible' => 1
			))
			->order_by('c.timeStamp');
		switch ($data['type']) {
			case 'image':
				$this->db->where("c.mimeType IN ('jpg','png','photo','image/jpeg') and c.fileType NOT IN('link','drawing')");
				break;
			case 'video':
				$this->db->where("c.mimeType IN ('jpg','video/webm','image/jpeg')  and c.fileType IN('link','photo')");
				break;
			case 'sound':
				$this->db->where("c.mimeType = 'audio/mpeg' and c.fileType = 'sound'");
				break;
			case 'drawing':
				$this->db->where("c.mimeType IN('png') and c.fileType='drawing'");
				break;
			case 'map':
				$this->db->where("c.mimeType = 'map' and c.fileType='link'");
				break;
			case 'file':
				$this->db->where("c.mimeType IN ('application/pdf','doc','docx','rtf','text/plain','application/vnd.oasis.opendocument.spreadsheet','application/octet-stream','application/msword') and c.fileType='file'");
				break;
			case 'others':
				$this->db->where("c.mimeType NOT IN ('jpg','png','photo','image/tiff','image/jpeg','audio/mpeg','video/webm')");
				break;
			case 'all':
				$this->db->where("c.mimeType IN ('jpg','png','photo','image/tiff','image/jpeg','video/webm','audio/mpeg','map','drawing','application/pdf','doc','docx','rtf','text/plain','application/vnd.oasis.opendocument.spreadsheet','application/octet-stream','application/msword')");
				break;
			default:
				break;
		}
		$result1 = $this->db->get();
		if($result1->num_rows() == 0) {
			//return false;
		}
		$result1 = $result1->result_array();

		$query1="CALL getLibraryNames(?,?)";
		$params=array($userId,$data['subjectId']);
		$query1 = $this->db->query($query1,$params);
		$result = $query1->result_array();
 
		$result2= array();
		foreach ($result as $row) {
			// $this->db->distinct();
			$this->db->select("l.fileName,c.description AS 'comment',mimeType,URL,numViews,userTitle, private")
				->from("Library AS l")
				->join("Content AS c", 'l.fileName = c.fileName')
				->join("Subject AS s", 's.subjectId = c.subjectId')
				->where(array(
					'l.subjectId' => $data['subjectId'],
					'libName'=>  $row["libName"], 
					'c.visible'=>  true
				))
				->order_by('c.timeStamp');
		
				/*SELECT DISTINCT Library.fileName,description AS 'comment',mimeType,URL,numViews,userTitle,postId,level FROM Library 
				JOIN Content ON Library.fileName = Content.fileName WHERE Library.subjectId = thisSubjectId AND libName = thisLibName AND fileType LIKE CONCAT('%',thisFileType,'%') AND Content.visible = true 
				ORDER BY description;*/
			switch ($data['type']) {
				case 'image':
					$this->db->where("c.mimeType IN ('jpg','png','photo','image/jpeg') and c.fileType NOT IN('link','drawing')");
					break;
				case 'video':
					$this->db->where("c.mimeType IN ('jpg','video/webm','image/jpeg')  and c.fileType IN('link','photo')");
					break;
				case 'sound':
					$this->db->where("c.mimeType = 'audio/mpeg' and c.fileType = 'sound'");
					break;
				case 'drawing':
					$this->db->where("c.mimeType IN('png') and c.fileType='drawing'");
					break;
				case 'map':
					$this->db->where("c.mimeType = 'map' and c.fileType = 'link'");
					break;
				case 'file':
					$this->db->where("c.mimeType IN ('application/pdf','doc','docx','rtf','text/plain','application/vnd.oasis.opendocument.spreadsheet','application/octet-stream','application/msword') and c.fileType='file'");
					break;
				case 'others':
					$this->db->where("c.mimeType NOT IN ('jpg','png','photo','image/tiff','image/jpeg','audio/mpeg','video/webm')");
					break;
				case 'all':
					$this->db->where("c.mimeType IN ('jpg','png','photo','image/tiff','image/jpeg','video/webm','audio/mpeg','map','drawing','application/pdf','doc','docx','rtf','text/plain','application/vnd.oasis.opendocument.spreadsheet','application/octet-stream','application/msword')");
					break;
				default:
					break;
			}
			$tmpresult = $this->db->get();
			$tmpresult = $tmpresult->result_array();
			$result2[]["libName"] = $row["libName"];
			$result2[count($result2)-1]["fileNames"] = $tmpresult;
		}

		if (!isset($result1)) {
			return false;
		} else {
			return(array_merge($result2,$result1));
		} 
		
		/* $data = array();//$data1 = array();
		$libs = array();
		foreach($result as $r) {
			$libName = $r['libName'];
			if(is_null($libName)) {
				$data[] = $r;
			} else {
				if(!isset($libs[$libName])) {
					$libs[$libName] = array();
				}
				$libs[$libName][] = $r;
			}
		}
		foreach($libs as $name => $files) {
			$data[] = array('libName' => $name, 'fileNames' => $files);
		}
		return $data;*/
	}

	// added cjt
	function getGalleryItems($data) {

		//var_dump($data);

		$userId = $this->user->authCheck();

		if ($userId) {

			$query = "SELECT l.*, c.modDeleted, c.modDeleteReason FROM Library l JOIN Content c ON c.fileName = l.fileName WHERE l.userId = ? AND l.subjectId = ? AND l.visibility = true AND l.libName = ? AND l.fileName != '';";
			$params = array($data['userId'], $data['subjectId'], $data['libName']);

			$query = $this->db->query($query, $params);

			if ($query->num_rows()>0) {

				$result = $query->result_array();

				foreach ($result as $k => $r) {

					// get BASIC comment count for that image (can't figure out query to get deleted info from Nodes without skewing count :\ cjt)
					// TODO: add moderation?  or is the moderation of the image enough to suppress comments?
					$this->db->select('COUNT(*) AS comments')->from('Post')->where('fileName', $r['fileName']);
					$res = $this->db->get()->row();
					$result[$k]['comments'] = $res->comments;
				}
				return $result;
			}
			return false;
		}
		return false;
	}
	
	function getLibraryContent($data) {
		
		$result3 = array();
		$stopWords = array("and", "or", "the", "an", "if", "the");
		// check if the user is logged in
		$userId = $this->user->authCheck();

		if ($userId !== false && isset($data['subjectId'])) {

			if (!is_numeric($data["subjectId"])) {
				$data["subjectId"] = $this->makeSubjectId($data["subjectId"], $userId);
			}

			if ($data['subjectId'] === 1) {
				// set up select_string, no call to checkUser
				$subjectIds = $this->db->query('SELECT subjectId FROM UserToSubject WHERE userId=? AND banned<NOW() AND displayed=true and (active=true OR subjectId IN (select subjectId from subjectGroups where userId=? and displayed=true))', [$userId, $userId]);
				$select_statement="";
				foreach ($subjectIds->result_array() as $row) {
					if (filter_var($row['subjectId'], FILTER_VALIDATE_INT)) {
						$select_statement.= $row['subjectId'].",";
					}
				}
				$select_statement=mb_substr($select_statement,0,mb_strlen($select_statement)-1);
	
			} else {
				// set up select string with one value in the 'in' clause and do an access check
				$select_statement=$data['subjectId'];
				$accessCheck = $this->db->query('CALL checkUser(?, ?, @outParam, true)', [$data["subjectId"], $userId]);
				if(!$accessCheck->result_array()[0]['valid']) {
					return [];
				}
			}
			if (isset($data['libNames'])) {
				switch ($data["fileType"]) {
	
					case "article":
					case "blog":
					case "review":
					case "event":
						$result = array();
						$result3 = array();
						//$result3['dates']=array();
	
						if (isset($data['date']) && $data['date'] != '' && $data['date'] != 'undefined') {
							$query = "SELECT Post.postId,postType as 'fileType',nodeId,Post.userId,Post.startTime,Post.endTime, Post.numComments FROM Post JOIN Nodes ON Post.postId = Nodes.postId WHERE ? >= startTime AND ? <= endTime AND parentNodeId = 0 AND postType = ? AND subjectId IN (".$select_statement.");";
	
							$params = array($data['date'] . ' 23:59:59', $data["date"] . ' 00:00:00', $data["fileType"]);
							$query = $this->db->query($query, $params);
							$result = $query->result_array();

							// Not sure what this part is for. Client doesn't seem to use it
							/* $query = "SELECT Post.startTime FROM Post JOIN Nodes ON Post.postId = Nodes.postId WHERE startTime >= ? AND startTime LIKE ? AND parentNodeId = 0 AND postType = ? AND subjectId IN (".$select_statement.");";
	
							$params = array($data['date'] . ' 00:00:00', mb_substr($data["date"], 0, 7) . '%', $data["fileType"]);
	
							$query = $this->db->query($query, $params);

							$Dateresult = $query->result_array();
							for ($i = 0; $i < count($Dateresult); $i++) {
								$result3['dates'][$i] = 1000 * strtotime($Dateresult[$i]["startTime"]);
							} */
						} else if (isset($data['postId']) && $data['postId'] != '') {
							$query = "SELECT Post.postId,"
									. "postType as 'fileType',"
									. "nodeId,Post.userId,"
									. "Post.startTime,"
									. "Post.endTime, "
									. "if(Post.modDeleted,'This content has been removed',Post.title) as 'postTitle', "
									. "Post.numComments,"
									. "Post.modDeleted as postModDeleted "
									. "FROM Post "
									. "JOIN Nodes "
									. "ON Post.postId = Nodes.postId "
									. "WHERE Post.postId = ? "
									. "AND parentNodeId = 0 "
									. "AND postType = ?"
									. "AND Post.modDeleted = 0";
									
							$params = array($data['postId'], $data["fileType"]);
							$query = $this->db->query($query, $params);
							$result = $query->result_array();
						} else {
							$query = "SELECT DISTINCT Post.postId,postType as 'fileType',"
									. "nodeId, "
									. "Post.userId,"
									. "startTime,"
									. "endTime, "
									. "if(Post.modDeleted,'This content has been removed',Post.title) as 'postTitle', "
									. "Post.numComments, "
									. "Post.modDeleted as postModDeleted"
									. "FROM Post JOIN Nodes ON Post.postId = Nodes.postId WHERE subjectId IN (".$select_statement.") AND parentNodeId = 0 AND postType = ? AND (";
							$params = array($data["fileType"]);
							foreach ($data['libNames'] as $libName) {
								if (!in_array($libName, $stopWords)) {
									$query .= "(title LIKE ? OR text LIKE ?) OR ";
									$params = array_merge($params, ['%' . $this->db->escape_like_str($libName) . '%', '%' . $this->db->escape_like_str($libName) . '%']);
								}
							}
							$query = mb_substr($query, 0, -4);
							$query .= ');';
							
							$query = $this->db->query($query, $params);
							$result = $query->result_array();
						}
						for ($i = 0; $i < count($result); $i++) {
							$result[$i]["subs"] = $this->post->getSubscribedCount($result[$i]['postId'], $data["fileType"]);
							$result[$i]["subscribed"] = $this->post->getSubscribed($result[$i]["postId"], $userId, $data["fileType"]);
							
						}
						$returnData = array_merge($result, $result3);
						if (empty($returnData)) {
							$returnData = ['isEmpty' => true];
						}
						return $returnData;
	
						break;
					default:
						if (isset($data['postId']) && $data['postId'] != '' && is_numeric($data['postId']) && $data["searchFlag"] == false) {
							if($data['fileType'] === 'vlog') {
								// vlogs are viewed in post context, so retrieve deleted based on post context, not the file itself
								$deleteSelect = 'ContentToNodes.deleted, LCASE(ContentToNodes.deleteReason) AS deleteReason ';
							} else {
								// other files are viewed in their own context, so retrieve deleted from the file itself
								$deleteSelect = 'Content.modDeleted AS deleted, LCASE(Content.modDeleteReason) AS deleteReason ';
							}
							$query = "SELECT Content.fileName,"
									. "mimeType,"
									. "fileSize,"
									. "Content.userId as 'owner',"
									. "URL,"
									. "Content.numViews,"
									. "Content.timeStamp,"
									. "Content.duration,"
									. "Content.subjectId as 'subject', "
									. "Content.description as 'comment',"
									. "userTitle,"
									. "width,"
									. "height,"
									. "ContentToNodes.nodeId, "
									. "Nodes.postId, "
									. "Nodes.level,"
									. "Post.modDeleted as postModDeleted,"
									. "Subject.private, "
									. "t.value AS 'theme',"
									. "Post.title as 'postTitle', "
									. "LEFT(Nodes.text,20) as 'nodeTitle',"
									. "Content.userFileName, "
									. "Content.numComments, "
									. "n2.nodeId as zeroNode, ";
							$query .= $deleteSelect;
							$query .= "FROM Content "
									. "LEFT JOIN ContentToNodes "
									. "ON Content.fileName = ContentToNodes.fileName "
									. "LEFT JOIN Nodes "
									. "ON ContentToNodes.nodeId = Nodes.nodeId "
									. "LEFT JOIN Post "
									. "ON Nodes.postId = Post.postId "
									. "JOIN Subject ON Subject.subjectId "
									. "IN(".$select_statement.") "
									. "LEFT JOIN UserToSubject us "
									. "ON us.subjectId = Subject.subjectId "
									. "AND us.userId = ? "
									. "JOIN ThemeColourOptions "
									. "AS t "
									. "ON t.id = "
									. "IF(us.themeColourOptionId IS NULL, us.themeColourOptionId, us.themeColourOptionId) "
									. "LEFT JOIN Nodes AS n2 ON n2.parentNodeId = 0 "
									. "AND n2.postId = Nodes.postId "
									. "WHERE Post.postId = ? and Content.fileType = 'link' and Content.mimeType != 'link'" //don't want links just vids
									. "AND (Nodes.draftNode = 0 OR Post.draftPostVisible = 1)"
									. "AND Post.modDeleted = 0";
							$params = array($userId, $data['postId']);
	
							$query = $this->db->query($query, $params);
	
							$result = $query->result_array();
							$result2 = array();
						} else {
							$query = "SELECT DISTINCT libName,subjectId,userId FROM Library WHERE subjectId IN (".$select_statement.") AND visibility = true AND ( ";
							$params = array();
							foreach ($data['libNames'] as $libName) {
								if (!in_array($libName, $stopWords)) {
									$query .= "libName LIKE ? OR ";
									$params = array_merge($params, ['%' . $this->db->escape_like_str($libName) . '%']);
								}
							}
							$query = mb_substr($query, 0, -4);
							$query .= ') ORDER BY libName;';
							$query = $this->db->query($query, $params);
							$result = $query->result_array();
				
							foreach ($result as $row) {
								$query = "CALL getLibraryFiles(?,?,?,?)";
								$params = array($userId, $data['subjectId'], $row['libName'], $data["fileType"]);
								$query = $this->db->query($query, $params);
								$result1 = $query->result_array();
								$result[$row['libName']] = $result1;
							}
							$query = "CALL getContent(?,?,?,?)";
							$params = array($userId, $data['subjectId'], $libName, $data["fileType"]);
							$query = $this->db->query($query, $params);
							$result2 = $query->result_array();
							//file_put_contents('/home/tech/DATABASE/chk', print_r($params,true));
						}
						break;
				}
			} else {
				// get forum type
				$forumType = $this->group->getType($data['subjectId']);
				if ($forumType === '') {
					$forumType = self::TYPE_GENERAL;
				}
				
				$defaultThemeColourOptionId = $this->group->getDefaultThemeColourOption()['id'];
				
				// Start by adding where clause for subjects, as it's the same for any fileType
				// my feed
				if ($data['subjectId'] == '1') {
					// following or grouped. Cat filters don't matter? - yes they do - jb!!!
					$this->db->where('
						(s.subjectId IN (
							SELECT s.subjectId
							FROM UserToSubject AS u
							JOIN Subject AS s ON u.subjectId = s.subjectId
							WHERE u.userId = '.$userId.'
							AND u.active = true
							AND u.displayed = true
							AND (s.private = false OR u.privateAccess = true)
							AND FIND_IN_SET(p.CategoryId, u.catFilterList) = 0
						) || s.subjectId IN (
							SELECT g.subjectId 
							FROM subjectGroups AS g
							JOIN UserToSubject AS u ON g.subjectId = u.subjectId AND g.userId = u.userId
							JOIN Subject AS s ON u.subjectId = s.subjectId 
							WHERE g.displayed = true 
							AND g.inFeed = true 
							AND u.userId = '.$userId.'
							AND (s.private = false OR u.privateAccess = true)
							AND FIND_IN_SET(p.CategoryId, u.catFilterList) = 0
						))', null, false)
						->where('s.subjectId !=', 1); // should only be avatars with subjectId 1, which we don't want
				
				// top 500 general, club18 or schools
				} else if ($data['subjectId'] == '624' || $data['subjectId'] == '596' || $data['subjectId'] == '655') {
					$this->db->where([
						's.type' => $forumType,
						's.private' => 0,
						's.visible' => 1
					]);

				// main feed - filter out forum types the user doesn't search for
				} else if ((int)$forumType === group::TYPE_RAMBLUR && $this->group->getName($data['subjectId']) === 'main feed') {
					$searchChoice = $this->user->getSearchChoice();
					$where = '(s.type = '.group::TYPE_RAMBLUR;
					if ($searchChoice['related']) {
						$where .= " OR s.type = " . group::TYPE_RELATED;
					}
					if ($searchChoice['business']) {
						$where .= " OR s.type = " . group::TYPE_BUSINESS;
					}
					if ($searchChoice['general']) {
						$where .= " OR s.type = " . group::TYPE_GENERAL;
					}
					if (isset($searchChoice['club18']) && $searchChoice['club18']) {
						$where .= " OR s.type = " . group::TYPE_CLUB18;
					}
					$where .= ')';
					$this->db->where($where, null, false)
						->where([
							's.private' => 0,
							's.visible' => 1,
							's.subjectId !=' => 1 // should only be avatars with subjectId 1, which we don't want
						]);
					
				// all others - cat filter and grouped
				} else {
					// this subject, or it's grouped subjects
					$this->db->where('
						(s.subjectId = ANY (
							SELECT subjectId
							FROM subjectGroups
							WHERE userId='.$userId.'
							AND groupWith = '.$data['subjectId'].'
							AND subjectId != '.$data['subjectId'].
						')
						OR s.subjectId = '.$data['subjectId'].')', null, false);
					// apply category filter to this subject
					// @todo - apply category filter to it's grouped subjects as well
					$this->db->where('(FIND_IN_SET(p.categoryId, (SELECT u.catFilterList FROM UserToSubject AS u WHERE u.subjectId = '.$data['subjectId'].' and u.userId = '.$userId.')) = 0 OR p.categoryId IS NULL)');
				}
				// Retrieve media
				if ($data["fileType"] === 'article'
					|| $data["fileType"] === 'blog'
					|| $data["fileType"] === 'review'
					|| $data["fileType"] === 'event'
				) {
					$this->db->select("
							p.postId,
							p.postType AS 'fileType',
							ANY_VALUE(n.nodeId) as nodeId,
							p.userId,
							p.startTime,
							p.endTime,
							p.timeStamp,
							p.modDeleted as postModDeleted,
							if(p.modDeleted,'Content has been removed',p.title) as title,
							count(cn.nodeId) as numComments,
							if(p.modDeleted,'This content has been removed from the forum by a moderator',ANY_VALUE(n.text)) as text,
							s.subjectId,
							s.subjectName,
							u.userName")
						->from('Post AS p')
						->join('Nodes AS n', 'n.postId = p.postId')
						->join('User AS u', 'u.userId = p.userId')
						->join('Subject AS s', 's.subjectId = p.subjectId', 'LEFT')
						->join('Post AS cp', 'cp.commentedPostId = p.postId', 'LEFT')
						->join('Nodes AS cn', 'cn.postId = cp.postId', 'LEFT')
						->where([
							'p.postType' => $data['fileType'],
							'n.deleted' => 0,
							//'p.modDeleted' => 0,
							//'p.subjectId' => $data['subjectId'],
							'n.parentNodeId' => 0
						])
					    ->where("((p.draftPost = '0' OR p.draftPostVisible = '1') OR (p.draftPost = '1' AND p.userId = $userId))") 
						->group_by('p.postId')
						->order_by('p.timeStamp', "DESC")
						->limit(20);
						
					$result = $this->db->get();
					
					if ($result->num_rows() > 0) {
						return $result->result_array();
					} else {
						return ['isEmpty' => true];
					}
				} else {
					
					$this->db->select("
							c.fileName,
							c.mimeType,
							c.fileSize,
							c.userId as 'owner',
							c.URL,
							c.numViews,
							c.timeStamp,
							c.duration,
							c.subjectId as 'subject',
							c.description as 'comment',
							c.userTitle,
							c.width,
							c.height,
							c.numComments,
							cn.nodeId,
							p.postId,
							p.postType,
							p.modDeleted as postModDeleted,
							n.`level`,
							s.private,
							c.modDeleted as 'deleted',
							p.title as 'postTitle',
							c.userFileName,
							n2.nodeId as zeroNode
							")
						->select("LEFT(n.text,20) as 'nodeTitle'",false)
						->select("LCASE(c.modDeleteReason) as 'deleteReason'", false)
						->select("t.value AS 'theme'", false)
						->from('Content AS c')
						->join('ContentToNodes AS cn', 'cn.fileName = c.fileName') //, 'LEFT') do not include items without posts
						->join('Nodes AS n', 'n.nodeId = cn.nodeID', 'LEFT')
						->join('Post AS p', 'p.postId = n.postId', 'LEFT')
						->join('Subject AS s', 's.subjectId = c.subjectId', 'LEFT')
						->join('UserToSubject AS us', 'us.subjectId = s.subjectId AND us.userId = '.$userId, 'LEFT')
						->join('ThemeColourOptions AS t', "t.id = IF(us.themeColourOptionId IS NOT NULL, us.themeColourOptionId, IF(s.themeColourOptionId IS NOT NULL, s.themeColourOptionId, '".$defaultThemeColourOptionId."'))")
						->join('Nodes AS n2', 'n2.parentNodeId = 0 AND n2.postId = p.postId', 'LEFT')
						->join('ContentToNodes AS cn2', 'cn2.fileName = c.fileName AND ((cn2.draftNode = 0 OR cn2.draftNodeVisible = 1) OR (cn2.draftNode = 1 AND p.userId = '.$userId.')) ') 
						->where([
							//'c.subjectId' => $data['subjectId'],
							'c.modDeleted' => 0,
							'p.modDeleted' => 0
						])
						->where("((p.draftPost = '0' OR p.draftPostVisible = '1') OR (p.draftPost = '1' AND p.userId = $userId))") 	
						->where("(p.postType != 'message' OR p.postType IS NULL)") // don't want attachments from messages')
						->order_by('c.timeStamp', 'DESC')
						->limit(20);
					
					if ($data['fileType'] === 'video') {
						$this->db->where("(c.fileType = 'video' OR (c.fileType = 'link' AND (c.URL LIKE '%youtube%' || c.URL LIKE '%vimeo%')))")
							->where("(p.postType != 'vlog' OR p.postType IS NULL)");
					} else if ($data['fileType'] === 'vlog') {
						$this->db->where('p.postType', 'vlog')
							->where('c.fileType', 'link');	
					} else {
						$this->db->join('SubjectDisplay AS d', 'd.fileName = c.fileName', "LEFT")
							->where('d.id', NULL)
							->like('c.fileType', $data['fileType']);
					}
					
					$result = $this->db->get();
					if ($result->num_rows() > 0) {
						return $result->result_array();
					} else {
						return ['isEmpty' => true];
					}
				}
			}

			if (!isset($result)) {
				return false;
			} else {
				$returnData = array_merge($result, $result2, $result3);
				if (empty($returnData)) {
					$returnData = ['isEmpty' => true];
				}
				return $returnData;
			}
		}
	}

	// added - cjt
	function getLibraryContentByFilename($fileName, $type) {
		
		// not private?  only if auth'd?

		// does require login - workarea (where this came from, on the mobile) can only be accessed logged in
		if($userId = $this->user->authCheck()) {

			$contentType = ($type==='image') ? 'photo' : $type;

			$query = "SELECT c.*, ctn.nodeID as nodeId, n.postId "
					. "FROM Content as c, "
					. "ContentToNodes as ctn, "
					. "Nodes as n "
					. "WHERE c.fileName = ? "
					. "AND n.nodeId = ctn.nodeID "
					. "AND ctn.fileName = c.fileName "
					. "AND c.fileType = ? "
					. "AND c.modDeleted = 0;";
			$params = array($fileName, $contentType);
			$query = $this->db->query($query, $params);

			if($query->num_rows()>0) {
				$result = $query->result_array(); 
				return $result;
			}
		}
		return false;
	}

	function setContentVisibility($data) {

		// check if the user is logged in
		$userId = $this->user->authCheck();

		if ($userId !== false && isset($data['fileNames'])) {

			foreach ($data['fileNames'] as $fileName) {
				$query = "UPDATE Content SET visible = false WHERE userId = ? AND fileName = ?";
				$params = array($userId, $fileName);
				$query = $this->db->query($query, $params);
			}
			return true;
		} else {
			return false;
		}
	}

	function deleteLibrary($data) {

		// check if the user is logged in
		$userId = $this->user->authCheck();

		if ($userId !== false && isset($data['libName']) && isset($data["subjectId"])) {
			if (!is_numeric($data["subjectId"])) {
				$data["subjectId"] = $this->makeSubjectId($data["subjectId"], $userId);
			}

			$query = "CALL setLibraryVisibility(?,?,?,?);";

			$params = array($data['libName'], $userId, $data['subjectId'], false);

			$query = $this->db->query($query, $params);

			return true;
		} else {
			return false;
		}
	}

	function librarySubscribe($data) {

		$userId = $this->user->authCheck();

		if ($userId !== false && isset($data['subscriberId']) && isset($data['action']) && (filter_var($data['action'], FILTER_VALIDATE_BOOLEAN,array('flags' => FILTER_NULL_ON_FAILURE))!==null) && isset($data['group']) && filter_var($data['group'],FILTER_VALIDATE_INT)) {

			$this->db->select('postType')
				->from('Post')
				->where('postId', $data['subscriberId']);
			$post = $this->db->get();
			if($post->num_rows() === 0) {
				return false;
			}

			$query = "CALL librarySubscribe(?,?,?,?,?);";

			$params = array($data['subscriberId'], $userId, $data['group'], $post->first_row("array")['postType'], $data['action']);

			$query = $this->db->query($query, $params);
			$result = array();
			$result['subs'] = $query->first_row('array')['subs'];
			$result['affectedPosts'] = array();
			$this->db->select('t.postId')
				->from('Post AS p')
				->join('Post AS t', 't.title = p.title AND t.userId = p.userId AND t.subjectId = p.subjectId AND p.postType = t.postType', 'left')
				->join('subscription AS s', 't.postId = s.subscriptionId', 'left')
				->where('p.postId', $data['subscriberId']);
			$affectedPosts = $this->db->get();
			if($affectedPosts->num_rows() > 0) {
				$affectedPosts = $affectedPosts->result_array();
				foreach($affectedPosts as $a) {
					$result['affectedPosts'][] = $a['postId'];
				}
				return $result;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function makeSubjectId($subjectName, $userId) {
		$subjectName = str_replace("-", " ", $subjectName);
		$query = "CALL getSubjectId(?,?);";
		$params = array($subjectName, $userId);
		$query = $this->db->query($query, $params);
		$tmp = $query->result_array();
		return($tmp[0]["subjectId"]);
	}
	
	function bumpNumViews($postId) {

		$userId = $this->user->authCheck();
		
		// Use a transaction, as could be viewed simultaneously by different users
		$this->db->trans_begin();
		
		//if(!empty($nodeId)) {
		//	$this->db->select('postId')
		//	->from('Nodes')
		//	->where('postId', $postId)
		//	->limit(1);
			
		//$res = $this->db->get();	
		//	if ($res->num_rows() > 0) {
		//		$postId = $res->postId;
		//}
		
		$this->db->select('userId, numViews, subjectId')
			->from('Post')
			->where('postId', $postId);
		
		$postResult = $this->db->get();
		
		if ($postResult->num_rows() === 0) {
			// The post doesn't exist
			return false;
		}
		$post = $postResult->first_row('array');
		
		$hasAccess = $this->group->getUserAccess($post['subjectId'], $userId);
		
		// If the user can't access the subject the post is in 
		if (!$hasAccess) { 
			return false;
		}
		
		// if the user created the post, don't bump up the count
		if($userId == $post['userId']) {
			return $post['numViews'] + $this->post->getReplyCount($postId);
		}
		
		$this->db->where('postId', $postId)
			->set('numViews', 'numViews + 1', false)
			->update('Post');
		
		if ($this->db->affected_rows() === 1 && $this->db->trans_status() !== FALSE) {
			$this->db->trans_commit();
			return $post['numViews'] + 1 + $this->post->getReplyCount($postId);
		} else {
			return false;
		}
	}
	
	function deviceBumpNumViews($nodeId) {

		$userId = $this->user->authCheck();
		$postId = 0;
		
		// Use a transaction, as could be viewed simultaneously by different users
		$this->db->trans_begin();
		
		
		$this->db->select('postId')
			->from('Nodes')
			->where('nodeId', $nodeId)
			->limit(1);
		
		$nodeResult = $this->db->get();
		if ($nodeResult->num_rows() > 0) {
			$postId = $nodeResult->row()->postId;
		}
		
		$this->db->select('userId, numViews, subjectId')
			->from('Post')
			->where('postId', $postId);
		
		$postResult = $this->db->get();
		
		if ($postResult->num_rows() === 0) {
			// The post doesn't exist
			return false;
		}
		$post = $postResult->first_row('array');
		
		$hasAccess = $this->group->getUserAccess($post['subjectId'], $userId);
		
		// If the user can't access the subject the post is in 
		if (!$hasAccess) { 
			return false;
		}
		
		// if the user created the post, don't bump up the count
		if($userId == $post['userId']) {
			return $post['numViews'] + $this->post->getReplyCount($postId);
		}
		
		$this->db->where('postId', $postId)
			->set('numViews', 'numViews + 1', false)
			->update('Post');
		
		if ($this->db->affected_rows() === 1 && $this->db->trans_status() !== FALSE) {
			$this->db->trans_commit();
			return $post['numViews'] + 1 + $this->post->getReplyCount($postId);
		} else {
			return false;
		}
	}
	
	
}
