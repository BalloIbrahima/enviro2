<?php
if (!defined('MCDATAPATH')) exit;

if (!class_exists('BVPrependInfo')) :
	class BVPrependInfo {
		public $brandName;

		function __construct($brand) {
			$this->brandName = $brand;
		}

		public function getBrandName() {
			return $this->brandName;
		}

	}
endif;