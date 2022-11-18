<?php
use Kubio\Core\License\License;

?>
<div class="tab-page">
	<div class="limited-width">
		<div class="kubio-admin-page-page-section">
			<div id="upgrade_to_pro_wrapper">
				<div class="test">
					<?php
					echo License::getInstance()->getActivationForm()->makeUpgradeView();
					?>
				</div>
				<?php wp_enqueue_script( 'wp-util' ); ?>
			</div>
		</div>
		<div class="kubio-admin-page-page-section kubio-admin-page-page-section__get_pro">
			<div class="kubio-admin-page-page-section-header notice-info">
				<span>
				<?php esc_html_e( "Don't have a Kubio Pro license yet?", 'kubio' ); ?></span>
						<a href="
						<?php
						echo esc_url(
							kubio_get_site_url_for(
								'upgrade',
								array(
									'source'  => 'upgrade',
									'content' => 'no-license',
								)
							)
						);
						?>
						" target="_blank" class="button button-primary button-large">
							<?php esc_html_e( 'Get a Kubio license', 'kubio' ); ?>
						</a>
			</div>
			</div>
	</div>
</div>
