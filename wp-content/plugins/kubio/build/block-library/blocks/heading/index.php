<?php


namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Utils;
use Kubio\Core\StyleManager\DynamicStyles;

class HeadingBlock extends BlockBase {

	const TEXT               = 'text';
	const SVG                = 'svgShape';
	const TYPOGRAPHY_HOLDERS = 'typographyHolders';


	public function mapDynamicStyleToElements() {
		$dynamicStyles = array();

		$typographyHoldersByMedia = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => $this->getDefaultElement(),
			)
		);

		$dynamicStyles[ self::TYPOGRAPHY_HOLDERS ] = DynamicStyles::typographyHolders( $typographyHoldersByMedia );

		return $dynamicStyles;
	}

	public function mapPropsToElements() {

		$headingType = $this->getProp( 'level', $this->getAttribute( 'headerType', 2 ) );

		$tagName   = "h$headingType";
		$fancyHtml = $this->getFancyHtml();
		return array(
			self::TEXT => array(
				'tag'       => $tagName,
				'innerHTML' => $fancyHtml,
			),
		);
	}


	public function getFancyHtml() {
		$params        = $this->getFancyParameters();
		$fancy         = LodashBasic::get( $params, 'fancy' );
		$content       = $this->getBlockInnerHtml();
		$typeStyle     = LodashBasic::get( $fancy, 'typeStyle' );
		$typeAnimation = LodashBasic::get( $fancy, 'typeAnimation' );
		$fancyWord     = LodashBasic::get( $fancy, 'fancyWord' );

		if ( ! $typeStyle || $typeStyle === 'none' || ! $fancyWord ) {
			return $content;
		}
		$fancyHtml = '';
		if ( $typeStyle === 'highlighted' ) {
			$fancyHtml = $this->getHighlightHeading( $fancy );
		} else {
			if ( $typeAnimation !== 'type' ) {
				$fancyHtml = $this->getRotatingHeading( $fancy );
			} else {
				$fancyHtml = $this->getRotatingTypeHeading( $fancy );
			}
		}

		$fancyHtml   = preg_replace( "/\r|\t/i", '', $fancyHtml );
		$fancyRegex  = sprintf( '/%s/i', preg_quote( $fancyWord ) );
		$htmlContent = preg_replace( $fancyRegex, $fancyHtml, $content );

		return $htmlContent;
	}

	public function getFancyParameters() {
		return array(
			'fancy'   => $content = $this->getProp( 'fancy' ),
			'content' => $this->getBlockInnerHtml(),
		);
	}

	public function getHighlightHeading( $fancy ) {
		$fancy_word   = LodashBasic::get( $fancy, 'fancyWord' );
		$bringToFront = LodashBasic::get( $fancy, 'bringToFront' );
		$typeShape    = LodashBasic::get( $fancy, 'typeShape' );
		$classes      = array( 'text-animation-fancy' );
		if ( $bringToFront ) {
			$classes[] = 'bring-to-front';
		}

		$svgList = $this->getFancySvgList();
		$svg     = LodashBasic::get( $svgList, $typeShape );
		ob_start();

		?>
		<span class="fancy-title-headline d-inline-flex">
			<span class="text-wrapper-fancy">
				<span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
					<?php echo wp_kses_post( $fancy_word ); ?>
				</span>
				<?php echo $svg; ?>
			</span>
		</span>
		<?php

		$htmlContent = str_replace( "\n", '', ob_get_clean() );

		return $htmlContent;
	}

	public function getFancySvgList() {
		return array(
			'circle'           =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M325,18C228.7-8.3,118.5,8.3,78,21C22.4,38.4,4.6,54.6,5.6,77.6c1.4,32.4,52.2,54,142.6,63.7 c66.2,7.1,212.2,7.5,273.5-8.3c64.4-16.6,104.3-57.6,33.8-98.2C386.7-4.9,179.4-1.4,126.3,20.7"/></svg>',
			'curly'            =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M3,146.1c17.1-8.8,33.5-17.8,51.4-17.8c15.6,0,17.1,18.1,30.2,18.1c22.9,0,36-18.6,53.9-18.6 c17.1,0,21.3,18.5,37.5,18.5c21.3,0,31.8-18.6,49-18.6c22.1,0,18.8,18.8,36.8,18.8c18.8,0,37.5-18.6,49-18.6c20.4,0,17.1,19,36.8,19 c22.9,0,36.8-20.6,54.7-18.6c17.7,1.4,7.1,19.5,33.5,18.8c17.1,0,47.2-6.5,61.1-15.6"></path></svg>',
			'underline'        =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M7.7,145.6C109,125,299.9,116.2,401,121.3c42.1,2.2,87.6,11.8,87.3,25.7"></path></svg>',
			'double'           =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M8.4,143.1c14.2-8,97.6-8.8,200.6-9.2c122.3-0.4,287.5,7.2,287.5,7.2"></path><path d="M8,19.4c72.3-5.3,162-7.8,216-7.8c54,0,136.2,0,267,7.8"></path></svg>',
			'double-underline' =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M5,125.4c30.5-3.8,137.9-7.6,177.3-7.6c117.2,0,252.2,4.7,312.7,7.6"></path><path d="M26.9,143.8c55.1-6.1,126-6.3,162.2-6.1c46.5,0.2,203.9,3.2,268.9,6.4"></path></svg>',
			'underline-zigzag' =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M9.3,127.3c49.3-3,150.7-7.6,199.7-7.4c121.9,0.4,189.9,0.4,282.3,7.2C380.1,129.6,181.2,130.6,70,139 c82.6-2.9,254.2-1,335.9,1.3c-56,1.4-137.2-0.3-197.1,9"></path></svg>',
			'diagonal'         =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M13.5,15.5c131,13.7,289.3,55.5,475,125.5"></path></svg>',
			'strikethrough'    =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M3,75h493.5"></path></svg>',
			'x'                =>
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none"><path d="M497.4,23.9C301.6,40,155.9,80.6,4,144.4"></path><path d="M14.1,27.6c204.5,20.3,393.8,74,467.3,111.7"></path></svg>',

		);
	}

	public function getRotatingHeading( $fancy ) {
		$duration      = LodashBasic::get( $fancy, 'animationDuration.value' );
		$animation     = LodashBasic::get( $fancy, 'typeAnimation' );
		$rotating_html = $this->getRotatingHtml( $fancy );
		ob_start();

		?>
		<span data-kubio-component="fancy-title"
			  data-animation-duration="<?php echo esc_attr( $duration ); ?>"
			  data-type-animation=<?php echo esc_attr( $animation ); ?>
		>
		<span class='ah-headline <?php echo  esc_attr( $animation ); ?>'>
				<span style="position: relative" class="ah-words-wrapper">
					<?php echo wp_kses_post( $rotating_html ); ?>
				</span>
			</span>
		</span>
		<?php
		$htmlContent = str_replace( "\n", '', ob_get_clean() );

		return $htmlContent;
	}

	public function getRotatingHtml( $fancy ) {
		$fancy_rotating_words   = LodashBasic::get( $fancy, 'fancyRotatingWords' );
		$fancy_rotating_words   = explode( "\n", $fancy_rotating_words );
		$fancy_word             = LodashBasic::get( $fancy, 'fancyWord' );
		$fancy_rotating_words[] = $fancy_word;
		$rotating_content       = array_map(
			function ( $word, $index ) {
				$modified_word = str_replace( ' ', '&nbsp;', $word );
				ob_start();
				?>
					<b class="<?php	echo ( $index === 0 ? 'is-visible' : '' ); ?>">
						<?php echo wp_kses_post( $modified_word ); ?>
					</b>
				<?php
				return str_replace( "\n", '', ob_get_clean() );
			},
			$fancy_rotating_words,
			array_keys( $fancy_rotating_words )
		);

		return implode( '', $rotating_content );
	}

	public function getRotatingTypeHeading( $fancy ) {

		$animationDuration        = LodashBasic::get( $fancy, 'animationDuration.value' );
		$typeAnimationDurationIn  = LodashBasic::get( $fancy, 'type.animationDuration.in.value' );
		$typeAnimationDurationOut = LodashBasic::get( $fancy, 'type.animationDuration.out.value' );
		$typeAnimation            = LodashBasic::get( $fancy, 'typeAnimation' );
		$fancy_word               = LodashBasic::get( $fancy, 'fancyWord' );
		$fancy_rotating_words     = LodashBasic::get( $fancy, 'fancyRotatingWords' );
		ob_start();

		?>
		<span data-kubio-component="fancy-title"
			  data-animation-duration="<?php echo esc_attr( $animationDuration ); ?>"
			  data-type-animation-duration-in="<?php echo esc_attr( $typeAnimationDurationIn ); ?>"
			  data-type-animation-duration-out="<?php echo esc_attr( $typeAnimationDurationOut ); ?>"
			  data-type-animation="<?php echo esc_attr( $typeAnimation ); ?>"
			  data-word="<?php echo esc_attr( $fancy_word ); ?>"
			  data-rotating-words="<?php echo esc_attr( $fancy_rotating_words ); ?>"
			  data-animation-type="true">

			  <?php echo wp_kses_post( $fancy_word ); ?>
		</span>
		<?php
		$htmlContent = ob_get_clean();
		return $htmlContent;
	}
}

Registry::registerBlock( __DIR__, HeadingBlock::class );
