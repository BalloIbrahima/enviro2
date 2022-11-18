<?php


namespace Kubio\Core\StyleManager\Props;

class CustomSize extends Property {
	public $properties = array(
		'x' => array(
			'type'    => 'UnitValue',
			'default' => 'auto',
		),
		'y' => array(
			'type'    => 'UnitValue',
			'default' => 'auto',
		),
	);

	public $map = array(
		'x',
		'y',
	);

	public function __toString() {
		$resolved = $this->resolveProperties();
		$this->resolveMap( $resolved );
	}
}
