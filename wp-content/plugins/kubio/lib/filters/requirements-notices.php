<?php

use Kubio\Core\Utils;

function _kubio_requirements_not_met_notice() {
	$has_valid_req       = Utils::validateRequirements();
	list($error_message) = _wp_die_process_input( $has_valid_req );

	$plugin_headers = get_plugin_data( KUBIO_ENTRY_FILE );
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	$required_php = ! empty( $plugin_headers['RequiresPHP'] ) ? $plugin_headers['RequiresPHP'] : '';

	$required_php = sprintf( __( 'PHP %1$s', 'kubio' ), $required_php );

	$required_wp         = sprintf( __( 'WordPress %1$s', 'kubio' ), KUBIO_MINIMUM_WP_VERSION );
	$requirement_message = '';

	if ( $has_valid_req->get_error_code() === 'plugin_wp_incompatible' ) {

			$requirement_message = $required_wp;
	}
	if ( $has_valid_req->get_error_code() === 'plugin_wp_php_incompatible' ) {
			$requirement_message = sprintf(
				__( '%1$s and %2$s', 'kubio' ),
				$required_wp,
				$required_php
			);
	}

	if ( $has_valid_req->get_error_code() === 'plugin_php_incompatible' ) {
		$requirement_message = $required_php;
	}

	$kubio_previous_versions = Utils::getPluginVersions( true );
	$previous_version        = isset( $kubio_previous_versions['1.4.3'] ) ? $kubio_previous_versions['1.4.3'] : null;

	?>
	<style>
		.kubio-mrn {
			display: flex;
			align-items: flex-start;
			padding: 12px 0;
		}

		.kubio-mrn svg {
			width: 20px;
			display: block;
			fill: #D63638;
		}

		.kubio-mrn-icon-wrapper {
			width: 60px;
			height: 60px;
			display: flex;
			align-items: center;
			background: rgb(214 ,54 ,56,.1);
			align-content: center;
			justify-items: center;
			justify-content: center;
			border-radius: 60px;
		}

		.kubio-mrn-icon-holder {padding-right: 12px;}
		.kubio-mrn-content-wrapper h2,
		.kubio-mrn-content-wrapper p{
			margin: 0;
			padding: 0;
		}

		.kubio-mrn-content-wrapper h2{
			margin-bottom: 10px;
		}
		.kubio-mrn-content-wrapper a {
			font-weight: 500;
		}

	</style>
	<div class="kubio-mrn">
		<div class="kubio-mrn-icon-holder">
		<div class="kubio-mrn-icon-wrapper">
			<?php echo wp_kses_post( KUBIO_LOGO_SVG ); ?>
		</div>

		</div>
		<div class="kubio-mrn-content-wrapper">
			<h2><?php printf( __( 'Kubio Page Builder requires %s!', 'kubio' ), $requirement_message ); ?></h2>
			<?php echo $error_message; ?>
			<p class="kubio-mrn-rollback-message">

			<?php
			if ( is_array( $previous_version ) && ! kubio_is_pro() ) {
				printf(
					__( 'If you want to rollback to a previous version you can get it here: <a href="%1$s">%2$s</a>. You can follow this steps to manually install a Kubio: <a target="_blank" href="%3$s">Manual plugin update</a>', 'kubio' ),
					$previous_version ['url'],
					$previous_version ['named_version'],
					'https://kubiobuilder.com/documentation/how-to-manually-update-kubio'
				);
			}

			if ( kubio_is_pro() ) {
				printf(
					__( 'If you want to rollback to a previous version please open a support ticket: <a target="_blank" href="%1$s">Open support ticket</a>', 'kubio' ),
					'https://kubiobuilder.com/contact/#support'
				);
			}
			?>
			</p>
		</div>
	</div>
	<?php
}

add_action(
	'init',
	function() {
		$has_valid_req = Utils::validateRequirements();

		if ( is_wp_error( $has_valid_req ) ) {
			kubio_add_dismissable_notice(
				'kubio_requirements_notice',
				'_kubio_requirements_not_met_notice',
				1, // set time limit to a small amount to always display the notice
				array(),
				'notice-error'
			);
		}
	},
	5
);
