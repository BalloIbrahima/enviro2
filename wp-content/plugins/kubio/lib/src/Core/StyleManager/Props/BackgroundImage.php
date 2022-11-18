<?php

namespace Kubio\Core\StyleManager\Props;

use Kubio\Core\StyleManager\ParserUtils;
use function is_string;
use function join;

/**
 * This class is capable of managing string type values for `background-size` like 'contain` or `cover` but it can
 * also return a x/y based size like `50% 50%` from an array like ['x' => ['value' => 50], y => ['value' => 50]]
 *
 * Class BackgroundImageSize
 * @package KubioCore\StyleManager\Props
 */
class BackgroundImageSize extends Property {

	public $properties = array(
		'anyOf' => array(
			array(
				'type'    => 'string',
				'default' => 'auto',
			),
			array(
				'type'    => 'CustomSize',
				'default' => 'auto',
			),
		),
	);

	public function __toString() {
		if ( is_string( $this->value ) ) {
			return $this->value;
		}

		return $this->computeCustomSizeStyle( $this->value );
	}

	public function computeCustomSizeStyle( $customSize ) {
		if ( ! is_array( $customSize ) ) {
			return '';
		}

		$bgSizeX = ParserUtils::toValueUnitString( $customSize['x'], 'auto' );
		$bgSizeY = ParserUtils::toValueUnitString( $customSize['y'], 'auto' );

		return join( ' ', array( $bgSizeX, $bgSizeY ) );
	}
}

class BackgroundImagePosition extends Property {

	public function __toString() {
		if ( is_string( $this->value ) ) {
			return $this->value;
		}

		return ParserUtils::toJoinedValueUnitString(
			array(
				array(
					'value' => $this->get( 'x' ),
					'unit'  => '%',
				),
				array(
					'value' => $this->get( 'y' ),
					'unit'  => '%',
				),
			)
		);
	}
}

class BackgroundImageSource extends Property {

	public function __toString() {
		if ( is_string( $this->value ) ) {
			return $this->value;
		}

		return $this->getBackgroundImage( (object) $this->value );
	}

	public function getBackgroundImage( $source ) {
		switch ( $source->type ) {
			case 'image':
				return 'url("' . $source->url . '")';
			case 'gradient':
				return $source->gradient;
		}

		return '';
	}
}

class FeaturedImageBackgroundImageSource extends Property {
	public function __toString() {
		if ( is_string( $this->value ) ) {
			return $this->value;
		}

		return $this->getBackgroundImage( (object) $this->value );
	}

	public function getBackgroundImage( $source ) {
		global $post;

		$page_for_posts = get_option( 'page_for_posts' );
		$page_id        = $post->ID;

		// Handle the case when we are on blog page
		if ( is_home() && $page_for_posts ) {
			$page_id = $page_for_posts;
		}

		$featured_image = get_the_post_thumbnail_url( $page_id, 'full' );

		// if we have a featured image we use it as a source.
		if ( ! empty( $featured_image ) ) {
			switch ( $source->type ) {
				case 'image':
					return 'url("' . $featured_image . '")';
			}
		}

		// otherwise, we keep returning the default image.
		switch ( $source->type ) {
			case 'image':
				return 'url("' . $source->url . '")';
		}
	}
}

class BackgroundImage extends Property {
	public $properties = array(
		'size'     => array( 'BackgroundImageSize' ),
		'position' => array( 'BackgroundImagePosition' ),
		'source'   => array( 'BackgroundImageSource' ),
	);

	public $map = array(
		'backgroundImage'      => 'source',
		'backgroundSize'       => 'size',
		'backgroundPosition'   => 'position',
		'backgroundAttachment' => 'attachment',
		'backgroundRepeat'     => 'repeat',
	);

	public function __construct( $value, $default = array() ) {
		parent::__construct( $value, $default );

		$source = $this->get( 'source' );
		if ( $this->get( 'useFeaturedImage' ) && $source['type'] === 'image' ) {
			$this->properties['source'] = array( 'FeaturedImageBackgroundImageSource' );
		}
	}

	public function toStyle() {
		 $computedStyle = array();
		$source         = $this->get( 'source' );
		$map            = $this->map;
		if ( $source['type'] === 'gradient' ) {
			$map = array(
				'backgroundImage'      => 'source',
				'backgroundAttachment' => 'attachment',
			);
		}
		foreach ( $map as $name => $property ) {
			if ( isset( $this->properties[ $property ] ) ) {
				$propertyDefinition     = $this->properties[ $property ];
				$class                  =
					'Kubio\\Core\\StyleManager\\Props\\' .
					$propertyDefinition[0];
				$instance               = new $class( $this->get( $property ) );
				$computedStyle[ $name ] = $instance->__toString();

				// a custom background is actually an array with x and y values, so we need to treat that case.
				if (
					$propertyDefinition[0] === 'BackgroundImageSize' &&
					$computedStyle[ $name ] === 'custom'
				) {
					$customSizeInstance     = new BackgroundImageSize(
						$this->get( 'sizeCustom' )
					);
					$computedStyle[ $name ] = $customSizeInstance->__toString();
				}
			} else {
				$computedStyle[ $name ] = $this->get( $property );
			}
		}

		return $computedStyle;
	}
}
