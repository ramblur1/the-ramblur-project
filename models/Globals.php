<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Globals extends CI_Model {


	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}

	// generate a unique id across the system
	// checks for conflicting files
	// if no matches, returns the id as its deemed unique
	function uuid() {
		$this->load->model('file');
		$i = 0;
		$result = false;
		$limit = 10000;		// this should be more than enough tries to get a unique id
		
		while (($result === false) && ($i < $limit)) {

			$data = openssl_random_pseudo_bytes(16);

			$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0010
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

			$result = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

			// if the file already exists
			if ($this->file->checkFilePath($result, false) || $this->file->checkFilePath($result, true)) {
				// force another loop
				$result = false;
				$i++;
			}
		}
		return $result;
	}
	
	// draws a nice button for forms, themed in the users colours
	function button($caption, $method = '', $value = false, $icon = false, $disabled = false, $class = '') {
		if(!$value) {
			$value = $caption;
		}
		if(!$icon) {
			$icon = $caption;
		}
		$id = uniqid();?>
		<button id="<? print($id); ?>"class="button <? print($class);?>" name="submit" type="submit" value="<? print($value); ?>" <? $disabled ? print('disabled') : ''; ?>>
			<script>
				getDojoLayerDependency(['dojo/tabsheader'], function () {
					require(["dojo/dom", "js/icon"], function(dom, icon) {
						dom.byId('<? print($id); ?>').innerHTML =  icon.iconDraw('<? print($caption); ?>', '<? print($method);?>', '<? print($icon); ?>', '', '') + '<? print($caption); ?>';
					});
				});
			</script>
		</button>
	<? }
	
	// draws form elements
	function form($element, $name, $value = "",$state = null) { ?>

		<? if ( $element == "colour" ) { ?>
		
			<div data-dojo-type="dijit.form.DropDownButton">
				<span>
					<div id="<?php print($name); ?>Swatch" style="width: 16px; height: 16px; border: 1px solid black; background-color: <?php print($value); ?>;"></div>
				</span>
				<div data-dojo-type="dijit.ColorPalette" data-dojo-props="onChange:function(){document.getElementById('<? print($name); ?>Swatch').style.backgroundColor = this.value; document.getElementById('<? print($name); ?>').value = this.value}, palette:'7x10'"></div>
			</div>
			<input type="hidden" id="<? print($name); ?>" name="<? print($name); ?>" value="<? print($value); ?>" />
		<? } else if ( $element == "opacity" ) { ?>

			<input type="text" size="5" id="<? print($name); ?>" name="<? print($name); ?>" value="<? if ( ( $value !== false ) && ( $value != '' ) ) { print($value); } else { print('100'); } ?>" readonly="readonly"/>
			<div style="width:150px;"
				name="horizontalSlider"
				data-dojo-type="dijit.form.HorizontalSlider"
				data-dojo-props="value: <? if ( ( $value != '' ) && ( $value !== false ) ) { print($value); } else { ?>100<? } ?>,
					minimum: 20,
					maximum: 100,
					discreteValues: 9,
					intermediateChanges: true,
					showButtons: false,
					onChange: function(){document.getElementById('<? print($name); ?>').value = this.value}
				"
			>
			<div data-dojo-type="dijit.form.HorizontalRule" container="bottomDecoration" count=9 style="height:5px;"></div>
				<ol data-dojo-type="dijit.form.HorizontalRuleLabels" container="bottomDecoration" style="height:1em; font-size:75%;">
					<li>clear</li>
					<li>solid</li>
				</ol>
			</div>
			
		<? } else if ( $element == "theme" ) { ?>

			<input type="radio" data-dojo-type="dijit.form.RadioButton" name="<? print($name); ?>" value="light"<? if ( ( $value === false ) || ( $value == 'light' ) ) { ?> checked<? } ?> /> light
			<input type="radio" data-dojo-type="dijit.form.RadioButton" name="<? print($name); ?>" value="dark"<? if ( $value == 'dark' ) { ?> checked<? } ?> /> dark

		<? } else if ( $element == "text" ) { ?>

			<input name="<? print($name); ?>" class="form-control" type="text" data-dojo-type="dijit.form.TextBox" value="<? print($value); ?>" />

		<? } else if ( $element == "textgroup" ) { ?>

			<div class="input-group"><input type="text" name="<? print($name); ?>" placeholder="Enter postcode..." value="<? print($value); ?>" id="postcode" class="form-control" /><span class="input-group-btn"><button class="btn btn-secondary" id="pcsrch" type="button">Submit</button></span></div>

		<? } else if ( $element == "holder" ) { // NB: first case use = id has been hardcoded!?>

			<div id="suburbWrapper"><input name="<? print($name); ?>" class="form-control" id="suburb" type="text" placeholder="Enter postcode first..." value="<? print($value); ?>" readonly="readonly" /></div>

		<? } else if ( $element == "timezone" ) { ?>

			<select name="<? print($name); ?>" value="<? print($value); ?>" data-dojo-type="dijit.form.Select">
				<option value="">Select a timezone</option>
				<?
					$timezones = DateTimeZone::listIdentifiers();
					
					for ( $j = 0, $jlen = count($timezones); $j < $jlen; $j++ ) { ?>
						<option value="<? print($timezones[$j]); ?>"<? if ( $value == $timezones[$j] ) { ?> selected<? } ?>><? print($timezones[$j]); ?></option>
					<? }
				?>
			</select>

		<? } else if ( $element == "country" ) { 

			$countries = $this->countries();
			$selectedState = '';
			?>

			<script>
				getDojoLayerDependency(['dojo/tabsheader'], function() {
					require(["js/global"], function(global) {
						global.writeCountriesToLocalMemory(<? print(json_encode($countries)); ?>);
					});
				});
			</script>

			<div id="countryDropdown" name="<? print ($name);?>"
				<? for ( $j = 0, $jlen = count($countries); $j < $jlen; $j++ ) { 
					if ( $value == $countries[$j]["name"] || $value == $countries[$j]['id']){ ?> 
						value="<? print($countries[$j]["id"]); ?>" <?
						// try to find selected state
						for ($k = 0, $klen = count($countries[$j]["states"]); $k < $klen; $k++) {
							if ($state == $countries[$j]["states"][$k]["name"] || $state == $countries[$j]["states"][$k]["id"]){
								$selectedState = $countries[$j]["states"][$k]["id"];
							}
						}
					}
				}?>
				data-dojo-type="dijit.form.Select" data-dojo-props="onChange:function(){getDojoLayerDependency(['dojo/tabsheader'], function() {require('js/global').getAllStates(dijit.byId('countryDropdown').value,'stateDropdown');});}"
			>
				<span value="" disabled>--- Select a country ---</span>
				<?
					for ( $j = 0, $jlen = count($countries); $j < $jlen; $j++ ) { ?>
						<span value="<? print($countries[$j]["id"]); ?>"
							<? if ( $value == $countries[$j]["id"] || $value == $countries[$j]['name'] ) {?> 
								selected
							<? } ?>>
							<? print($countries[$j]["name"]); ?>
							<?php /*<img src="/assets/themes/default/img/flags_iso/24/<? print(mb_strtolower($countries[$j]["id"])); ?>.png" border="0" />*/ ?>
						</span>
					<? }
				?>
			</div>
			<tr>
				<td>
					<? $this->caption("state","state","");?>
				</td>
				<td>
					<div id="stateDropdown" name="<? print ("store_state");?>" value="<? print($selectedState); ?>" data-dojo-type="dijit.form.Select">
					<span value="" disabled>--- Select a state ---</span>
				<?
					for ( $j = 0, $jlen = count($countries); $j < $jlen; $j++ ) {
						if ( $value == $countries[$j]["name"]  || $value == $countries[$j]['id']){
							for ($k=0,$klen = count($countries[$j]["states"]); $k < $klen;$k++){
								?> <span value="<? print($countries[$j]["states"][$k]["id"]); ?>"><? print($countries[$j]["states"][$k]["name"])?></span>
						<?	}
						}
					} ?>
				</div>
				</td>
			</tr>

		<? } else if ( $element == "gender" ) { ?>

			<input type="radio" data-dojo-type="dijit.form.RadioButton" name="<? print($name); ?>" value="M"<? if ( $value == 'M' ) { ?> checked<? } ?> /> male
			<input type="radio" data-dojo-type="dijit.form.RadioButton" name="<? print($name); ?>" value="F"<? if ( $value == 'F' ) { ?> checked<? } ?> /> female

		<? } else if ( $element == "date" ) { 

				if($value==='1001-01-01') $value = '';
			?>
		
			<input type="text" name="<? print($name); ?>" id="<? print($name); ?>" value="<? print($value); ?>" data-dojo-type="dijit.form.DateTextBox" data-dojo-props="constraints: { datePattern : 'yyyy-MM-dd' }, promptMessage: 'yyyy-MM-dd', invalidMessage: 'Invalid date format. Use yyyy-MM-dd'"/>

		<? } else if ( $element == "file" ) { ?>

			<input type="file" name="<? print($name); ?>" id="<? print($name); ?>" />

		<? }else if ( $element == "files" ) { ?>

			<input type="file" name="<? print($name); ?>[]" id="<? print($name); ?>" multiple/>

		<? } else {

			return false;

		} ?>
	<? }

	/**
	 * Alternative to using globals->form('country'...). Allows for the inclusion of 'none' as an option for countries. 
	 * 
	 * @param string $name
	 * @param string $selected
	 * @param boolean $allowNone
	 */
	function countrySelect($name, $selected, $allowNone = false) {
		$countries = $this->countries();
		?> 
		<div name="<? print($name); ?>" value="<? print($selected); ?>"  data-dojo-type="dijit.form.Select">
			<? if($allowNone) { ?>
				<span value="">None</span>
			<? } ?>
			<? foreach($countries as $c) { ?>
				<span value="<? print($c['id']); ?>" <? if($selected == $c['id']) { ?> selected <? } ?>>
					<? print($c['name']); ?> 
					<img src="/assets/themes/default/img/flags_iso/24/<? print(mb_strtolower($c['id'])); ?>.png" border="0" />
				</span>
			<? } ?>
		</div>
		<?
	}
	
	// draws a nicely formatted form
	// TODO: move span "outside" the <i>
	function caption($title, $label, $hint = "") { ?>
		<span class="settingsCaption" id="sc-<?php echo mb_strtolower($title); ?>"><? print(ucwords($label)); ?></span>
		<? if ( $hint != '' ) { ?>
			<br />
			<i><? print($hint); ?></i>
		<? } ?>
	<? }


	
	// filters text
	function filter($text = "") {

		if ( $text != "" ) {

			// hashtags
			// $text = preg_replace('/(^|\s)#(\w*[a-zA-Z_]+\w*)/', '\1<span class="hashtag">‖\2</span>', $text);
			$text = preg_replace('/(^|\s)#(\w*[a-zA-Z_]+\w*)/', '\1<span class="hashtag">#\2</span>', $text);

			// for links
			$text = preg_replace("/(\b)(http|ftp|https|mailto)([\:\/\/])([A-z0-9~!@$%&*()_+:?,.\/;'=#-]{2,}[A-z0-9~@$%&*_+\/'=])/","<a href=\"$2$3$4\" ref=\"nofollow\" target=\"_blank\"><span class=\"url\">$2$3$4</span></a>", $text);

			// for newlines
			$text = str_replace("\n", "<br />", $text);

			// for smileys
			$text = str_replace(":)", " <img src=\"/assets/emoticons/smile.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":(", " <img src=\"/assets/emoticons/Sad.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":P", " <img src=\"/assets/emoticons/Silly.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":I", " <img src=\"/assets/emoticons/Straight-Face.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":|", " <img src=\"/assets/emoticons/Straight-Face.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":D", " <img src=\"/assets/emoticons/Big-Grin.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("8)", " <img src=\"/assets/emoticons/Nerd.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":`(", " <img src=\"/assets/emoticons/Crying.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":x", " <img src=\"/assets/emoticons/Not-Talking.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":X", " <img src=\"/assets/emoticons/Not-Talking.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(";)", " <img src=\"/assets/emoticons/Winking.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(">:(", " <img src=\"/assets/emoticons/Angry.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("*:o)", " <img src=\"/assets/emoticons/Clown.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("*:oP", " <img src=\"/assets/emoticons/Clown.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("B)", " <img src=\"/assets/emoticons/Smug.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("}:)", " <img src=\"/assets/emoticons/Devil.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("%)", " <img src=\"/assets/emoticons/Drooling.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace(":O", " <img src=\"/assets/emoticons/Scared.png\" border=\"0\" align=\"absmiddle\" />", $text);

			$text = str_replace("<3", " <img src=\"/assets/emoticons/Heart.png\" border=\"0\" align=\"absmiddle\" />", $text);

			// acronyms
			$text = str_replace("lol", " <img src=\"/assets/emoticons/Laughing.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("wtf", " <img src=\"/assets/emoticons/Confused.png\" border=\"0\" align=\"absmiddle\" />", $text);

			// others
			$text = str_replace("shh", " <img src=\"/assets/emoticons/Don't-tell-Anyone.png.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("zzz", " <img src=\"/assets/emoticons/Sleeping2.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("ghost", " <img src=\"/assets/emoticons/Ghost.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("hrm", " <img src=\"/assets/emoticons/Thinking.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("hmm", " <img src=\"/assets/emoticons/Thinking.png\" border=\"0\" align=\"absmiddle\" />", $text);

			// these are all thumbs up people
			$text = str_replace("cool", " <img src=\"/assets/emoticons/Cool.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("yes", " <img src=\"/assets/emoticons/Cool.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("awesome", " <img src=\"/assets/emoticons/Cool.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("great", " <img src=\"/assets/emoticons/Cool.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("super", " <img src=\"/assets/emoticons/Cool.png\" border=\"0\" align=\"absmiddle\" />", $text);
			$text = str_replace("sweet", " <img src=\"/assets/emoticons/Cool.png\" border=\"0\" align=\"absmiddle\" />", $text);

			// these are the word filters
				$filters = array();
				$filters[] = array('match' => 'hilux', 'phrases' => array('unbreakable but not unboggable', 'hiluxs\' do it in the mud'));
				$filters[] = array('match' => 'starlet', 'phrases' => array('DID SOMEONE SAY HOT HATCH?!'));

				for ( $i = 0, $ilen = count($filters); $i < $ilen; $i++ ) {
					$text = str_replace($filters[$i]['match'], '<abbr title="'.$filters[$i]['phrases'][mt_rand(0, count($filters[$i]['phrases'])-1)].'">'.$filters[$i]['match'].'</abbr>', $text);
				}

			// now lets do the 'phrase' ads
				$query = "select `nodeId` from `properties` where `name` = 'type' and `value` = 'phrase';";
				$query = $this->db->query($query);

				// if there are any ads
				if ( $query->num_rows() > 0 ) {

					// go through each result
					foreach ( $query->result_array() as $row ) {

						// fetch more info about this ad
						$ad = $this->node->info($row['nodeId']);

						// title, description, url
						$title = $this->findProperty('title', $ad["properties"]);
						$desc = $this->findProperty('description', $ad["properties"]);
						$url = $this->findProperty('url', $ad["properties"]);

						// if they're all valid
						if ( ( $title !== false ) && ( $title != '' ) && ( $desc !== false ) && ( $desc != '' ) && ( $url !== false ) && ( $url != '' ) ) {

							// do a filter for it.
							// onclick="window.open(\''.$url.'\');"
							$text = str_replace($title, '<i title="'.$desc.'" style="cursor: pointer;" onclick="window.open(\''.$url.'\');">'.$title.'</i>', $text);

						}

					}

				}

			// now fix up quotes
			str_replace('"', '\"', $text);

		}

		return $text;

	}

	
	// list of all countries and states/provinces
	function countries($selected = false) {
		

		$this->db->select("c.countryId, c.name as 'countryName', s.stateId, s.name AS 'stateName', s.abbreviation as 'stateAbbreviation'")
			->from('Country AS c')
			->join('State AS s', 's.countryId = c.countryId')
			->order_by('c.name');
		$results = $this->db->get()->result_array();
		$countriesById = array();
		foreach($results as $r) {
			$id = $r["countryId"];
			if(!isset($countriesById[$id])) {
				$countriesById[$id] = array();
			}
			$countriesById[$id][] = $r;
		}
		$countries = array();
		foreach($countriesById as $id => $countryRows) {
			$country = array();
			$country["id"] = $id;
			$country["name"] = $countryRows[0]["countryName"];
			$country["states"] = array();
			foreach($countryRows as $c) {
				$state = array();
				$state["id"] = $c["stateId"];
				$state["name"] = $c["stateName"];
				$state["abbreviation"] = $c["stateAbbreviation"];
				$country["states"][] = $state;
			}
			$countries[] = $country;
		}
		
		// if no country specified
		if((!isset($selected)) || ($selected == '') || ($selected === false)) {
			// return the whole list
			return $countries;
			
		// a country has been specified
		} else {
			// go through all countries
			for($i = 0; $i < count($countries); $i++) {
				// if the country matches
				if(
					($countries[$i]["name"] == $selected) ||
					($countries[$i]["id"] == $selected)// ||
//					($countries[$i]["alpha-2"] == $selected) ||
//					($countries[$i]["alpha-3"] == $selected) ||
//					($countries[$i]["numeric"] == $selected) ||
//					($countries[$i]["iso 3166-2"] == $selected)
				) {
					// return it
					return $countries[$i];
				}
			}
		}
		return false;
	}
	
	function getTimezones() { 
		$timezones = DateTimeZone::listIdentifiers();
		return $timezones;
	}

	function getAllStatesAndPostcodes($selected = false) {

		$memcache = new Memcached();
		$memcache->addServer($this->config->item('memchostname'),$this->config->item('memcport'));
		$states = $memcache->get("states");
		$data = array();

		if ($states) {
			return $states;
		}

		$this->db->select("s.stateId, s.abbreviation as 'stateName', p.postcodeId, CONCAT(p.postCode, '  ', p.locality) AS 'postcodeName', p.locality", FALSE)
			->from('State AS s')
			->join('postCode AS p', 's.stateId = p.stateId');
			//->order_by('c.name');
		$results = $this->db->get()->result_array();
		$statesById = array();
		foreach ($results as $r) {
			$id = $r["stateId"];
			if (!isset($statesById[$id])) {
				$statesById[$id] = array();
			}
			$statesById[$id][] = $r;
		}
		$states = array();
		foreach ($statesById as $id => $stateRows) {
			$state = array();
			$state["id"] = $id;
			$state["name"] = $stateRows[0]["stateName"];
			$state["postcodes"] = array();
			foreach ($stateRows as $c) {
				$postcode = array();
				$postcode["id"] = $c["postcodeId"];
				$postcode["name"] = $c["postcodeName"];
				$postcode["abbreviation"] = $c["locality"];
				$state["postcodes"][] = $postcode;
			}
			$states[] = $state;
		}

		// if no country specified
		if ((!isset($selected)) || ($selected == '') || ($selected === false)) {
			// return the whole list
			$memcache->set("states", $states);
			return $states;

		// a country has been specified
		} else {
			// go through all countries
			for ($i = 0; $i < count($states); $i++) {
				// if the country matches
				if (
					($states[$i]["name"] == $selected) ||
					($states[$i]["id"] == $selected)
				) {
					return $states[$i];
				}
			}
		}
		return false;
	}

	function states($country){

		$query="SELECT stateId, name from State WHERE countryId = ?;";
		$params=array($country);

		$query = $this->db->query( $query, $params);

		if ( $query->num_rows() > 0 ) {

			$states = array();
			$states = $query->result_array();

			return $states;

		} else {
			return false;
		}
	}

	// print out all the countries to choose from
	function countriesJs() { ?>
		<script>
			<? $countries = $this->countries(); ?>
			var countries = <? print(json_encode($countries)); ?>;
		</script>
	<? }



	// prints out all the possible timezone settings
	function timezonesJs() { ?>
		<script language="javascript">
			<? $timezones = DateTimeZone::listIdentifiers(); ?>
			var timezones = new Array();
			<? for ( $j = 0, $jlen = count($timezones); $j < $jlen; $j++ ) { ?>
				timezones[timezones.length] = '<? print($timezones[$j]); ?>';
			<? } ?>
		</script>
	<? }

	

	function toolBar($tool = false, $user = false) {?>
		<? if ( $tool !== false ) { ?>
			<table class="toolBar" border="0" width="100%">
				<tbody>
					<tr>
						<td nowrap="nowrap">
							<div id="logo">
								<? print($tool); ?>
							</div>
						</td>
						<td width="1" nowrap="nowrap">
							<? if ( ( isset($user) ) && ( $user !== false ) ) { ?>
								<div onclick="globalLogoff();" class="button">
									<script language="javascript">
									require(["dojo/dom", "dojo/dom-construct", "dojo/ready", "js/icon","js/api", "dojo/domReady!"], function(dom, domConstruct, ready,icn,api) {
									ready(function() {		
										icn.iconDraw('logout', '', 'logout', '', '');
									});	});		
									</script>
									logout
								</div>
							<? } else { ?>
								<div id="logonPlaceholder">
								</div>
								<script language="javascript">
									require(["dojo/ready","js/global","js/api", "dojo/domReady!"], function(ready,gb,api) {
									ready(function() {	
									// draw a login form
										gb.globalLogonForm('logonPlaceholder');
									});	});
								</script>
							<? } ?>
						</td>
						<td width="1" style="white-space: nowrap;">
							<?
								$buttons = array("settings", "advertising", "management", "admin", "tools", "forum");
								for ( $i = 0; $i < count($buttons); $i++ ) { ?>
									<? if (
											(
												( $buttons[$i] == "settings" ) || ( $buttons[$i] == "advertising" ) || ( $buttons[$i] == "tools" ) || ( $buttons[$i] == "forum" )
											) || (
												$user !== false
											)
									) { ?>

									       <div class="toolbarButton<? if ( $buttons[$i] == $tool ) { ?>Hover<? } ?>" onclick="document.location = '<?php echo $this->config->base_url(); if ( $buttons[$i] !== 'forums' ) { print($buttons[$i]); } ?>';"<? if ( $buttons[$i] != $tool ) { ?> onmouseover="this.className='toolbarButtonHover';" onmouseout="this.className='toolbarButton';"<? } ?>>
											<script language="javascript">
											require(["dojo/dom", "dojo/dom-construct", "dojo/ready", "js/icon","dojo/domReady!"], function(dom, domConstruct, ready,icn) {
											ready(function() {
												icn.iconDraw('<? print($buttons[$i]); ?>', '', '<? print($buttons[$i]); ?>', '', '<? print($buttons[$i]); ?>');
											});	});										
											</script>
											<? print($buttons[$i]); ?>
										</div>
									<? } ?>
								<? }
							?>
						</td>
					</tr>
				</tbody>
			</table>
		<? } else { ?>
			no tool found...
		<? } ?>
	<? }

	// verifies an email address as best we can!
	function emailVerify($address) {

		if (
			( $address !== false ) &&
			( is_string($address) !== false ) &&
			( stripos($address, '@') !== false ) &&
			( stripos($address, '.') !== false ) &&
			( mb_strlen($address) >= 5 )
		) {
			return true;
		} else {
			return false;
		}
	}

	// sends an outgoing message via email - can be extended to sms later
	function externalMail($data, $line_breaks='', $branded = true) {

		// if data has something in it
		if ( ( isset($data) ) && ( count($data) > 0 ) ) {

			if ( ( isset($data["to"]) ) && ( $data["to"] !== false ) ) {

				// if it seems like an email address
				if ( $this->emailVerify($data["to"]) !== false ) {

					$to = $data["to"];

				// if it doesn't look like an email address
				} else {

					// fetch the user info
					$userTo = $this->user->info($data["to"]);

					// find the email address
					$emailTo = $this->findProperty("email", $userTo["properties"]);

					if ( $emailTo !== false ) {

						$to = $emailTo;

					}

					unset($userTo);
					unset($emailTo);

				}
			}
			if ((isset($data["from"])) && ($data["from"] !== false )) {

				// if it looks like an email address
				//if ( $this->emailVerify($data["from"]) !== false ) {
				if ( filter_var($data["from"],FILTER_VALIDATE_EMAIL )) {

					$from = filter_var($data["from"],FILTER_SANITIZE_EMAIL);

				// if it doesn't look like an email address
				} else {

					// if its the server sending something
					if ( $data["from"] == 'server' || $data["from"] == 'Ramblur' || $data["from"] == 'Ramblur Support' ) {

						$from = 'admin@ramblur.com';

					} else {

						// fetch the user info
						if(!filter_var($data["from"],FILTER_VALIDATE_INT)){return false;}
						$userFrom = $this->user->info($data["from"]);
						$from = $this->findProperty("email", $userFrom["properties"]);

					}

				}
			}
			if ( ( isset($data["subject"]) ) && ( $data["subject"] !== false ) ) {
				$subject = $data["subject"];
			}
			if ( ( isset($data["message"]) ) && ( $data["message"] !== false ) ) {

				if ($branded) {
					$header = $this->load->view('email/generic-header', '', true);
					$footer = $this->load->view('email/generic-footer', '', true);
				} else {
					$header = $this->load->view('email/generic-header-no-brand', '', true);
					$footer = $this->load->view('email/generic-footer-no-brand', '', true);
				}

				if ($line_breaks != '') {
					$message = $header.$data["message"].$footer;
				} else {
					$message = $header.nl2br($data["message"]).$footer;
				}

			}

			if (isset($to, $from, $subject, $message)) {

				$headers = 'From: ' . $from . "\r\n" .
						'Reply-To: ' . $from . "\r\n" .
						'MIME-Version: ' . '1.0' . "\r\n".
						'Content-Type: ' . 'text/html; charset=UTF-8' . "\r\n".
						'X-Mailer: PHP/' . phpversion();
				$success = mb_send_mail($to, $subject, $message, $headers);
				return $success;

			}
			return false;

		}
		return false;

	}

	function getCatFilterList($userId,$euid){

		$query = "SELECT catFilterList FROM UserToSubject WHERE subjectId = ? AND userId = ?;";
		$params = array($euid,$userId);
		$query = $this->db->query($query, $params);
		$tmp = $query->result_array(); 

		if (!isset($tmp[0]["catFilterList"])) {
			return '';
		}
		return($tmp[0]["catFilterList"]);
	}

	// USES GEONAMES API
	function verifyRecaptcha($response) {
		if (ENVIRONMENT === 'development') {
			return ['success' => true];
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify?secret='.$this->config->item("recaptchaSecret").'&response='.$response);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
		curl_setopt($ch, CURLOPT_PROXY, $this->config->item("ramblur_internal_proxy").$this->config->item("ramblur_internal_proxy_port"));
		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);

		return $response;
	}

	/**
	 * Replaces old property->find(). Searches through an array of properties to return what the user is looking for.
	 * @todo - redo data that uses property structure so it's more direct
	 */
	function findProperty($needle, $haystack) {

		for ( $i = 0, $ilen = count($haystack); $i < $ilen; $i++ ) {
			if ( ( isset($haystack[$i]["name"]) ) && ( $haystack[$i]["name"] == $needle ) ) {
				return $haystack[$i]["value"];
			}
		}
		return false;
	}
	
	function getInvestorEmail() {

		$query = $this->db->select('toEmail');
		$query = $this->db->limit('1');
		$query = $this->db->get('investor');

		if ($query->num_rows() > 0) {
			//$row = $query->row();
			return $query->row()->toEmail;
		}
		return null;
	}
}
