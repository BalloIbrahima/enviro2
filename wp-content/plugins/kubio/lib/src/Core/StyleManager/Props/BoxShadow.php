<?php


namespace Kubio\Core\StyleManager\Props;


use Kubio\Core\LodashBasic;
use Kubio\Core\StyleManager\ParserUtils;
use Kubio\Core\StyleManager\Utils;
use function join;

class BoxShadow extends PropertyBase {
	public function parse( $value, $options ) {
		$enabled = LodashBasic::get( $value, 'enabled', false );
		if ( ! $enabled ) {
			return array(
				'boxShadow' => 'none',
			);
		}

		$layerDefault = LodashBasic::get( $this->config( 'default' ), 'layers.0', array() );
		$layer        = LodashBasic::merge( $layerDefault, LodashBasic::get( $value, 'layers.0', array() ) );
		$style        = array();
		if ( ParserUtils::areAllNonEmpty( LodashBasic::omit( $layer, 'inset' ) ) ) {
			$style['boxShadow'] = join( ' ', array( "{$layer['x']}px", "{$layer['y']}px", "{$layer['blur']}px", "{$layer['spread']}px", $layer['color'], $layer['inset'] ) );
		}
		return $style;
	}
}
