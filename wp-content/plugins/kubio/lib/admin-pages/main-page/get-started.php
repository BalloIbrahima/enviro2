<?php

use Kubio\Core\Utils;

function kubio_admin_page_get_connect_options() {
	return array(
		array(
			'description' => __( 'Kubio Website', 'kubio' ),
			'icon'        => KUBIO_LOGO_SVG,
			'link'        => kubio_get_site_url_for( 'homepage', array( 'source' => 'get-started' ) ),
		),
		array(
			'description' => __( 'Kubio Documentation', 'kubio' ),
			'icon'        => KUBIO_LOGO_SVG,
			'link'        => kubio_get_site_url_for( 'documentation', array( 'source' => 'get-started' ) ),
		),
		array(
			'description' => __( 'Talk to support', 'kubio' ),
			'icon-file'   => 'email.svg',
			'link'        => kubio_get_site_url_for( 'contact', array( 'source' => 'get-started' ) ),
		),
		array(
			'description' => __( 'Connect with us', 'kubio' ),
			'icon-file'   => 'facebook.svg',
			'link'        => kubio_get_site_url_for( 'facebook' ),
		),
	);
}

?>

<div class="tab-page">

	<div class="get-started-with-kubio limited-width">

		<?php
		do_action( 'kubio/admin-page/before-get-started' );
		?>

		<div class="kubio-admin-page-page-section kubio-get-started-section-1">
			<div class="get-started-demo">
				<div class="demo-title">
					<?php esc_html_e( 'Check out our 2 min getting started video', 'kubio' ); ?>
				</div>
				<div class="kubio-demo-video">
					<div class="kubio-demo-video-wrapper">
						<iframe width="100%" height="100%"
								src="https://player.vimeo.com/video/655007132?h=d0d9a5f8a3&title=0&byline=0&portrait=0">
						</iframe>
					</div>
				</div>
			</div>
		</div>

		<div class="kubio-admin-page-page-section kubio-get-started-section-2">



			<div class="get-started-connect">

				<div class="connect-title">
					<?php esc_html_e( 'Connect with us', 'kubio' ); ?>
				</div>

				<div class="connect-body">
					<ul class="body-items">
						<?php foreach ( kubio_admin_page_get_connect_options() as $kubio_admin_page_resource ) : ?>
							<li>
								<div class="item-icon">
									<?php
									$icon = false;
									if ( isset( $kubio_admin_page_resource['icon'] ) ) {
										$icon = $kubio_admin_page_resource['icon'];
									}
									if ( isset( $kubio_admin_page_resource['icon-file'] ) ) {
										$icon_file = kubio_admin_assets_path() . sanitize_file_name( $kubio_admin_page_resource['icon-file'] );
										$icon      = file_exists( $icon_file ) ? file_get_contents( $icon_file ) : '';
									}

									echo  $icon ? wp_kses_post( $icon ) : '';
									?>
								</div>
								<a target="_blank" href=" <?php echo esc_url( $kubio_admin_page_resource['link'] ); ?>" class="item-description">
									<?php echo esc_html( $kubio_admin_page_resource['description'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>

				</div>
			</div>

		</div>

	</div>

</div>



