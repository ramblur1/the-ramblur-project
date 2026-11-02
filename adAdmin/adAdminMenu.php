<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
	<div class="well adAdminTitleWell"> 
	    <span class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;
	    ad administraton
	    </span>
	    <span style="float:right; font-weight:bold;font-size:1.2em;">Login&nbsp;
	    <input type="login" name="login">&nbsp;Password&nbsp;<input type="password" name="password"
	    </span>
	</div>
	<div class="well">
	    <? echo form_open('settings/adStats/adStatsMenu'); ?>
	    <button type=submit class="flat-butt flat-primary-butt">Ad Stats</button>
	    <? echo form_close(); ?>          
	    </button>
        <a class="flat-butt flat-primary-butt" href="<? print(base_url('advertising/adFree')); ?>" style="text-decoration:none;text-align: center;">Create Free Ads</a>
	    <? echo form_open('settings/adConfigure'); ?>
	    <button class="flat-butt flat-primary-butt">Configure Ads</button>
	    <? echo form_close(); ?>
            <? echo form_open('settings/adPayments'); ?>
	    <button class="flat-butt flat-primary-butt">Payments</button>
	    <? echo form_close(); ?>
	</div> <!-- end well -->
	<!--if you want a selection of images <? //if (rand() & 1) { ?>
	    <img alt="" src="<?=web_assets_url()?>assets/img/adAdmin/filmStrip.jpg" width="100%" height="64%">
	<? //} else { ?>-->
	    <img alt="" src="<?=web_assets_url()?>assets/img/adAdmin/filmStrip2.jpg" width="100%" height="75%">
	<!--<?// } ?>-->
    </div> <!-- end container -->
