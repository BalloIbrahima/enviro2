<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;
use Kubio_Walker_Comment;
use Kubio\Core\StyleManager\DynamicStyles;

class PostCommentsBlock extends BlockBase {

	const CONTAINER                     = 'commentsContainer';
	const TYPOGRAPHY_HOLDERS_CONTAINER  = 'typographyHoldersContainer';
	const TYPOGRAPHY_HOLDERS_CONTENT    = 'typographyHoldersContent';
	const TYPOGRAPHY_HOLDERS_REPLY_FORM = 'typographyHoldersReplyForm';


	public function mapDynamicStyleToElements() {
		$dynamicStyles = array();

		$containerTypography = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => self::CONTAINER,
			)
		);
		$contentTypography   = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => self::TYPOGRAPHY_HOLDERS_CONTENT,
			)
		);
		$replyFormTypography = $this->getStyleByMedia(
			'typography.holders',
			array(),
			array(
				'styledComponent' => self::TYPOGRAPHY_HOLDERS_REPLY_FORM,
			)
		);

		$dynamicStyles = array(
			self::TYPOGRAPHY_HOLDERS_CONTAINER  => DynamicStyles::typographyHolders( $containerTypography ),
			self::TYPOGRAPHY_HOLDERS_CONTENT    => DynamicStyles::typographyHolders( $contentTypography ),
			self::TYPOGRAPHY_HOLDERS_REPLY_FORM => DynamicStyles::typographyHolders( $replyFormTypography ),
		);

		return $dynamicStyles;
	}


	static function getPostCommentsTemplate() {
		return KUBIO_ROOT_DIR . '/lib/blog/comments.php';
	}

	public function serverSideRender() {
		global $withcomments;
		$withcomments = true;

		return $this->getPostComments(
			array(
				'none'        => $this->getAttribute( 'noCommentsTitle' ),
				'one'         => $this->getAttribute( 'oneCommentTitle' ),
				'multiple'    => $this->getAttribute( 'multipleComments' ),
				'disabled'    => $this->getAttribute( 'commentsDisabled' ),
				'avatar_size' => $this->getAttribute( 'avatarSize' ),
			)
		);
	}

	function getPostComments( $attrs = array() ) {

		if ( apply_filters( 'kubio/sandboxed_render', false ) ) {
			return '';
		}

		$atts = array_merge(
			array(
				'none'        => 'No responses yet',
				'one'         => 'One response',
				'multiple'    => '{COMMENTS-COUNT} Responses',
				'disabled'    => 'Comments are closed',
				'avatar_size' => 32,
			),
			$attrs
		);

		global $kubio_comments_data;
		$kubio_comments_data = $atts;

		ob_start();
		add_filter( 'kubio/walker-comment', array( $this, 'getCommentWalker' ) );

		add_filter( 'comments_template', array( PostCommentsBlock::class, 'getPostCommentsTemplate' ) );
		if ( comments_open( get_the_ID() ) ) {
			comments_template();
		} else {
			return sprintf( '<p class="comments-disabled">%s</p>', $atts['disabled'] );
		}
		$content = ob_get_clean();

		remove_filter( 'comments_template', array( PostCommentsBlock::class, 'getPostCommentsTemplate' ) );
		remove_filter( 'kubio/walker-comment', array( $this, 'getCommentWalker' ) );

		return $content;
	}

	public function getCommentWalker( $walker ) {
		$migrations = $this->getAppliedMigrations();
		if ( in_array( 1, $migrations ) || in_array( '1', $migrations ) ) {
			require_once KUBIO_ROOT_DIR . '/lib/blog/walker-comment.php';
			return new Kubio_Walker_Comment();
		}

		return $walker;
	}

	public function mapPropsToElements() {
		return array(
			self::CONTAINER => array(
				'innerHTML' => $this->getPostComments(
					array(
						'none'        => $this->getAttribute( 'noCommentsTitle' ),
						'one'         => $this->getAttribute( 'oneCommentTitle' ),
						'multiple'    => $this->getAttribute( 'multipleComments' ),
						'disabled'    => $this->getAttribute( 'commentsDisabled' ),
						'avatar_size' => $this->getAttribute( 'avatarSize' ),
						'html5'       => true,
					)
				),
			),
		);
	}
}

Registry::registerBlock( __DIR__, PostCommentsBlock::class );
