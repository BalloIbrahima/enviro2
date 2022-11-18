<?php

function kubio_prepare_classic_theme_block_template_parts() {
	require_once __DIR__ . '/kubio-block-header-template-part.php';
	require_once __DIR__ . '/kubio-block-footer-template-part.php';
}

add_action(
	'after_setup_theme',
	'kubio_prepare_classic_theme_block_template_parts',
	20
);

add_filter(
	str_replace( '-', '_', get_template() ) . '_theme_components',
	function ( $components ) {

		if ( class_exists( 'KubioBlockBasedHeaderTemplatePart' ) ) {
			$components['header'] = KubioBlockBasedHeaderTemplatePart::class;
		}

		if ( class_exists( 'KubioBlockBasedFooterTemplatePart' ) ) {
			$components['footer'] = KubioBlockBasedFooterTemplatePart::class;
		}

		add_action(
			'wp_footer',
			function () {
				global $kubio_force_render_partials_style;
				if ( $kubio_force_render_partials_style ) {
					kubio_enqueue_frontend_scripts();
					wp_enqueue_style( 'kubio-block-library' );

					$style = array(
						// shapes
						kubio_get_shapes_css(),
						// colors
						kubio_render_global_colors(),
						// global
						kubio_get_global_data( 'additional_css' ),
						// page css
						kubio_get_page_css(),
					);

					echo '<style>' . implode( "\n", $style ) . '</style>';
				}
			}
		);

		return $components;
	},
	100
);
