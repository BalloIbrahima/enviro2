<?php
if ( class_exists( '\Gutenberg_REST_Templates_Controller' ) ) {
	class KubioRestTemplateController extends \Gutenberg_REST_Templates_Controller {
	}

} else {
	class KubioRestTemplateController extends \WP_REST_Templates_Controller {
	}
}
