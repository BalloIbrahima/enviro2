<?php

function kubio_page_template_get() {

	$pages     = array();
	$pages_tmp = get_pages();

	foreach ( $pages_tmp as $page ) {
		$pages[] = array(
			'label'   => $page->post_title,
			'value'   => $page->ID,
			'content' => $page->post_content,
		);
	}

	return $pages;
}



add_action(
	'rest_api_init',
	function () {
		$namespace = 'kubio/v1';

		register_rest_route(
			$namespace,
			'/page-templates/get',
			array(
				'methods'             => 'GET',
				'callback'            => 'kubio_page_template_get',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},

			)
		);
	}
);
