<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;

class CopyrightBlock extends BlockBase {

	const CONTAINER = 'container';
	const OUTER     = 'outer';

	public function mapPropsToElements() {
		$template = $this->getBlockInnerHtml();

		return array(
			self::OUTER => array( 'innerHTML' => $this->kubio_copyright_shortcode( array(), $template ) ),
		);
	}

	function kubio_copyright_shortcode( $atts, $content ) {
		//TODO the href will need changing to the kubio website when will have one
		$default = '&copy; {year} {site-name}. Built using WordPress and <a target="_blank" href="https://colibriwp.com">{site-name}</a>';
		$msg     = $content ? $content : $default;
		$msg     = str_replace( '{year}', date( 'Y' ), $msg );
		$msg     = str_replace( '{site-name}', get_bloginfo( 'name' ), $msg );
		$msg     = sprintf( '<p>%s</p>', $msg );
		return html_entity_decode( $msg );
	}
}

Registry::registerBlock(
	__DIR__,
	CopyrightBlock::class
);
