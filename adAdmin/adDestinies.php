    <div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
	<div class="well adAdminTitleWell"> 
	    <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;<a href="<? echo base_url(); ?>adadmin">ad administration</a> / ad destinies</div> 
	</div>
	<div class="well">
	    <? echo form_open('adadmin/adStats/adStatsMenu'); ?>
	    <button type=submit class="flat-butt flat-primary-butt">Quoted Ads</button>
	    <input type="hidden" name="year" value="<? echo date('Y'); ?>">
	    <? echo form_close(); ?>          
	    </button>
	    <? echo form_open('adadmin/adStats/adStatsMenu'); ?>
	    <button class="flat-butt flat-primary-butt">Unfinished Ads</button>
            <input type="hidden" name="year" value="<? echo date('Y'); ?>">
	    <? echo form_close(); ?>
	    <? echo form_open('adadmin/adStats/adStatsMenu'); ?>
	    <button class="flat-butt flat-primary-butt">Free Ads</button>
	    <? echo form_close(); ?>
	</div> <!-- end well -->
	<img alt="" src="<?=web_assets_url()?>assets/img/adAdmin/filmStripSepia.jpg" width="100%" height="64%">
	<div id="chartNode"></div>
    </div> <!-- end container -->