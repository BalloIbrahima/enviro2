<?php

// phpcs:disable WordPress.Security.EscapeOutput
// the escaping rule is disabled as the WXR generation require the raw database content

namespace Kubio\DemoSites;

class WXRExporter {

	const WXR_VERSION = '1.2';

	public static function export( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'content'    => 'all',
			'author'     => false,
			'category'   => false,
			'start_date' => false,
			'end_date'   => false,
			'status'     => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		$post_types = get_post_types( array( 'can_export' => true ) );

		// remove colibri post types
		foreach ( $post_types as $key => $post_type ) {
			if ( strpos( $post_type, 'extb_post_' ) === 0 ) {
				unset( $post_types[ $key ] );
			}
		}

		$esses = array_fill( 0, count( $post_types ), '%s' );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$where = $wpdb->prepare( "{$wpdb->posts}.post_type IN (" . implode( ',', $esses ) . ')', $post_types );

		if ( $args['status'] && ( 'post' === $args['content'] || 'page' === $args['content'] ) ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_status = %s", $args['status'] );
		} else {
			$where .= " AND {$wpdb->posts}.post_status != 'auto-draft'";
		}

		$join = '';
		if ( $args['category'] && 'post' === $args['content'] ) {
			$term = term_exists( $args['category'], 'category' );
			if ( $term ) {
				$join   = "INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
				$where .= $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id = %d", $term['term_taxonomy_id'] );
			}
		}

		if ( in_array( $args['content'], array( 'post', 'page', 'attachment' ), true ) ) {
			if ( $args['author'] ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $args['author'] );
			}

			if ( $args['start_date'] ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date >= %s", gmdate( 'Y-m-d', strtotime( $args['start_date'] ) ) );
			}

			if ( $args['end_date'] ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date < %s", gmdate( 'Y-m-d', strtotime( '+1 month', strtotime( $args['end_date'] ) ) ) );
			}
		}

		// Grab a snapshot of post IDs, just in case it changes during the export.
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" );

		$cats  = array();
		$tags  = array();
		$terms = array();
		if ( isset( $term ) && $term ) {
			$cat  = get_term( $term['term_id'], 'category' );
			$cats = array( $cat->term_id => $cat );
			unset( $term, $cat );
		} elseif ( 'all' === $args['content'] ) {
			$categories = (array) get_categories( array( 'get' => 'all' ) );
			$tags       = (array) get_tags( array( 'get' => 'all' ) );

			$custom_taxonomies = get_taxonomies( array( '_builtin' => false ) );
			$custom_terms      = (array) get_terms(
				array(
					'taxonomy' => $custom_taxonomies,
					'get'      => 'all',
				)
			);

			// Put categories in order with no child going before its parent.
			while ( $cat = array_shift( $categories ) ) {
				if ( ! $cat->parent || isset( $cats[ $cat->parent ] ) ) {
					$cats[ $cat->term_id ] = $cat;
				} else {
					$categories[] = $cat;
				}
			}

			// Put terms in order with no child going before its parent.
			while ( $t = array_shift( $custom_terms ) ) {
				if ( ! $t->parent || isset( $terms[ $t->parent ] ) ) {
					$terms[ $t->term_id ] = $t;
				} else {
					$custom_terms[] = $t;
				}
			}

			unset( $categories, $custom_taxonomies, $custom_terms );
		}

		add_filter( 'wxr_export_skip_postmeta', array( WXRExporter::class, 'skipPostMeta' ), 10, 2 );

		$content = static::getContent( $post_ids, $cats, $tags, $terms );

		remove_filter( 'wxr_export_skip_postmeta', array( WXRExporter::class, 'skipPostMeta' ) );

