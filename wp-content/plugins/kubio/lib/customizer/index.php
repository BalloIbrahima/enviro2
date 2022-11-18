<?php


function kubio_get_editor_url() {
	return add_query_arg(
		array( 'page' => 'kubio' ),
		admin_url( 'admin.php' )
	);

}

function kubio_set_admin_bar_menu_customize_to_kubio_editor( WP_Admin_Bar &$admin_bar ) {

	if ( ! is_user_logged_in() || ! kubio_theme_has_kubio_block_support() ) {
		return;
	}

	$url            = kubio_fronend_get_editor_url();
	$appearanceNode = $admin_bar->get_node( 'customize' );
	if ( ! $appearanceNode ) {
		return;
	}
	$appearanceNode->href = $url;
	$admin_bar->add_node( $appearanceNode );
}

function kubio_set_admin_bar_customize_to_kubio_editor() {
	if ( ! kubio_theme_has_kubio_block_support() ) {
		return;
	}
	global $submenu;
	if ( ! isset( $submenu['themes.php'] ) ) {
		return;
	}

	foreach ( $submenu['themes.php'] as $key => $theme_submenu ) {
		$slug = $theme_submenu[1];
		if ( $slug !== 'customize' ) {
			continue;
		}
		$submenu['themes.php'][ $key ][2] = 'admin.php?page=kubio';
	}

}

function kubio_update_theme_page_customize_url( $prepared_themes ) {
	if ( ! kubio_theme_has_kubio_block_support() ) {
		return $prepared_themes;
	}
	foreach ( $prepared_themes as $key => $theme ) {
		if ( $theme['active'] === true ) {
			$prepared_themes[ $key ]['actions']['customize'] = kubio_get_editor_url();
		}
	}

	return $prepared_themes;
}

function kubio_update_dashboard_customizer_url() {
	if ( ! kubio_theme_has_kubio_block_support() ) {
		return;
	}
	$request_uri        = $_SERVER['REQUEST_URI'];
	$is_dashboard_admin = str_contains( $request_uri, 'wp-admin/index.php' );
	if ( ! $is_dashboard_admin ) {
		return;
	}

	$kubio_url = kubio_get_editor_url();
	ob_start();
	?>
	<script>
		(function($) {
			$(document).ready(function(){
				var customizeLink = document.querySelector('a.load-customize');
				if(!customizeLink) {
					return
				}
				customizeLink.setAttribute('href', "<?php echo $kubio_url ?>")
			})
		})(jQuery)
	</script>
	<?php
	$script = strip_tags( ob_get_clean() );
	wp_add_inline_script( 'jquery', $script );
	return;
}

add_filter( 'admin_init', 'kubio_update_dashboard_customizer_url' );
add_action( 'wp_prepare_themes_for_js', 'kubio_update_theme_page_customize_url', 1000 );
add_action( 'admin_bar_menu', 'kubio_set_admin_bar_menu_customize_to_kubio_editor', 1000 );
add_action( 'admin_menu', 'kubio_set_admin_bar_customize_to_kubio_editor', 1000 );

