<?php

if(!defined('ABSPATH')){
	die();
}

echo '
<style>
.backuply_button {
background-color: #4CAF50; /* Green */
border: none;
color: white;
padding: 8px 16px;
text-align: center;
text-decoration: none;
display: inline-block;
font-size: 16px;
margin: 4px 2px;
-webkit-transition-duration: 0.4s; /* Safari */
transition-duration: 0.4s;
cursor: pointer;
}

.backuply_button:focus{
border: none;
color: white;
}

.backuply_button1 {
color: white;
background-color: #4CAF50;
border:3px solid #4CAF50;
}

.backuply_button1:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
border:3px solid #4CAF50;
}

.backuply_button2 {
color: white;
background-color: #0085ba;
}

.backuply_button2:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
}

.backuply_button3 {
color: white;
background-color: #365899;
}

.backuply_button3:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
}

.backuply_button4 {
color: white;
background-color: rgb(66, 184, 221);
}

.backuply_button4:hover {
box-shadow: 0 6px 8px 0 rgba(0,0,0,0.24), 0 9px 25px 0 rgba(0,0,0,0.19);
color: white;
}

.backuply_promo-close{
float:right;
text-decoration:none;
margin: 5px 10px 0px 0px;
}

.backuply_promo-close:hover{
color: red;
}

#backuply_promo li {
list-style-position: inside;
list-style-type: circle;
}

.backuply-loc-types {
display:flex;
flex-direction: row;
align-items:center;
flex-wrap: wrap;
}

.backuply-loc-types li{
list-style-type:none !important;
margin-right: 10px;
}

</style>

<script>
jQuery(document).ready( function() {
	(function($) {
		$("#backuply_promo .backuply_promo-close").click(function(){
			var data;
			
			// Hide it
			$("#backuply_promo").hide();
			
			// Save this preference
			$.post("'.admin_url('?backuply_promo=0').'&security='.wp_create_nonce('backuply_nonce').'", data, function(response) {
				//alert(response);
			});
		});
	})(jQuery);
});
</script>

<div class="notice notice-success" id="backuply_promo" style="min-height:120px; background-color:#FFF; padding: 10px;">
	<a class="backuply_promo-close" href="javascript:" aria-label="Dismiss this Notice">
		<span class="dashicons dashicons-dismiss"></span> Dismiss
	</a>
	<table>
	<tr>
		<th>
			<img src="'.BACKUPLY_URL.'/assets/images/backuply-square.png" style="float:left; margin:10px 20px 10px 10px" width="150" />
		</th>
		<td>
			<p style="font-size:16px">We are glad you like Backuply and have been using it since the past few days. It is time to take the next step </p>
			<p>
			<strong>Upgrade to Pro and get:</strong>
			<ul class="backuply-nag-list">
				<li>Auto Backups</li>
				<li>Backup Rotation</li>
				<li>Backup support to 10 remote locations like FTPS, SFTP, WebDav, OneDrive, Dropbox, Amazon S3, DigitalOcean Spaces, Vultr Object Storage, Linode Object Storage, Cloudflare R2 and more to come...</li>
				<li>Professional Support</li>
			</ul>
			<ul class="backuply-loc-types">
				<li><img src="'. BACKUPLY_URL . '/assets/images/softftpes.svg" height="40" width="40" alt="FTPS Logo" title="FTPS"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/softsftp.svg" height="40" width="40" alt="SFTP Logo" title="SFTP"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/dropbox.svg" height="40" width="40" alt="Dropbox Logo" title="Dropbox"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/onedrive.svg" height="40" width="40" alt="OneDrive Logo" title="OneDrive"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/aws.svg" height="40" width="40" alt="Amazon S3 Logo" title="Amazon S3"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/digitalocean.svg" height="40" width="40" alt="DigitalOcean Logo" title="DigitalOcean Spaces"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/linode.svg" height="40" width="40" alt="Linode Logo" title="Linode Object Storage"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/vultr.svg" height="40" width="40" alt="Vultr Logo" title="Vultr Object Storage"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/cloudflare.svg" height="40" width="40" alt="Cloudflare Logo" title="Cloudflare R2"/></li>
				<li><img src="'. BACKUPLY_URL . '/assets/images/webdav.svg" height="40" width="40" alt="WebDav Logo" title="WebDav"/></li>
			</ul>
			</p>
			<p>
				<a class="backuply_button backuply_button1" target="_blank" href="https://backuply.com/pricing">Upgrade to Pro</a>
				<a class="backuply_button backuply_button2" target="_blank" href="https://wordpress.org/support/view/plugin-reviews/backuply">Rate it 5★\'s</a>
				<a class="backuply_button backuply_button3" target="_blank" href="https://www.facebook.com/backuply/">Like Us on Facebook</a>
				<a class="backuply_button backuply_button4" target="_blank" href="https://twitter.com/intent/tweet?text='.rawurlencode('I use @wpbackuply to backup my #WordPress site - https://backuply.com').'">Tweet about Backuply</a>
			</p>
	</td>
	</tr>
	</table>
</div>';