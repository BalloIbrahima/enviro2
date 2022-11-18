<?php


namespace Kubio\Core\Styles;

use Kubio\Core\LodashBasic;
use function implode;

use Kubio\Config;
use Kubio\Core\Utils as CoreUtils;

class Utils {

	public static function composeClassesByMedia( $valuesByMedia, $prefix, $allow_empty = false ) {
		$classes = array();
		foreach ( $valuesByMedia as $media => $value ) {
			if ( $value !== null || $allow_empty ) {
				$classes[] = self::composeClassForMedia( $media, $value, $prefix, $allow_empty );
			}
		}

		return $classes;
	}

	public static function composeClassForMedia( $media, $value, $prefix, $allow_empty = false ) {
		if ( ! $allow_empty ) {
			$isEmptyString = is_string( $value ) && strlen( $value ) === 0;
			if ( $isEmptyString ) {
				return '';
			}
		}
		$mediaPrefix   = self::getMediaPrefix( $media );
		$values        = LodashBasic::compactWithExceptions( array( $prefix, $mediaPrefix, $value ), array( '0', 0 ) );
		$prefixedClass = implode( '-', $values );

		return $prefixedClass;
	}

	public static function getMediaPrefix( $media ) {
		return LodashBasic::get( Config::mediasById(), $media . '.' . 'gridPrefix', false );
	}


	public static function getPrefixedCss( $css, $prefix ) {
		# Wipe all block comments
		$css = preg_replace( '!/\*.*?\*/!s', '', $css );

		$parts               = explode( '}', $css );
		$keyframe_started    = false;
		$media_query_started = false;

		foreach ( $parts as &$part ) {
			$part = trim( $part ); # Wht not trim immediately .. ?
			if ( empty( $part ) ) {
				$keyframe_started = false;
				continue;
			} else # This else is also required
			{
				$part_details = explode( '{', $part );

				if ( strpos( $part, 'keyframes' ) !== false ) {
					$keyframe_started = true;
					continue;
				}

				if ( $keyframe_started ) {
					continue;
				}

				if ( substr_count( $part, '{' ) === 2 ) {
					$mediaQuery          = $part_details[0] . '{';
					$part_details[0]     = $part_details[1];
					$media_query_started = true;
				}

				$sub_parts = explode( ',', $part_details[0] );
				foreach ( $sub_parts as &$subPart ) {
					if ( trim( $subPart ) === '@font-face' ) {
						continue;
					} else {
						$subPart = $prefix . ' ' . trim( $subPart );
					}
				}

				if ( substr_count( $part, '{' ) == 2 ) {
					$part = $mediaQuery . "\n" . implode( ', ', $sub_parts ) . '{' . $part_details[2];
				} elseif ( empty( $part[0] ) && $media_query_started ) {
					$media_query_started = false;
					$part                = implode( ', ', $sub_parts ) . '{' . $part_details[2] . "}\n"; //finish media query
				} else {
					if ( isset( $part_details[1] ) ) {   # Sometimes, without this check,
						# there is an error-notice, we don't need that..
						$part = implode( ', ', $sub_parts ) . '{' . $part_details[1];
					}
				}

				unset( $part_details, $mediaQuery, $sub_parts ); # Kill those three ..
			}   unset( $part ); # Kill this one as well
		}

		if ( CoreUtils::isDebug() ) {
			return implode( "}\n", $parts );
		}

		# Finish with the whole new prefixed string/file in one line
		return( preg_replace( '/\s+/', ' ', implode( '} ', $parts ) ) );
	}

}

