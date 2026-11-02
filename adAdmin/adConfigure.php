<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
	<div class="well adAdminTitleWell"> 
	    <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" 
                 style="width:60px;height:60px;">&nbsp;ad administration / configure ads</div> 
	</div>
	<div class="well">
            <? echo form_open('settings/advertisingAdmin-adToggle'); ?>
	    <button type="submit" class="flat-butt flat-primary-butt">Toggle Ads</button>
	    <? echo form_close(); ?>
	    <? echo form_open('adadmin/'); ?>
	    <button type="submit" class="button-ad adlink large gray" disabled="disabled">Set Ad Prices</button>
	    <? echo form_close(); ?>
	    <? echo form_open('adadmin/'); ?>
	    <button type="submit" class="button-ad adlink large gray" disabled="disabled">Set Ad Options</button>
	    <? echo form_close(); ?>
            <? echo form_open('/settings/advertisingAdmin-adAdmin'); ?>
                    <span class="pull-right">
                        <button type="submit" name="submit" value="back" class="flat-butt flat-primary-butt">Ad Admin</button>
                    </span>
                <? echo form_close(); ?>
	</div> <!-- end well -->
	<img alt="" src="<?=web_assets_url()?>assets/img/adAdmin/filmStripCropped.jpg" width="100%" height="75%">
	<div id="chartNode"></div>
    </div> <!-- end container -->