		return trim( $content );
	}

	private static function getContent( $post_ids, $cats, $tags, $terms ) {
		global $wpdb;
		ob_start(); ?>

		<?php the_generator( 'export' ); ?>
		<rss version="2.0"
			 xmlns:excerpt="http://wordpress.org/export/<?php echo WXRExporter::WXR_VERSION; ?>/excerpt/"
			 xmlns:content="http://purl.org/rss/1.0/modules/content/"
			 xmlns:dc="http://purl.org/dc/elements/1.1/"
			 xmlns:wp="http://wordpress.org/export/<?php echo WXRExporter::WXR_VERSION; ?>/"
		>

			<channel>
				<title><?php bloginfo_rss( 'name' ); ?></title>
				<link><?php bloginfo_rss( 'url' ); ?></link>
				<description><?php bloginfo_rss( 'description' ); ?></description>
				<pubDate><?php echo gmdate( 'D, d M Y H:i:s +0000' ); ?></pubDate>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<wp:wxr_version><?php echo WXRExporter::WXR_VERSION; ?></wp:wxr_version>
				<wp:base_site_url><?php echo static::getSiteURL(); ?></wp:base_site_url>
				<wp:base_blog_url><?php bloginfo_rss( 'url' ); ?></wp:base_blog_url>

				<?php static::printAuthorsList( $post_ids ); ?>

				<?php foreach ( $cats as $c ) : ?>
					<wp:category>
						<wp:term_id><?php echo (int) $c->term_id; ?></wp:term_id>
						<wp:category_nicename><?php echo static::getCData( $c->slug ); ?></wp:category_nicename>
						<wp:category_parent><?php echo static::getCData( $c->parent ? $cats[ $c->parent ]->slug : '' ); ?></wp:category_parent>
						<?php
						static::printCatName( $c );
						static::printCatDescription( $c );
						static::printTermMeta( $c );
						?>
					</wp:category>
				<?php endforeach; ?>
				<?php foreach ( $tags as $t ) : ?>
					<wp:tag>
						<wp:term_id><?php echo (int) $t->term_id; ?></wp:term_id>
						<wp:tag_slug><?php echo static::getCData( $t->slug ); ?></wp:tag_slug>
						<?php
						static::printTagName( $t );
						static::printTagDescription( $t );
						static::printTermMeta( $t );
						?>
					</wp:tag>
				<?php endforeach; ?>
				<?php foreach ( $terms as $t ) : ?>
					<wp:term>
						<wp:term_id><?php echo (int) $t->term_id; ?></wp:term_id>
						<wp:term_taxonomy><?php echo static::getCData( $t->taxonomy ); ?></wp:term_taxonomy>
						<wp:term_slug><?php echo static::getCData( $t->slug ); ?></wp:term_slug>
						<wp:term_parent><?php echo static::getCData( $t->parent ? $terms[ $t->parent ]->slug : '' ); ?></wp:term_parent>
						<?php
						static::printTermName( $t );
						static::printTermDescription( $t );
						static::printTermMeta( $t );
						?>
					</wp:term>
				<?php endforeach; ?>
				<?php static::printNavTerms(); ?>

				<?php
				/** This action is documented in wp-includes/feed-rss2.php */
				do_action( 'rss2_head' );
				?>

				<?php
				if ( $post_ids ) {
					/**
					 * @global WP_Query $wp_query WordPress Query object.
					 */
					global $wp_query;

					// Fake being in the loop.
					$wp_query->in_the_loop = true;

					// Fetch 20 posts at a time rather than loading the entire table into memory.
					while ( $next_posts = array_splice( $post_ids, 0, 20 ) ) {
						$where = 'WHERE ID IN (' . implode( ',', $next_posts ) . ')';
						$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} $where" );

						// Begin Loop.
						foreach ( $posts as $post ) {
							setup_postdata( $post );

							/**
							 * Filters the post title used for WXR exports.
							 *
							 * @param string $post_title Title of the current post.
							 *
							 * @since 5.7.0
							 *
							 */
							$title = static::getCData( apply_filters( 'the_title_export', $post->post_title ) );

							/**
							 * Filters the post content used for WXR exports.
							 *
							 * @param string $post_content Content of the current post.
							 *
							 * @since 2.5.0
							 *
							 */
							$content = static::getCData( apply_filters( 'the_content_export', $post->post_content ) );

							/**
							 * Filters the post excerpt used for WXR exports.
							 *
							 * @param string $post_excerpt Excerpt for the current post.
							 *
							 * @since 2.6.0
							 *
							 */
							$excerpt = static::getCData( apply_filters( 'the_excerpt_export', $post->post_excerpt ) );

							$is_sticky = is_sticky( $post->ID ) ? 1 : 0;
							?>
							<item>
								<title><?php echo $title; ?></title>
								<link><?php the_permalink_rss(); ?></link>
								<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
								<dc:creator><?php echo static::getCData( get_the_author_meta( 'login' ) ); ?></dc:creator>
								<guid isPermaLink="false"><?php the_guid(); ?></guid>
								<description></description>
								<content:encoded><?php echo $content; ?></content:encoded>
								<excerpt:encoded><?php echo $excerpt; ?></excerpt:encoded>
								<wp:post_id><?php echo (int) $post->ID; ?></wp:post_id>
								<wp:post_date><?php echo static::getCData( $post->post_date ); ?></wp:post_date>
								<wp:post_date_gmt><?php echo static::getCData( $post->post_date_gmt ); ?></wp:post_date_gmt>
								<wp:post_modified><?php echo static::getCData( $post->post_modified ); ?></wp:post_modified>
								<wp:post_modified_gmt><?php echo static::getCData( $post->post_modified_gmt ); ?></wp:post_modified_gmt>
								<wp:comment_status><?php echo static::getCData( $post->comment_status ); ?></wp:comment_status>
								<wp:ping_status><?php echo static::getCData( $post->ping_status ); ?></wp:ping_status>
								<wp:post_name><?php echo static::getCData( $post->post_name ); ?></wp:post_name>
								<wp:status><?php echo static::getCData( $post->post_status ); ?></wp:status>
								<wp:post_parent><?php echo (int) $post->post_parent; ?></wp:post_parent>
								<wp:menu_order><?php echo (int) $post->menu_order; ?></wp:menu_order>
								<wp:post_type><?php echo static::getCData( $post->post_type ); ?></wp:post_type>
								<wp:post_password><?php echo static::getCData( $post->post_password ); ?></wp:post_password>
								<wp:is_sticky><?php echo (int) $is_sticky; ?></wp:is_sticky>
								<?php if ( 'attachment' === $post->post_type ) : ?>
									<wp:attachment_url><?php echo static::getCData( wp_get_attachment_url( $post->ID ) ); ?></wp:attachment_url>
								<?php endif; ?>
								<?php static::printPostTaxonomy( $post ); ?>
								<?php
								$postmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID ) );
								foreach ( $postmeta as $meta ) :
									/**
									 * Filters whether to selectively skip post meta used for WXR exports.
									 *
									 * Returning a truthy value from the filter will skip the current meta
									 * object from being exported.
									 *
									 * @param bool $skip Whether to skip the current post meta. Default false.
									 * @param string $meta_key Current meta key.
									 * @param object $meta Current meta object.
									 *
									 * @since 3.3.0
									 *
									 */
									if ( ! apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) ) {
										?>
										<wp:postmeta>
											<wp:meta_key><?php echo static::getCData( $meta->meta_key ); ?></wp:meta_key>
											<wp:meta_value><?php echo static::getCData( $meta->meta_value ); ?></wp:meta_value>
										</wp:postmeta>
										<?php
									}

								endforeach;

								$_comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved <> 'spam'", $post->ID ) );
								$comments  = array_map( 'get_comment', $_comments );
								foreach ( $comments as $c ) :
									?>
									<wp:comment>
										<wp:comment_id><?php echo (int) $c->comment_ID; ?></wp:comment_id>
										<wp:comment_author><?php echo static::getCData( $c->comment_author ); ?></wp:comment_author>
										<wp:comment_author_email><?php echo static::getCData( $c->comment_author_email ); ?></wp:comment_author_email>
										<wp:comment_author_url><?php echo esc_url_raw( $c->comment_author_url ); ?></wp:comment_author_url>
										<wp:comment_author_IP><?php echo static::getCData( $c->comment_author_IP ); ?></wp:comment_author_IP>
										<wp:comment_date><?php echo static::getCData( $c->comment_date ); ?></wp:comment_date>
										<wp:comment_date_gmt><?php echo static::getCData( $c->comment_date_gmt ); ?></wp:comment_date_gmt>
										<wp:comment_content><?php echo static::getCData( $c->comment_content ); ?></wp:comment_content>
										<wp:comment_approved><?php echo static::getCData( $c->comment_approved ); ?></wp:comment_approved>
										<wp:comment_type><?php echo static::getCData( $c->comment_type ); ?></wp:comment_type>
										<wp:comment_parent><?php echo (int) $c->comment_parent; ?></wp:comment_parent>
										<wp:comment_user_id><?php echo (int) $c->user_id; ?></wp:comment_user_id>
										<?php
										$c_meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $c->comment_ID ) );
										foreach ( $c_meta as $meta ) :
											/**
											 * Filters whether to selectively skip comment meta used for WXR exports.
											 *
											 * Returning a truthy value from the filter will skip the current meta
											 * object from being exported.
											 *
											 * @param bool $skip Whether to skip the current comment meta. Default false.
											 * @param string $meta_key Current meta key.
											 * @param object $meta Current meta object.
											 *
											 * @since 4.0.0
											 *
											 */
											if ( apply_filters( 'static::wxr_export_skip_commentmeta', false, $meta->meta_key, $meta ) ) {
												continue;
											}
											?>
											<wp:commentmeta>
												<wp:meta_key><?php echo static::getCData( $meta->meta_key ); ?></wp:meta_key>
												<wp:meta_value><?php echo static::getCData( $meta->meta_value ); ?></wp:meta_value>
											</wp:commentmeta>
										<?php endforeach; ?>
									</wp:comment>
								<?php endforeach; ?>
							</item>
							<?php
						}
					}
				}
				?>
			</channel>
		</rss>
		<?php

		return ob_get_clean();
	}

	/**
	 * Return the URL of the site
	 *
	 * @return string Site URL.
	 * @since 2.5.0
	 *
	 */
	public static function getSiteURL() {
		if ( is_multisite() ) {
			// Multisite: the base URL.
			return network_home_url();
		} else {
			// WordPress (single site): the blog URL.
			return get_bloginfo_rss( 'url' );
		}
	}

	/**
	 * Output list of authors with posts
	 *
	 * @param int[] $post_ids Optional. Array of post IDs to filter the query by.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @since 3.1.0
	 *
	 */
	private static function printAuthorsList( array $post_ids = null ) {
		global $wpdb;

		if ( ! empty( $post_ids ) ) {
			$post_ids = array_map( 'absint', $post_ids );
			$and      = 'AND ID IN ( ' . implode( ', ', $post_ids ) . ')';
		} else {
			$and = '';
		}

		$authors = array();
		$results = $wpdb->get_results( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE post_status != 'auto-draft' $and" );
		foreach ( (array) $results as $result ) {
			$authors[] = get_userdata( $result->post_author );
		}

		$authors = array_filter( $authors );

		foreach ( $authors as $author ) {
			echo "\t<wp:author>";
			echo '<wp:author_id>' . (int) $author->ID . '</wp:author_id>';
			echo '<wp:author_login>' . static::getCData( $author->user_login ) . '</wp:author_login>';
			echo '<wp:author_email>' . static::getCData( $author->user_email ) . '</wp:author_email>';
			echo '<wp:author_display_name>' . static::getCData( $author->display_name ) . '</wp:author_display_name>';
			echo '<wp:author_first_name>' . static::getCData( $author->first_name ) . '</wp:author_first_name>';
			echo '<wp:author_last_name>' . static::getCData( $author->last_name ) . '</wp:author_last_name>';
			echo "</wp:author>\n";
		}
	}

	/**
	 * Wrap given string in XML CDATA tag.
	 *
	 * @param string $str String to wrap in XML CDATA tag.
	 *
	 * @return string
	 * @since 2.1.0
	 *
	 */
	private static function getCData( $str ) {
		if ( ! seems_utf8( $str ) ) {
			$str = utf8_encode( $str );
		}

		$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';

		return $str;
	}

	/**
	 * Output a cat_name XML tag from a given category object
	 *
	 * @param WP_Term $category Category Object
	 *
	 * @since 2.1.0
	 *
	 */
	private static function printCatName( $category ) {
		if ( empty( $category->name ) ) {
			return;
		}

		echo '<wp:cat_name>' . static::getCData( $category->name ) . "</wp:cat_name>\n";
	}

	/**
	 * Output a category_description XML tag from a given category object
	 *
	 * @param WP_Term $category Category Object
	 *
	 * @since 2.1.0
	 *
	 */
	private static function printCatDescription( $category ) {
		if ( empty( $category->description ) ) {
			return;
		}

		echo '<wp:category_description>' . static::getCData( $category->description ) . "</wp:category_description>\n";
	}

	/**
	 * Output term meta XML tags for a given term object.
	 *
	 * @param WP_Term $term Term object.
	 *
	 * @since 4.6.0
	 *
	 */
	private static function printTermMeta( $term ) {
		global $wpdb;

		$termmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->termmeta WHERE term_id = %d", $term->term_id ) );

		foreach ( $termmeta as $meta ) {
			/**
			 * Filters whether to selectively skip term meta used for WXR exports.
			 *
			 * Returning a truthy value from the filter will skip the current meta
			 * object from being exported.
			 *
			 * @param bool $skip Whether to skip the current piece of term meta. Default false.
			 * @param string $meta_key Current meta key.
			 * @param object $meta Current meta object.
			 *
			 * @since 4.6.0
			 *
			 */
			if ( ! apply_filters( 'wxr_export_skip_termmeta', false, $meta->meta_key, $meta ) ) {
				printf( "\t\t<wp:termmeta>\n\t\t\t<wp:meta_key>%s</wp:meta_key>\n\t\t\t<wp:meta_value>%s</wp:meta_value>\n\t\t</wp:termmeta>\n", wxr_cdata( $meta->meta_key ), wxr_cdata( $meta->meta_value ) );
			}
		}
	}

	/**
	 * Output a tag_name XML tag from a given tag object
	 *
	 * @param WP_Term $tag Tag Object
	 *
	 * @since 2.3.0
	 *
	 */
	private static function printTagName( $tag ) {
		if ( empty( $tag->name ) ) {
			return;
		}

		echo '<wp:tag_name>' . static::getCData( $tag->name ) . "</wp:tag_name>\n";
	}

	/**
	 * Output a tag_description XML tag from a given tag object
	 *
	 * @param WP_Term $tag Tag Object
	 *
	 * @since 2.3.0
	 *
	 */
	private static function printTagDescription( $tag ) {
		if ( empty( $tag->description ) ) {
			return;
		}

		echo '<wp:tag_description>' . static::getCData( $tag->description ) . "</wp:tag_description>\n";
	}

	/**
	 * Output a term_name XML tag from a given term object
	 *
	 * @param WP_Term $term Term Object
	 *
	 * @since 2.9.0
	 *
	 */
	private static function printTermName( $term ) {
		if ( empty( $term->name ) ) {
			return;
		}

		echo '<wp:term_name>' . static::getCData( $term->name ) . "</wp:term_name>\n";
	}

	/**
	 * Output a term_description XML tag from a given term object
	 *
	 * @param WP_Term $term Term Object
	 *
	 * @since 2.9.0
	 *
	 */
	private static function printTermDescription( $term ) {
		if ( empty( $term->description ) ) {
			return;
		}

		echo "\t\t<wp:term_description>" . static::getCData( $term->description ) . "</wp:term_description>\n";
	}

	/**
	 * Output all navigation menu terms
	 *
	 * @since 3.1.0
	 */
	private static function printNavTerms() {
		$nav_menus = wp_get_nav_menus();
		if ( empty( $nav_menus ) || ! is_array( $nav_menus ) ) {
			return;
		}

		foreach ( $nav_menus as $menu ) {
			echo "\t<wp:term>";
			echo '<wp:term_id>' . (int) $menu->term_id . '</wp:term_id>';
			echo '<wp:term_taxonomy>nav_menu</wp:term_taxonomy>';
			echo '<wp:term_slug>' . static::getCData( $menu->slug ) . '</wp:term_slug>';
			static::printTermName( $menu );
			echo "</wp:term>\n";
		}
	}

	/**
	 * Output list of taxonomy terms, in XML tag format, associated with a post
	 *
	 * @since 2.3.0
	 */
	private static function printPostTaxonomy( $post ) {

		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( empty( $taxonomies ) ) {
			return;
		}
		$terms = wp_get_object_terms( $post->ID, $taxonomies );

		foreach ( (array) $terms as $term ) {
			echo "\t\t<category domain=\"{$term->taxonomy}\" nicename=\"{$term->slug}\">" . static::getCData( $term->name ) . "</category>\n";
		}
	}

	/**
	 * @param bool $return_me
	 * @param string $meta_key
	 *
	 * @return bool
	 */
	public static function skipPostMeta( $return_me, $meta_key ) {

		$skipped = array( '_edit_lock', 'extend_builder' );

		if ( in_array( $meta_key, $skipped ) ) {
			$return_me = true;
		}

		return $return_me;
	}

}
