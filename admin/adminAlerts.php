<script>
	getDojoLayerDependency(['dojo/tabsheader'],  function () {
		require([
			"dijit/form/RadioButton",
			"dijit/form/TextBox",
			"dijit/form/Select",
			"js/widget/SubjectFilteringSelect"
		]);
	});
</script>

<style>
	label, #countrySelectContainer, input, input[type=file] {
		/* in order to define widths */
		display: inline-block;
	}
	
	label {
		width: 30%;
		/* positions the label text beside the input */
		text-align: right;
		margin: 10px;
	}
</style>
<div id="createAlertContainer">
	<? echo form_open_multipart('admin/alerts-create', array('style' => 'width: 50%')); ?>
		<label>Title:</label><input type="text" name="alert_title" data-dojo-type="dijit/form/TextBox" value="<? print(set_value('alert_title')); ?>"/>
		<br>
		<label>Type:</label><!--<input type="radio" name="alert_type" data-dojo-type="dijit/form/RadioButton" value="adminDebate" <? //print(set_radio('alert_type', "adminDebate", TRUE)); ?> />The Great Debate-->
		<input type="radio" name="alert_type" data-dojo-type="dijit/form/RadioButton" value="adminNotice" <? print(set_radio('alert_type', "adminNotice")); ?> />Administration Notice
	<!--	<input type="radio" name="alert_type" data-dojo-type="dijit.form.RadioButton" value="adminVideos" <? //print(set_radio('alert_type', "adminVideo")); ?> />Most Watched Videos-->
		<br>
		<label>Text:</label><textarea name="alert_content"><? print(set_value('alert_content')); ?></textarea>
		<br>
		<label>Target Country:</label><div id="countrySelectContainer"><? $this->globals->countrySelect('alert_country', 'AU', true); ?></div>
		<br>
		<label>Target Forum:</label><div id="subjectSelect" name="alert_subject" data-dojo-type="js/widget/SubjectFilteringSelect"></div>
		<br>
		<label>Include 'have your say'?</label>
		<input type="radio" name="alert_vote" data-dojo-type="dijit.form.RadioButton" value="yes" <? print(set_radio('alert_vote', "yes")); ?> />Yes
		<input type="radio" name="alert_vote" data-dojo-type="dijit.form.RadioButton" value="no" <? print(set_radio('alert_vote', "no", TRUE)); ?> />No
		<br>
		<label>Target User Type:</label>
		<select name='alert_usertype' data-dojo-type="dijit.form.Select" style="width:100px">
			<option value="" selected>All</option>
			<option value="moderator">Moderators</option>
			<option value="supervisor">Supervisors</option>
		</select>
		<br>
		<label>Upload Attachment:</label><? $this->globals->form('file', 'alert_file'); ?>
		<? $this->globals->button('create'); ?>
	<? echo form_close(); ?>
	<!--<p style="color:purple">@todo - add advertisement option, with subject search and selection.</p>-->
	<!--<p style="color:purple">@todo - great debates should be attached to a great debate forum post.</p>-->
	<!--<p style="color:purple">@todo - allow search for specific videos, by name, or popularity etc.</p>-->
	
	<? if(isset($error)) { ?>
		<div style="color: red"><? print($error); ?></div>
	<? } ?>
</div>

<script>
	// Country select widget requires parsing. Since most js widgets are parsed manually, continue to do so to avoid parse onload problems.
	getDojoLayerDependency(['dojo/tabsheader'],  function () {
		require(["dojo/parser", "dojo/dom"], function(parser, dom) {
			parser.parse(dom.byId("createAlertContainer"));
		});
	});
</script>