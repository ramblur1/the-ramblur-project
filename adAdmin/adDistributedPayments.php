<div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
    <div class="well adAdminTitleWell"> 
        <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;ad administration / payments / distributed payments</div> 
    </div>
    <!-- load buttons component -->
    <? echo $adAdminPaymentsButtonWell; ?>
 <div class="row">
    <div class="col-md-12">        
        <!-- show distributed payments -->         
        <? if (!empty($showDistributedPayments)) {
            
                if(!empty($calcMessage)) { ?>    
                    <div style="font-size:1.8em; font-style:italic; color:red"><br/>      
                    <? echo $calcMessage; ?><br/><br/>
                    </div>
                <? } 
            if(!$processing) { // don't want extra titles    
                if ($detail === 'detail') { ?>
                    <h1 style="font-weight:bold;font-size:2em;text-align:left;"><? echo $stage; ?>Moderator Payments  <? echo $authTitle; ?>- Detail</h1>
                <? } else { ?>
                    <h1 style="font-weight:bold;font-size:2em;text-align:left;"><? echo $stage; ?>Moderator Payments <? echo $authTitle; ?>- Summary</h1>
                <? } 
            } ?>
                
            <? if ($processing) {  ?>       
                <div style="font-size:2em; font-weight:bold">      
                <br/>You are about to process the following Authorized Distributed Moderator Payments:<br/><br/>
                </div>
            <? } ?>
            <? if ($processing) { ?>
                            <div class="well">
                                <div style="font-size:1.4em">       
                                    <i> Please note that the payments will be processed via PayPal once the "Process Payments" button below is selected.<br/><br/>
                                        Alternatively, the "Enable Automated Moderator Distributed Payments (via cron)" button can be selected to automate <br/>
                                        the process from this point.<br/><br/>
                                        To cancel, select the "Ad Admin" button, or an alternative option button above.<br/><br/> 
                                    </i>  
                                </div> 
                            </div>
            <? } ?>
           <div style="float-left">
           <? echo form_open('settings/getDistributedPayments'); ?>
            <button type="submit" name="detail" value="summary" class="flat-butt flat-primary-butt">Show Summary</button>
            <input type="hidden" name="stage" value="<? echo $paid; ?>" />
            <input type="hidden" name="authorize" value="<? echo $authorize; ?>" />
            <input type="hidden" name="detail" value="summary" />
            <input type="hidden" name="processing" value="<? echo $processing; ?>" />
	    <? echo form_close(); ?>      
            
            <? echo form_open('settings/getDistributedPayments'); ?>
            <button type="submit" name="detail" value="detail" class="flat-butt flat-primary-butt">Show Detail</button>
            <input type="hidden" name="detail" value="detail" />
            <input type="hidden" name="stage" value="<? echo $paid; ?>" />
            <input type="hidden" name="authorize" value="<? echo $authorize; ?>" />
            <input type="hidden" name="processing" value="<? echo $processing; ?>" />
	    <? echo form_close(); ?>          
            </div>
            <br/><br/>   
            
  <div class="table-responsive">    
   <table class="table table-bordered table-striped" style="width:100%;">      
   <?
   if ($detail === 'detail') { 
    if ($stage === 'Pending ') {
        $payText = "Amount to Pay";
    } else {
        $payText = 'Amount Paid';
    }?>
    <th style="color:white;background-color:#5a7b97">Month</th>
    <th style="color:white;background-color:#5a7b97">Year</th>
    <th style="color:white;background-color:#5a7b97">Forum</th>
    <th style="color:white;background-color:#5a7b97">Monthly Forum Revenue</th>
    <th style="color:white;background-color:#5a7b97">Number of Moderators</th>
    <th style="color:white;background-color:#5a7b97">Percentage Paid</th>
    <th style="color:white;background-color:#5a7b97">Receiver Email</th>
    <th style="color:white;background-color:#5a7b97">Fee</th>
    <th style="color:white;background-color:#5a7b97"><? echo $payText; ?></th>
    <th style="color:white;background-color:#5a7b97">Payment Date</th>
    <th style="color:white;background-color:#5a7b97">Date Payment Claimed</th>
    <th style="color:white;background-color:#5a7b97">Authorized</th>
    <th style="color:white;background-color:#5a7b97">Transaction Status</th>
    <th style="color:white;background-color:#5a7b97">Payment Status</th>
    <? if ($authorize) { ?>
        <th style="color:white;background-color:#5a7b97">Authorize?</th>
    <? } ?>
     <? if ($stage == 'Pending ' && !$authorize) { ?>
        <th style="color:white;background-color:#5a7b97">De-authorize?</th>
    <? } ?>    
    </tr>
    <tr>
       <? if(!empty($DistributedPayments )) { ?>
      <? foreach ($DistributedPayments as $payment) {
        $checked = NULL;  
        if ($payment["preAuthorized"]) {
            $checked = 'checked="checked"';
        } 
        if (!$payment["payment_status"]) {
            $payment["payment_status"] = 'Pending';
            $payment["status"] = 'Pending';
        }
        ?>
    <td><? echo $payment["month"]; ?></td>
    <td><? echo $payment["year"]; ?></td>
    <td><? echo $this->adadminmodel->getForumName($payment["forumId"])->forumName; ?></td>
    <td><? echo $payment["forumMonthlyRevenueTotal"]; ?></td>
    <td><? echo $payment["numberOfModerators"]; ?></td>
    <td><? echo $payment["actualPaymentPercentage"] . '%'; ?></td>
    <td><? echo $payment["receiver_email"]; ?></td>
    <td><? echo $payment["fee"]; ?></td>
    <td><? echo $payment["paymentAmount"]; ?></td>
    <td><? echo $payment["payment_date"] === NULL ? NULL : date("d/m/Y H:i:s", strtotime($payment["payment_date"])); ?></td>
    <td><? echo $payment["paymentClaimedDate"] === NULL ? NULL : date("d/m/Y H:i:s", strtotime($payment["paymentClaimedDate"])); ?></td>
    <td><? echo $payment["authorized"]; ?></td>
    <td><? echo $payment["payment_status"]; ?></td>
    <td><? echo $payment["status"]; ?></td>
     <? if ($authorize) { ?>
         <td style="text-align:center"><input type="checkbox" <? echo $checked; ?> id="<? echo $payment["paymentId"]; ?>" onClick="require('js/adAuthPayment').adAuthPayment(id);"/></td>
    <? } ?>
         <? if ($stage === 'Pending ') { 
             if ($payment["authorized"]) { ?>
         <td style="text-align:center"><input type="checkbox"
         id="<? echo $payment["paymentId"]; ?>" 
         onClick="require('js/adAuthPayment').adAuthPayment(id);"/></td>
             <? } else if (!$authorize) { ?>
         <td></td>
             <? } ?>
    <? } ?> 
    <? if ($payment["paid"] == 0 && ($payment["payment_status"] === 'Processed' || $payment["payment_status"] === 'Waiting for PayPal IPN')) { 
         $paymentId = $payment["paymentId"]; ?>
         <div style="color:red;font-size:1.2em;">
             <? echo "* Payment ID $paymentId previously errored when attempting to process. Please contact support if this error continues."; ?>
         </div>
    <? } ?>
  </tr>
      <? } ?>
    <? } ?>
 <? } else { ?>
  <tbody>
  <tr>
    <th style="color:white;background-color:#5a7b97">Month</th>
    <th style="color:white;background-color:#5a7b97">Year</th>
    <th style="color:white;background-color:#5a7b97">Authorized</th>
    <th style="color:white;background-color:#5a7b97">Total Revenue</th>
    <th style="color:white;background-color:#5a7b97">Total Amount Paid</th>
    <? if ($authorize) { ?>
        <th style="color:white;background-color:#5a7b97">Authorize?</th>
    <? } ?>
  </tr>
  <tr>
      <? if(!empty($DistributedPayments)) { ?>
      <? foreach ($DistributedPayments as $payment) { 
        $checked = NULL;  
        if ($payment["preAuthorized"]) {
            $checked = 'checked="checked"';
        } ?>
    <td><? echo $payment["month"]; ?></td>
    <td><? echo $payment["year"]; ?></td>
    <td><? echo $payment["authorized"]; ?></td>
    <td><? echo $payment["revenueTotal"]; ?></td>
    <td><? echo $payment["paymentAmount"]; ?></td>
     <? if ($authorize) { ?>
    <td style="text-align:center"><input type="checkbox" <? echo $checked; ?> 
        onClick="require('js/adAuthPayment').adAuthPaymentRange('<? echo $payment["month"]; ?>', '<? echo $payment["year"]; ?>');"/></td>
    <? } ?>
  </tr>
  <? if ($payment["paid"] == 0 && ($payment["payment_status"] === 'Processed' || $payment["payment_status"] === 'Waiting for PayPal IPN')) { 
         $paymentId = $payment["paymentId"]; ?>
         <div style="color:red;font-size:1.2em;">
             <? echo "* Payment ID $paymentId previously errored when attempting to process. Please contact support if this error continues."; ?>
         </div>
    <? } ?>
        <? } ?>
   <? } ?>
<? } ?>
   </tbody>
 </table>
</div>
      
   <? if ($authorize || $stage === 'Pending ') { ?>
   <? echo form_open('settings/getDistributedPayments'); ?>
         <br/><button type="submit" name="detail" value="summary" class="flat-butt flat-primary-butt"><? echo $authButton; ?></button>
               <input type="hidden" name="stage" value="<? echo $paid; ?>" />
            <input type="hidden" name="authorize" value="<? echo $authorize; ?>" />
            <input type="hidden" name="detail" value="summary" />
            <input type="hidden" name="commitAuthorize" value="commitAuthorize" /><br/><br/><br/><br/>         
	    <? echo form_close(); ?>      
      <? } ?>

      <? if($processing) {
            echo form_open('settings/processMonthlyDistributedPayments'); ?>
            <button type="submit" name="submit" value="" class="flat-butt flat-primary-butt">Process Payments</button> 
            <br/><br<br/><br/><br/><br/>
            <? echo form_close(); 
      } ?>
        <? } ?>   
      <!-- end show distributed payments -->          
        <br/><br/><br/><br/>        
    </div>
 </div>
</div> <!-- end container -->
   
   
    <script>
    require(["dojo/dom","dojo/parser", "js/adAuthPayment", "dojo/domReady!"]);
    </script>