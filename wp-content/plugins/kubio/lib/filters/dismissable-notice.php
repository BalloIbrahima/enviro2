<?php

use IlluminateAgnostic\Arr\Support\Arr;

/**
 * This function adds an action which hooks into admin_notices and creates a dismissible notices via AJAX.
 *
 * @param string $name
 * @param callable $callback
 * @param integer $repeat_after - use 0 to disable the reappearance time limit
 * @param array $params
 * @param string $classes
 * @return void
 */
function kubio_add_dismissable_notice( $name, $callback, $repeat_after = 0, $params = array(), $classes = '' ) {

	if ( kubio_dismissable_notice_is_dismissed( $name, $repeat_after ) ) {
		return;
	}

	add_action(
		'admin_notices',
		function () use ( $name, $params, $callback, $classes ) {
			$id   = 'kubio-notice-' . uniqid();
			$data = array(
				'id'   => $id,
				'name' => $name,
			);
			?>
				<div data-kubio-notice-id="<?php echo esc_attr( $id ); ?>" class="notice is-dismissible <?php echo esc_attr( $classes ); ?>">
					<?php call_user_func( $callback, $params ); ?>
					<script>
						jQuery(function($){
							var data =<?php	echo wp_json_encode( $data ); ?>;
							$(document).on('click','[data-kubio-notice-id=' + data.id + '] .notice-dismiss',function(){
								wp.ajax.post('kubio-dismissable-notice--dismiss',{kubio_notice_name:data.name});
							});
						});
					</script>
				</div>

				<?php

				$notices          = get_option( '_kubio_dismissable_notices', array() );
				$notices[ $name ] = array( 'dismiss_time' => 0 );
				update_option( '_kubio_dismissable_notices', $notices );
		}
	);
}

/**
 * This is an ajax callback which marks notices as dismissed.
 *
 * @return void
 */
function _kubio_dismiss_dismissable_notice() {
	$notice  = Arr::get( $_REQUEST, 'kubio_notice_name', false );
	$notices = get_option( '_kubio_dismissable_notices', array() );

	if ( $notice && Arr::exists( $notices, $notice ) ) {
		$notices[ $notice ] = array( 'dismiss_time' => time() );
		update_option( '_kubio_dismissable_notices', $notices );
	}

}

/**
 * Checks if a named Kubio notice is dismissed at this moment.
 * @param $name
 * @param $repeat_after
 * @return bool
 */
function kubio_dismissable_notice_is_dismissed( $name, $repeat_after = 0 ) {
	$notices = get_option( '_kubio_dismissable_notices', array() );

	if ( Arr::has( $notices, $name ) ) {

		$dismissed_time = Arr::get( $notices, "{$name}.dismiss_time", 0 );

		if ( $repeat_after === 0 && $dismissed_time !== 0 ) {
			return true;
		}

		if ( $dismissed_time && time() < $dismissed_time + $repeat_after ) {
			return true;
		}
	}
	return false;
}

add_action( 'wp_ajax_kubio-dismissable-notice--dismiss', '_kubio_dismiss_dismissable_notice' );
