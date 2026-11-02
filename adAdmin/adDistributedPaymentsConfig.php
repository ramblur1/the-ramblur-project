<div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
	<div class="well adAdminTitleWell"> 
	    <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;ad administration / payments / distributed payments</div> 
	</div>
    
        <!-- load buttons component -->
        <? echo $adAdminPaymentsButtonWell; ?>

<? echo form_open('settings/manageModeratorPaymentSettings'); ?>
  <div style="font-size:2em">      
      <br/><b>Update Current Distributed Payments Moderator Settings</b><br/><br/>
  </div>
  <div style="font-size:1.4em"> 
  <div class="table-responsive">    
   <table class="table table-bordered table-striped" style="margin-left:20px;width:80%;">          
   <tr>    
   <th style="color:white;background-color:#5a7b97">Setting</th>
   <th style="color:white;background-color:#5a7b97">Current Value</th>
   <th style="color:white;background-color:#5a7b97">New Value</th>
   <th style="color:white;background-color:#5a7b97">Description</th>
   <tr/>
   
   <!--
   <tr>
   <td>Distributed Payments On</td>
   <td><? //echo $paymentConfig["distributedPaymentsOn"]; ?></td>
   <td><input type="text" name="distributedPaymentsOn" value="<?//echo $paymentConfig["distributedPaymentsOn"];?>"></td>
   <td>1 for On, 0 for Off</td>
   </tr>
   -->
   <!--<tr>
   <td>distributedPaymentsAutomated</td>
   <td><?//echo $paymentConfig["distributedPaymentsAutomated"];?></td>
   <td><input type="text" name="distributedPaymentsAutomated" value="<?//echo $paymentConfig["distributedPaymentsAutomated"];?>"></td>
   <td>Percentage fee for sending local payments</td>
   </tr>
   -->
   <tr>
   <td>Distributed Payments Require Authorization</td>
   <td><?echo $paymentConfig["distributedPaymentsRequireAuth"] === '1' ? 'Yes' : 'No';?></td>
   <td><input type="text" name="distributedPaymentsRequireAuth" value="<?echo $paymentConfig["distributedPaymentsRequireAuth"] === '1' ? 'Yes' : 'No';?>"></td>
   <td>If this is set to "Yes", payments require manual authorization before they are sent to PayPal. A system message is emailed to 
       the Ad Admin users directing them to authorize the payments for a given month. If set to "No", payments will not require
       authorization and a system message will not be sent. 
       <span style="color:red;font-style:italic"> Note that authorization cannot currently be turned off, but this feature may be implemented in the future!</span> 
   </td>
   </tr>
   <tr>
   <td>Default Moderator Payment Percentage</td>
   <td><?echo $paymentConfig["distributedPaymentPercentage"];?>%</td>
   <td><input type="text" name="distributedPaymentPercentage" value="<?echo $paymentConfig["distributedPaymentPercentage"];?>"></td>
   <td>Default percentage of forum ad revenue paid to moderators. This can also be set at a per-moderator level by selecting the "Manage Moderators" button. 
       <i>Note this percentage is divided between forum moderators if there is more than one moderator for the forum.</i></td>
   </tr>
   <tr>
   <td>Local Fee Percentage</td>
   <td><?echo $paymentConfig["localFeePercentage"];?>%</td>
   <td><input type="text" name="localFeePercentage" value="<?echo $paymentConfig["localFeePercentage"];?>"></td>
   <td>Percentage fee for sending local payments (i.e. within the same country). This is charged by PayPal.</td>       
   </tr>
   <tr>
   <td>International Fee Percentage</td>
   <td><?echo $paymentConfig["internationalFeePercentage"];?>%</td>
   <td><input type="text" name="internationalFeePercentage" value="<?echo $paymentConfig["internationalFeePercentage"];?>"></td>
   <td>Percentage fee for sending international payments. This is charged by PayPal.</td>         
   </tr> 
   <tr>
   <td>Local Country ID</td>
   <td><?echo $paymentConfig["localCountryId"];?></td>
   <td><input type="text" name="localCountryId" value="<?echo $paymentConfig["localCountryId"];?>"></td>
   <td>This sets the local payment country (e.g. AU).</td>
   </tr>   
   <tr>
   <td>Local Payment Fee Cap</td>
   <td>$<?echo $paymentConfig["localFeeCap"];?>AUD</td>
   <td><input type="text" name="localFeeCap" value="<?echo $paymentConfig["localFeeCap"];?>"></td>
   <td>Local payment fees are capped at this amount in AUD. This is charged by PayPal.</td>      
   </tr>
   <tr>
   <td>International Payment Fee Cap</td>
   <td>$<?echo $paymentConfig["internationalFeeCap"];?>AUD</td>
   <td><input type="text" name="internationalFeeCap" value="<?echo $paymentConfig["internationalFeeCap"];?>"></td>
   <td>International payment fees are capped at this amount in AUD. This is charged by PayPal.</td>       
   </tr>
   <tr>
   <td>Payment Currency</td>
   <td><?echo $paymentConfig["currency"];?></td>
   <td><input type="text" name="currency" value="<?echo $paymentConfig["currency"];?>"></td>
   <td>Payments are made in this currency (e.g. AUD).</td>       
   </tr>  
   </table> 
  </div>
   <button type="submit" name="submit" value="updateDistributedPaymentsConfig" class="flat-butt flat-primary-butt">Update</button>    
   <? echo form_close(); ?>  
   <br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>
   </div>
</div> <!-- end container -->