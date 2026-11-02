
    <div class="container-fluid" id="adContainer" style="background-color:#FFFFFF;height:100%;width:100%;">
        <!-- <div class="row"> -->
	  <div class="well adAdminTitleWell"> 
	      <div class="adAdminTitle" ><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;ad administration / ad stats  / <? echo $sectionTitle; ?> / <? echo $sectionTitle2; ?> 
		<? 
		if(!empty($sectionTitle3)) { ?>
		    / <? echo $sectionTitle3; ?>
		<? } ?>
	    </div> 
	</div>

      <div class="well">
       <div class="row">
        <div class="col-sm-12">    
	    <!-- draw main buttons -->
	    <? if ($sectionTitle2 != 'region') { ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/period'); ?>
    	    <button type=submit class="flat-butt flat-primary-butt">By Period</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio1" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioPeriod" name="adMonthRadioPeriod">
    	    <input type="hidden" id="setAdDate1_1" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_1" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio1" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio1" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio1" name="adLimitRadio">
		<? echo form_close(); ?>          
		<? echo form_open('settings/adStats/' . $sectionTitle . '/forum'); ?>
    	    <button class="flat-butt flat-primary-butt">By Forum</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio2" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioForum" name="adMonthRadioForum">
    	    <input type="hidden" id="setAdDate1_2" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_2" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio2" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio2" name="adFreeRadio">
		<? echo form_close(); ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/region/international'); ?>
    	    <button type="submit" class="flat-butt flat-primary-butt">Region</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio3" name="adTypeRadio">
    	    <!-- above id has same id as below, but only one in DOM at same time -->
    	    <input type="hidden" id="setAdMonthRadioInternational" name="adMonthRadioInternational">
    	    <input type="hidden" id="setAdDate1_3" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_3" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio3" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio3" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio3" name="adLimitRadio">
		<? echo form_close(); ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/ageGroup'); ?>
    	    <button class="flat-butt flat-primary-butt">By Age Group</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio4" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioAgeGroup" name="adMonthRadioAgeGroup">
    	    <input type="hidden" id="setAdDate1_4" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_4" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio4" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio4" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio4" name="adLimitRadio">
		<? echo form_close(); ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/gender'); ?>
    	    <button class="flat-butt flat-primary-butt">By Gender</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio5" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioGender" name="adMonthRadioGender">
    	    <input type="hidden" id="setAdDate1_5" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_5" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio5" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio5" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio5" name="adLimitRadio">
		<? echo form_close(); ?>
	    <? } else { ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/region/international'); ?>
    	    <button type="submit" class="flat-butt flat-primary-butt">International</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio6" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioInternational" name="adMonthRadioInternational">
    	    <input type="hidden" id="setAdDate1_6" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_6" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio6" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio6" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio6" name="adLimitRadio">
		<? echo form_close(); ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/region/country'); ?>
    	    <button type="submit" class="flat-butt flat-primary-butt">By Country</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio7" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioCountry" name="adMonthRadioCountry">
    	    <input type="hidden" id="setAdDate1_7" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_7" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio7" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio7" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio7" name="adLimitRadio">
		<? echo form_close(); ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/region/state'); ?>
    	    <button type="submit" class="flat-butt flat-primary-butt">By State</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio8" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioState" name="adMonthRadioState">
    	    <input type="hidden" id="setAdDate1_8" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_8" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio8" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio8" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio8" name="adLimitRadio">
		<? echo form_close(); ?>
		<? echo form_open('settings/adStats/' . $sectionTitle . '/region/postcode'); ?>
    	    <button type="submit" class="flat-butt flat-primary-butt">By Postcode</button>
    	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
    	    <input type="hidden" id="setAdTypeRadio9" name="adTypeRadio">
    	    <input type="hidden" id="setAdMonthRadioPostcode" name="adMonthRadioPostcode">
    	    <input type="hidden" id="setAdDate1_9" name="adDate1Period">
    	    <input type="hidden" id="setAdDate2_9" name="adDate2Period">
    	    <input type="hidden" id="setAdStatusRadio9" name="adStatusRadio">
    	    <input type="hidden" id="setAdFreeRadio9" name="adFreeRadio">
    	    <input type="hidden" id="setAdLimitRadio9" name="adLimitRadio">
		<? echo form_close(); ?>
	    <? } ?>
	    <!-- end main buttons -->
            
	    <!-- update button -->
		<? echo form_open('settings/adStats/' . $sectionTitle . '/' . $sectionTitle2 . '/' . $sectionTitle3); ?>
	    <button type="submit" style="float:right;width:100px;border-radius:10px;padding:6px;" class="flat-butt flat-primary-butt">Update</button>
	    <input type="hidden" id="setAdTypeRadioUpdate" name="adTypeRadio"> 
	    <input type="hidden" name="year" value="<? echo $currentYear ?>">
	    <input type="hidden" id="setAdMonthRadioUpdate" name="adMonthRadioUpdate">
	    <input type="hidden" id="setAdDate1_10" name="adDate1Period">
	    <input type="hidden" id="setAdDate2_10" name="adDate2Period">
	    <input type="hidden" id="setAdStatusRadio10" name="adStatusRadio">
	    <input type="hidden" id="setAdFreeRadio10" name="adFreeRadio">
	    <input type="hidden" id="setAdLimitRadio10" name="adLimitRadio">
	    <input type="hidden" name="update" value="update">
		<? echo form_close(); ?>
	    <!-- End update button -->

	    <!-- draw drop buttons -->
            <div class="btn-group" style="float:right;">
		<button type="button" class="dropdown-toggleflat-butt flat-primary-butt" style="width:200px;border-radius:10px;padding:6px;" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		    <div class="dropButText"> Options</div><div class="carett"><!-- sic --></div>
		</button>
		<ul class="dropdown-menu" style="overflow: auto;">
		    <!-- draw year buttons -->
		    <? foreach ($years as $year) { ?>
			<? echo form_open('settings/adStats/' . $sectionTitle . '/' . $sectionTitle2 . '/' . $sectionTitle3); ?>
                        <li><button type="submit" class="flat-butt flat-primary-butt"><? echo $year["year"]; ?></button></li>
                        <input type="hidden" name="adTypeRadio" id="<? echo 'setAdTypeRadio' . $year["year"]; ?>">
                        <input type="hidden" name="adMonthRadioYear" id="<? echo 'setAdMonthRadioYear' . $year["year"]; ?>">
                        <input type="hidden" name="year" value="<? echo $year["year"]; ?>" >
                        <input type="hidden" id="<? echo 'setAdStatusRadio' . $year["year"]; ?>" name="adStatusRadio">
                        <input type="hidden" id="<? echo 'setAdFreeRadio' . $year["year"]; ?>" name="adFreeRadio">
                        <input type="hidden" id="<? echo 'setAdLimitRadio' . $year["year"]; ?>" name="adLimitRadio">
			<? echo form_close(); ?>
		    <? } ?>
		    <!-- end draw year buttons -->

		    <!-- month radio buttons -->
                    <li role="separator" class="divider"></li>
		    <div style="margin-bottom: 10px;height: 110px;background: #1abc9c;padding: 10px;
				border: 1px solid #DDD;border-radius: 5px;box-shadow: 2px 2px 2px #F8F8F8;">
			<li> 
			    <label class="radio-inline" style="margin-left:10px"><input id="adMonthRadio1" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus1; ?>><span style="color:#FFFFFF;">Jan</span></label>
			    <label class="radio-inline" style="margin-left:10px"><input id="adMonthRadio2" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus2; ?>><span style="color:#FFFFFF;">Feb</span></label>
			    <label class="radio-inline" style="margin-left:10px"><input id="adMonthRadio3" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus3; ?>><span style="color:#FFFFFF;">Mar</span></label>
			    <label class="radio-inline" style="margin-left:10px;float:left"><input id="adMonthRadio4" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus4; ?>><span style="color:#FFFFFF;">Apr</span></label>
			    <label class="radio-inline" style="margin-left:10px;float:left"><input id="adMonthRadio5" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus5; ?>><span style="color:#FFFFFF;">May</span></label>
			    <label class="radio-inline" style="margin-left:10px"><input id="adMonthRadio6" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus6; ?>><span style="color:#FFFFFF;">Jun</span></label>
			    <label class="radio-inline" style="margin-left:10px" ><input id="adMonthRadio7" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus7; ?>><span style="color:#FFFFFF;">Jul</span></label>
			    <label class="radio-inline" style="margin-left:12px"><input id="adMonthRadio8" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus8; ?>><span style="color:#FFFFFF;">Aug</span></label>
			    <label class="radio-inline" style="margin-left:10px"><input id="adMonthRadio9" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus9; ?>><span style="color:#FFFFFF;">Sep</span></label>
			    <label class="radio-inline" style="margin-left:10px;float:left"><input id="adMonthRadio10" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus10; ?>><span style="color:#FFFFFF;">Oct</span></label>
			    <label class="radio-inline" style="margin-left:10px;float:left"><input id="adMonthRadio11" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus11; ?>><span style="color:#FFFFFF;">Nov</span></label>
			    <label class="radio-inline" style="margin-left:11px;float:left"><input id="adMonthRadio12" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus12; ?>><span style="color:#FFFFFF;">Dec</span></label>
			    <label class="radio-inline" style="margin-left:10px"><input id="adMonthRadio13" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus13; ?>><span style="color:#FFFFFF;">By Year</span></label>
			    <!-- 
			    <label class="radio-inline" style="margin-left:10px"><input id="adMonthRadio14" type="radio" name="adMonthRadio" <? echo $adMonthRadioStatus14; ?>><span style="color:#FFFFFF;">All Time</span></label>
			    -->
			</li>

		    </div>
		    <!-- end month radio buttons -->

		    <!-- date range button -->
		    <li role="separator" class="divider"></li>
		    <li><button type="button" class="flat-butt flat-primary-butt" onClick="require('js/adAdmin').setByYear();">Choose Date Range</button></li>
		    <input type="hidden" id="setDateRangeUpdate" name="">
		    <input type="hidden" name="year" value="<? echo $currentYear ?>">
                    <li role="separator" class="divider"></li>
		    <!-- date range button -->

		    <!-- all time buttons -->
                    <? if ($sectionTitle === 'revenue') { ?>
                    <li role="separator" class="divider"></li>
		    <? echo form_open('settings/adStats/allRevenue'); ?> 
		    <li><button type="submit" class="flat-butt flat-primary-butt">All Time Ad Revenue</button></li>
		    <input type="hidden" id="setAdTypeRadio10" name="adTypeRadio">
		    <? echo form_close(); ?>
                    <? } ?>
                    <? if ($sectionTitle === 'numbers') { ?>
                    <li role="separator" class="divider"></li>
		    <? echo form_open('settings/adStats/allNumbers'); ?>
		    <li><button type="submit" class="flat-butt flat-primary-butt">All Time Ad Numbers</button></li>
		    <input type="hidden" id="setAdTypeRadio11" name="adTypeRadio">
		    <? echo form_close(); ?>
                    <? } ?>
		    <!-- End all time buttons -->
		    
		    <!-- draw ad style selection radio buttons -->
		    <li role="separator" class="divider"></li>
		    <div style="margin-bottom: 10px;height: 94px;background: #1abc9c;padding: 10px;
				border: 1px solid #DDD;border-radius: 5px;box-shadow: 2px 2px 2px #F8F8F8;">
			<li><label class="radio-inline"><input id="adTypeRadio2" type="radio" name="adTypeRadio" <? echo $adTypeShowRadioStatus; ?>><span style="color:#FFFFFF;">Show Ad Types</span></label></li>
			<li><label class="radio-inline"><input id="adTypeRadio3" type="radio" name="adTypeRadio" <? echo $adTypeCatRadioStatus; ?>><span style="color:#FFFFFF;">Show Ad Categories</span></label></li>
			<li><label class="radio-inline"><input id="adTypeRadio4" type="radio" name="adTypeRadio" <? echo $adTypeCostModelRadioStatus; ?>><span style="color:#FFFFFF;">Show Ad Cost Models</span></label></li>
                        <li><label class="radio-inline"><input id="adTypeRadio1" type="radio" name="adTypeRadio" <? echo $adTypeGroupRadioStatus; ?>><span style="color:#FFFFFF;">Group All Ads</span></label></li>
		    </div>
		     <!-- End ad style selection radio buttons -->
		     
		     <!-- draw ad status buttons -->
		     <li role="separator" class="divider"></li>
		     <div style="margin-bottom: 10px;height: 150px;background: #1abc9c;padding: 10px;
				border: 1px solid #DDD;border-radius: 5px;box-shadow: 2px 2px 2px #F8F8F8;">

			 <li><label class="radio-inline"><input id="adStatusRadio1" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus1; ?>><span style="color:#FFFFFF;">Active Status</span></label></li>
			 <li><label class="radio-inline"><input id="adStatusRadio2" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus2; ?>><span style="color:#FFFFFF;">Paused Status</span></label></li>
			 <li><label class="radio-inline"><input id="adStatusRadio3" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus3; ?>><span style="color:#FFFFFF;">Ended Ads</span></label></li>
			 <li><label class="radio-inline"><input id="adStatusRadio4" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus4; ?>><span style="color:#FFFFFF;">Items Sold</span></label></li>
			 <li><label class="radio-inline"><input id="adStatusRadio5" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus5; ?>><span style="color:#FFFFFF;">Expired Ads</span></label></li>
			 <li><label class="radio-inline"><input id="adStatusRadio6" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus6; ?>><span style="color:#FFFFFF;">Ad Quotes</span></label></li>
			 <li><label class="radio-inline"><input id="adStatusRadio7" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus7; ?>><span style="color:#FFFFFF;">Incomplete Ads</span></label></li>
			 <!--
			 <li><label class="radio-inline"><input id="adStatusRadio8" type="radio" name="adStatusRadio" <? echo $adStatusRadioStatus8; ?>><span style="color:#FFFFFF;">Deleted Ads</span></label></li>
			 -->
		     </div>

		     <!-- draw free ad radio button -->
		     <li role="separator" class="divider"></li>
		     <div style="margin-bottom: 10px;height: 55px;background: #1abc9c;padding: 10px;
				border: 1px solid #DDD;border-radius: 5px;box-shadow: 2px 2px 2px #F8F8F8;">

			 <li><label class="radio-inline"><input id="adFreeRadio0" type="radio" name="adFreeRadio"<? echo $adFreeRadioStatus0; ?>><span style="color:#FFFFFF;">Non-Free Ads</span></label></li>
			 <li><label class="radio-inline"><input id="adFreeRadio1" type="radio" name="adFreeRadio"<? echo $adFreeRadioStatus1; ?>><span style="color:#FFFFFF;">Free Ads Only</span></label></li>

		     </div>

		     <!-- limit disabled due to problems with ad types and cat selections
		     <? //if ($sectionTitle2 === 'forum' || $sectionTitle2 === 'region') { ?>
		     <li role="separator" class="divider"></li>
		     <div style="margin-bottom: 10px;height: 38px;background: #1abc9c;padding: 10px;
				 border: 1px solid #DDD;border-radius: 5px;box-shadow: 2px 2px 2px #F8F8F8;">
			 <label><input id="adLimitRadio1" type="radio" name="adLimitRadio"<? echo $adLimitRadioStatus1; ?>><span style="color:#FFFFFF;">Top 10 </span></label>
			 <label><input id="adLimitRadio2" type="radio" name="adLimitRadio"<? echo $adLimitRadioStatus2; ?>><span style="color:#FFFFFF;">20 </span></label>
			 <label><input id="adLimitRadio3" type="radio" name="adLimitRadio"<? echo $adLimitRadioStatus3; ?>><span style="color:#FFFFFF;">50 </span></label>
			 <label><input id="adLimitRadio4" type="radio" name="adLimitRadio"<? echo $adLimitRadioStatus4; ?>><span style="color:#FFFFFF;">All</span></label>
		     </div>
		     <? // } ?>
		     -->
		     
		     <!-- end radio buttons -->

		</ul>
	    </div>
      </div>
     </div>
    </div> <!-- end well -->
    
    <div class="row">
     <div class="col-sm-12">
        <? echo form_open('/settings/advertisingAdmin-adAdmin'); ?>
                    <span class="pull-right">
                        <button type="submit" name="submit" value="ad admin" class="flat-butt-back flat-primary-butt">Ad Admin</button>
                    </span>
            <? echo form_close(); ?>
        <? echo form_open('/settings/adStats/adStatsMenu'); ?>
                    <span class="pull-right">
                        <button type="submit" name="submit" value="back" class="flat-butt-back flat-primary-butt">Back</button>
                    </span>
            <? echo form_close(); ?>
	<? if ($allRevenue || $allNumbers) { ?>
    	<div id="chartNode" style="margin-left:20px;float:left;width:1px;height:1px;"></div>
    	<div id="pieChartNode" style="float:left;width:800px;height:400px;"></div>
	<? } else { ?>
    	<div id="chartNode" style="margin-left:20px;float:left;width:<? echo $chartWidth; ?>;height:400px;"></div>
	<? } ?>
         
	<div id="dateRange" style="<? echo $dateRangeVisibility; ?>">
	    <label for="date1">From Date:</label>
	    <input type="text" name="date1" id="date1" value=""
		   data-dojo-type="dijit/form/DateTextBox"  constraints="{datePattern:'dd-MM-yyyy'}" 
		   required="true" /></br></br>
	    <label for="date1">To Date:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
	    <input type="text" name="date1" id="date2" value=""
		   data-dojo-type="dijit/form/DateTextBox" constraints="{datePattern:'dd-MM-yyyy'}" 
		   required="true" />
	    <br/><i>&nbsp;&nbsp;&nbsp;Note spanning across years is not currently supported</i>
	</div>

	<!-- legend for bar chart -->
        <div id="legend1"></div> 

	<!--Pie Chart legend disabled as color-coded table being used
	<div style="float:left; padding-top:80px">
	<div id="legend2" ></div>
	</div>-->
	
	
	    <div id="table-wrapper"> 
                <div id="table-scrollx"> <!-- scroll not working now!! -->
		<table class="table table-bordered table-striped" style="margin-left:20px;width:20%;height:10%; overflow:auto">
		    <thead>
			<tr style="color:white;">
			    <th style="background-color:#5a7b97"><? echo $xAxisTitle; ?></th>
			    <th style="background-color:#5a7b97"><? echo $yAxisTitle; ?></th>

			</tr>
		    </thead>
		    <tbody>

			<?
			// (miami nice color scheme only has 5 colors)!
			$bgcolors = array("#7f9599", "#45b8cc", "#8ecfb0", "#f8acac", "#cc4482", "#7f9599", "#45b8cc");

			$total = 0;
			if (!empty($xAxisChartData)) {
			    for ($i = 0; $i < count($xAxisChartData); $i++) {
				$total += $yAxisChartData[$i];
				if (!empty($xAxisChartData[$i])) {
				    ?>
	    			<tr>
					<? if ($allRevenue || $allNumbers) { ?>    
					    <td style="background-color:<? echo $bgcolors[$i]; ?>"><? echo $xAxisChartData[$i]; ?></td>
					    <td style="background-color:<? echo $bgcolors[$i]; ?>"><? echo $yAxisChartData[$i]; ?></td>
					<? } else { ?>
					    <td><? echo $xAxisChartData[$i]; ?></td>
					    <td><? echo $yAxisChartData[$i]; ?></td>
					<? } ?>
	    			<tr>
				    <? } ?>    
					<? }
				    } else {
					echo '<div style="color:red;font-weight:bold;font-size:3em;">No Data Found</div>';
				    } ?>

		    </tbody>
		    <? if ($sectionTitle2 === 'period' || $allRevenue || $allNumbers) { ?>
    		    <tfoot>
    			<tr>
    			    <td style="background-color:#5a7b97">Total</td>
    			    <td style="background-color:#5a7b97"><? echo $total; ?></td>
    			</tr>
    		    </tfoot>
		    <? } ?>
		</table>
		<? if ($allRevenue || $allNumbers) { ?>
                    <i>Note ad status, date selections, grouped ad or <br/>free ad selections are overridden by All Time</i>
		<? } ?>
	    </div> <!-- table wrapper removed (was outside scroll) -->
	</div> <!-- end table scroll -->
     </div>
    </div>
	<!-- </div>  end row 1 
    </div> <!-- end container -->

    
