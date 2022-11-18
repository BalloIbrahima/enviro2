<?php

function kubio_page_disable_astra_editor_dynamic_css() {
	if ( kubio_is_kubio_editor_page() ) {
		add_filter( 'astra_block_editor_dynamic_css', '__return_empty_string' );
	}
}

add_action( 'admin_init', 'kubio_page_disable_astra_editor_dynamic_css' );
