<?php

if(!defined('ABSPATH')) {
	die('HACKING ATTEMPT!');
}

include_once BACKUPLY_DIR . '/main/settings.php';


function backuply_license_page() {
	global $backuply;
	
	if(!empty($_POST['save_backuply_license'])) {
		backuply_license();
	}
	
	backuply_page_header('License');
	settings_errors('backuply-notice');
	
	
	if(!empty($_REQUEST['install_pro'])){
		backuply_install_pro();
		return;		
	}
	
	// If the license is active and you are the free version, then suggest to install the pro
	if(!empty($backuply['license']['active']) && !defined('BACKUPLY_PRO') && empty($_REQUEST['install_pro'])){
		echo '<div class="updated"><p>'. esc_html__('You have activated the license, but are using the Free version !', 'backuply').' <a href="'.esc_url(admin_url('admin.php?page=backuply-license&install_pro=1')).'" class="button button-primary">Install Pro Now</a></p></div><br />';
	}
	?>
		<table class="wp-list-table fixed striped users backuply-license-table" cellspacing="1" border="0" width="95%" cellpadding="10" align="center">
			<tbody>
				<tr>				
					<th align="left" width="25%">Backuply Version</th>
					<td><?php
						echo BACKUPLY_VERSION.(defined('BACKUPLY_PRO') ? ' (Pro Version)' : '');
					?>
					</td>
				</tr>
				<tr>			
					<th align="left" valign="top">Backuply License</th>
					<td align="left">
						<form method="post">
							<span style="color:red"><?php echo (defined('BACKUPLY_PRO') && empty($backuply['license']) ? '<span style="color:red">Unlicensed</span> &nbsp; &nbsp;' : '')?></span>

							<input type="text" name="backuply_license" value="<?php echo (empty($backuply['license']) ? '' : esc_html($backuply['license']['license']))?>" size="30" placeholder="e.g. BAKLY-11111-22222-33333-44444" style="width:300px;"> &nbsp; 
							<?php wp_nonce_field( 'backuply_license_form','backuply_license_nonce' ); ?>
							<input name="save_backuply_license" class="button button-primary" value="Update License" type="submit">
						</form>
						<?php if(!empty($backuply['license'])){
							
							$expires = $backuply['license']['expires'];
							$expires = substr($expires, 0, 4).'/'.substr($expires, 4, 2).'/'.substr($expires, 6);
							
							echo '<div style="margin-top:10px;">License Status : '.(empty($backuply['license']['status_txt']) ? 'N.A.' : wp_kses_post($backuply['license']['status_txt'])).' &nbsp; &nbsp; &nbsp; 
							License Expires : '.($backuply['license']['expires'] <= date('Ymd') ? '<span style="color:red">'.esc_html($expires).'</span>' : esc_html($expires)).'
							</div>';
						}?>
					</td>
				</tr>
				<tr>
					<th align="left">URL</th>
					<td><?php echo esc_url(get_site_url()); ?></td>
				</tr>
				<tr>				
					<th align="left">Path</th>
					<td><?php echo ABSPATH; ?></td>
				</tr>
				<tr>				
					<th align="left">Server's IP Address</th>
					<td><?php echo !empty($_SERVER['SERVER_ADDR']) ? wp_kses_post(wp_unslash($_SERVER['SERVER_ADDR'])) : '-'; ?></td>
				</tr>
				<tr>				
					<th align="left">.htaccess is writable</th>
					<td><?php echo (is_writable(ABSPATH.'/.htaccess') ? '<span style="color:red">Yes</span>' : '<span style="color:green">No</span>');?></td>
				</tr>		
			</tbody>
		</table>
	</td>
	<td>
		<?php backuply_promotion_tmpl(); ?>
	</td>
</tr>
</table>
</div>
</div>
</div>
</div>
	
<?php
}



?>