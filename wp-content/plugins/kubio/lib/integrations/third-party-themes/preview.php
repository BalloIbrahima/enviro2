<?php


// function kubio_preview_third_pa

use IlluminateAgnostic\Arr\Support\Arr;

function kubio_third_party_theme_preview_content( $content ) {
	$autosaved_posts = kubio_get_current_changeset_data( 'autosaves', array() );
	$post_id         = get_the_ID();

	foreach ( $autosaved_posts as $map ) {
		if ( Arr::get( $map, 'parent' ) === $post_id ) {
			$autosaved = get_post( Arr::get( $map, 'id' ) );
			if ( ! is_wp_error( $autosaved ) ) {
				$content = $autosaved->post_content;
			}
			break;
		}
	}

	return $content;
}

function kubio_third_party_theme_prepare_preview_post_content( $template ) {
	if ( kubio_is_page_preview() ) {
		global $_wp_current_template_content, $post;

		$is_previewing_3rd_party_theme_template = kubio_3rd_party_theme_is_previewing_theme_template();

		if ( ( ! $_wp_current_template_content || $is_previewing_3rd_party_theme_template ) && $post && $post->post_type === 'page' ) {
			$page_id = $post ? intval( $post->ID ) : null;

			add_filter(
				'the_content',
				function ( $content ) use ( $page_id ) {
					global $kubio_third_party_theme_preview_post_content_executed;
					$current_post = get_post();
					if ( ! $kubio_third_party_theme_preview_post_content_executed && $page_id === $current_post->ID ) {
						$kubio_third_party_theme_preview_post_content_executed = true;
						return kubio_third_party_theme_preview_content( $content );
					}
					return $content;
				},
				0
			);
		}
	}

	return $template;
}

add_action( 'template_include', 'kubio_third_party_theme_prepare_preview_post_content', 500 );
