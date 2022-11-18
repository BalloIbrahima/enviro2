<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Utils as GeneralUtils;

class ImageBlock extends BlockContainerBase {

	const OUTER           = 'outer';
	const IMAGE           = 'image';
	const OVERLAY         = 'overlay';
	const CAPTION         = 'caption';
	const FRAME_IMAGE     = 'frameImage';
	const FRAME_CONTAINER = 'frameContainer';


	public function computed() {
		$showCaption    = $this->getAttribute( 'captionEnabled', false );
		$showOverlay    = $this->getStyle( 'background.overlay.enabled', false, array( 'styledComponent' => 'overlay' ) );
		$showFrameImage = $this->getPropByMedia( 'frame.enabled', false );
		return array(
			'showCaption'    => $showCaption,
			'showOverlay'    => $showOverlay,
			'showFrameImage' => in_array( true, $showFrameImage ),
		);
	}

	public function mapPropsToElements() {
		$frameHideClasses = GeneralUtils::mapHideClassesByMedia(
			$this->getPropByMedia( 'frame.enabled' ),
			true
		);

		$size_slug = $this->getAttribute( 'sizeSlug' );
		$align     = $this->getAttribute( 'align', 'center' );
		//the wp image class is used to add the src set by WordPress
		$imageClasses             = array( 'wp-image-' . $this->getAttribute( 'id' ) );
		$defaultImg               = GeneralUtils::getDefaultAssetsUrl( 'default-image.png' );
		$src                      = $this->getAttribute( 'url' );
		$src                      = $src ? $src : $defaultImg;
		$outerClasses             = array( "size-$size_slug", $this->getAlignClasses( $align ) );
		$map[ self::OUTER ]       = array( 'className' => $outerClasses );
		$map[ self::IMAGE ]       = array(
			'alt'       => $this->getAttribute( 'alt', '' ),
			'src'       => $src,
			'className' => $imageClasses,
		);
		$map[ self::FRAME_IMAGE ] = array(
			'className' => array_merge(
				$frameHideClasses
			),
		);
		$map[ self::CAPTION ]     = array( 'innerHTML' => $this->getBlockInnerHtml() );

		return $map;
	}

	public function getAlignClasses( $align ) {
		return sprintf( 'align-items-%s', $align ? $align : 'center' );
	}
}
Registry::registerBlock( __DIR__, ImageBlock::class );
