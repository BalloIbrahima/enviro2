<?php


/**
 * Override the default woocommerce templates with block templates
 *
 * @param string $template
 *
 * @return string
 */
function kubio_woocommerce_support_template_include( $template ) {

	$post_type              = get_post_type();
	$is_displaying_products = is_archive() || is_single() || is_tax();


	// This theme doesn't have a traditional sidebar.
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

	if ( $post_type === 'product' && $is_displaying_products ) {
		$pathinfo     = pathinfo( $template );
		$default_file = isset( $pathinfo['filename'] ) ? $pathinfo['filename'] : null;

		if ( ! $default_file ) {
			return $template;
		}

		$templates = is_archive() ? array( "$default_file.php", 'archive-product.php', 'archive.php' ) : array( "$default_file.php", 'single-product.php', 'single.php' );

		if ( is_tax() ) {
			$templates = array( 'archive-product.php', 'archive.php' );
		}

		$templates = array_unique( $templates );
		$template  = locate_block_template( "$default_file.php", 'wp_template', $templates );

		global $_wp_current_template_content;

		if ( ! empty( $_wp_current_template_content ) ) {

			if ( is_single() ) {
				add_theme_support( 'wc-product-gallery-zoom' );
				add_theme_support( 'wc-product-gallery-lightbox' );
				add_theme_support( 'wc-product-gallery-slider' );
			}

			// replace the 'post-content' block with the <!-- wp:html --> block to display the woocommerce content
			$_wp_current_template_content = preg_replace(
				'#<!-- wp:post-content(.*)/-->#',
				'<!-- wp:html -->' . kubio_get_woocommerce_content() . '<!-- /wp:html -->',
				$_wp_current_template_content
			);
		}
	}

	return $template;

}

function kubio_woocommerce_support_rendered_content( $content ) {
	$post_type              = get_post_type();
	$is_displaying_products = is_archive() || is_single();

	if ( $post_type === 'product' && $is_displaying_products ) {
		$content = kubio_get_woocommerce_content();
	}

	return $content;
}

add_filter( 'template_include', 'kubio_woocommerce_support_template_include', 20, 1 );

add_filter( 'kubio/editor/rendered-content', 'kubio_woocommerce_support_rendered_content' );


function kubio_woocommerce_achive_page_title() {
	if ( function_exists( 'is_shop' ) && is_shop() && wc_get_page_id( 'shop' ) ) {
		return get_the_title( wc_get_page_id( 'shop' ) );
	}
}

add_filter( 'post_type_archive_title', 'kubio_woocommerce_achive_page_title' );
