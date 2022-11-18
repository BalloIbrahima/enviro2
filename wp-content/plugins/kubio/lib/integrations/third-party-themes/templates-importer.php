<?php

use Kubio\Core\Importer;

class KubioThirdPartyThemeBlockImporter {

	public static function mapBlocksTemplateParts( $content ) {

		$blocks         = parse_blocks( $content );
		$updated_blocks = kubio_blocks_update_template_parts_theme(
			$blocks,
			get_stylesheet()
		);

		return kubio_serialize_blocks( $updated_blocks );

		return $content;
	}

	private static function importTemplates( $mapped_templates ) {
		$files     = glob(
			KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH .
				'/default-blog/templates/*.html'
		);
		$templates = array();

		foreach ( $files as $template ) {
			$slug               = preg_replace(
				'#(.*)/templates/(.*).html#',
				'$2',
				wp_normalize_path( $template )
			);
			$templates[ $slug ] = $template;
		}

		foreach ( $mapped_templates as $slug => $template_key ) {
			$content = file_get_contents( $templates[ $template_key ] );
			$result  = Importer::createTemplate(
				$slug,
				static::mapBlocksTemplateParts( $content ),
				true,
				'kubio'
			);

			if ( is_wp_error( $result ) ) {
				break;
				return $result;
			}
		}

		return true;
	}

	private static function importTemplateParts() {
		$files     = glob(
			KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH .
				'/default-blog/parts/*.html'
		);
		$templates = array();

		foreach ( $files as $template ) {
			$slug               = preg_replace(
				'#(.*)/parts/(.*).html#',
				'$2',
				wp_normalize_path( $template )
			);
			$templates[ $slug ] = $template;
		}

		foreach ( $templates as $slug => $file ) {
			$content = file_get_contents( $file );
			$result  = Importer::createTemplatePart(
				$slug,
				static::mapBlocksTemplateParts( $content ),
				false,
				'kubio'
			);

			if ( is_wp_error( $result ) ) {
				break;
				return $result;
			}
		}

		return true;
	}

	private static function importContent( $templates ) {
		$mapped_templates = static::mapTemplatesToImportSlug( $templates );

		$result = static::importTemplates( $mapped_templates );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = static::importTemplateParts();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	private static function importFSETheme() {
		$block_templates            = get_block_templates( array(), 'wp_template' );
		$block_templates_to_replace = array();
		$blog_templates_slugs       = array(
			'home',
			'index',
			'single',
			'search',
			'archive',
			'singular',
		);

		foreach ( $block_templates as $template ) {
			if ( in_array( $template->slug, $blog_templates_slugs ) ) {
				$block_templates_to_replace[] = $template->slug;
			}
		}

		return static::importContent( $block_templates_to_replace );
	}

	private static function importClassicTheme() {
		$theme     = wp_get_theme();
		$files     = (array) $theme->get_files( 'php', 0, true );
		$templates = array_keys( $files );

		$block_templates_to_install   = array(
			'index',
			'single',
			'search',
			'archive',
		);
		$other_blog_related_templates = array(
			'home',
			'singular',
		);

		foreach ( $templates as $template ) {
			$template_slug = preg_replace(
				'#(.*).php#',
				'$1',
				wp_normalize_path( $template )
			);

			if ( in_array( $template_slug, $other_blog_related_templates ) ) {
				$block_templates_to_install[] = $template_slug;
			}
		}

		 return static::importContent( $block_templates_to_install );
	}

	public static function mapTemplatesToImportSlug( $templates ) {
		$result                    = array();
		$index_fallback_templates  = array( 'home', 'index', 'archive' );
		$single_fallback_tempaltes = array( 'singular' );

		foreach ( $templates as $template ) {
			$result[ $template ] = $template;
			if ( in_array( $template, $index_fallback_templates ) ) {
				$result[ $template ] = 'index';
			}

			if ( in_array( $template, $single_fallback_tempaltes ) ) {
				$result[ $template ] = 'single';
			}
		}

		return $result;
	}

	public static function import() {

		$result = null;

		$is_fse = is_readable( get_template_directory() . '/templates/index.html' ) ||
		is_readable( get_stylesheet_directory() . '/templates/index.html' );

		if ( $is_fse ) {
			$result = static::importFSETheme();
		} else {
			$result = static::importClassicTheme();
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true );
	}

	public static function registerRestRoute() {
		$namespace = 'kubio/v1';
		register_rest_route(
			$namespace,
			'/3rd_party_themes/import_blog',
			array(
				'methods'             => 'GET',
				'callback'            => array( KubioThirdPartyThemeBlockImporter::class, 'import' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);
	}
}

add_action(
	'rest_api_init',
	array(
		KubioThirdPartyThemeBlockImporter::class,
		'registerRestRoute',
	)
);



function kubio_new_template_get_appropriate_content( $data ) {
	if ( $data['post_type'] !== 'wp_template' ) {
		return $data;
	}

	if ( $data['post_status'] !== 'publish' ) {
		return $data;
	}

	if ( $data['post_content'] === '__KUBIO_REPLACE_WITH_APPROPRIATE_CONTENT__' ) {
		$template         = $data['post_name'];
		$mapped_templates = KubioThirdPartyThemeBlockImporter::mapTemplatesToImportSlug( array( $template ) );
		$template         = $mapped_templates[ $template ];

		$data['post_content'] = '';

		$file = null;

		if ( file_exists( KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . "/default-blog/templates/{$template}.html" ) ) {
			$file = KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . "/default-blog/templates/{$template}.html";
		}

		if ( file_exists( KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . "/primary/templates/{$template}.html" ) ) {
			$file = KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . "/primary/templates/{$template}.html";
		}

		if ( $file ) {
			$data['post_content'] = KubioThirdPartyThemeBlockImporter::mapBlocksTemplateParts( file_get_contents( $file ) );
		}

		// import parts if needed

		// header
		if ( $template === 'front-page' ) {
			$file   = KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . '/primary/parts/kubio-front-header.html';
			$header = file_get_contents( $file );
			Importer::createTemplatePart(
				'kubio-front-header',
				KubioThirdPartyThemeBlockImporter::mapBlocksTemplateParts( $header ),
				false,
				'kubio'
			);
		} else {
			$header = file_get_contents( KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . '/parts/kubio-header.html' );
			Importer::createTemplatePart(
				'kubio-header',
				KubioThirdPartyThemeBlockImporter::mapBlocksTemplateParts( $header ),
				false,
				'kubio'
			);

		}

		// header
		$footer = file_get_contents( KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . '/parts/kubio-footer.html' );
			Importer::createTemplatePart(
				'kubio-header',
				KubioThirdPartyThemeBlockImporter::mapBlocksTemplateParts( $footer ),
				false,
				'kubio'
			);

		// sidebar
		if ( in_array( $template, array( 'index', 'single', 'search' ), true ) ) {
			$sidebar = file_get_contents( KUBIO_3RD_PARTY_DEFAULT_TEMPLATES_PATH . '/default-blog/parts/kubio-blog-sidebar.html' );
			Importer::createTemplatePart(
				'kubio-blog-sidebar',
				KubioThirdPartyThemeBlockImporter::mapBlocksTemplateParts( $sidebar ),
				false,
				'kubio'
			);
		}
	}

	return $data;
}

add_filter(
	'wp_insert_post_data',
	'kubio_new_template_get_appropriate_content',
	10,
	1
);
