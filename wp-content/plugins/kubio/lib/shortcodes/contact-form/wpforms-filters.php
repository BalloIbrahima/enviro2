<?php


function shortcode_render_can_apply_wpforms_filters( $shortcode ) {
	if ( ! class_exists( 'WPForms' ) ) {
		return false;
	}

	if ( strpos( $shortcode, '[wpforms' ) !== false ) {
		return true;
	}

	if ( strpos( $shortcode, 'wpforms' ) !== false ) {
		return true;
	}

	return false;
}

add_filter(
	'kubio/editor/before_render_shortcode',
	function ( $content, $shortcode ) {
		if ( kubio_shortcode_is_kubio_contact_form( $shortcode ) ) {
			$shortcode = kubio_get_kubio_contact_form_shortcode( $shortcode );
		}
		if ( shortcode_render_can_apply_wpforms_filters( $shortcode ) ) {

			remove_all_actions( 'wp_enqueue_scripts' );
			remove_all_actions( 'wp_print_footer_scripts' );
			remove_all_actions( 'wp_print_styles' );

			\WPForms::instance()->frontend->assets_css();

			ob_start();
			wp_print_styles();
			$ob_content    = ob_get_clean();
			 $extraContent = "\n\n<!--header  shortcode=wpforms scripts-->\n{$ob_content}<!--header scripts-->\n\n";
			 return $extraContent . $content;
		}
		return $content;
	},
	10,
	2
);

add_filter(
	'kubio/editor/after_render_shortcode',
	function ( $content, $shortcode ) {
		if ( kubio_shortcode_is_kubio_contact_form( $shortcode ) ) {
			$shortcode = kubio_get_kubio_contact_form_shortcode( $shortcode );
		}
		if ( shortcode_render_can_apply_wpforms_filters( $shortcode ) ) {
			ob_start();
			?>
		<div class="wpforms-confirmation-container-full h-hidden">
			<p>This is a success message preview text</p>
		</div>
			<?php
			wp_print_footer_scripts();
			$ob_content   = ob_get_clean();
			$extraContent = "\n\n<!--footer scripts shortcode=wpforms-->\n{$ob_content}<!--footer scripts-->\n\n";

			return $content . $extraContent;
		}
		return $content;

	},
	10,
	2
);
