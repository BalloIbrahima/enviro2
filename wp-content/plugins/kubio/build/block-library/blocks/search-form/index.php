<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;

class SearchForm extends BlockBase {

	const OUTER      = 'outer';
	const FORM       = 'form';
	const INPUT      = 'input';
	const BUTTON     = 'button';
	const ICON       = 'icon';
	const BUTTONTEXT = 'buttonText';

	public function computed() {
		$computedProps = array(
			'showInput'      => true,
			'showButton'     => 'inputAndButton' === $this->getProp( 'layout' ),
			'showButtonIcon' => 'icon' === $this->getProp( 'buttonType' ),
			'showButtonText' => 'text' === $this->getProp( 'buttonType' ),
			'iconButton'     => $this->getAttribute( 'iconName' ),
		);

		return $computedProps;
	}
	public function mapPropsToElements() {
		$inputPlaceholder = $this->getAttribute( 'placeholderText' );
		$buttonText       = $this->getProp( 'buttonText' );
		$button           = array( 'className' => array( 'search-button' ) );
		$iconButton       = $this->getAttribute( 'iconName' );

		return array(
			self::FORM       => array(
				'className' => array( 'd-flex', 'search-form' ),
				'action'    => home_url(),
				'role'      => 'search',
				'method'    => 'GET',
			),
			self::BUTTON     => $button,
			self::INPUT      => array(
				'className'   => array( 'search-input' ),
				'placeholder' => $inputPlaceholder,
				'value'       => get_search_query(),
				'name'        => 's',
			),
			self::ICON       => array(
				'name' => $iconButton,
			),
			self::BUTTONTEXT => array(
				'innerHTML' => $buttonText,
			),
		);
	}
}


Registry::registerBlock(
	__DIR__,
	SearchForm::class
);
