	    <? echo form_open('adadmin/adStats/revenue/period'); ?>
	    <button type=submit class="flat-butt flat-primary-butt">By Period</button>
	    <input type="hidden" name="year" value="<? echo date('Y'); ?>">
	    <? echo form_close(); ?>          
	    </button>
	    <? echo form_open('adadmin/adStats/revenue/forum'); ?>
	    <button class="flat-butt flat-primary-butt">By Forum</button>
	    <input type="hidden" name="year" value="<? echo date('Y'); ?>">
	    <? echo form_close(); ?>
	    <? echo form_open('adadmin/adStats/revenue/region'); ?>
	    <button type="submit" class="flat-butt flat-primary-butt">By Region</button>
	    <input type="hidden" name="year" value="<? echo date('Y'); ?>">
	    <? echo form_close(); ?>
	    <? echo form_open('adadmin/adStats/revenue/ageGroup'); ?>
	    <button class="flat-butt flat-primary-butt">By Age Group</button>
	    <input type="hidden" name="year" value="<? echo date('Y'); ?>">
	    <? echo form_close(); ?>
	    <? echo form_open('adadmin/adStats/revenue/gender'); ?>
	    <button class="flat-butt flat-primary-butt">By Gender</button>
	    <input type="hidden" name="year" value="<? echo date('Y'); ?>">
	    <? echo form_close(); ?>
