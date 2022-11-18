<?php

class Kubio_Menu_Rest_Controller extends WP_REST_Controller {
	public function __construct() {
		$this->namespace = 'kubio/v1';
		$this->rest_base = 'menu';
	}

	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_permissions' ),
					'args'                => $this->get_collection_params(),
				),

				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/save-menu-location',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_menu_to_location' ),
					'permission_callback' => array( $this, 'get_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),

				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'key' => array(
						'description' => __( 'Unique identifier for menu.', 'kubio' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_permissions' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'get_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	public function get_item_schema() {
		$schema = parent::get_item_schema();

		return $schema;
	}

	public function get_permissions() {
		return current_user_can( 'edit_theme_options' );
	}

	public function create_item( $request ) {
		return array();

	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return array|WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$type           = $request->get_param( 'type' );
		$content        = $request->get_param( 'data' );
		$parsed_content = json_decode( $content );
		$menu           = $parsed_content->menu;
		$items          = $parsed_content->items;
		$location       = $request->get_param( 'location' );

		$id = $this->update_menu_term( $menu, $location );

		$this->remove_deleted_items( $items, $id );
		$tree_items = $this->items_list_to_tree( $items );

		$this->update_menu_items( $tree_items, $id );
		$result_menu = wp_get_nav_menu_object( $id );

		$response = array(
			'id'           => intval( $request->get_param( 'id' ) ),
			'modified_gmt' => gmdate( 'Y-m-d H:i:s', time() ),
			'location'     => $location,
			'data'         => json_encode(
				array(
					'blocks' => false,
					'menu'   => $result_menu,
					'items'  => $this->get_menu_items( $id ),
				)
			),
		);

		return $response;
	}

	private function update_menu_term( $menu_data, $location ) {
		$id = $menu_data->term_id;

		if ( $id < 0 ) {
			$id = 0;
		}

		$id = wp_update_nav_menu_object(
			$id,
			array(
				'menu-name' => $menu_data->name,
			)
		);

		if ( $location ) {
			$locations              = get_nav_menu_locations();
			$locations[ $location ] = $id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		return $id;
	}

	private function remove_deleted_items( $items, $menu_id ) {
		$menu_items     = wp_get_nav_menu_items( $menu_id );
		$menu_items_ids = array_map(
			function( $item ) {
				return $item->ID;
			},
			$menu_items
		);

		if ( ! is_array( $menu_items_ids ) ) {
			return;
		}

		$current_ids = array_map(
			function ( $items ) {
				return intval( $items->id );
			},
			$items
		);

		foreach ( $menu_items_ids as $menu_items_id ) {
			if ( ! in_array( $menu_items_id, $current_ids ) ) {
				wp_delete_post( $menu_items_id, true );
			}
		}
	}

	private function items_list_to_tree( $items, $parent = null ) {
		$parent_id = $parent ? $parent->id : 0;
		if ( ! $parent ) {
			$parent = array(
				'children' => array(),
			);
		}

		$children = array();

		foreach ( $items as $item ) {
			if ( $item->parent === $parent_id ) {
				$children[] = (object) array(
					'item'     => $item,
					'children' => $this->items_list_to_tree( $items, $item ),
				);
			}
		}

		return $children;
	}

	private function update_menu_items( $items, $menu_id, $parent_id = 0 ) {

		foreach ( $items as $item ) {
			$children = $item->children;
			$item     = $item->item;
			$id       = $item->id > 0 ? $item->id : 0;
			$args     = (array) $item;

			$id = wp_update_nav_menu_item(
				$menu_id,
				$id,
				array(
					'menu-item-type'      => $args['type'],
					'menu-item-object-id' => $args['objectId'],
					'menu-item-object'    => $args['object'],
					'menu-item-position'  => $args['order'],
					'menu-item-title'     => $args['label'],
					'menu-item-url'       => $args['url'] ? $args['url'] : '#',
					'menu-item-target'    => $args['target'],
					'menu-item-status'    => 'publish',
					'menu-item-parent-id' => $parent_id,
				)
			);

			$this->update_menu_items( $children, $menu_id, $id );
		}
	}

	private function get_menu_items( $term_id ) {

		$menu_items = wp_get_nav_menu_items( $term_id );
		$items      = array();

		if ( ! is_array( $menu_items ) ) {
			return $menu_items;
		}

		$orders        = array();
		$order         = 0;
		$processed_ids = array();
		foreach ( $menu_items as $item ) {
			$item_id = intval( $item->ID );

			if ( in_array( $item_id, $processed_ids ) ) {
				continue;
			}

			$order++;
			$orders[ $item_id ] = $order;
			$processed_ids[]    = $item_id;

			foreach ( $menu_items as $item2 ) {
				$item2_id = intval( $item2->ID );
				if ( in_array( $item2_id, $processed_ids ) ) {
					continue;
				}

				$parent_id = intval( $item2->menu_item_parent );
				if ( $parent_id && $parent_id === $item_id ) {
					$order++;
					$orders[ $item2_id ] = $order;
					$processed_ids[]     = $item2_id;
				}
			}
		}

		foreach ( $menu_items as $item ) {
			/** @type object $item */
			$items[] = (object) array(
				'id'       => intval( $item->ID ),
				'parent'   => intval( $item->menu_item_parent ),
				'label'    => $item->title,
				'url'      => $item->url,
				'target'   => $item->target,
				'object'   => $item->object,
				'type'     => $item->type,
				'objectId' => intval( $item->object_id ),
				// used for initial load
				'order'    => $orders[ intval( $item->ID ) ],
			);
		}

		return $items;
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return array|WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$id = intval( $request->get_param( 'id' ) );

		if ( $id <= 0 ) {
			return $this->new_menu( $request );
		}

		$location = $request->get_param( 'location' );
		$menu     = wp_get_nav_menu_object( $id );

		return array(
			'id'       => $id,
			'location' => $location,
			'data'     => json_encode(
				array(
					'blocks' => false,
					'menu'   => $menu,
					'items'  => $this->get_menu_items( $id ),
				)
			),
		);
	}

	private function new_menu( $request ) {
		$id       = intval( $request->get_param( 'id' ) );
		$location = $request->get_param( 'location' );
		$label    = $request->get_param( 'label' );
		$label    = $label ? $label : 'New Menu';

		$term_base = array(
			'name'     => $label,
			'taxonomy' => 'nav_menu',
			'parent'   => 0,
		);

		return array(
			'id'       => $id,
			'location' => $location,
			'data'     => json_encode(
				array(
					'blocks' => false,
					'menu'   => array_merge(
						$term_base,
						array(
							'slug' => wp_unique_term_slug( sanitize_title( $label ), (object) $term_base ),
						)
					),
					'items'  => array(),
				)
			),
		);
	}

	public function get_items( $request ) {
		return array();
	}

	public function set_menu_to_location( $request ) {
		$location = $request->get_param( 'location' );
		$id       = $request->get_param( 'id' );

		$locations              = get_nav_menu_locations();
		$locations[ $location ] = $id;
		set_theme_mod( 'nav_menu_locations', $locations );

		return true;
	}
}
