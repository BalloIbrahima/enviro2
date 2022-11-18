<?php

if ( class_exists( '\Kubio\Theme\Components\Footer' ) ) {
	class KubioBlockBasedFooterTemplatePart extends \Kubio\Theme\Components\Footer {

		protected static function getOptions() {
			return array();
		}

		public function renderContent( $parameters = array() ) {

			$slug     = 'footer';
			$template = get_stylesheet();
			$part     = kubio_get_block_template( "$template//$slug", 'wp_template_part' );

			if ( $part ) {
				global $kubio_force_render_partials_style;
				$kubio_force_render_partials_style = true;
				echo do_blocks( $part->content );
			} else {
				parent::renderContent( $parameters );
			}
		}
	}

}
