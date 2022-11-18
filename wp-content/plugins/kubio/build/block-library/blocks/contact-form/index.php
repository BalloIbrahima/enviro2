<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class ContactFormBlock extends BlockBase {

	const FORM_CONTAINER = 'formContainer';
	const FORM_WRAPPER   = 'formWrapper';
	const PLACEHOLDER    = 'placeholder';


	public function computed() {
		$shortcode = $this->getAttribute( 'shortcode' );
		return array(
			'disableStyleClasses' => $this->getAttribute( 'useShortcodeStyle', false ),
			'renderContainer'     => ! ! $shortcode,
			'renderPlaceholder'   => ! $shortcode,
		);
	}

	public function getShortcodeAttributes() {
		return array(
			'shortcode'           => $this->getAttribute( 'shortcode' ),
			'use_shortcode_style' => $this->getAttribute( 'useShortcodeStyle' ) ? 1 : 0,

			'decode_data'         => 0,
		);
	}


	public function mapPropsToElements() {
		$shortcode   = $this->getAttribute( 'shortcode' );
		$content     = null;
		$placeholder = null;
		if ( $shortcode ) {
			$content = kubio_contact_form_shortcode( $this->getShortcodeAttributes() );
		} else {
			$placeholder = Utils::getEmptyShortcodePlaceholder();
		}

		$containerClasses  = array();
		$useShortcodeStyle = $this->getAttribute( 'useShortcodeStyle', false );
		if ( $useShortcodeStyle ) {
			$containerClasses[] = 'kubio-no-style';
		} else {
			$containerClasses[] = 'kubio-use-style';
		}
		return array(
			self::FORM_CONTAINER => array(
				'innerHTML'         => $content,
				'className'         => $containerClasses,
				'useShortcodeStyle' => $useShortcodeStyle,

			),
			self::FORM_WRAPPER   => array(
				'useShortcodeStyle' => $useShortcodeStyle,
			),
			self::PLACEHOLDER    => array(
				'innerHTML' => $placeholder,
			),
		);
	}

}

Registry::registerBlock(
	__DIR__,
	ContactFormBlock::class
);
