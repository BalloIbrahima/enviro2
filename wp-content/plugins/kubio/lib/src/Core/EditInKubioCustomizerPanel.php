<?php

namespace Kubio\Core;

class EditInKubioCustomizerPanel extends \WP_Customize_Panel {


	public function __construct( $manager, $id, $args = array() ) {

		$manager->add_section(
			"{$id}-section",
			array( 'panel' => $id )
		);

		$manager->add_control(
			"{$id}-control",
			array(
				'section'    => "{$id}-section",
				'settings'   => array(),
				'type'       => 'button',
				'capability' => 'manage_options',
			)
		);

		add_action( 'customize_controls_print_footer_scripts', array( $this, 'printScripts' ) );
		parent::__construct( $manager, $id, $args );
	}

	public function printScripts() {
		?>
		<style>
			.kubio-customizer-panel {
				margin: 15px;
				border: none !important;
			}

			.kubio-customizer-panel .accordion-section-title {
				cursor: default;
				border: 1px solid #ddd !important;
				box-shadow: none !important;
			}

			.kubio-customizer-panel .accordion-section-title:after {
				display: none;
			}

			.kubio-customizer-panel p {
				font-weight: normal;
				font-size: 13px;
				margin: 0 0 10px 0;
			}

			.kubio-customizer-panel .button.button-primary svg {
				fill: currentColor;
				width: 1em;
				height: 1em;
				margin-right: 0.5em;
			}

			.kubio-customizer-panel .button.button-primary {
				align-items: center;
				width: 100%;
				display: flex;
				justify-content: center;
				padding: 5px 10px;
			}
		</style>
		<?php
	}

	protected function render() {

		$message = __( 'After installing the Kubio builder, all page contents editing will be done using the WordPress block editor instead of the Customizer.', 'kubio' );

		if ( kubio_theme_has_block_templates_support() ) {
			$message = __( 'After installing the Kubio builder, all site editing will be done using the WordPress block editor instead of the Customizer.', 'kubio' );
		}

		?>
		<li class="accordion-section kubio-customizer-panel">
			<div class="accordion-section-title">
				<p><?php echo esc_html( $message ); ?></p>
				<p><?php esc_html_e( ' Please use the button below to open the Kubio editor.', 'kubio' ); ?></p>
				<button class="button button-primary kubio-open-editor-panel-button">
					<?php echo wp_kses_post( KUBIO_LOGO_SVG ); ?>
					<span><?php esc_html_e( 'Open Kubio Editor', 'kubio' ); ?></span>
				</button>
				<script>
					(function () {
						document.querySelector('.kubio-open-editor-panel-button').addEventListener('click', function () {
							window.location = '<?php echo esc_url( add_query_arg( 'page', 'kubio', admin_url( 'admin.php' ) ) ); ?>';
						})
					})();
				</script>
			</div>
		</li>
		<?php
	}

}
