<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\Utils;

add_action(
	'template_redirect',
	function () {

		if ( Arr::has( $_REQUEST, '__kubio-page-query' ) && Utils::canEdit() ) {

			$cats = array_unique(
				array_merge(
					array( get_query_var( 'cat', null ) ),
					get_query_var( 'category__in', array() )
				)
			);

			foreach ( $cats as $index => $cat ) {
				if ( ! $cat ) {
					unset( $cats[ $index ] );
				}
			}

			$tags = array_unique(
				array_merge(
					array( get_query_var( 'tag_id', null ) ),
					get_query_var( 'tag__in', array() )
				)
			);

			foreach ( $tags as $index => $tag ) {
				if ( ! $tag ) {
					unset( $tags[ $index ] );
				}
			}

			$post_type = get_query_var( 'post_type', '' );

			if ( ! $post_type ) {
				$post_type = 'post';
			}

			$vars = array(
				'offset'      => 0,
				'categoryIds' => $cats,
				'postType'    => $post_type,
				'tagIds'      => $tags,
				'order'       => strtolower( get_query_var( 'order', 'desc' ) ),
				'orderBy'     => strtolower( get_query_var( 'orderBy', 'date' ) ),
				'author'      => get_query_var( 'author', 0 ),
				'search'      => get_query_var( 's', '' ),
				'exclude'     => get_query_var( 'post__not_in', array() ),
				'sticky'      => '',
				'perPage'     => intval( get_option( 'posts_per_page', 10 ) ),
			);

			return wp_send_json_success(
				$vars
			);
		}
	},
	PHP_INT_MAX
);
