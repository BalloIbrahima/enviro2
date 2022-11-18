<?php

function _kubio_gutenberg_check_notice() {
	?>
	<p>
		<?php
			printf(
				__( 'The %1$s plugin is active on this site. This might interfere with the proper functioning of the %2$s plugin. It is recommended to deactivate the %1$s plugin' ),
				'<strong>Gutenberg</strong>',
				'<strong>Kubio Page Builder</strong>'
			);
		?>
	</p>
	<?php
}

function kubio_add_gutenberg_plugin_notice() {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if ( is_plugin_active( 'gutenberg/gutenberg.php' ) || defined( 'GUTENBERG_VERSION' ) ) {
			kubio_add_dismissable_notice( 'kubio-gutenberg-check-1', '_kubio_gutenberg_check_notice', 1 * DAY_IN_SECONDS, array(), 'notice-warning' );
	}
}

add_action( 'plugins_loaded', 'kubio_add_gutenberg_plugin_notice' );
