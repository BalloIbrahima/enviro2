<?php

use IlluminateAgnostic\Arr\Support\Arr;

function kubio_preview_menu_item( $item, $initial_item, $menu_term_id = 0 ) {

	return (object) array_merge(
		array(
			'object_id'        => 0,
			'object'           => '',
			'menu_item_parent' => 0,
			'position'         => 1,
			'type'             => '',
			'title'            => '',
			'url'              => '',
			'target'           => '',
			'attr_title'       => '',
			'description'      => '',
			'classes'          => '',
			'xfn'              => '',
			'status'           => '',
			'original_title'   => '',
			'nav_menu_term_id' => 0,
			'type_label'       => '',
			'current'          => false,
			'menu_order'       => 1,
		),
		(array) $initial_item,
		array(
			'object_id'        => $item['objectId'],
			'object'           => $item['objectId'],
			'menu_item_parent' => $item['parent'],
			'position'         => $item['order'],
			'menu_order'       => ( $item['parent'] * 1000 ) + $item['order'],
			'type'             => $item['type'],
			'title'            => $item['label'],
			'url'              => $item['url'],
			'original_title'   => $item['label'],
			'nav_menu_term_id' => $menu_term_id,
			'db_id'            => $item['id'],
			'ID'               => $item['id'],
			'post_parent'      => $item['id'] ? wp_get_post_parent_id( $item['id'] ) : $item['id'],
			'target'           => $item['target'],
		)
	);
}

add_action(
	'kubio/preview/handle_custom_entities',
	function ( $data ) {
		$kind = Arr::get( $data, 'kind' );
		$name = Arr::get( $data, 'name' );

		if ( $kind === 'kubio' && $name === 'menu' ) {
			$menu_data = json_decode( Arr::get( $data, 'data' ), true );
			$items     = Arr::get( (array) $menu_data, 'items' );
			$menu      = Arr::get( (array) $menu_data, 'menu' );

			add_filter(
				'wp_get_nav_menu_items',
				function ( $menu_items, $current_menu, $args ) use ( $items, $menu ) {

					if ( $cache = wp_cache_get( intval( $current_menu->term_id ), 'kubio/preview-menus' ) ) {
						return $cache;
					}

					if ( intval( $current_menu->term_id ) === intval( $menu['term_id'] ) ) {
						$mapped_items = array();

						foreach ( $items as $item ) {

							$initial_item = null;

							foreach ( $menu_items as $menu_item ) {
								if ( intval( $menu_item->db_id ) === intval( $item['id'] ) ) {
									$initial_item = $menu_item;
									break;
								}
							}

							$mapped_items[] = kubio_preview_menu_item( $item, $initial_item, intval( $menu['term_id'] ) );
						}

						wp_cache_add( intval( $current_menu->term_id ), $mapped_items, 'kubio/preview-menus' );

					} else {
						$mapped_items = $menu_items;
					}

					return $mapped_items;
				},
				10,
				3
			);
		}

	}
);


function kubio_nav_menu_locations_preview( $locations ) {

	if ( ! kubio_is_page_preview() ) {
		return $locations;
	}

	$global_data_locations = kubio_get_global_data( 'menuLocations', array() );

	foreach ( $global_data_locations as $global_data_location ) {
		$location = $global_data_location['name'];
		$menu_id  = $global_data_location['menu'];

		if ( $menu_id ) {
			$locations[ $location ] = $menu_id;
		}
	}

	return $locations;
}

add_filter(
	'theme_mod_nav_menu_locations',
	'kubio_nav_menu_locations_preview'
);
