<style>

.dojoxCalendar .dojoxCalendarMonthLabel {
	color: black;
}
.dojoxCalendar .dojoxCalendarYearLabel {
	color: black;
}

.dojoxCalendar .dojoxCal-MY-btns .dijitButtonNode {
	color:black;
}

</style>

<div id="wrapper" style="height:100%;">
<div data-dojo-type="dojox/widget/MonthAndYearlyCalendar" 
	<? if(isset($year)) {
		if(isset($month)) {
			print("value=\"$year-$month-01\"");
		} else {
			print("value=\"$year-".date('m')."-01\"");
		}
	} ?> data-dojo-props="constraints:{max:new Date()}" class="statsCalendar">
	<script type="dojo/method" data-dojo-event="onChange" data-dojo-args="value">
		require(['dojo/dom', 'dojo/date/locale'], function(dom, locale){
			dom.byId('hiddenYear').value = locale.format(value, {datePattern:'y', selector:'date'});
			dom.byId('hiddenMonth').value = locale.format(value, {datePattern:'MM', selector:'date'});
		});
	</script>
</div>

<? echo form_open('admin/statistics-'.$type); ?>
	<input id="hiddenYear" type="hidden" name="stats_year" value="<? isset($year) ? print($year) : ''; ?>"/>
	<input id="hiddenMonth" type="hidden" name="stats_month" value="<? isset($month) ? print($month) : ''; ?>"/>
	<input id="wholeYear" type="checkbox" name="stats_wholeYear" value="Yes"<? if(isset($wholeYear)) {print(" checked");} ?> <? if (!$includeYearly) { ?>disabled="true"<? } ?>>Entire Year?
	<input id="allTime" type="checkbox" name="stats_allTime" value="Yes"<? if(isset($allTime)) {print(" checked");} ?>>All Time?
<? $this->globals->button('update'); ?>
<? echo form_close(); ?>

<? if(isset($error)) {
	?><div style="color: red"><? print($error); ?></div><?
} ?>

<div id="statsGrid" class="admin-grid"></div>

<? if(is_array($stats)) {
	if(empty($stats)) { ?>
		No results
	<? } else { ?>
		<script>
			require([
				"dojo/_base/declare",
				'dgrid/Grid',
				'dgrid/extensions/DijitRegistry',
				'dojo/domReady!'
			], function(declare, Grid, DijitRegistry) {

				var grid, columns = [], col;
				var renderCellFunction = function(object, value, node, options) {
					node.innerHTML = value.value;
				};
				
			<? foreach($stats[0] as $k => $v) { ?>
				columns.push({
					label: '<? print($v['label']); ?>',
					field: '<?php print($k); ?>',
					sortable: false, // sortable would be handy, but since the column value is an object, not a primitive, it doesn't work properly
					renderCell: renderCellFunction
				});
			<? } ?>
				grid = new (declare([Grid, DijitRegistry])) ({
					columns: columns
				}, 'statsGrid');
				grid.renderArray(<? print(json_encode($stats)); ?>);
			});
		</script>

	<?} ?>
<? } else { ?>
	No results
<? } ?>

</div>

<script>
	require(["dojo/parser", "dojo/dom", "dojo/ready"], function(parser, dom, ready) {
		ready(function() {
			parser.parse(dom.byId('wrapper'));
		});
	});
</script>