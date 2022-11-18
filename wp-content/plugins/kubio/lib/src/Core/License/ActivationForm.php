<?php
namespace Kubio\Core\License;

use Kubio\Core\LodashBasic;
use Kubio\Core\License\License;
use Kubio\Core\License\Updater;
use Kubio\Flags;
use Plugin_Upgrader;

class ActivationForm {
	public function __construct() {
		add_action( 'wp_ajax_kubiowp-page-builder-activate', array( $this, 'callActivateLicenseEndpoint' ) );
		add_action( 'wp_ajax_kubiowp-page-builder-maybe-install-pro', array( $this, 'maybeInstallPRO' ) );
	}

	public function printForm() {
		add_action( 'admin_notices', array( $this, 'makeActivateNotice' ) );
		$this->enqueue();
	}

	public function enqueue() {
		wp_enqueue_script( 'wp-util' );
	}

	public function makeUpgradeView( $message = '' ) {
		?>
		<div class="kubio-page-builder-upgade-view kubio-admin-panel">
			<div class="kubio-page-builder-license-notice kubio-page-builder-activate-license">
				<h3 class="notice_title"><?php esc_html_e( 'Enter a valid Kubio PRO license key to unlock all the PRO features', 'kubio' ); ?></h3>
				<?php echo $this->formHtml( $message ); ?>
			</div>
		</div>
		<?php
	}

	public function makeActivateNotice( $formId = '', $classHhtml = array(), $message = '' ) {
		if ( ! array( $classHhtml ) ) {
			$classHhtml = array( $classHhtml );
		}
		if ( $formId !== '' ) {
			$formId = ' id="' . $formId . '"';
		}
		$classHhtml = implode( ' ', $classHhtml );
		?>
		<div class="notice notice-error is-dismissible kubio-activation-wrapper <?php echo $classHhtml; ?>"<?php echo $formId; ?>>
			<div class="notification-logo-wrapper">
				<div class="notification-logo">
					<?php echo wp_kses_post( KUBIO_LOGO_SVG ); ?>
				</div>
			</div>
			<div class="kubio-page-builder-license-notice kubio-page-builder-activate-license">
				<h1 class="notice_title"><?php esc_html_e( 'Activate Kubio PRO License', 'kubio' ); ?></h1>
				<h3 class="notice_sub_title"><?php esc_html_e( 'If this is a testing site you can ignore this message. If this is your live site then please insert the license key below.', 'kubio' ); ?></h3>
				<?php echo $this->formHtml( $message ); ?>
			</div>
		</div>
		<?php
	}

	public function formHtml( $message = '' ) {
		$html = '<div class="kubio-page-builder-activate-license-form_wrapper">
			<form id="kubio-page-builder-activate-license-form" class="activate-form">
				<input placeholder="6F474380-5929B874-D2E0CB90-C7282097" type="text"
					value="' . esc_attr( get_option( 'kubio_sync_data_source', '' ) ) . '"
					class="regular-text">
				<button type="submit" class="button button-primary">' . esc_html__( 'Activate License', 'kubio' ) . '</button>
			</form>
			' . $this->formMessage( $message ) . '
		</div>';

		return $html;
	}

	public function formMessage( $message = '' ) {
		$html = '';
		if ( '' === $message ) {
			$html .= '<p id="kubio-page-builder-activate-license-message" class="message" style="display: none;"></p>';
		} else {
			$html .= '<p id="kubio-page-builder-activate-license-message" class="message">' . $message . '</p>';
		}

		$html .= '<p class="description">';
		$html .= sprintf( __( 'Your key was sent via email when the purchase was completed. Also you can find the key in the %1$s of your %2$s account', 'kubio' ), '<a href="' . esc_attr( License::getInstance()->getDashboardUrl() ) . '/#/my-plans" target="_blank">My plans</a>', '<a href="' . esc_attr( License::getInstance()->getDashboardUrl() ) . '" target="_blank">Kubio</a>' );
		$html .= '</p>
		<div class="spinner-holder plugin-installer-spinner" style="display: none;">
			<span class="icon">
				<span class="loader">' . kubio_get_iframe_loader(
			array(
				'size'  => '19px',
				'color' => '#2271B1',
			)
		) . '</span>
				<span class="ok"><span class="dashicons dashicons-before dashicons-yes"></span></span>
			</span>
			<span class="message"></span>
		</div>';

		return $html;
	}

	public function callActivateLicenseEndpoint() {
		$key = isset( $_REQUEST['key'] ) ? $_REQUEST['key'] : false;

		if ( ! $key ) {
			wp_send_json_error( esc_html__( 'License key is empty', 'kubio' ), 403 );
		}

		License::getInstance()->setLicenseKey( $key );
		$response = Endpoint::activate();

		if ( $response->isError() ) {
			License::getInstance()->setLicenseKey( null );
		}

		wp_send_json(
			array(
				'data'    => $response->getMessage( true ),
				'success' => $response->isSuccess(),
			),
			$response->getResponseCode()
		);
	}

	public function maybeInstallPRO() {
		add_filter(
			'kubio/companion/update_remote_data',
			function ( $data ) {
				$data['args'] = array(
					'product' => 'kubio-pro',
					'key'     => License::getInstance()->getLicenseKey(),
				);

				$data['plugin_path'] = 'kubio-pro/plugin.php';

				return $data;
			},
			PHP_INT_MAX
		);

		$status = (array) Updater::getInstance()->isUpdateAvailable();
		$url    = LodashBasic::array_get_value( $status, 'package_url', false );

		if ( $url ) {

			if ( ! function_exists( 'plugins_api' ) ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' ); //for plugins_api..
			}

			if ( ! class_exists( 'Plugin_Upgrader' ) ) {
				/** Plugin_Upgrader class */
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			$upgrader = new Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->install( $url );

			if ( $result !== true && $result !== null ) {
				wp_send_json_error();
			}

			$ac   = get_option( 'active_plugins' );
			$ac   = array_diff( $ac, array( 'kubio/plugin.php' ) );
			$ac[] = 'kubio-pro/plugin.php';
			update_option( 'active_plugins', $ac );

			// set the kubio pro plugin activation time
			if ( ! Flags::get( 'kubio_pro_activation_time', false ) ) {
				Flags::set( 'kubio_pro_activation_time', time() );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'No error', 'kubio' ) ) );
		}

		wp_send_json_success( $status );
	}
}
