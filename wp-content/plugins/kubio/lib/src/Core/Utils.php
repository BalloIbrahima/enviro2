<?php


namespace Kubio\Core;

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Config;
use Kubio\Env;
use function json_encode;
use \WP_Error;

class Utils {


	private static $execute_start_time;

	public static function mapHideClassesByMedia(
		$hiddenByMedia,
		$negateValue = false
	) {
		$mapHideClassesByMedia = array();
		foreach ( $hiddenByMedia as $media => $isHidden ) {
			if ( $negateValue ) {
				$isHidden = ! $isHidden;
			}
			if ( $isHidden ) {
				array_push( $mapHideClassesByMedia, "kubio-hide-on-$media" );
			}
		}

		return $mapHideClassesByMedia;
	}

	public static function useJSComponentProps( $name, $settings = array() ) {
		$prefix = Config::$name;

		return array(
			"data-${prefix}-component" => $name,
			"data-${prefix}-settings"  => json_encode( $settings ),
		);
	}

	public static function getLinkAttributes( $linkObject ) {
		$defaultValue     = array(
			'value'         => '',
			'typeOpenLink'  => 'sameWindow',
			'noFollow'      => false,
			'lightboxMedia' => '',
		);
		$mergedLinkObject = LodashBasic::merge( array(), $defaultValue, $linkObject );
		$linkAttributes   = array(
			'href'                 => null,
			'target'               => null,
			'rel'                  => null,
			'data-kubio-component' => null,
		);

		if ( $mergedLinkObject ) {
			if ( $mergedLinkObject['value'] ) {
				$linkAttributes['href'] = $mergedLinkObject['value'];
			}
			$linkType = LodashBasic::get( $mergedLinkObject, 'typeOpenLink', '' );
			if ( $linkType === 'newWindow' ) {
				$linkAttributes['target'] = '_blank';
			}

			if ( $linkType === 'lightbox' ) {
				$lightboxType = $mergedLinkObject['lightboxMedia'];
				if ( $lightboxType === '' ) {
					$lightboxType = null;
				}
				$linkAttributes['data-default-type'] = $lightboxType;
				$linkAttributes['data-fancybox']     = rand() . '';
			}
			if ( $mergedLinkObject['noFollow'] ) {
				$linkAttributes['rel'] = 'nofollow';
			}
		}

		return $linkAttributes;
	}

	public static function shortcodeDecode( $data ) {
		return urldecode( base64_decode( $data ) );
	}

	public static function getDefaultAssetsUrl( $url ) {
		$staticUrl = kubio_url( 'static/default-assets' );

		return $staticUrl . '/' . ltrim( $url, '/' );
	}

