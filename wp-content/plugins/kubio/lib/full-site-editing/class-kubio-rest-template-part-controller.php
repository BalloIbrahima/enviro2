<?php


trait KubioRestTemplatePartsAreaSupportTrait {
	protected function prepare_item_for_database( $request ) {
		$template = $request['id'] ? kubio_get_block_template( $request['id'], $this->post_type ) : null;
		$changes  = parent::prepare_item_for_database( $request );

		if ( isset( $request['area'] ) ) {
			$changes->tax_input['wp_template_part_area'] = kubio_filter_template_part_area( $request['area'] );
		} elseif ( null !== $template && 'custom' !== $template->source && $template->area ) {
			$changes->tax_input['wp_template_part_area'] = kubio_filter_template_part_area( $template->area );
		} elseif ( ! $template->area ) {
			$changes->tax_input['wp_template_part_area'] = WP_TEMPLATE_PART_AREA_UNCATEGORIZED;
		}

		return $changes;
	}

	public function prepare_item_for_response( $template, $request ) {

		if ( ! property_exists( $template, 'area' ) ) {
			$type_terms = get_the_terms( $template->wp_id, 'wp_template_part_area' );
			if ( ! is_wp_error( $type_terms ) && false !== $type_terms ) {
				$template->area = $type_terms[0]->name;
			} else {
				$template->area = WP_TEMPLATE_PART_AREA_UNCATEGORIZED;
			}
		}

		$result = array(
			'id'             => $template->id,
			'theme'          => $template->theme,
			'content'        => array( 'raw' => $template->content ),
			'slug'           => $template->slug,
			'source'         => $template->source,
			'type'           => $template->type,
			'area'           => $template->area,
			'description'    => $template->description,
			'title'          => array(
				'raw'      => $template->title,
				'rendered' => $template->title,
			),
			'status'         => $template->status,
			'wp_id'          => $template->wp_id,
			'has_theme_file' => $template->has_theme_file,
		);

			$result['area'] = $template->area;

		$result = $this->add_additional_fields_to_object( $result, $request );

		$response = rest_ensure_response( $result );
		$links    = $this->prepare_links( $template->id );
		$response->add_links( $links );
		if ( ! empty( $links['self']['href'] ) ) {
			$actions = $this->get_available_actions();
			$self    = $links['self']['href'];
			foreach ( $actions as $rel ) {
				$response->add_link( $rel, $self );
			}
		}

		return $response;
	}
}

if ( class_exists( '\Gutenberg_REST_Templates_Controller' ) ) {
	class KubioRestTemplatePartController extends \Gutenberg_REST_Templates_Controller {
		use KubioRestTemplatePartsAreaSupportTrait;
	}

} else {
	class KubioRestTemplatePartController extends \WP_REST_Templates_Controller {
		use KubioRestTemplatePartsAreaSupportTrait;
	}
}
