<?php

namespace Kubio\Core\StyleManager;

use Kubio\Config;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;

class BlockStyleRender extends  StyleRender {
	protected $block;
	private $styleRef;


	private static $rendererRefs = array();

	protected $parentBlock         = null;
	protected $parentStyleRenderer = null;

	public static function normalizeBlockData( $mainAttr, $blockType ) {
		$supports             = $blockType->supports;
		$styledElementsByName = LodashBasic::get( $supports, array( Config::$mainAttributeKey, Config::$elementsKey ), array() );
		$styledElementsEnum   = LodashBasic::get( $supports, array( Config::$mainAttributeKey, Config::$elementsEnum ), array() );

		return self::normalizeData( $mainAttr, $styledElementsByName, $styledElementsEnum );
	}


	public function setFlags() {

	}


	public function __construct( $block, $parent_block = null ) {
		$this->block               = $block;
		$this->parentBlock         = $parent_block;
		$this->parentStyleRenderer = $parent_block ? new BlockStyleRender( $parent_block ) : null;
		$mainAttr                  = $block->getMergedMainAttribute();
		$this->styleRef            = LodashBasic::get( $mainAttr, 'styleRef', null );

		// check if the same style was already rendered and skip new renders
		if ( $this->styleRef && in_array( $this->styleRef, BlockStyleRender::$rendererRefs, true ) ) {
			$this->skipSharedStyleRender = true;
		}

		BlockStyleRender::$rendererRefs[] = $this->styleRef;

		$normalized  = self::normalizeBlockData( array_merge( $mainAttr, array( 'id' => $this->block->localId() ) ), $block->block_type );
		$this->model = (object) LodashBasic::get( $normalized, 'model', array() );

		$prefixParents   = array();
		$useParentPrefix = $this->block->getBlockSupport( 'useParentPrefix' );
		if ( $this->parentBlock ) {
			$prefixParents[] = '.' . $this->parentStyleRenderer->componentInstanceClass( $this->parentBlock->getWrapperElementName(), 'shared' );
		}

		parent::__construct(
			array(
				'styledElementsByName' => LodashBasic::get( $normalized, 'styledElementsByName', array() ),
				'styledElementsEnum'   => LodashBasic::get( $normalized, 'styledElementsEnum', array() ),
				'wrapperElement'       => $block->getWrapperElementName(),
				'prefixParents'        => $prefixParents,
				'useParentPrefix'      => $useParentPrefix,
			)
		);
	}

	public function export( $dynamicStyle = null ) {
		return parent::export( $dynamicStyle );
	}
}
