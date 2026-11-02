    <div class="container-fluid" style="background-color:#FFFFF0;height:100%;width:100%;">
        <div class="well adAdminTitleWell"> 
            <div class="adAdminTitle"><img src="<?=web_assets_url()?>assets/img/ram150x150grey.png" style="width:60px;height:60px;">&nbsp;ad administration / payments / distributed payments</div> 
        </div>
        <!-- load buttons component -->
        <? echo $adAdminPaymentsButtonWell; ?>
        <? echo form_open('settings/calculateMonthlyDistributedPayments'); ?>
        <div style="font-size:1.8em">      
            <br/>Enter the month and year for which the moderator payments require calculation.<br/><br/>
        </div>
        <div style="font-size:1.4em">       
            <select name="month">
                <? foreach ($months as $month) { ?>
                    <option value="<? echo $month["month"]; ?>"><? echo $month["month"]; ?></option>
                <? } ?>
            </select>
            <select name="year">
                <? foreach ($years as $year) { ?>
                    <option value="<? echo $year["year"]; ?>"><? echo $year["year"]; ?></option>
                <? } ?>
            </select>    
            <button type="submit" name="submit" value="calculateMonthlyDistributedPayments" class="flat-butt flat-primary-butt">Calculate</button>    
            <br/><br/><br/><br/><br/><i>
                Please note that the payments will not be processed by PayPal at this stage. They will first require<br/>
                authorization, and then be manually processed by selecting "Manually Process Moderator Distributed Payments" (or <br/>
                select "Enable Automated Moderator Distributed Payments" to automate the process via cron).<br/><br/> </i>    
            <? echo form_close(); ?> 
            <br/><br/>
        </div>
    </div> <!-- end container -->

