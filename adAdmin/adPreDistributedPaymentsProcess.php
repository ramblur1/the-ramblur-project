    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?=web_assets_url()?>assets/css/bootstrap.min.css" >
    <link rel="stylesheet" href="<?=web_assets_url()?>assets/css/adAdmin.css?v=<?=$this->config->item('css_version')?>" >
</head>
<body>
    <div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
	<div class="well adAdminTitleWell"> 
	    <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;ad administration / payments / distributed payments</div> 
	</div>
        
        <!-- load buttons component -->
        <? echo $adAdminPaymentsButtonWell; ?>
        
<? echo form_open('settings/calculateMonthlyDistributedPayments'); ?>
  
  <div style="font-size:1.8em">      
      <br/>You are about to process the following Authorized Distributed Moderator Payments:<br/><br/>
  </div>
        
  <div style="font-size:1.4em">       
  
  <br/><br/><br/><i>
      Please note that the payments will be processed via PayPal once the "Process Payments" button below is selected.<br/><br/>
      Alternatively the "Enable Automated Moderator Distributed Payments (via cron)" button can be selected.<br/>
      to automate the process from this point.<br/><br/>
      To cancel, select the "Ad Admin" button, or another option button above.<br/><br/> </i>    
 <button type="submit" name="submit" value="" class="flat-butt flat-primary-butt">Process Payments</button>           
  
<? echo form_close(); ?>          
  </div>
    </div> <!-- end container -->
   
    
   