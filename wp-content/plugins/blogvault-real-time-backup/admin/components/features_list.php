<?php
	if ($this->bvinfo->isMalcare()) {
		$heading = "MALCARE 360 DEGREE PROTECTION";
		$subheading = "How can Malcare help protect your site?";
		$img_url = plugins_url("/../../img/mc-features-list.png", __FILE__);
		$intro_video_url = "https://youtu.be/rBuYh2dIadk";
		$brand_name = "MalCare";
	} else {
		$heading = "AUTOMATE YOUR SITE MANAGEMENT";
		$subheading = "All-in-one solution for complete website management";
		$img_url = plugins_url("/../../img/bv-features-list.png", __FILE__);
		$intro_video_url = "https://youtu.be/Y4teDRL08mY";
		$brand_name = "BlogVault";
	}
?>
<section id="list-features">
	<div class="custom-container">
		<div class="heading text-center">
		<h5><?php echo esc_html($heading); ?></h5>
		<h4><?php echo esc_html($subheading); ?></h4>
		</div>
		<div class="row">
			<div class="col-xs-12 d-flex">
				<div class="col-xs-12 col-lg-6 px-3">
					<div>
						<img class="main-image" src="<?php echo esc_url($img_url); ?>"/>
					</div>
				 <div class="text-center intro-video d-flex"> 
				 <a href="<?php echo esc_url($intro_video_url); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url(plugins_url("/../../img/play-video.png", __FILE__)); ?>"/>
								&nbsp;Watch the <?php echo esc_html($brand_name); ?> Video
						</a> 
					</div>
				</div>
				<?php
					if ($this->bvinfo->isMalcare()) {
				?>
				<div class="col-xs-12 col-lg-6 d-flex px-3">
					<div id="accordion">
						<div>
							<input type="radio" name="accordion-group" id="option-1" checked />
							<div class="acc-card">
							<label for="option-1">
								<h5>MALCARE SCANNER</h5>
								<h4>WordPress Malware Scanner that will NEVER slow down your website.</h4>
							</label>
							<div class="article">
								<p>MalCare’s “Early Detection Technology” finds WordPress Malware that other popular plugins miss!
									It uses 100+ signals to accurately detect and pinpoint even “Unknown” malware. You can now scan your website
									for malware automatically, with ZERO overload on your server!</p>
							</div>
							</div>
						</div>
						<div>
							<input type="radio" name="accordion-group" id="option-2" />
							<div class="acc-card">
							<label for="option-2">
								<h5>MALCARE FIREWALL</h5>
								<h4>Get 100% Protection from Hackers with our Advanced WordPress Firewall </h4>
							</label>
							<div class="article">
								<p>Automatically block malicious traffic with MalCare’s intelligent visitor pattern detection.
									With CAPTCHA-based Login Protection, Timely alerts for suspicious logins and Security Features
									recommended by WordPress - you can say Goodbye to Hackers!</p>
							</div>
							</div>
						</div>
						<div>
							<input type="radio" name="accordion-group" id="option-3" />
							<div class="acc-card">
							<label for="option-3">
								<h5>MALCARE CLEANER</h5>
								<h4>Instant Malware Removal that takes less than 60 Seconds in just 1-Click!</h4>
							</label>
							<div class="article">
								<p>No more waiting for hours or days to clean your hacked website. With MalCare’s fully automated
									malware removal, you malware will be gone in a jiffy! Our powerful cleaner removes even complex &amp;
									unknown malware in a matter of seconds. Leave the heavy lifting to us while you sit back and
									relax - your site is in safe hands!</p>
							</div>
							</div>
						</div>
					</div>
				</div>
				<?php
					} else {
				?>
				<div class="col-xs-12 col-lg-6 d-flex px-3">
					<div id="accordion">
						<div>
							<input type="radio" name="accordion-group" id="option-1" checked />
							<div class="acc-card">
								<label for="option-1">
									<h5>Backups That Always Work</h5>
									<h4>Reliable WordPress Backup trusted by 400,000+ site owners.</h4>
								</label>
								<div class="article">
									<ul>
										<li>Incremental backups to never overload your server</li>
										<li>Free offsite storage ensures 24X7 availability</li>
										<li>Quickly identify problems with our change logs</li>
										<li>First plugin with Multi-site backup support</li>
									</ul>
								</div>
							</div>
						</div>
						<div>
							<input type="radio" name="accordion-group" id="option-2" />		
								<div class="acc-card">
								<label for="option-2">
									<h5>100% Successful Restores</h5>
									<h4>Experience up to 70% faster website recovery with BlogVault.</h4>
								</label>
								<div class="article">
									<ul>
										<li>1 million+ website restores with 100% success rate</li>
										<li>Differential Restore for lightning fast recovery</li>
										<li>90 days archive to recover from any mistake</li>
										<li>Perform full Restore even if your website is offline</li>
									</ul>
								</div>		
							</div>
						</div>
						<div>
							<input type="radio" name="accordion-group" id="option-3" />	
							<div class="acc-card">
								<label for="option-3">
									<h5>Integrated Free Staging</h5>
									<h4>Never break your site with our Staging, works on any host.</h4>
								</label>
								<div class="article">
									<ul>
										<li>Safely test your website updates and changes.</li>
										<li>Staging site runs on our cloud servers.</li>
										<li>Completely free. No extra cost for anything.</li>
										<li>One-click Merge to push changes to live site.</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php
					}
				?>
			</div>
		</div>
	</div>
</section>