<style>
	form {
		display: inline-block;
	}
</style>
<h1>Moderator Applications</h1>
<? if (isset($message)) { ?>
	<p><? print($message); ?></p>
<? } ?>
<? if(empty($moderators)) { ?>
	There are no moderator applications needing attention.
<? } else {
	foreach($moderators as $id => $m) { ?>
		<div>
			<h4><? print($m['userName']); ?></h4>
			<div style="border:1px solid black;">
				<div><? print($m['fullName']); ?></div>
				<? echo form_open('admin/users-view/'.$m['userId']); ?>
					<? $this->globals->button('view');?>
				<? echo form_close(); ?>
				<div><? print($m['text']); ?></div>
				<div><? print($m['publicProfile']); ?></div>
				Subjects applied for:<br>
				<? foreach($m['subjects'] as $s) { ?>
					<? print($s['name']); ?>
					<? echo form_open('admin/subjects-applications/'.$s['id']); ?>
						<input type="hidden" name="user" value="<? print($m['userId']); ?>"/>
						<? $this->globals->button('approve');?>
					<? echo form_close(); ?>
					<? echo form_open('admin/subjects-applications/'.$s['id']); ?>
						<? $this->globals->button('deny');?>
						<input type="hidden" name="user" value="<? print($m['userId']); ?>"/>
					<? echo form_close(); ?>
					<br>
				<? } ?>
			</div>
		</div>
	<? } ?>
<? } ?>