<script>
    
    require([
        "dojo/dom",
        "js/adAdmin",
        "dojo/parser",
	"dojox/charting/Chart",
	"dojox/charting/plot2d/Pie",
	"dojox/charting/themes/MiamiNice",
	"dojox/charting/widget/Legend",
	"dojox/charting/plot2d/Columns",
	"dojox/charting/plot2d/ClusteredColumns",
	"dojox/charting/plot2d/Markers",
	"dojox/charting/axis2d/Default",
	"dijit/form/DateTextBox",
	"dojo/domReady!"
    ], function (dom, adm, parser, Chart, Pie, theme, Legend) {

var yAxisChartData;

//the dojo calendar needs parsing
parser.parse(dom.byId("adContainer"));

<? if (!empty($yAxisChartData)) { ?>
    <? if (empty($groupedBy)) { ?>
            yAxisChartData = <? echo json_encode($yAxisChartData); ?>;
    <? } else { ?>
	    yAxisChartData = <? echo json_encode($yAxisChartData); ?>;
	<? for ($i = 1; $i <= $numberOfDataArrays; $i++) {
	    ?> var yAxisChartData<? echo $i; ?>; <?
	    if (!empty(${"yAxisChartData" . $i})) {
		?>
                yAxisChartData<? echo $i; ?> = <? echo json_encode(${"yAxisChartData" . $i}); ?>;
	    <? } ?>
	<? } ?>  
    <? } ?>   
<? } ?>  
	    
 
/**************************************************************************************
// start of Bar chart
/*************************************************************************************/
<? if (!$allRevenue && !$allNumbers) { ?>
    	var labelsArr = [];
    	var chart = new Chart("<? echo $chartNode; ?>", {
    	    title: "<? echo $chartTitle; ?>",
    	    titlePos: "top",
    	    titleGap: 60,
    	    titleFont: "normal normal normal 15pt Arial",
    	    titleFontColor: "#315B7D"
    	});

    	// Set the theme
    	chart.setTheme(theme);

    	// Add the only/default plot
    	chart.addPlot("default", {
    	    type: "ClusteredColumns",
    	    markers: true,
    	    gap: 5
    	});

    	chart.addAxis("y", {vertical: true, fixLower: "major", fixUpper: "major", min: 0, title: "<? echo $yAxisTitle; ?>", titleFontColor: "#315B7D"});
    //end bar chart
<? } ?> 
/*************************************************************************************/
//end of Bar chart
/*************************************************************************************/    


/*************************************************************************************/
//start of Pie chart
/*************************************************************************************/
<? if ($allRevenue || $allNumbers) { ?>
    var chart = new Chart("pieChartNode",
    {
    	    title: "<? echo $chartTitle; ?>",
    	    titlePos: "top",
    	    titleGap: 25,
    	    titleFont: "normal normal normal 15pt Arial",
    	    titleFontColor: "#315B7D"
    });

    // Set the theme
    chart.setTheme(theme);

    // Add the only/default plot
    chart.addPlot("default", {
        type: Pie,
        markers: true,
        radius:170
    });

    // Add axes
    chart.addAxis("x");
    chart.addAxis("y", { min: 5000, max: 30000, vertical: true, fixLower: "major", fixUpper: "major" });

    // Add the series of data
    chart.addSeries("Total Ads", yAxisChartData);

    // Render the chart
    chart.render();
        
    //Pie chart legend not needed as color-coded table is being shown
    // var legend2 = new Legend({ chart: chart , horizontal: false}, "legend2");
<? } ?>

/*************************************************************************************/
//end Pie chart
/*************************************************************************************/

<?
if (!$allRevenue && !$allNumbers) {
    if (!empty($xAxisChartData)) {
        //array_multisort($yAxisChartData,$xAxisChartData);
	foreach ($xAxisChartData as $k => $v) {
	    ?>//Note that index must start on 1
	    labelsArr.push({value: '<? echo ++$k; ?>', text: '<? echo $v; ?>'});
	    <?
	}
    }
    ?>
	    chart.addAxis("x", {title: "<? echo $xAxisTitle; ?>", titleOrientation: "away", rotation: <? echo $xTitleRotation; ?>, titleFontColor: "#315B7D",
    	    labels: labelsArr
    	});
<? } ?>

	// Add the series of data
<? if (empty($groupedBy)) { ?>
    	    if (typeof yAxisChartData != 'undefined') {
    	    chart.addSeries("All Ads", yAxisChartData);
    	}
<? } else { ?>
        if (typeof yAxisChartData != 'undefined') { 
        chart.addSeries("Total Ads", yAxisChartData); //all
        }
    
        <? for ($i = 1; $i <= $numberOfDataArrays; $i++) { ?> 
            if (typeof yAxisChartData1 != 'undefined') { 
                <? if (!empty(${'series' . $i})) { ?>
                    chart.addSeries("<? echo ${'series' . $i}; ?>", yAxisChartData<? echo $i;?>); //all
                <? } ?>
            }
        <? } ?> //end of loop
   
<? } ?> //end of else
    
	// Render the chart if required
    <? if (!$allRevenue && !$allNumbers) { ?>
        	chart.render();
    	var legend = new Legend({ chart: chart }, "legend1");
    <? } ?>
                
    $('form').submit(function() { 
	 var i;
	 for (i=1; i<=10; i++) {
            var setAdDate1;
	    if(setAdDate1 = dom.byId('setAdDate1_' + i)) {
		setAdDate1.value = dom.byId('date1').value;
	    }
            var setAdDate2;
	    if(setAdDate2 = dom.byId('setAdDate2_' + i)) {
		setAdDate2.value = dom.byId('date2').value;
	    }}
  
	    //set the ad status radio buttons
	    for (i=1; i<=7; i++) {
                var adStatusRadio;
		if (dom.byId('adStatusRadio' + i).checked) {
		    for (j=1; j<=10; j++) {
			if (adStatusRadio = dom.byId('setAdStatusRadio' + j)) {
			    adStatusRadio.value = i; 
			}
   
		    }
		    <? foreach ($years as $year) { ?> 
                        var setAdStatusRadio;            
			if(setAdStatusRadio = dom.byId('<? echo "setAdStatusRadio" . $year["year"]; ?>')) { 
			    setAdStatusRadio.value = i;
			}
		    <? } ?>
		}
	 
	    }
     
       //set the ad limit radio buttons
       var setAdLimitRadio;
       for (i=1; i<=4; i++) {
        var adLimitRadio;
	if(adLimitRadio = dom.byId('adLimitRadio' + i)) {
	    if (adLimitRadio.checked) {
		for (j=1; j<=10; j++) {
		    if (setAdLimitRadio = dom.byId('setAdLimitRadio' + j)) {
		    	setAdLimitRadio.value = i; 
		    }
		}
		<? foreach ($years as $year) { ?> 
		    if(setAdLimitRadio = dom.byId('<? echo "setAdLimitRadio" . $year["year"]; ?>')) { 
			setAdLimitRadio.value = i;
		    }
	    <? } ?>
	   }
	}
     }
	    
	//set the free ad radio button
	if (dom.byId('adFreeRadio0').checked) {
	    for (i=1; i<=11; i++) {
		if(setAdLimitRadio = dom.byId('setAdFreeRadio' + i)) {
		    setAdLimitRadio.value = 0;
		}
	    }
		<? foreach ($years as $year) { ?> 
                    var setAdFreeRadioYear;
		    if(setAdFreeRadioYear = dom.byId('<? echo "setAdFreeRadio" . $year["year"]; ?>')) { 
			setAdFreeRadioYear.value = 0;
		<? } ?>
		}
        }
    
	if (dom.byId('adFreeRadio1').checked) {
	    for (i=1; i<=11; i++) {
                var setAdFreeRadio;
		if(setAdFreeRadio = dom.byId('setAdFreeRadio' + i)) {
		    setAdFreeRadio.value = 1;
		}
	    }
	    <? foreach ($years as $year) { ?> 
                var setAdFreeRadioYear;
		if(setAdFreeRadioYear = dom.byId('<? echo "setAdFreeRadio" . $year["year"]; ?>')) { 
		    setAdFreeRadioYear.value = 1;
		}
	    <? } ?>
	}
    
    
   //set the month radio buttons
	for (i=1; i<=14; i++) {
	   if (dom.byId('adMonthRadio' + i)) {  
	    if (dom.byId('adMonthRadio' + i).checked) {
                var setAdMonthRadioPeriod;
		if(setAdMonthRadioPeriod = dom.byId('setAdMonthRadioPeriod')) {
		    setAdMonthRadioPeriod.value = i;
		}
                var setAdMonthRadioForum;
		if(setAdMonthRadioForum= dom.byId('setAdMonthRadioForum')) {
		   setAdMonthRadioForum.value = i;
		}
                var setAdMonthRadioInternational;
                if (setAdMonthRadioInternational = dom.byId('setAdMonthRadioInternational')) {
                   setAdMonthRadioInternational.value = i;
                }
                var setAdMonthRadioCountry;
                if (setAdMonthRadioCountry= dom.byId('setAdMonthRadioCountry')) {
                   setAdMonthRadioCountry.value = i;
                }
                var setAdMonthRadioState;
                if (setAdMonthRadioState= dom.byId('setAdMonthRadioState')) {
                   setAdMonthRadioState.value = i;
                }
                var setAdMonthRadioPostcode;
                if (setAdMonthRadioPostcode = dom.byId('setAdMonthRadioPostcode')) {
                   setAdMonthRadioPostcode.value = i;
                }
                var setAdMonthRadioAgeGroup;
                if (setAdMonthRadioAgeGroup = dom.byId('setAdMonthRadioAgeGroup')) {
		setAdMonthRadioAgeGroup.value = i;
                }
                var setAdMonthRadioGender;
                if (setAdMonthRadioGender = dom.byId('setAdMonthRadioGender')) {
                    setAdMonthRadioGender.value = i;
                }
                var setAdMonthRadioUpdate;
                if (setAdMonthRadioUpdate = dom.byId('setAdMonthRadioUpdate')) {
                    setAdMonthRadioUpdate.value = i;
                }
                <? foreach ($years as $year) { ?>
                    var setAdMonthRadioYear;        
                    if(setAdMonthRadioYear = dom.byId('<? echo "setAdMonthRadioYear" . $year["year"]; ?>')) { 
                        setAdMonthRadioYear.value = i;
                }
                <? 
                }
                ?>
	    
	     }
          }
	}

        var setAdTypeRadio;
        var setAdTypeRadioUpdate;
	if (dom.byId('adTypeRadio1').checked) {
            for (i=1; i<=11; i++) {
                if (setAdTypeRadio = dom.byId('setAdTypeRadio' + i)) {
                    setAdTypeRadio.value = 'groupAdTypes';
		}	     
	    }
            if (setAdTypeRadioUpdate= dom.byId('setAdTypeRadioUpdate')) {
                setAdTypeRadioUpdate.value = 'groupAdTypes'; 
            }
            <? foreach ($years as $year) { ?>
                var setAdTypeRadio;
                if(setAdTypeRadio = dom.byId('<? echo "setAdTypeRadio" . $year["year"]; ?>')) { 
                    setAdTypeRadio.value = 'groupAdTypes';
                }
            <? } ?>
	 
        } else if (dom.byId('adTypeRadio2').checked) {
            for (i=1; i<=11; i++) { 
                if(setAdTypeRadio = dom.byId('setAdTypeRadio' + i)) {
                    setAdTypeRadio.value = 'showAdTypes';
                }
            }
            if (setAdTypeRadioUpdate = dom.byId('setAdTypeRadioUpdate')) {
                setAdTypeRadioUpdate.value = 'showAdTypes'; 
	    }
	     
            <? foreach ($years as $year) { ?>
                if(setAdTypeRadio = dom.byId('<? echo "setAdTypeRadio" . $year["year"]; ?>')) { 
        	setAdTypeRadio.value = 'showAdTypes';
        	}
            <? } ?>
            
        } else if (dom.byId('adTypeRadio3').checked) {
            for (i=1; i<=11; i++) { 
                if (setAdTypeRadio = dom.byId('setAdTypeRadio' + i)) {
                    setAdTypeRadio.value = 'showAdCats';
                }
            }
            if (setAdTypeRadioUpdate = dom.byId('setAdTypeRadioUpdate')) {
                setAdTypeRadioUpdate.value = 'showAdCats';
	     }
            <? foreach ($years as $year) { ?>
                if(setAdTypeRadio = dom.byId('<? echo "setAdTypeRadio" . $year["year"]; ?>')) { 
                    setAdTypeRadio.value = 'showAdCats';
                }
            <? } ?>
            
        } else if (dom.byId('adTypeRadio4').checked) {
            for (i=1; i<=11; i++) { 
                if (setAdTypeRadio = dom.byId('setAdTypeRadio' + i)) {
                    setAdTypeRadio.value = 'showAdCostModels';
                }
            }
            if (setAdTypeRadioUpdate = dom.byId('setAdTypeRadioUpdate')) {
                setAdTypeRadioUpdate.value = 'showAdCostModels';
	    }
            <? foreach ($years as $year) { ?>
                if(setAdTypeRadio = dom.byId('<? echo "setAdTypeRadio" . $year["year"]; ?>')) { 
                    setAdTypeRadio.value = 'showAdCostModels';
                }
            <? } ?>
        }

  });

});
</script>


 


