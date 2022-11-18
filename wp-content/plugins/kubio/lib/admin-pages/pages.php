<?php

use IlluminateAgnostic\Arr\Support\Arr;
use Kubio\Core\StyleManager\Utils;

function kubio_print_admin_page_header( $subtitle = null, $tabs = array(), $options = array() ) {

	global $_wp_admin_css_colors;
	$color_scheme = get_user_option( 'admin_color', get_current_user_id() );

	$colors = Arr::get( $_wp_admin_css_colors, "{$color_scheme}", array() );

	$action_params = Arr::get( $options, 'action_params', array() );

	$current_tab = sanitize_key( Arr::get( $_REQUEST, 'tab', 'get-started' ) );

	if ( ! isset( $tabs[ $current_tab ] ) && count( $tabs ) > 0 ) {
		$current_tab = array_keys( $tabs )[0];
	}

	$base_url = add_query_arg( 'page', 'kubio-get-started', admin_url( 'admin.php' ) );

	?>

	<style>
		:root{
			<?php foreach ( Arr::get( (array) $colors, 'colors', array() ) as $index => $color ) : ?>
				--kubio-admin-page-color-<?php echo esc_html( $index + 1 ); ?>:<?php echo  esc_html( Utils::hex2rgba( $color, false, true ) ); ?>;
			<?php endforeach; ?>
		}
	</style>
	<div class="kubio-admin-page-header">
		<div class="limited-width">

			<div class="kubio-admin-row">
				<div class="kubio-admin-col-1 no-gap">

					<h1 class="kubio-admin-page-header-title">
						<?php echo wp_kses_post( file_get_contents( kubio_admin_assets_path() . '/kubio-logo.svg' ) ); ?>
					</h1>

					<div class="kubio-admin-row kubio-admin-row-items-end">
						<?php if ( $subtitle ) : ?>
							<div class="kubio-admin-page-header-subtitle">
								<?php echo esc_html( $subtitle ); ?>
							</div>
						<?php endif; ?>
					</div>

				</div>

				<div class="kubio-admin-col justify-content-end">
					<div class="kubio-admin-page-header-start-editing">
						<a href="<?php echo esc_url( add_query_arg( 'page', 'kubio', admin_url( 'admin.php' ) ) ); ?>"
						   class="button button-hero button-primary">
							<?php esc_html_e( 'Start editing', 'kubio' ); ?>
						</a>
					</div>
				</div>

			</div>


		</div>
	</div>
	<?php if ( $tabs ) : ?>
		<div class="kubio-admin-page-tabs">

			<div class="kubio-admin-row limited-width">
				<ul class="">
					<?php
					foreach ( $tabs as $tab_slug => $tab_data ) :
						$class_add = ( $current_tab === $tab_slug ? 'active' : '' );
						if ( isset( $tab_data['class'] ) ) {
							if ( ! is_array( $tab_data['class'] ) ) {
								$tab_data['class'] = array( $tab_data['class'] );
							}

							$classes   = array_merge( array( $class_add ), $tab_data['class'] );
							$class_add = implode( ' ', $classes );
						}
						?>
						<li class="<?php echo $class_add; ?>">
							<?php if ( $tab_data['type'] === 'page' ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_slug, $base_url ) ); ?>">
									<?php echo esc_html( $tab_data['label'] ); ?>
								</a>
							<?php elseif ( $tab_data['type'] === 'link' ) : ?>

								<a href="<?php echo esc_url( $tab_data['href'] ); ?>" class="link-tab">
								<?php echo esc_html( $tab_data['label'] ); ?>
								</a>

							<?php endif; ?>

						</li>
					<?php endforeach; ?>
				</ul>
			</div>

		</div>
	<?php endif; ?>


	<?php do_action( 'kubio/welcome-page/after-header', $action_params ); ?>
	<?php
}

function kubio_print_admin_page_start() {
	?>
	<div class="wrap">
		<div id="kubio-admin-page">
	<?php

}

function kubio_print_admin_page_end() {
	?>
		</div>
	</div>
	<?php

}

function kubio_admin_assets_path() {
	return KUBIO_ROOT_DIR . '/static/admin-pages/';

}


function kubio_admin_page_class( $page_name, $extra_classes = array(), $echo = true ) {
	$classes = array_merge(
		array( "kubio-admin-page--{$page_name}" ),
		$extra_classes
	);

	$classes = implode( ' ', $classes );

	if ( $echo ) {
		echo esc_attr( $classes );
	} else {
		return $classes;
	}

}

function kubio_admin_page_component_class( $component, $extra_classes = array(), $echo = true ) {
	$classes = array_merge(
		array( "kubio-admin-page-component--{$component}" ),
		$extra_classes
	);

	$classes = implode( ' ', $classes );

	if ( $echo ) {
		echo esc_attr( $classes );
	} else {
		return $classes;
	}

}

function kubio_print_continous_loading_bar( $hidden = false ) {
	?>
	<div class="kubio-progress-bar <?php echo( $hidden ? 'hidden' : '' ); ?>">
		<div class="kubio-progress-bar-value"></div>
	</div>
	<?php
}


add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style( 'kubio-admin-area' );
		wp_enqueue_script( 'kubio-admin-area' );

		add_action(
			'admin_head',
			function () {
				?>
						<style>
							:root {
								--kubio-admin-pages-assets-root-url: <?php echo esc_url( kubio_url( '/static/admin-pages' ) ); ?>;
							}
						</style>
						<?php
			}
		);
	}
);

require __DIR__ . '/kubio-get-started.php';
