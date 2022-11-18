<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Utils;

add_action(
	'template_redirect',
	function () {
		if ( Arr::has( $_REQUEST, '__kubio-rendered-content' ) && Utils::canEdit() ) {

			$content = apply_filters( 'kubio/editor/rendered-content', do_blocks( '<!-- wp:post-content /-->' ) );

			$stripped_content = strip_tags( $content );

			if ( ! trim( $stripped_content ) ) {
				$content = sprintf(
					'<p>%s</p>' .
					'<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum</p>',
					__( 'Current post content is empty. A placeholder text is displayed in editor', 'kubio' )
				);
			}

			return wp_send_json_success(
				array(
					'content' => $content,
				)
			);
		}
	},
	PHP_INT_MAX
);
