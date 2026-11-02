<div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;x">
	<div class="well adAdminTitleWell"> 
	    <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;ad administration / payments / distributed payments</div> 
	</div>
        <!-- load buttons component -->
        <? echo $adAdminPaymentsButtonWell; ?>
<div class="row">
    <div class="col-md-6" style="padding-bottom:3%">
  <div style="font-size:1.6em">      
      <br/><b>Current Moderator Payments Info</b><br/><br/>
  </div>
  <div style="font-size:1em">  
  <div class="table-responsive">
   <table class="table table-bordered table-striped table-responsive">          
   <tr>    
   <th style="color:white;background-color:#5a7b97">User Name</th>
   <th style="color:white;background-color:#5a7b97">User Id</th>
   <th style="color:white;background-color:#5a7b97">Email</th>
   <th style="color:white;background-color:#5a7b97">Forum Id</th>
   <th style="color:white;background-color:#5a7b97">Forum Name</th>
   <th style="color:white;background-color:#5a7b97">Country Id</th>
   <th style="color:white;background-color:#5a7b97">Receive Moderator Payments</th>
   <th style="color:white;background-color:#5a7b97">Payment Percentage</th>
   <tr/>
   
   <? if(!empty($moderatorPaymentInfo)) { 
        foreach($moderatorPaymentInfo as $info) { ?>  
            <tr>
            <td><? echo $info["userName"]; ?></td>
            <td><? echo $info["userId"]; ?></td>
            <td><? echo $info["email"]; ?></td>
            <td><? echo $info["subjectId"]; ?></td>
            <td><? echo $info["subjectName"]; ?></td>
            <td><? echo $info["countryId"]; ?></td>
            <td><?echo $info["distributedPayments"] === '1' ? 'Yes' : 'No';?></td>
            <td><?echo $info["distributedPaymentPercentage"];?></td>
            </tr>
        <? } 
   } ?>
   
   </table>     
      </div>
  </div>
    </div>
      <div class="col-md-6" style="padding-bottom:3%">
        <? echo form_open('settings/manageModeratorPayments'); ?>
  <div style="font-size:1.6em">      
      <br/><b>Update Moderator Payment Info</b><br/><br/>
  </div>
  <div style="font-size:1em">   
      <div class="table-responsive">
      <table class="table table-bordered table-striped">          
          <tr>    
              <th style="color:white;background-color:#5a7b97">User Id</th>
              <th style="color:white;background-color:#5a7b97">Forum Id</th>
              <th style="color:white;background-color:#5a7b97">Receive Moderator Payments <br/>(yes/no)</th>
              <th style="color:white;background-color:#5a7b97">Payment Percentage</th>
          <tr/>
          <tr>
              <td><input type="text" name="userId" value=""></td>
              <td><input type="text" name="subjectId" value="">
              <td><input type="text" name="distributedPayments" value=""></td>
              <td><input type="text" name="distributedPaymentPercentage" value=""></td>
          </tr>
      </table>   
      </div>
      <br/><button type="submit" name="submit" value="updateModeratorPayments" class="flat-butt flat-primary-butt">Update</button>    
   <? echo form_close(); ?>  
    </div>
</div>
  </div>
</div> <!-- end container -->