	public static function canEdit() {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'edit_posts' );
	}

	public static function getEmptyShortcodePlaceholder() {
		if ( is_user_logged_in() ) {
			return static::getFrontendPlaceHolder(
				sprintf(
					'%s<br/><div class="kubio-frontent-placeholder--small">%s</div>',
					__( 'Shortcode is empty.', 'kubio' ),
					__( 'Edit this page to insert a shortcode or delete this block.', 'kubio' )
				)
			);
		} else {
			return '';
		}

	}

	//the production build does not include the patterns folder, we can use this to determine if the build is dev or prod
	public static function isProduction() {
		$isProd = ! file_exists( KUBIO_ROOT_DIR . '/static/patterns/content-converted.json' );

		return $isProd;
	}

	public static function getFrontendPlaceHolder( $message, $options = array() ) {

		$options = array_merge(
			array(
				'info'      => true,
				'title'     => __( 'Kubio info', 'kubio' ),
				'if_logged' => true,
			),
			$options
		);

		if ( $options['if_logged'] ) {
			if ( ! is_user_logged_in() ) {
				return;
			}
		}

		if ( is_callable( $message ) ) {
			$message = call_user_func( $message );
		}

		$info = '';
		if ( $options['info'] ) {
			$info = sprintf(
				'<div class="kubio-frontent-placeholder--info">' .
				'	<div class="kubio-frontent-placeholder--logo">%s</div>' .
				'   <div class="kubio-frontent-placeholder--title">%s</div>' .
				'</div>',
				wp_kses_post( KUBIO_LOGO_SVG ),
				$options['title']
			);
		}

		return sprintf( '<div class="kubio-frontent-placeholder"><div>%s</div><div>%s</div></div>', $info, $message );
	}

	public static function kubioCacheGet( $name, $default = null ) {

		$kubio_cache = isset( $GLOBALS['__kubio_plugin_cache__'] ) ? $GLOBALS['__kubio_plugin_cache__'] : array();
		$value       = $default;

		if ( self::kubioCacheHas( $name ) ) {
			$value = $kubio_cache[ $name ];
		}

		return $value;

	}

	public static function kubioCacheHas( $name ) {
		$kubio_cache = isset( $GLOBALS['__kubio_plugin_cache__'] ) ? $GLOBALS['__kubio_plugin_cache__'] : array();

		return array_key_exists( $name, $kubio_cache );
	}

	public static function kubioCacheSet( $name, $value ) {
		$kubio_cache          = isset( $GLOBALS['__kubio_plugin_cache__'] ) ? $GLOBALS['__kubio_plugin_cache__'] : array();
		$kubio_cache[ $name ] = $value;

		$GLOBALS['__kubio_plugin_cache__'] = $kubio_cache;

	}

	/**
	 * Remove empty branches from array
	 *
	 * @param array $array the array to walk
	 *
	 * @return array
	 */
	public static function arrayRecursiveRemoveEmptyBranches( array &$array ) {
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				$array[ $key ] = static::arrayRecursiveRemoveEmptyBranches( $value );

				if ( empty( $value ) ) {
					unset( $array[ $key ] );
				}
			}
		}

		return $array;
	}

	public static function walkBlocks( &$blocks, $callback ) {
		array_walk(
			$blocks,
			function ( &$block ) use ( $callback ) {
				if ( isset( $block['blockName'] ) ) {
					$callback( $block );
				}
				if ( isset( $block['innerBlocks'] ) ) {
					static::walkBlocks( $block['innerBlocks'], $callback );
				}
			}
		);
	}

	public static function kses( $text, $allowed_protocols = array() ) {

		static $allowed_html;

		if ( ! $allowed_html ) {
			$allowed_html = wp_kses_allowed_html( 'post' );
		}

		// fix the issue with rgb / rgba colors in style atts

		$rgbRegex = '#rgb\(((?:\s*\d+\s*,){2}\s*[\d]+)\)#i';
		$text     = preg_replace( $rgbRegex, 'rgb__$1__rgb', $text );

		$rgbaRegex = '#rgba\(((\s*\d+\s*,){3}[\d\.]+)\)#i';
		$text      = preg_replace( $rgbaRegex, 'rgba__$1__rgb', $text );

		$text = wp_kses( $text, $allowed_html, $allowed_protocols );

		$text = str_replace( 'rgba__', 'rgba(', $text );
		$text = str_replace( 'rgb__', 'rgb(', $text );
		$text = str_replace( '__rgb', ')', $text );

		return $text;
	}

	/**
	 * Compares version string to WP base version ( e.g. X.Y.Z without looking for -beta* -RC* suffixes )
	 *
	 * @param string $compare_to - semver version number
	 * @param string $operator - version_compare operator
	 * @return void
	 */
	public static function wpVersionCompare( $compare_to, $operator ) {
		global $wp_version;
		$version_parts = sscanf( $wp_version, '%d.%d.%d' );
		$version       = array();

		foreach ( $version_parts as $version_part ) {
			if ( $version_part !== null ) {
				$version[] = $version_part;
			}
		}

		$version = implode( '.', $version );
		return version_compare( $version, $compare_to, $operator );
	}

	public static function ksesSVG( $svg_content ) {
		$allowed_html = wp_kses_allowed_html( 'post' );
		return wp_kses( $svg_content, $allowed_html );
	}

	/**
	 * Check if the execution time has enough remaining seconds
	 *
	 * @param integer $compare_to_time - necessary time in seconds
	 * @return boolean
	 */
	public static function hasEnoughRemainingTime( $compare_to_time = 10 ) {

		if ( ! static::$execute_start_time ) {
			static::$execute_start_time = intval( Arr::get( $_SERVER, 'REQUEST_TIME_FLOAT', time() ) );
		}

		$diff = time() - static::$execute_start_time;

		$max_exec_time = @ini_get( 'max_execution_time' );

		// assume 30 seconds if not available
		if ( ! $max_exec_time ) {
			$max_exec_time = 30;
		}

		return ( intval( $max_exec_time ) - $diff >= $compare_to_time );
	}

	/**
	 * Check if current WordPress installation validates plugin requirements
	 *
	 * @return boolean|\WP_Error
	 */
	public static function validateRequirements() {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$plugin_headers = get_plugin_data( KUBIO_ENTRY_FILE );
		$required_wp    = ! empty( $plugin_headers['RequiresWP'] ) ? $plugin_headers['RequiresWP'] : false;
		$required_php   = ! empty( $plugin_headers['RequiresPHP'] ) ? $plugin_headers['RequiresPHP'] : false;

		if ( defined( 'KUBIO_MINIMUM_WP_VERSION' ) && KUBIO_MINIMUM_WP_VERSION ) {
			$required_wp = KUBIO_MINIMUM_WP_VERSION;
		}

		$compatible_wp  = $required_wp ? Utils::wpVersionCompare( $required_wp, '>=' ) : true;
		$compatible_php = version_compare( phpversion(), $required_php, '>=' );

		$php_update_message = '</p><p>' . sprintf(
			/* translators: %s: URL to Update PHP page. */
			__( '<a href="%s">Learn more about updating PHP</a>' ),
			esc_url( wp_get_update_php_url() )
		);

		$update_wp_core = sprintf(
			/* translators: %s: URL to Update PHP page. */
			__( '<a href="%s">Update WordPress now!</a>', 'kubio' ),
			esc_url( admin_url( 'update-core.php' ) )
		);

		if ( ! $compatible_wp && ! $compatible_php ) {
			return new WP_Error(
				'plugin_wp_php_incompatible',
				'<p>' . sprintf(
					/* translators: 1: Current WordPress version, 2: Current PHP version, 3: Plugin name, 4: Required WordPress version, 5: Required PHP version. */
					_x( '<strong>Error:</strong> Current versions of WordPress (%1$s) and PHP (%2$s) do not meet minimum requirements for %3$s. The plugin requires WordPress %4$s and PHP %5$s.', 'kubio' ),
					get_bloginfo( 'version' ),
					phpversion(),
					$plugin_headers['Name'],
					$required_wp,
					$required_php
				) . $php_update_message . '<br/>' . $update_wp_core . '</p>'
			);
		} elseif ( ! $compatible_php ) {
			return new WP_Error(
				'plugin_php_incompatible',
				'<p>' . sprintf(
					/* translators: 1: Current PHP version, 2: Plugin name, 3: Required PHP version. */
					_x( '<strong>Error:</strong> Current PHP version (%1$s) does not meet minimum requirements for %2$s. The plugin requires PHP %3$s.', 'kubio' ),
					phpversion(),
					$plugin_headers['Name'],
					$required_php
				) . $php_update_message . '</p>'
			);
		} elseif ( ! $compatible_wp ) {
			return new WP_Error(
				'plugin_wp_incompatible',
				'<p>' . sprintf(
					/* translators: 1: Current WordPress version, 2: Plugin name, 3: Required WordPress version. */
					_x( '<strong>Error:</strong> Current WordPress version (%1$s) does not meet minimum requirements for %2$s. The plugin requires WordPress %3$s.', 'kubio' ),
					get_bloginfo( 'version' ),
					$plugin_headers['Name'],
					$required_wp
				) . '&nbsp;' . $update_wp_core . '</p>'
			);
		}

	}

	public static function getPluginVersions( $skip_current = false ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$plugin_headers = get_plugin_data( KUBIO_ENTRY_FILE );
		$version        = ! empty( $plugin_headers['Version'] ) ? $plugin_headers['Version'] : false;
		$name           = ! empty( $plugin_headers['Name'] ) ? $plugin_headers['Name'] : false;
		$url            = apply_filters(
			'kubio/previous-versions/url',
			sprintf( 'https://api.wordpress.org/plugins/info/1.0/%s.json', KUBIO_SLUG )
		);

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response = wp_remote_retrieve_body( $response );

		if ( is_serialized( $response ) ) {
			$response = maybe_unserialize( $response );
		} else {
			$response = json_decode( $response );
		}

		if ( ! is_object( $response ) ) {
			return null;
		}
		if ( ! isset( $response->versions ) ) {
			return null;
		}

		$versions = array();
		foreach ( $response->versions as $key => $value ) {

			$version = is_object( $value ) ? $value->version : $key;

			if ( $version === 'trunk' ) {
				continue;
			}

			if ( $skip_current && $version === \KUBIO_VERSION ) {
				continue;
			}

			$versions[ $version ] = array(
				'version'       => $version,
				'named_version' => sprintf( '%s v%s', $name, $version ),
				'url'           => is_object( $value ) ? $value->file : $value,
			);
		}

		return $versions;
	}

	public static function getCloudUrl( $url = null ) {
		$cloudRootUrl = KUBIO_CLOUD_URL;

		if ( ! $url ) {
			return $cloudRootUrl;
		}
		return "$cloudRootUrl/$url";
	}

	public static function isDebug() {
		return defined( 'KUBIO_DEBUG' ) && KUBIO_DEBUG;
	}

	public static function isCLI() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * return and unique autoinc id based on prefix
	 *
	 * @param  string $prefix
	 * @return string
	 */
	public static function uniqueId( $prefix = '' ) {
		static $state;

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		if ( ! isset( $state[ $prefix ] ) ) {
			$state[ $prefix ] = 0;
		}

		$id = $state[ $prefix ]++;

		return $id;
	}
}
