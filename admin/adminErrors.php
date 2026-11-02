<? if(isset($reportedErrors)) { ?>
	<table class="table">
		<tbody>
			<tr style="text-align:left;vertical-align:middle">
				<td>
					Id/Key
				</td>
				<td>
					Summary
				</td>
				<td>
					Description
				</td>
				<td>
					Reported By
				</td>
				<td>
					Status
				</td>
			</tr>
			<? for ( $i = 0; $i < count($reportedErrors); $i++ ) { ?>
				<tr>
					<td>
						<? print($reportedErrors[$i]["key"]); ?>
					</td>
					<td>
						<? print($reportedErrors[$i]["summary"]); ?>
					</td>
					<td>
						<? print($reportedErrors[$i]["description"]); ?>
					</td>
					<td>
						<? print($reportedErrors[$i]["userName"]); ?>
						<? echo form_open('admin/users-view/'.$reportedErrors[$i]["userId"]); ?>
							<? $this->globals->button('view');?>
						<? echo form_close(); ?>
					</td>
					<td>
						<? if(isset($reportedErrors[$i]["status"])) { ?>
							<? print($reportedErrors[$i]["status"]); ?>
						<? } ?>
					</td>
				</tr>
			<? } ?>
		</tbody>
	</table>
<? } ?>