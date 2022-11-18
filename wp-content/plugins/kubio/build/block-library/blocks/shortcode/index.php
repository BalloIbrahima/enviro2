<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class ShortcodeBlock extends BlockBase {
	const OUTER = 'outer';

	public function mapPropsToElements() {
		$shortcode = $this->getAttribute( 'shortcode' );
		$in_editor = $this->getAttribute( 'inEditor' );

		if ( $in_editor ) {
			$content = $this->getEditorShortcode( $shortcode );
		} else {
			if ( $shortcode ) {
				$content = $this->getLiveShortcode( $shortcode );
			} else {
				$content = Utils::getEmptyShortcodePlaceholder();
			}
		}

		return array(
			self::OUTER => array( 'innerHTML' => $content ),
		);
	}

	public function getEditorShortcode( $shortcode ) {
		$content          = '';
		$content          = apply_filters( 'kubio/editor/before_render_shortcode', $content, $shortcode );
		$shortcode_output = do_shortcode( $shortcode );
		$content         .= $shortcode_output;
		$content          = apply_filters( 'kubio/editor/after_render_shortcode', $content, $shortcode );
		return urldecode( $content );
	}

	public function getLiveShortcode( $shortcode ) {
		return do_shortcode( $shortcode );
	}
}

Registry::registerBlock(
	__DIR__,
	ShortcodeBlock::class
);
