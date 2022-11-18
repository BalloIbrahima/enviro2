<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\GlobalElements\Icon;
use Kubio\Core\LodashBasic;
use Kubio\Core\Registry;
use Kubio\Core\Utils;

class PostMetaBlock extends BlockBase {


	const CONTAINER = 'metaDataContainer';

	private $postId;
	private $metaFunctionsMap = array();

	public function __construct( $block, $autoload = true, $context = array() ) {
		parent::__construct( $block, $autoload, $context );
		$this->metaFunctionsMap = array(
			'author'   => array(
				'content' => 'getAuthorContent',
				'url'     => 'getAuthorUrl',
			),
			'date'     => array(
				'content' => 'getDateContent',
				'url'     => 'getDateUrl',
			),
			'time'     => array(
				'content' => 'getTimeContent',
				'url'     => 'getTimeUrl',
			),
			'comments' => array(
				'content' => 'getCommentsContent',
				'url'     => 'getCommentsUrl',
			),
		);
	}

	public function mapPropsToElements() {
		$this->postId = LodashBasic::get( $this->block_context, 'postId' );

		$map[ self::CONTAINER ] = array(
			'innerHTML' => $this->getMetaItems(),
		);

		return $map;

	}

	private function getMetaItems() {
		$meta        = $this->getAttribute( 'metadata' );
		$metadata    = $this->parseItems( $meta );
		$showIcons   = $this->getAttribute( 'showIcons' );
		$separator   = $this->getAttribute( 'separator' );
		$isLastIndex = count( $metadata ) - 1;
		ob_start();

		foreach ( $metadata as $index => $item ) {

			$itemValue = LodashBasic::get( $item, 'value' );
			$icon      = new Icon( 'span', array( 'name' => $item['icon'] ) );

			?>
			<span class="metadata-item">
					<?php if ( $item['prefix'] != '' ) : ?>
						<span class="metadata-prefix"> <?php echo esc_html( $item['prefix'] ); ?> </span>
					<?php endif; ?>

					<a href="<?php echo esc_url( $this->getHref( $itemValue ) ); ?>">
						<?php if ( $showIcons ) : ?>
							<?php echo wp_kses_post( $icon ); ?>
						<?php endif; ?>
						<?php echo wp_kses_post( $this->getItemContent( $itemValue ) ); ?>
					</a>

					<?php if ( $item['suffix'] != '' ) : ?>
						<span class="metadata-suffix"> <?php echo esc_html( $item['suffix'] ); ?> </span>
					<?php endif; ?>
				</span>
			<?php if ( $isLastIndex > $index && $separator != '' ) : ?>
				<span class="metadata-separator"> <?php echo esc_html( $separator ); ?> </span>
			<?php endif; ?>

			<?php
		}

		return ob_get_clean();
	}

	private function parseItems( $items ) {
		$sortedItems = array();
		foreach ( $items as $item ) {
			$checkValue = LodashBasic::get( $item, 'check' );
			if ( $checkValue === 'true' || $checkValue === true ) {
				$sortedItems[] = $item;
			}
		}

		return $sortedItems;
	}

	private function getHref( $itemName ) {

		return $this->getUrl( $itemName );
	}

	public function getUrl( $type, $arg = array() ) {
		$functionName = LodashBasic::get( $this->metaFunctionsMap, array( $type, 'url' ) );
		if ( $functionName ) {
			return call_user_func( array( $this, $functionName ), $arg );
		}
	}

	private function getItemContent( $itemName ) {

		$atts = array();
		if ( $itemName === 'date' ) {
			$atts['dateformat'] = $this->getAttribute( 'dateFormat' );
		}

		return $this->getContent( $itemName, $atts );
	}

	public function getContent( $type, $arg ) {
		$functionName = LodashBasic::get( $this->metaFunctionsMap, array( $type, 'content' ) );
		if ( $functionName ) {
			return call_user_func( array( $this, $functionName ), $arg );
		}
	}

	public function serverSideRender() {
		$this->postId = $this->getAttribute( 'editorContext.postId' );

		return $this->getMetaItems();
	}

	public function getAuthorContent() {
		$authorId = get_post_field( 'post_author', $this->postId );
		$content  = get_the_author_meta( 'display_name', $authorId );

		return apply_filters( 'kubio_post_meta_author_content', $content );
	}

	public function getAuthorUrl() {
		$authorId = get_post_field( 'post_author', $this->postId );
		$url      = get_author_posts_url( $authorId );

		return apply_filters( 'kubio_post_meta_author_url', $url );
	}

	public function getDateContent( $atts ) {
		$atts    = shortcode_atts(
			array(
				'dateformat' => '',
			),
			$atts
		);
		$format  = apply_filters( 'kubio_post_meta_date_format', $atts['dateformat'] );
		$date    = get_the_date( $format, $this->postId );
		$content = apply_filters( 'kubio_post_meta_date_content', $date, $format );

		return $content;
	}

	public function getDateUrl() {
		$id   = $this->postId;
		$link = get_day_link(
			get_post_time( 'Y', false, $id, true ),
			get_post_time( 'm', false, $id, true ),
			get_post_time( 'j', false, $id, true )
		);

		return apply_filters( 'kubio_post_meta_date_url', $link );
	}

	public function getTimeContent() {
		$time = get_the_time( '', $this->postId );

		return apply_filters( 'kubio_post_meta_time_content', $time );
	}

	public function getTimeUrl() {
		$time = null;

		return apply_filters( 'kubio_post_meta_time_url', $time );
	}

	public function getCommentsUrl() {
		$url = get_comments_link( $this->postId );

		return apply_filters( 'kubio_post_meta_comments_url', $url );
	}

	public function getCommentsContent() {
		$content = get_comments_number( $this->postId );

		return apply_filters( 'kubio_post_meta_comments_content', $content );
	}

}


Registry::registerBlock( __DIR__, PostMetaBlock::class );
