<?php

namespace Kubio\Core\StyleManager;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Config;
use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Blocks\DataHelper;
use Kubio\Core\LodashBasic;

class GlobalStyleRender extends StyleRender {

	const V_SPACE_NEGATIVE   = 'vSpaceNegative';
	const V_SPACE            = 'vSpace';
	const H_SPACE_GROUP      = 'hSpaceGroup';
	const H_SPACE            = 'hSpace';
	const GLOBAL_PREFIX      = '#kubio';
	const BODY_SELECTOR      = '';
	const KUBIO_BLOCK_PREFIX = '[data-kubio]';

	private $dataHelper = null;
	public function __construct( $main_attr ) {
		 $this->dataHelper = new DataHelper( array(), array( Config::$mainAttributeKey => $main_attr ) );

		$styledElementsByName = Config::value( 'definitions.globalStyle.elementsByName' );
		$styledElementsEnum   = Config::value( 'definitions.globalStyle.elementsEnum' );

		$styledElementsByName = $this->maybePrefixStyledElements( $styledElementsByName );

		$normalized  = self::normalizeData( $main_attr, $styledElementsByName, $styledElementsEnum );
		$this->model = (object) array_merge(
			LodashBasic::get( $normalized, 'model', array() ),
			array(
				'globalStyle' => true,
			)
		);
		parent::__construct(
			array(
				'styledElementsByName' => $styledElementsByName,
				'styledElementsEnum'   => $styledElementsEnum,
				'wrapperElement'       => false,
			)
		);
	}


	public function maybePrefixStyledElements( $styledElementsByName ) {
		foreach ( $styledElementsByName as $name => $styled_element ) {

			//if the styled element has isGlobalSelector true do not change it.
			if ( isset( $styled_element['selector'] ) && Arr::get( $styled_element, 'isGlobalSelector', false ) || $name === 'body' ) {
				continue;
			}

			if ( isset( $styled_element['selector'] ) && Arr::get( $styled_element, 'withGlobalPrefix', false ) ) {
				$prefix = GlobalStyleRender::GLOBAL_PREFIX . ' ' . GlobalStyleRender::BODY_SELECTOR;
			} elseif ( isset( $styled_element['selector'] ) && Arr::get( $styled_element, 'withKubioBlockPrefix', false ) ) {
				$prefix = GlobalStyleRender::KUBIO_BLOCK_PREFIX;
			} else {
				$prefix = GlobalStyleRender::BODY_SELECTOR;
			}
				$selector = $styled_element['selector'];

			if ( is_string( $selector ) ) {
					$styled_element['selector'] = $this->prefixSelector( $selector, $prefix );
			} else {
				foreach ( $selector as $state => $state_selector ) {
					$styled_element['selector'][ $state ] = $this->prefixSelector( $state_selector, $prefix );
				}
			}

				$styledElementsByName[ $name ] = $styled_element;
		}

		return $styledElementsByName;
	}

	private function prefixSelector( $selector, $prefix ) {
		$selector_parts = explode( ',', $selector );

		foreach ( $selector_parts as $index => $value ) {
			$selector_parts[ $index ] = $prefix . ' ' . trim( $value );
		}

		return implode( ',', $selector_parts );
	}

	public function getDynamicStyle() {
		 $vSpaceByMedia = $this->dataHelper->getPropByMedia( 'vSpace' );
		$hSpaceByMedia  = $this->dataHelper->getPropByMedia( 'hSpace' );

		return  self::normalizeDynamicStyle(
			array(
				self::V_SPACE_NEGATIVE => DynamicStyles::vSpace(
					$vSpaceByMedia,
					true
				),
				self::V_SPACE          => DynamicStyles::vSpace(
					$vSpaceByMedia
				),
				self::H_SPACE_GROUP    => DynamicStyles::hSpaceParent(
					$hSpaceByMedia
				),
				self::H_SPACE          => DynamicStyles::hSpace(
					$hSpaceByMedia
				),
			)
		);
	}

	public function export( $dynamicStyle = null ) {
		$style = $this->model->style['shared'];
		$css   = array();

		$css['global'] = $this->convertStyleToCss(
			$style,
			array(
				'styledElementsByName' => $this->styledElementsByName,
				'styleType'            => 'global',
			)
		);

		$css['dynamic'] = $this->convertStyleToCss(
			$this->getDynamicStyle(),
			array(
				'styledElementsByName' => $this->styledElementsByName,
				'styleType'            => 'global',
			)
		);

		return $css;
	}
}
