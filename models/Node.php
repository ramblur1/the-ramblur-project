<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'interfaces/IReportableContent.php';

class Node extends CI_Model implements IReportableContent {

	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}

	// subscribe to a node (adds a button if its not a user)
	function subscribe($data) {
		if (!isset($data['postId'])) {
			return false;
		}
		// check if the user is logged in
		$userId = $this->user->authCheck();
		
		if ($userId === false) {
			return false;
		}

		$this->db->select('postId')
			->from('UserToPost')
			->where([
				'postId' => $data['postId'],
				'userId' => $userId
			]);
		$alreadySubscribed = $this->db->get()->num_rows() > 0;
		if (!$alreadySubscribed) {
			$this->db->insert('UserToPost', [
				'postId' => $data['postId'],
				'userId' => $userId
			]);
		}
	}

	// UNsubscribe from a node (removes a button)
	function unsubscribe($data) {
		
		if (!isset($data['postId'])) {
			return false;
		}

		// check if the user is logged in
		$userId = $this->user->authCheck();
		
		if ($userId === false) {
			return false;
		}

		$this->db->delete('UserToPost', [
			'userId' => $userId,
			'postId' => $data['postId']
		]);
		return $this->db->affected_rows() > 0;
	}

	// returns the type of the specified node
	function type($nodeId) {

		$query = "select `type` from `nodes` where `id` = ?;";
		$query = $this->db->query($query, array($nodeId));

		if ( $query->num_rows() > 0 ) {

			$query = $query->result_array();

			return $query[0]["type"];

		} else {

			return false;

		}

	}

	// fetches the node and all its properties
	function info($nodeId) {
		
		if ($nodeId === NULL) {
			return false;
		}

		$userId = $this->user->authCheck();

		// check removed by cjt to get device info for not logged in views
		//if ( $userId !== false ) {  NB access control to private forum nodes still controlled by get nodedetails

		$query = "CALL getNodeDetails(?,?);"; 

		$params = array($userId,$nodeId);

		$query = $this->db->query($query,$params);

		if ($query->num_rows() > 0) {
			$result = $query->result_array();
		} else {
			return false;
		}

		// moved from "type" condition (was at line 353) - may as well always have the subjectId
		$result[0]['subjectId'] = $this->group->findByName($result[0]["subjectName"]);

		$query2 = "CALL getNodeFiles(?,?);"; 

		$params = array($userId,$nodeId);
		
		$query2 = $this->db->query($query2,$params);

		if ($query2->num_rows() > 0) {
			$result[0]["attachments"] = $query2->result_array();
		}

		/* NB: COPIED FROM group::post - TODO: Move to 1 function?
		 * find out if it is a map, and if so, 
		 * create the info needed to display the 
		 * default map with correct location in the post.
		 * Note this is dummy attachment data as maps do not
		 * currently have an entry in the Content table.
		 * 
		 */
				
		$this->db->select('lat')
			->select('lng')
			->from('UserToMapMarker')
			->where('nodeId', $nodeId);
		$mapQuery = $this->db->get();

		if ($mapQuery->num_rows() > 0) {
			
			$maps = $mapQuery->result_array();
			$marArr = array();
			
			// in case the attachments array hasn't been created
			if(!isset($result[0]['attachments'])) $result[0]["attachments"] = array();
			
			//note this url should be in config but references to google are all over the place already!
			$mapArr["node"] = $nodeId;
			$mapArr["type"] = 'pinloc';
			$mapArr["url"]  = 'https://www.google.com/maps/@' . $maps[0]['lat'] . ',' . $maps[0]['lng'] . ',15z';
			array_push($result[0]["attachments"], $mapArr);
		}

		if (!isset($result[0]["avatar"])) {
			// filler avatar 
			$result[0]["avatar"] = ''; 
		} else { 
			$result[0]["avatar"]= $result[0]["avatar"] . '-avatar';
		}
		$query1 = "
			SELECT
			t.value AS 'theme',
			IF(u.opacity IS NULL, s.opacity, u.opacity)  AS 'opacity',
			IF(u.primaryColour IS NULL, s.primaryColour, u.primaryColour)  AS 'primaryColour',
			IF(u.secondaryColour IS NULL, s.secondaryColour, u.secondaryColour)  AS 'secondaryColour',
			fp.value AS 'primaryFontColour',
			fs.value AS 'secondaryFontColour'
			FROM
			Subject AS s
			LEFT JOIN UserToSubject u ON u.subjectId = s.subjectId AND u.userId = ?
			JOIN ThemeColourOptions AS t ON t.id = IF(u.themeColourOptionId IS NULL, s.themeColourOptionId, u.themeColourOptionId)
			JOIN ThemeFontOptions AS fp ON fp.id = IF(u.primaryThemeFontOptionId IS NULL, s.primaryThemeFontOptionId, u.primaryThemeFontOptionId)
			JOIN ThemeFontOptions AS fs ON fs.id = IF(u.secondaryThemeFontOptionId IS NULL, s.secondaryThemeFontOptionId, u.secondaryThemeFontOptionId)
			WHERE s.subjectName = ?
			";
		if(isset($userId) && $userId != ''){
			$params1=array($userId,$result[0]["subjectName"]);
		} else {
			$params1=array(null,$result[0]["subjectName"]);
		}
		$query1 = $this->db->query($query1, $params1);
		$result2 = $query1->result_array();
		
		foreach($result2 as $row){
			$theme = $row["theme"];
			$opacity = $row["opacity"];
			$primaryColour = $row["primaryColour"];
			$secondaryColour = $row["secondaryColour"];
			$primaryFontColour = $row["primaryFontColour"];
			$secondaryFontColour = $row["secondaryFontColour"];
		}
 
		if(!isset($theme)){$result[0]["theme"] = $this->group->getDefaultThemeColourOption()['value'];}else{$result[0]["theme"] = $theme;} 
		if(!isset($opacity)){$result[0]["opacity"] = group::DEFAULT_OPACITY;}else{$result[0]["opacity"] = $opacity;} 
		if(!isset($primaryColour)){$result[0]["primaryColour"] = group::DEFAULT_PRIMARYCOLOUR;}else{$result[0]["primaryColour"] = $primaryColour;} 
		if(!isset($secondaryColour)){$result[0]["secondaryColour"] = group::DEFAULT_SECONDARYCOLOUR;}else{$result[0]["secondaryColour"] = $secondaryColour;}
		if(!isset($primaryFontColour)){$result[0]["primaryFontColour"] = $this->group->getDefaultThemeFontOption()['value'];}else{$result[0]["primaryFontColour"] = $primaryFontColour;}
		if(!isset($secondaryFontColour)){$result[0]["secondaryFontColour"] = $this->group->getDefaultThemeFontOption()['value'];}else{$result[0]["secondaryFontColour"] = $secondaryFontColour;}

		$query="SELECT nodeId AS 'zeroNode' FROM Nodes WHERE postId = ? and parentNodeId=0"; 
		$params=array($result[0]["postId"]); 
		$query = $this->db->query($query, $params);  
		$tmp1=$query->result_array(); 

		$result[0]["zeroNode"] = $tmp1[0]["zeroNode"]; 

		$result[0]["threadTypeId"]='post';
		
		if( $result[0]["type"] == "article" || $result[0]["type"] == "event" || $result[0]["type"] == "blog" || $result[0]["type"] == "vlog" || $result[0]["type"] == "review"){

			$result[0]["subs"] = $this->post->getSubscribedCount($result[0]['postId'], $result[0]['type']);
			$result[0]["subscribed"] = $this->post->getSubscribed($result[0]['postId'], $userId, $result[0]['type']);
		}
		
		// if event, convert times from utc using ***event*** timezone
		// old events will have timezones retro-fitted - cjt
		// NB: THIS MAY BE A MOBILE SPECIFIC REQUIRMENT - but there's no way to differentiate between request device sources yet...
		if($result[0]["type"]==='event') {
			if(!empty($result[0]["postTimezone"])) {
				$this->load->model("Geo");
				$result[0]["eventStartTime"] = $this->Geo->convertFromUtc($result[0]["startTime"], $result[0]["postTimezone"])->format('Y-m-d H:i:s');
				$result[0]["eventEndTime"] = $this->Geo->convertFromUtc($result[0]["endTime"], $result[0]["postTimezone"])->format('Y-m-d H:i:s');
			} else {
				$result[0]["eventStartTime"] = $result[0]["startTime"];
				$result[0]["eventEndTime"] = $result[0]["endTime"];
			}
		}
		
		return($result[0]);
	}
	
	/**
	 * node->info() doesn't have enough information to always draw a single node, so fill rest of data using this function.
	 * 
	 * @param array $nodeInfo - the return data from node->info()
	 * @return []
	 */
	public function drawableInfo($nodeId) {
		$nodeInfo = $this->info($nodeId);
		$nodeInfo['timezone'] = $nodeInfo['postTimezone'];
		$this->db->select('numViews')
			->from('Post')
			->where('postId', $nodeInfo['postId']);
		$numViews = $this->db->get()->first_row('array')["numViews"];
		
		$this->db->select('COUNT(nodeId) AS "replies"')
			->from('Nodes')
			->where([
				'postId' => $nodeInfo["postId"],
				'parentNodeId' => $nodeId
			]
			);
		$replies = $this->db->get()->first_row('array')["replies"];
		$nodeInfo["numViews"] = $numViews;
		$nodeInfo["replies"] = $replies;
		
		// Can't use attachment info from node.info, as it doesn't have enough info to draw the photo. Use same query that's in group.post
		if(isset($nodeInfo['attachments'])) {
			
			// keep pinloc info
			$pinlocs = [];
			foreach ($nodeInfo['attachments'] as $a) {
				if ($a['type'] === 'pinloc' && !isset($a['fileType'])) {
					$pinlocs[] = $a;
				}
			}
			
			$nodeInfo['attachments'] = array();
			$this->db->select("
				c.userTitle as 'title',
				c.description,
				c.URL as 'url',
				c.numViews,
				c.fileName as 'post',
				c.fileName as 'original',
				c.fileType as 'type',
				c.userFileName as 'filename',
				c.mimeType,
				n.nodeId as 'node',
				c.timeStamp,
				n.deleted,
				s.private")
			->select("LCASE(n.deleteReason) AS 'deleteReason'", FALSE)
			->from("ContentToNodes AS n")
			->join('Content AS c', 'c.fileName = n.fileName')
			->join('Subject AS s', 's.subjectId = c.subjectId')
			->where('n.nodeId', $nodeInfo['id'])
			->order_by('c.timeStamp');
			$att = $this->db->get();
			if($att->num_rows() > 0) {
				$nodeInfo['attachments'] = $att->result_array();
				//get lightox image extension for parent post
				$this->load->model('group');
				$nodeInfo["lightboxImgExt"] = $this->group->getLightboxImg($nodeInfo['attachments'][0]['original'], $nodeInfo['attachments'][0]['private']);
			
			}
			$nodeInfo['attachments'] = array_merge($nodeInfo['attachments'], $pinlocs);
		}
		return $nodeInfo;
	}

	function shortUserInfo($group) {

		$userId = $this->user->authCheck();
		if (!$userId) {
			return false;
		}
		$result = array();
 
		if (!is_numeric($group)) {
			$group = $this->makeSubjectId($group,$userId);
			if ($group === NULL) {
				return false;
			}
		}	
		$myResults = array();

		$query = "CALL getUserDetails(?);";

		$params = array($userId);

		$query = $this->db->query($query,$params);
		
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$myResult['userName'] = $result[0]['userName'];
			$myResult['avatar'] = $result[0]['avatar'] ? $result[0]['avatar'].'-avatar' : '';
			$myResult['age'] = $result[0]['age'];
			$myResult['gender'] = $result[0]['gender'];
			$myResult['country'] = $result[0]['country'];
			$myResult['countryId'] = $result[0]['countryId'];
			$myResult['userId'] = $userId;
		}
		$query = "CALL getSubjectNode(?,?);";

		$params = array($group,$userId);
	
		$query = $this->db->query($query,$params);
		if ( $query->num_rows() > 0 ) {
			$result = $query->result_array();
			$myResult['subjectName'] = $result[0]['subjectName'];
			$myResult['primaryColour'] = $result[0]['primaryColour'];
			$myResult['secondaryColour'] = $result[0]['secondaryColour'];
			$myResult['primaryFontColour'] = $result[0]['primaryFontColour'];
			$myResult['secondaryFontColour'] = $result[0]['secondaryFontColour'];
			$myResult['theme'] = $result[0]['theme'];
		}
		return $myResult;
	}
	
	function userInfo($nodeId) {

		$myResults = array();
		$query = "CALL getUserDetails(?);"; //parameter should be $nodeId, not 1.

		$params = array($nodeId);
	
		$query1 = $this->db->query($query,$params);
		if ($query1->num_rows() > 0) {

			foreach ($query1->result_array() as $row) {
				$myResults["properties"][]  = array ('name' => 'userName', 'value' => $row["userName"]);
				$myResults["properties"][]  = array ('name' => 'mobile', 'value' => $row["mobile"]);
				$myResults["properties"][]  = array ('name' => 'skype', 'value' => $row["skype"]);
				$myResults["properties"][]  = array ('name' => 'firstName', 'value' => $row["firstName"]);
				$myResults["properties"][]  = array ('name' => 'lastName', 'value' => $row["lastName"]);
				$myResults["properties"][]  = array ('name' => 'email', 'value' => $row["email"]);
				$myResults["properties"][]  = array ('name' => 'gender', 'value' => $row["gender"]);
				$myResults["properties"][]  = array ('name' => 'timezone', 'value' => $row["timeZone"]);
				$myResults["properties"][]  = array ('name' => 'age', 'value' => $row["age"]);
				$myResults["properties"][]  = array ('name' => 'country', 'value' => $row["country"]);
				$myResults["properties"][] = array ('name' => 'state', 'value' => $row["state"]);
				$myResults["properties"][] = array ('name' => 'areacode', 'value' => $row["areaCode"]);
				$myResults["properties"][] = array ('name' => 'dob', 'value' => $row["dob"]);
				$myResults["properties"][] = array ('name' => 'verified', 'value' => $row["verified"]);
				$myResults["properties"][] = array ('name' => 'ageVisible', 'value' => $row["ageVisible"]);
				$myResults["properties"][] = array ('name' => 'locationVisible', 'value' => $row["locationVisible"]);
				$myResults["properties"][] = array ('name' => 'genderVisible', 'value' => $row["genderVisible"]);
				$myResults["properties"][] = array ('name' => 'userNameSearch', 'value' => $row["userNameSearch"]);
				$myResults["properties"][] = array ('name' => 'firstNameSearch', 'value' => $row["firstNameSearch"]);
				$myResults["properties"][] = array ('name' => 'emailSearch', 'value' => $row["emailSearch"]);
				$myResults["properties"][] = array ('name' => 'lastNameSearch', 'value' => $row["lastNameSearch"]);
				$myResults["properties"][] = array ('name' => 'avatar', 'value' => $row["avatar"] ? $row["avatar"].'-avatar' : '');
				$myResults["properties"][] = array ('name' => 'primaryColour', 'value' => $row["primaryColour"]);
				$myResults["properties"][] = array ('name' => 'secondaryColour', 'value' => $row["secondaryColour"]);
				$myResults["properties"][] = array ('name' => 'primaryFontColour', 'value' => $row["primaryFontColour"]);
				$myResults["properties"][] = array ('name' => 'secondaryFontColour', 'value' => $row["secondaryFontColour"]);
				$myResults["properties"][] = array ('name' => 'theme', 'value' => $row["theme"]);
				$myResults["properties"][] = array ('name' => 'opacity', 'value' => $row["opacity"]);
				$myResults["properties"][] = array ('name' => 'subjectsVisible', 'value' => $row["subjectsVisible"]);
				$myResults["properties"][] = array ('name' => 'verificationCode', 'value' => $row["verificationCode"]);
				$myResults["properties"][] = array ('name' => 'facetime', 'value' => $row["facetime"]);
				$myResults["properties"][] = array ('name' => 'logins', 'value' => $row["loginCount"]);
				$myResults["properties"][] = array ('name' => 'suburb', 'value' => $row["suburb"]);
				$myResults["properties"][] = array ('name' => 'postcode', 'value' => $row["postcode"]);
				$myResults["id"] = $nodeId;
			}
		}

		return $myResults;
	}

	// allows the user to 'like' a node
	function like($data) {
	
		// check if the user is logged in
		$userId = $this->user->authCheck();
		if (!$userId) {
			return false;
		}
		$query = "CALL addVote(?,?,?);"; 

		// passing boolean from js -> php unreliable, so set to 1/0 on mobile version
		// adapting this to cover both circumstances
		if($data["liked"]===1||$data["liked"]){
			$liked = true;
		} else $liked = false;

		$params=array($data["nodeId"],$userId,$liked);

		$query = $this->db->query($query,$params);

		return true;
	}

	// allows the user to follow/unfollow a user socially in a group.

	// Deletes or adds an entry to the table UserToSocial to achieve a "toggling" effect, that is, this
	// code inserts an entry if there isn't one and deletes the entry if there is one.

        // The flag 'displayed' applies under a different circumstance.  See group->setSocials

	function follow($socialId, $subjectId) {
		// check if the user is logged in
		$userId = $this->user->authCheck();
		if (!$userId) {
			return false;
		}
		if (!is_numeric($subjectId)) {
			$subjectId = $this->makeSubjectId($subjectId,$userId);
		}
		$query="INSERT INTO UserToSocial (userId, socialId, subjectId) VALUES(?,?,?) ON DUPLICATE KEY UPDATE del=true;"; 
		$query = $this->db->query($query,array($userId,$socialId, $subjectId));

		$query="DELETE FROM UserToSocial WHERE del=true;"; 
		$query = $this->db->query($query);

		return true;
	}

	// the original follow added $subjectId, so "quicker" to just add a working mobile copy for now - cjt
	// Aug2016 - realised that api.php controller determines $subjectId, so possible to revert to 'follow' above (with follow2 edits)
	function follow2($data) {

		// check if the user is logged in
		$userId = $this->user->authCheck();
		
		if (!$userId) {
			return false;
		}

		// mobile dev required an unfollow to action - cjt
		// couldn't locate elsewhere, so jimmying in here...
		if(isset($data['action'])&&$data['action']==='unfollow'){
			$query="DELETE FROM UserToSocial WHERE userId = ? AND socialId = ? AND subjectId = ?;"; // DELETE or update flag? 'displayed' didn't seem quite right?
		// original query, still applies to "new" follows
		} else {
			$query="INSERT INTO UserToSocial (userId, socialId, subjectId) VALUES(?,?,?) ON DUPLICATE KEY UPDATE userId=userId;";
		}

		$params = array($userId, $data['socialId'], $data['groupId']);

		if($query = $this->db->query($query, $params)){
			return true;
		}
		return false;
	}

		// allows the user to unfollow socials in a group
	function unfollow($socialId, $subjectId) {
		$userId = $this->user->authCheck();
		
		if ( $userId !== false ) {
			$query = "delete from `UserToSocial` where `userID` = ? and `socialId` = ? and `subjectId`=?";
			$query = $this->db->query($query, array($userId,$socialId, $subjectId));
			return true;
		}
		// otherwise
		return false;
	}

	// allows the owner of a thread to tick a given comment/reply
	function tick($data) {

		// check if the user is logged in
		$userId = $this->user->authCheck();

		if (!$userId) {
			return false;
		}

		// passing boolean from js -> php unreliable, so set to 1/0 on mobile version
		// adapting this to cover both circumstances
		if($data["ticked"]===1||$data["ticked"]){
			$ticked = true;
		} else $ticked = false;

		$query = "CALL tickBox(?, ?, ?)";
		$params = array($data['nodeId'], $userId, $ticked);

		if($query = $this->db->query($query, $params)){
			return true;
		}
		return false;
	}

	function updateNode($data, $accessChecked = false, &$error = false) {

		$userId = $this->user->authCheck();
		
		if (!$userId) {
			return false;
		}
		$nodeId = $data['nodeId'];
		$this->load->helper(array('htmlpurifier', 'censor', 'stripper'));
		$updated = false;
	
		// added cjt - update edited date, if the type is an 'event'
		if(isset($data['startTime'], $data['endTime']) && DateTime::createFromFormat('Y-m-d H:i:s', $data['startTime']) !== false && DateTime::createFromFormat('Y-m-d H:i:s', $data['endTime']) !== false) {
			if(!empty($data['timezone'])) {
				$this->load->model("Geo");
				$data["startTime"] = $this->Geo->convertToUTC($data["startTime"], $data["timezone"])->format('Y-m-d H:i:s');
				$data["endTime"] = $this->Geo->convertToUTC($data["endTime"], $data["timezone"])->format('Y-m-d H:i:s');
			}
			$sql = "UPDATE Post SET startTime = ?, endTime = ? , timezone = ? WHERE postId = ?;";
			$params = array($data['startTime'], $data['endTime'], $data['timezone'], $data['postId']);
			$result = $this->db->query($sql, $params);

		}

		if(isset($data["text"]) && $data["text"] != ''){
			$data["text"] = censor_text(html_purify($data["text"], 'post'));
			if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && (filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))){$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];}else{$ip=$_SERVER['REMOTE_ADDR'];}
			if($accessChecked) {
				$this->db->update('Nodes',
					array('text' => $data['text']),
					array('nodeId' => $nodeId),
					array('clientIP' => $ip)
				);
				if($this->db->affected_rows() <= 0) {
					$error = "The post body was not updated.";
					return false;
				}
				$updated = true;
				
			} else {
				$query="CALL updateNodeText(?,?,?,?,?);";
	
				// in case, for some craazzy reason, an id is passed instead of the forum name :\
				$subject = (is_numeric($data['subjectName'])) ? $this->post->getSubjectNameById($data['subjectName']) : $data['subjectName'];

				$params=array($userId, $nodeId, $data["text"], $subject, $ip);
				$query = $this->db->query($query, $params);
			}
		}
	
		if(isset($data["title"]) && $data["title"] != '' && isset($data['postId']) && $data['postId'] !== 'undefined') {
			$data["title"] = mb_substr(censor_text(html_purify($data["title"], 'post')), 0, 160); // backend limit to 100 chars for question/subject - cjt
			if($accessChecked) {
				// Save original post details
				$this->db->select('title, postType, subjectId, userId')
					->from('Post')
					->where('postId', $data['postId']);
				$originalPost = $this->db->get();
				if($originalPost->num_rows() === 0) {
					$error = "The post could not be found.";
					return false;
				}
				$originalPost = $originalPost->first_row('array');
				// Update post
				$this->db->update('Post',
					array('title' => $data['title']),
					array('postId' => $data['postId'])
				);
				if($this->db->affected_rows() <= 0) {
					$error = "The post title was not updated.";
					return false;
				}

				// Update titles of blogs/vlogs in series, or subscriptionMsg
				if($originalPost['postType'] === 'vlog' || $originalPost['postType'] === 'blog') {
					$this->db->update('Post',
						array('title' => $data['title']),
						array(
							'userId' => $originalPost['userId'],
							'title' => $originalPost['title'],
							'subjectId' => $originalPost['subjectId'],
							'postType' => $originalPost['postType']
						));
				}
				// update related subscription messages
				$subUpdateSql = "UPDATE `Post` AS `p`
					JOIN `userToSubscriptionMsg` AS `s` ON `s`.`postId` = `p`.`postId`
					SET `p`.`title` = ?
					WHERE `s`.`parentPostId` = ?
					";
				$this->db->query($subUpdateSql, [$data['title'], $data['postId']]);
				
				$updated = true;
			} else {
				$query="CALL updatePostTitle(?,?,?,?);";

				$params=array($userId,$data["postId"],$data["title"],$data["subjectName"]);
				$query = $this->db->query($query, $params);
			}
		}
		if(isset($data["category"]) && $data["category"] != '' && $data["category"] != 'undefined'  && isset($data['postId']) && $data['postId'] !== 'undefined') {

			if($accessChecked) {
				$this->db->update('Post',
					array('categoryId' => $data['category'])
				);
				if($this->db->affected_rows() <= 0) {
					$error = "The post category was not updated.";
					return false;
				}
				$updated = true;
			} else {
				$query="CALL updatePostCategory(?,?,?,?);";

				$params=array($userId,$data["postId"],$data["category"],$data["subjectName"]);
				$query = $this->db->query($query, $params);
			}
		}

		if(isset($data["author"]) && $data["author"] != '' && isset($data["subjectName"]) && $data["subjectName"]!='' && isset($data['postId']) && $data['postId'] !== 'undefined') {
			$data["author"] = censor_text(html_purify($data["author"], 'post'));
			$query="CALL updatePostAuthor(?,?,?,?);";

			$params=array($userId,$data["postId"],$data["author"],$data["subjectName"]);
			$query = $this->db->query($query, $params);
		}

		if(isset($data["about"]) && $data["about"] != '' && isset($data["subjectName"]) && $data["subjectName"]!='' && isset($data['postId']) && $data['postId'] !== 'undefined') {
			$data["about"] = censor_text(html_purify($data["about"], 'post'));
			$query="CALL updatePostAbout(?,?,?,?);";
	
			$params=array($userId,$data["postId"],$data["about"],$data["subjectName"]);
			$query = $this->db->query($query, $params);
		}

		if(isset($data["reason"]) && $data["reason"] != '') {
			$data["reason"] = censor_text(html_purify($data["reason"], 'post'));
			if($accessChecked) {
				$this->db->update('Nodes',
					array('reason' => $data['reason']),
					array('nodeId' => $nodeId)
				);
				if($this->db->affected_rows() <= 0) {
					$error = "The edit reason was not updated.";
					return false;
				}
				$updated = true;
			} else {
				$query="CALL updateLastEdit(?,?,?);";
	
				$params=array($userId,$nodeId,$data["reason"]);
				$query = $this->db->query($query, $params);
			}
		} 

		if(isset($data['commentList'])) {

			//print_r($data['commentList']);

			for($i=0;$i<count($data["commentList"]);$i++) {

			  $description = censor_text(stripper($data["commentList"][$i][0]));

				//echo "---- ".$description." ----";

				$query = "CALL updateContentComment(?,?,?,?,false)";
				$params = array($userId,$data['nodeId'],$description,$data["commentList"][$i][1]);
				$query = $this->db->query($query, $params);

			}

			// KB - Possibly should make sure each comment was updated, but not sure if comments are submitted without changes
			$updated = true;
		}
		if(isset($data['deleteList'])) {
			foreach($data["deleteList"] as $fileName){
				//$query="DELETE FROM ContentToNodes WHERE nodeId = ? AND fileName = ?";
				$query="CALL updateContentComment(?,?,?,?,true)";
				$params=array($userId,$data['nodeId'],"",$fileName);
				$query = $this->db->query($query, $params);
			}
			// KB - Possibly should make sure each file was deleted, but not sure if files in the list have been checked to belong to the node, so silent fail is probably ok
			$updated = true;
		}
		
		if($accessChecked && $updated) {
			$this->db->set('lastEdit', 'NOW()', FALSE)
				->where('nodeId', $nodeId)
				->update('Nodes');
		}
		return $nodeId; 
	}

	function setVisibility($data){
		
		$userId = $this->user->authCheck();
		if (!$userId) {
			return false;
		}

		if(!isset($data["visibility"]) || $data["visibility"] == '') {
			$data["visibility"]=false;
		}

		$query="CALL setNodeVisibility(?,?,?);";

		$params=array($data["nodeId"],$userId,$data["visibility"]);
		$query = $this->db->query($query, $params);

		if($data["visibility"] == true){

			$query="CALL getNodeDetails(?,?);";

			$params=array($userId,$data["nodeId"]);
			$query = $this->db->query($query, $params);

			$result = $query->result_array();

			return($result[0]["body"]);

		} else {
			return true;
		}
	}
	
	/**
	 * For the given contentIds return nodeId,fileName
	 * @param type $contentIds
	 * @return boolean
	 */
	 public function getNodeIdsWithFileNameFromContentIds(array $nodeIds) {
		if(empty($nodeIds)){
			 return false;
		}
		$nodes = [] ;
		foreach (array_chunk($nodeIds, 200) as $chunkOfIds) {
			$this->db->select("ContentToNodes.nodeId, ContentToNodes.fileName")
				->from("ContentToNodes")			
				->where_in('ContentToNodes.nodeId', $chunkOfIds) ;
				$result = $this->db->get();
				if ($result->num_rows() > 0) {
				  $nodes = array_merge($nodes, $result->result_array());				  
				}			
			}
		if(!empty($nodes)){
			return $nodes;
		}
		return false;
    }
	
	function getContent($nodeId, $type, $attachmentId = null) {
		if(!($type == 'text' || $type == 'title' || $type == 'attachment')) {
			return false;
		}
		if($type == 'text' || $type == 'title') {
			$this->db->select("$type AS 'content'")
				->from('Nodes AS n')
				->join('Post AS p', 'p.postId = n.postId')
				->where('n.nodeId', $nodeId);
		} else {
			if(is_null($attachmentId)) {
				return false;
			}
			// determine which text can be editied for different file types
			// sounds, drawings and photos can have description edited
			// maps, pinlocs, files and links can have userTitle edited
			$this->db->select("IF(c.fileType IN ('photo', 'drawing', 'sound'), c.description, c.userTitle) AS 'content'", FALSE)
				->from('ContentToNodes AS n')
				->join('Content AS c', 'c.fileName = n.fileName')
				->where(array(
					'n.fileName' => $attachmentId,
					'n.nodeID' => $nodeId
				));
		}
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return false;
		} else {
			return $result->first_row('array')["content"];
		}
	}
	
	function updateContent($nodeId, $text, $type, $reason = '', $attachmentId = null) {

		$this->load->helper(array('htmlpurifier', 'censor', 'stripper'));
		$text = censor_text(html_purify($text, 'post'));
		$reason = censor_text(html_purify($reason, 'post'));
		$type = censor_text(html_purify($type, 'post'));

		if($type === 'text' || $type === 'title') {
			
			$sql = "UPDATE `Nodes` AS `n`
				JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
				SET `n`.`lastEdit` = ?,
				`n`.`reason` = ?,
				" . ($type === 'text' ? '`n`.`text`' : '`p`.`title`') . " = ?
				WHERE `n`.`nodeId` = ?
				";
			$this->db->query($sql, [date('Y-m-d H:i:s'), $reason, $text, $nodeId]);

		} else if ($type === 'attachment') {
			if(is_null($attachmentId)) {
				return false;
			}
			
			// sounds, drawings and photos can have description edited
			// maps, pinlocs, files and links can have userTitle edited
			$sql = "UPDATE `Nodes` AS `n`
				JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
				JOIN `ContentToNodes` AS `cn` ON `cn`.`NodeID` = `n`.`nodeId`
				JOIN `Content` AS `c` ON `c`.`fileName` = `cn`.`fileName`
				SET `n`.`lastEdit` = ?,
				`n`.`reason` = ?,
				`c`.`description` = IF(`c`.`fileType` IN ('photo', 'drawing', 'sound'), ?, `c`.`description`),
				`c`.`userTitle` = IF(`c`.`fileType` IN ('photo', 'drawing', 'sound'), `c`.`userTitle`, ?)
				WHERE `n`.`nodeId` = ? AND `cn`.`fileName` = ?
				";
			$this->db->query($sql, [date('Y-m-d H:i:s'), $reason, $text, $text, $nodeId, $attachmentId]);
		} else {
			return false;
		}
		
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Finds the subject id for the given node. If the node does not exist, returns false.
	 * 
	 * @param int $nodeId
	 * @return boolean|int
	 */
	public function getSubjectId($nodeId) {
		$this->db->select('p.subjectId')
			->from('Nodes AS n')
			->join('Post AS p', 'p.postId = n.postId')
			->where('n.nodeId', $nodeId);
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return false;
		} else {
			return $result->first_row('array')["subjectId"];
		}
	}
	
	/**
	 * Finds the post id for the given node. If the node does not exist, returns false.
	 *
	 * @param int $nodeId
	 * @return boolean|int
	 */
	public function getPostId($nodeId) {
		$this->db->select('p.postId')
			->from('Nodes AS n')
			->join('Post AS p', 'p.postId = n.postId')
			->where('n.nodeId', $nodeId);
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return false;
		} else {
			return $result->first_row('array')["postId"];
		}
	}
	
	/**
	 * Handles adding censor markup to text within a node (title, body or attachment descriptions).
	 * Checks that the original text isn't changed, and will only be censored by strikethrough.
	 * Returns true if all text given was successfully edited, false otherwise.
	 * nb. passes on to post, but necessary here to be compatible with IReportableContent interface
	 *
	 * @param int $nodeId
	 * @param string | null $text
	 * @param string | null $title
	 * @param array $attachments
	 * @param $error - provided by reference so error can be checked by the caller
	 *
	 * @return boolean
	 */
	public function censorText($nodeId, $text, $title, $attachments, &$error) {
		return $this->post->censorText($nodeId, $text, $title, $attachments, $error);
	}
	
	/**
	 * Marks a node as having been deleted, along with a reason.
	 * Permissions should be checked before calling this.
	 * Returns true if at least one row was updated.
	 * 
	 * @param int $nodeId
	 * @param string $reason
	 * @return boolean
	 */
	function markAsDeleted($nodeId, $reasons, $parentNodeId) {
		$this->load->model('reported_content');
		for ($i = 0; $i < count($reasons); $i++) {
			$reason = mb_strtoupper($reasons[$i]);
			if(!in_array($reason,  $this->reported_content->getReasons())) {
				$reasons[$i] = 'OTHER';
			}
		}

		// Remove entire node
		if($parentNodeId == $nodeId) {
			$this->db->update(
				'Nodes',
				array(
					'deleted' => 1,
					'deleteReason' => implode(',', $reasons)
				),
				array(
					'nodeId' => $nodeId
			));
			return $this->db->affected_rows() > 0;
		// Remove node content
		} else {
			$this->db->update(
				'ContentToNodes',
				array(
					'deleted' => 1,
					'deleteReason' => implode(',', $reasons)
				),
				array(
					'nodeID' => $parentNodeId,
					'fileName' => $nodeId
			));
			return $this->db->affected_rows() > 0;
		}
	}
	
	/**
	 * Marks a node so that it is not considered deleted. Used to
	 * restore deleted nodes.
	 * Permissions should be checked before calling this.
	 * Returns true if at least one row was updated.
	 *
	 * @param int $nodeId
	 * @return boolean
	 */
	function markAsNotDeleted($parentNodeId, $nodeId) {
		
		// Restore entire node
		if($parentNodeId == $nodeId) {
			$this->db->update(
				'Nodes',
				array(
					'deleted' => 0,
					'deleteReason' => ''
				),
				array(
					'nodeId' => $parentNodeId
			));
			return $this->db->affected_rows() > 0;
			// Restore node content
		} else {
			$this->db->update(
				'ContentToNodes',
				array(
					'deleted' => 0,
					'deleteReason' => ''
				),
				array(
					'nodeID' => $parentNodeId,
					'fileName' => $nodeId
			));
			return $this->db->affected_rows() > 0;
		}
	}
	
	function makeSubjectId($subjectName,$userId){
		$subjectName = str_replace("-"," ",$subjectName);
		$query="CALL getSubjectId(?,?);";
		$params=array($subjectName,$userId);
		$query = $this->db->query($query, $params);
		$tmp=$query->result_array();
                if(!empty($tmp)) {
                    return($tmp[0]["subjectId"]);
                } else {
                    return NULL;
                }
	}


	/* added cjt */

	// is this node liked by this user?
	function liked($nodeId){

		$userId = $this->user->authCheck();

		if ($userId !== false) {

			$query = "SELECT * FROM Votes WHERE nodeId = ? AND userId = ? AND thumbsUp = 1;";
			$params = array($nodeId, $userId);
			$query = $this->db->query($query, $params); 
			
			if($query->num_rows()==1){
				return '1'; // it is liked
			}
			return '0';  // it is not liked, or has been liked and unliked (?)

		}
		return '0';

	}

	function iconStatuses($socialId, $subjectId, $nodeId, $postId){

		// get status, for icon display, for:
		// - follow
		// - like
		// - subscribed (eg. am i subscribed to this blog/article/etc)

		// setup array for returning
		$statuses = array();

		// get logged in user id
		$userId = $this->user->authCheck();

		if ($userId !== false) {

			// is this user following the other user in this topic?
			$statuses['following'] = $this->user->isFollowing($socialId, $subjectId); // returns '0' or '1'
			$statuses['subscribed'] = ($this->post->getSubscribed($postId, $userId)) ? 1 : 0; // returns true or false
			$statuses['liked'] = $this->node->liked($nodeId);
			//print_r($statuses);

			return $statuses;

		}
		return false;

	}

	function hasZings($nodeId, $depthToRetrieve){

		$query="SELECT * FROM Nodes WHERE parentNodeId = ? AND level > ?;"; // double check that this is correct in all cases
		$params = array($nodeId, $depthToRetrieve);
		$query = $this->db->query($query, $params); 
		
		if($query->num_rows()>0){
			return true; // there *are* zings
		}
		return false; // zings be there none

	}

	/**
	 * toggles the display state of a node - when active is 1 the node is ready to be displayed
	 * 
	 * @param int $nodeId
	 * @param int $state
	 */
	function toggleNode($nodeId, $active) {
		if(!$userId = $this->user->authCheck()) {
			return false;
		}

		$this->db->select('level')
			->select('postId');
		$this->db->where('nodeId', $nodeId)
			->where('level', 0);
		$query = $this->db->get('Nodes');

		if ($query->num_rows() > 0) {
			$node = $query->row();

			$this->db->set('visible',  $active);
			$this->db->where('postId', $node->postId);
			$this->db->update('Post');
		}

		$this->db->set('active', $active);
		$this->db->where('nodeId', $nodeId);
		$this->db->update('Nodes');
		return true;
	}
	
	function getNodeFromContentId($contentId){
		$userId = $this->user->authCheck() ;
		
		$this->db->select('nodeId');
		$this->db->where('fileName', $contentId);		
		$query = $this->db->get('ContentToNodes');
		if(!$query->num_rows()){
			return false;
		}
		$node = $query->row();
		$nodeDetailsQuery = "CALL getNodeDetails(?,?);"; 
		$params = array($userId,$node->nodeId);
		$nodeDetails = $this->db->query($nodeDetailsQuery,$params);
		if ($nodeDetails->num_rows() > 0) {			
			return $nodeDetails->row();
		} else {
			return false;
		}
	}
}