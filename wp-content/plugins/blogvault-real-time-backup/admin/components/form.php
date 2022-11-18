<?php
	if ($this->bvinfo->isMalcare()) {
		$brand_name = "MalCare";
		$webpage = "https://www.malcare.com";
	} else {
		$brand_name = "BlogVault";
		$webpage = "https://www.blogvault.net";
	}
?>
<div class="email-form">
	<div class="row">
		<div class="col-xs-12 form-container">
			<div class="search-container text-center ">
			<form action="<?php echo esc_url($this->bvinfo->appUrl()); ?>/plugin/signup" style="padding-top:10px; margin: 0px;" onsubmit="document.getElementById('get-started').disabled = true;"  method="post" name="signup">
				<input type='hidden' name='bvsrc' value='wpplugin'/>
				<?php echo $this->siteInfoTags(); ?>
				<input type="text" placeholder="Enter your email address to continue" id="email" name="email" class="search" required>
				<h5 class="check-box-text"><input type="checkbox" class="check-box" name="consent" value="1" required>
				<label>I agree to <?php echo esc_html($brand_name); ?> <a href="<?php echo esc_url($webpage).'/tos'; ?>" target="_blank" rel="noopener noreferrer">Terms of Service</a> and <a href="<?php echo esc_url($webpage).'/privacy'; ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a></label></h5>
				<button id="get-started" type="submit" class="e-mail-button"><span class="text-white">Submit</span></button>		
			</form>
		</div>
	</div>
</div>