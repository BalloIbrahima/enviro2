<?php


namespace Kubio\Core\Blocks;


class TemplatePartBlockBase extends BlockBase {
	const CONTAINER = 'container';

	public function mapPropsToElements() {
		$html_tag = esc_attr( $this->getAttribute( 'tagName', 'div' ) );

		return array(
			self::CONTAINER => array(
				'tag'       => $html_tag,
				'innerHTML' => $this->getContent(),
			),
		);
	}

	public function getContent() {
		$content = $this->getTemplateContent();

		if ( is_null( $content ) ) {
			return __( 'Template Part Not Found', 'kubio' );
		}

		// Run through the actions that are typically taken on the_content.
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		$content = convert_smilies( $content );

		//corrupts the html for link wrappers. It adds extra paragraphs and the html tag structure get
		//$content = wpautop( $content );

		$content = shortcode_unautop( $content );
		if ( function_exists( 'wp_filter_content_tags' ) ) {
			$content = wp_filter_content_tags( $content );
		} else {
			$content = wp_make_content_images_responsive( $content );
		}
		$content = do_shortcode( $content );

		return str_replace( ']]>', ']]&gt;', $content );
	}


	private function getTemplateContent() {
		$content = null;
		$post_id = $this->getAttribute( 'postId' );
		$theme   = $this->getAttribute( 'theme' );
		$slug    = $this->getAttribute( 'slug' );

		$post = null;
		if ( ! empty( $post_id ) && get_post_status( $post_id ) && ( $post = get_post( $post_id ) ) ) {
			$content = $post->post_content;
		} else {
			if ( basename( wp_get_theme()->get_stylesheet() ) === $theme ) {

				$template_part_query = new \WP_Query(
					array(
						'post_type'      => 'wp_template_part',
						'post_status'    => 'publish',
						'post_name__in'  => array( $slug ),
						'tax_query'      => array(
							array(
								'taxonomy' => 'wp_theme',
								'field'    => 'slug',
								'terms'    => $theme,
							),
						),
						'posts_per_page' => 1,
						'no_found_rows'  => true,
					)
				);

				$template_part_post = $template_part_query->have_posts() ? $template_part_query->next_post() : null;
				if ( $template_part_post ) {
					// A published post might already exist if this template part was customized elsewhere
					// or if it's part of a customized template.
					$content = $template_part_post->post_content;
				} else {
					// Else, if the template part was provided by the active theme,
					// render the corresponding file content.
					$template_part_file_paths = array(
						get_stylesheet_directory() . '/full-site-editing/block-template-parts/' . $slug . '.html',
						get_stylesheet_directory() . '/block-template-parts/' . $slug . '.html',
					);

					foreach ( $template_part_file_paths as $template_part_file_path ) {
						if ( 0 === validate_file( $slug ) && file_exists( $template_part_file_path ) ) {
							$content = file_get_contents( $template_part_file_path );
							break;
						}
					}
				}
			}
		}

		return $content;
	}
}
