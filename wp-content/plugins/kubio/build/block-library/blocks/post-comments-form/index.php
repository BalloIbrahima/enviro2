<?php

namespace Kubio\Blocks;

use Kubio\Core\Blocks\BlockBase;
use Kubio\Core\Registry;

class PostCommentsFormBlock extends BlockBase {

	const CONTAINER = 'container';

	function disableCurrentUser() {
		return false;
	}

	public function serverSideRender() {
		$user = wp_get_current_user();
		wp_set_current_user( 0 );
		add_filter( 'determine_current_user', array( $this, 'disableCurrentUser' ), PHP_INT_MAX );

		$content = $this->renderForm();

		if ( empty( $content ) ) {
			$content = sprintf(
				'<p class="comments-disabled"><p>%s</p></p>',
				__( 'Comments are closed. This Post Comments Form block is still present here but is not visible on front-end.', 'kubio' )
			);
		}

		if ( $user ) {
			wp_set_current_user( $user->ID );
		}
		remove_filter( 'determine_current_user', array( $this, 'disableCurrentUser' ), PHP_INT_MAX );

		return $content;
	}

	function renderForm() {

		if ( $this->isSandboxRender() ) {
			return '';
		}

		ob_start();
		if ( comments_open( get_the_ID() ) ) {
			comment_form( get_the_ID() );
		}

		return ob_get_clean();
	}

	public function mapPropsToElements() {
		return array(
			self::CONTAINER => array(
				'innerHTML' => $this->renderForm(),
			),
		);

	}
}

Registry::registerBlock( __DIR__, PostCommentsFormBlock::class );
