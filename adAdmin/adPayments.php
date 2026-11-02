    <div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
	<div class="well adAdminTitleWell"> 
	    <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;ad administration / payments</div> 
	</div>
	<div class="well">
	    <? echo form_open('settings/getDistributedPayments'); ?>
            <button type=submit class="flat-butt flat-primary-butt">Moderator Payments<br/><br/></button>
            <input type="hidden" name="authorize" value="authorize" />
            <input type="hidden" name="stage" value="0" />
	    <? echo form_close(); ?>
            <? echo form_open('settings/manageModeratorPaymentSettings'); ?>
	    <button type=submit class="flat-butt flat-primary-butt">Manage Distributed Payment Settings</button>
            <input type="hidden" name="authorize" value="authorize" />
            <input type="hidden" name="stage" value="0" />
	    <? echo form_close(); ?>
            <? echo form_open('settings/manageModeratorPayments'); ?>
            <button type=submit class="flat-butt flat-primary-butt">Manage Moderators<br/><br/></button>
            <input type="hidden" name="authorize" value="authorize" />
            <input type="hidden" name="stage" value="0" />
	    <? echo form_close(); ?>      
	    </button>
             <span class="pull-right">
                 <? echo form_open('/settings/advertisingAdmin-adAdmin'); ?>
                <button type="submit" name="submit" value="ad admin" class="flat-butt-back flat-primary-butt">Ad Admin</button>
                <? echo form_close(); ?>
            </span>
	</div> <!-- end well -->
	<img alt="" src="<?=web_assets_url()?>assets/img/adAdmin/goldBars.jpg" width="100%" height="64%">
	<div id="chartNode"></div>
    </div> <!-- end container -->
