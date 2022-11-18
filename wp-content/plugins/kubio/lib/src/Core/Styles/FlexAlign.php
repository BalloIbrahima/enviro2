<?php
namespace Kubio\Core\Styles;

use Kubio\Core\LodashBasic;
use Kubio\Core\Styles\Utils;

class FlexAlign {
	public static function getVAlignClasses( $alignByMedia, $options = array() ) {
		$self        = LodashBasic::get( $options, 'self', false );
		$alignPrefix = $self ? 'align-self' : 'align-items';
		return Utils::composeClassesByMedia( $alignByMedia, $alignPrefix );
	}

	public static function getHAlignClasses( $alignByMedia, $options = array() ) {
		$self        = LodashBasic::get( $options, 'self', false );
		$alignPrefix = $self ? 'justify-self' : 'justify-content';
		return Utils::composeClassesByMedia( $alignByMedia, $alignPrefix );
	}

}
