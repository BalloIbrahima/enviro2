<section id="account-list">
	<?php $accounts = BVAccount::accountsByPlugname($this->settings); ?>
	<div class="account-list-container custom-container text-center">
		<h4>Accounts associated with this website</h4>
		<div class="table-container">
			<table>
				<tr><th>Account Email</th><th>Last Synced At</th><th></th></tr>
				<?php
					$nonce = wp_create_nonce('bvnonce');
					foreach($accounts as $key => $value) {
				?>
					<form action="" method="post">
						<input type='hidden' name='bvnonce' value="<?php echo esc_attr($nonce); ?>" />
						<input type='hidden' name='pubkey' value="<?php echo esc_attr($key); ?>" />
						<tr>
							<td><?php echo esc_html($value['email']); ?></td>
							<td><?php echo esc_html(date('Y-m-d H:i:s', $value['lastbackuptime'])); ?></td>
							<td><input type='submit' class="btn btn-primary" style="font-size:12px" value='Disconnect' name='disconnect' onclick="return confirm('Are you sure?');"></td>
						</tr>
					</form>
				<?php } ?>
			</table>
		</div>
		<div style="margin: 15px;">
			<a class="btn btn-primary" href="<?php echo esc_url($this->bvinfo->appUrl()); ?>" target="_blank">Visit Dashboard</a>
			<a class="btn btn-primary" style="margin-left: 15px;" href="<?php echo esc_url($this->mainUrl('&add_account=true')); ?>">Connect New Account</a>
		</div>
	</div>
</section>