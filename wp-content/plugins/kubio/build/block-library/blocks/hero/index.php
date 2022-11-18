<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Styles\FlexAlign;

class HeroBlock extends SectionBlock {
	const INLINE_SCRIPT = 'inlineScript';

	public function mapPropsToElements() {
		$map = parent::mapPropsToElements();

		$map[ self::INLINE_SCRIPT ] = array(
			'innerHTML' => $this->getOverlapScript(),
		);

		return $map;
	}

	public function getOverlapScript() {
		ob_start();
		?>
		<script type='text/javascript'>
			(function () {
				// forEach polyfill
				if (!NodeList.prototype.forEach) {
					NodeList.prototype.forEach = function (callback) {
						for (var i = 0; i < this.length; i++) {
							callback.call(this, this.item(i));
						}
					}
				}
				var navigation = document.querySelector('[data-colibri-navigation-overlap="true"], .h-navigation_overlap');
				if (navigation) {

					var els = document
						.querySelectorAll('.h-navigation-padding');
					if (els.length) {
						els.forEach(function (item) {
							item.style.paddingTop = navigation.offsetHeight + "px";
						});
					}
				}
			})();
		</script>
		<?php
		return ob_get_clean();
	}
}

Registry::registerBlock(
	__DIR__,
	HeroBlock::class,
	array(
		'metadata'        => '../section/block.json',
		'metadata_mixins' => array( 'block.json' ),
	)
);
