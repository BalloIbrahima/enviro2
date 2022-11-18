<?php


use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\DemoSites\DemoSitesRepository;
use Kubio\PluginsManager;

if ( ! current_user_can( 'edit_theme_options' ) ) {
	?>
	<div class="tab-page">
		<div class="limited-width">
			<div class="kubio-admin-page-page-section">
				<div class="kubio-admin-page-page-section-header">
					<?php _e( 'You need a higher level of permission.', 'kubio' ); ?>
				</div>
				<div class="kubio-admin-page-page-section-content">
					<p><?php _e( 'Current user is not allowed to import starter sites. This feature is available only for administrators', 'kubio' ); ?></p>
				</div>
			</div>
		</div>
	</div>
	<?php
	return;
} else {

	?>

	<div class="kubio-admin-page-page-section">
		<div class="<?php kubio_admin_page_class( 'templates-wrapper' ); ?>">
			<div id="kubio-templates-list" class="<?php kubio_admin_page_component_class( 'templates' ); ?>">
				<?php if ( empty( DemoSitesRepository::getDemos() ) ) : ?>
						<h2><?php esc_html_e( 'Retrieving starter sites...', 'kubio' ); ?></h2>
				<?php endif; ?>
				<?php foreach ( DemoSitesRepository::getDemos() as $demo ) : ?>
					<div class="<?php kubio_admin_page_component_class( 'template' ); ?>"
						 data-demo-site="<?php echo esc_attr( Arr::get( $demo, 'slug', '' ) ); ?>">

						<?php if ( Arr::get( $demo, 'is_pro', '' ) && ! kubio_is_pro() ) : ?>
						<div class="<?php kubio_admin_page_component_class( 'pro-badge' ); ?>">PRO</div>
						<?php endif; ?>

						<div class="<?php kubio_admin_page_component_class( 'template-image' ); ?>">
							<img src="<?php echo esc_url( Arr::get( $demo, 'thumb', kubio_url( '/static/admin-pages/' ) ) ); ?>"
								 alt="image">
							<?php if ( Arr::get( $demo, 'preview', 'null' ) ) : ?>
								<div class="<?php kubio_admin_page_component_class( 'template-image-overlay' ); ?>">
									<a href="<?php echo esc_url( Arr::get( $demo, 'preview' ) ); ?>" target="_blank">
										<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!-- Font Awesome Pro 5.15.4 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) --><path d="M572.52 241.4C518.29 135.59 410.93 64 288 64S57.68 135.64 3.48 241.41a32.35 32.35 0 0 0 0 29.19C57.71 376.41 165.07 448 288 448s230.32-71.64 284.52-177.41a32.35 32.35 0 0 0 0-29.19zM288 400a144 144 0 1 1 144-144 143.93 143.93 0 0 1-144 144zm0-240a95.31 95.31 0 0 0-25.31 3.79 47.85 47.85 0 0 1-66.9 66.9A95.78 95.78 0 1 0 288 160z"/></svg>
										<span><?php esc_html_e( 'Preview', 'kubio' ); ?></span>
									</a>
								</div>
							<?php endif; ?>
						</div>

						<div class="<?php kubio_admin_page_component_class( 'template-body' ); ?>">
							<div class="<?php kubio_admin_page_component_class( 'template-title' ); ?>">
								<?php echo esc_html( Arr::get( $demo, 'name', __( 'Untitled', 'kubio' ) ) ); ?>
							</div>

							<div class="<?php kubio_admin_page_component_class( 'template-buttons' ); ?>">
								<button class="button button-primary"
										data-slug="<?php echo esc_attr( Arr::get( $demo, 'slug', '' ) ); ?>">
									<?php esc_html_e( 'Import site', 'kubio' ); ?>
								</button>


								<a href="<?php echo esc_url( kubio_try_demo_url( Arr::get( $demo, 'slug', '' ) ) ); ?>" target="_blank"
									rel="nofollow"
									class="button ">
									<?php esc_html_e( 'Try Online', 'kubio' ); ?>
								</a>

							</div>

						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<div id="kubio-template-installing"
				 class="<?php kubio_admin_page_component_class( 'template-installing', array( 'hidden' ) ); ?>">
				<div class="<?php kubio_admin_page_component_class( 'template-installing-wrapper' ); ?>">
					<div class="kubio-admin-row">
						<div class="<?php kubio_admin_page_component_class( 'template-installing-image-col' ); ?>">
							<div class="<?php kubio_admin_page_component_class( 'template-image' ); ?>">
								<img/>
							</div>
						</div>
						<div class="<?php kubio_admin_page_component_class( 'template-installing-info-col' ); ?>">
							<h1 data-title></h1>

							<div data-info
								 class="<?php kubio_admin_page_component_class( 'template-installing-info' ); ?>">
								<h2><?php esc_html_e( 'You are about to import a demo site', 'kubio' ); ?></h2>
								<ul>
									<li>
										<?php esc_html_e( 'Current pages will be moved to trash. You can restore the content back at any time.', 'kubio' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'Posts, pages, images, widgets, menus and other theme settings will get imported.', 'kubio' ); ?>
									</li>
									<li class="text-danger">
										<?php esc_html_e( 'Your current design will be completely overwritten by the new template. This process is irreversible. If you wish to be able to go back to the current design, please create a backup of your site before proceeding with the import.', 'kubio' ); ?>
									</li>
								</ul>

								<div class="hidden" data-plugins>
									<hr/>
									<h2><?php esc_html_e( 'The following plugins will be installed as they are part of the demo', 'kubio' ); ?></h2>
									<ul data-plugins-list
										class="<?php kubio_admin_page_component_class( 'template-plugins-list' ); ?>">
									</ul>
								</div>
							</div>
							<div data-progress
								 class="<?php kubio_admin_page_component_class( 'template-installing-progress', array( 'hidden' ) ); ?>">

								<ul data-progress-list>
									<li data-installing-plugins>
										<?php esc_html_e( 'Installing required plugins', 'kubio' ); ?>
									</li>
									<li data-preparing-for-import>
										<?php esc_html_e( 'Preparing for demo site import', 'kubio' ); ?>
									</li>
									<li data-importing-content>
										<?php esc_html_e( 'Importing content', 'kubio' ); ?>
									</li>
								</ul>

								<div data-importing-errors
									 class="<?php kubio_admin_page_component_class( 'template-installing-errors', array( 'hidden' ) ); ?>">
									<p><?php esc_html_e( 'Errors', 'kubio' ); ?></p>
									<div data-importing-errors-content>

									</div>
								</div>
							</div>

							<h2 data-available-pro-only
								class="hidden"
								style="text-align: right">
								<?php esc_html_e( 'Available only with Kubio PRO', 'kubio' ); ?>
							</h2>

							<div data-install-buttons
								 class="<?php kubio_admin_page_component_class( 'template-installing-buttons' ); ?>">
								<button class="button " data-cancel-import>
									<?php esc_html_e( 'Cancel', 'kubio' ); ?>
								</button>
								<button id="import-button" class="button button-primary">
									<?php esc_html_e( 'Import site', 'kubio' ); ?>
								</button>
								<a  target="_blank" href="<?php echo esc_url( kubio_get_site_url_for( 'features', array( 'source' => 'demos' ) ) ); ?>"
									class="button button-primary hidden" data-check-pro-features>
									<?php esc_html_e( 'Check all PRO features', 'kubio' ); ?>
								</a>
							</div>

							<div data-install-success-buttons
								 class="<?php kubio_admin_page_component_class( 'template-installing-success-buttons', array( 'hidden' ) ); ?>">
								<div class="kubio-admin-row">
									<div class="kubio-admin-col">
										<p><?php esc_html_e( 'Demo site imported sucessfuly', 'kubio' ); ?></p>
									</div>
									<div class="kubio-admin-col">

										<a target="_blank" href="<?php echo esc_url( site_url() ); ?>"
										   class="button " t>
											<?php esc_html_e( 'View site', 'kubio' ); ?>
										</a>
										<a href="<?php echo esc_url( add_query_arg( 'page', 'kubio', admin_url( 'admin.php' ) ) ); ?>"
										   class="button button-primary">
											<?php esc_html_e( 'Start editing', 'kubio' ); ?>
										</a>

									</div>
								</div>


							</div>

						</div>
					</div>
					<?php kubio_print_continous_loading_bar( true ); ?>
				</div>
			</div>
		</div>
	</div>

	<?php

	function kubio_admin_page_templates_js_init() {
		$demos = DemoSitesRepository::getDemos();

		$plugins_states = DemoSitesRepository::getPluginsStates();


		$data = array(
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'demos'            => $demos,
			'ajax_nonce'       => wp_create_nonce( 'kubio-ajax-demo-site-verification' ),
			'texts'            => array(
				'importing_template' => '%s',
				'plugins_states'     => array(
					'ACTIVE'        => esc_html__( 'Active', 'kubio' ),
					'INSTALLED'     => esc_html__( 'Installed', 'kubio' ),
					'NOT_INSTALLED' => esc_html__( 'Not Installed', 'kubio' ),
				),
				'import_stopped'     => esc_html__( 'Import stopped', 'kubio' ),
			),
			'plugins_states'   => $plugins_states,
			'kubio_pro_active' => kubio_is_pro(),
		);

		wp_add_inline_script(
			'kubio-admin-area',
			sprintf( 'kubio.adminArea.initDemoImport(%s)', wp_json_encode( $data ) ),
			'after'
		);
	}

	add_action( 'admin_footer', 'kubio_admin_page_templates_js_init', 0 );

}
