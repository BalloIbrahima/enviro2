<?php


namespace Kubio\Core\StyleManager;

use Kubio\Core\LodashBasic;

class DynamicStyles {


	public static function hSpaceParent( $spaceByMedia ) {
		$style = array();
		foreach ( $spaceByMedia as $media => $mediaValue ) {
			$style[ $media ] = array();
			if ( isset( $mediaValue['value'] ) ) {
				$halfSpace                      = number_format( $mediaValue['value'] / 2, 0 );
				$style[ $media ]['marginLeft']  = -$halfSpace . 'px';
				$style[ $media ]['marginRight'] = -$halfSpace . 'px';
			}
		}
		return $style;
	}

	public static function hSpace( $spaceByMedia ) {
		$style = array();

		foreach ( $spaceByMedia as $media => $mediaValue ) {
			$style[ $media ] = array();
			if ( isset( $mediaValue['value'] ) ) {
				$halfSpace                       = number_format( $mediaValue['value'] / 2, 0 );
				$style[ $media ]['paddingLeft']  = $halfSpace . 'px';
				$style[ $media ]['paddingRight'] = $halfSpace . 'px';
			}
		}
		return $style;
	}

	public static function vSpace( $spaceByMedia, $negative = false ) {
		 $style = array();
		foreach ( $spaceByMedia as $media => $mediaValue ) {
			$style[ $media ] = array();
			if ( isset( $mediaValue['value'] ) ) {
				$style[ $media ]['marginBottom'] = ( $negative ? '-' : '' ) . $mediaValue['value'] . 'px';
			}
		}
		return $style;
	}

	public static function typographyHolders( $typographyHoldersByMedia ) {

		$typographyByMedia = array();
		foreach ( $typographyHoldersByMedia as $media => $holders ) {
			$typographyByMedia[ $media ] = array(
				'typography' => array(
					'holders' => $holders,
				),
			);
		}
		return $typographyByMedia;
	}
}
