<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;


class SocialIconsBlock extends BlockBase {
	const OUTER = 'outer';

	public function __construct( $block, $autoload = true ) {
		parent::__construct( $block, $autoload );
	}

	public function mapPropsToElements() {
		return array(
			self::OUTER => array(),
		);
	}

}

Registry::registerBlock( __DIR__, SocialIconsBlock::class );

class SocialIconBlock extends BlockBase {
	const LINK = 'link';
	const ICON = 'icon';

	public function mapPropsToElements() {
		$link           = $this->getAttribute( 'link' );
		$linkAttributes = Utils::getLinkAttributes( $link );

		$iconName = $this->getAttribute( 'icon.name' );

		return array(
			self::LINK => $linkAttributes,

			self::ICON => array(
				'name' => $iconName,
			),
		);
	}
}

Registry::registerBlock(
	__DIR__,
	SocialIconBlock::class,
	array(
		'metadata' => './blocks/social-icon/block.json',
	)
);
