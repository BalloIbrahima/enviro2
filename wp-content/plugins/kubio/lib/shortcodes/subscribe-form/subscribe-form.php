<?php

//namespace ExtendBuilder;
use Kubio\Core\Utils;
use Kubio\Core\GlobalElements\Icon;

function kubio_mailchimp_create_sample_form() {
	if ( class_exists( '\MC4WP_Forms_Admin' ) ) {
		//code from MC4WP_Forms_Admin->process_add_form function
		$form_content = include MC4WP_PLUGIN_DIR . '/config/default-form-content.php';

		wp_insert_post(
			array(
				'post_type'    => 'mc4wp-form',
				'post_status'  => 'publish',
				'post_title'   => 'Kubio form',
				'post_content' => $form_content,
			)
		);
	}
}

add_filter( 'mc4wp_form_content', 'kubio_mc4wp_filter' );
function kubio_mc4wp_filter( $content ) {

	$attrs = Utils::kubioCacheGet( 'kubio_newsletter_attrs' );

	//if the shortcode is not used using the newsletter component don't modify it;
	if ( ! $attrs ) {
		return $content;
	}
	if ( $attrs['use_shortcode_layout'] == '1' ) {
		return $content;
	}

	$use_agree_terms = $attrs['use_agree_terms'];

	ob_start();

	?>
	<div class="kubio-newsletter__email-group kubio-newsletter-group">
		<?php if ( $attrs['email_label'] ) : ?>
			<label><?php echo esc_html( $attrs['email_label'] ); ?></label>
		<?php endif; ?>
		<input type="email" name="EMAIL" placeholder="<?php echo esc_attr( $attrs['email_placeholder'] ); ?>" required/>
	</div>
	<?php
	$email_html = ob_get_clean();
	ob_start();
	?>
	<div class=" kubio-newsletter__agree-terms-group kubio-newsletter-group">
			<input type="checkbox" name="AGREE_TO_TERMS" value="1" required/>
			<?php echo wpautop( wp_kses_post( stripslashes( $attrs['agree_terms_label'] ) ) ); ?>
	</div>
	<?php
	$agree_terms_html = ob_get_clean();
	ob_start();
	?>
	<div class="kubio-newsletter__submit-group kubio-newsletter-group">
		<button type="submit">

			<?php
			if ( $attrs['submit_button_use_icon'] == '1' ) {
				$icon = new Icon( 'span', array( 'name' => $attrs['submit_button_icon_name'] ) );
				echo wp_kses_post( $icon );
			}
			?>

			<span class="kubio-newsletter__submit-text"><?php echo esc_html( $attrs['submit_button_label'] ); ?></span>
		</button>
	</div>
	<?php
	$submit_html = ob_get_clean();

	$form  = '';
	$form .= $email_html;

	if ( $use_agree_terms == 1 ) {
		$form .= $agree_terms_html;
	}

	$form .= $submit_html;

	return $form;
}

add_shortcode( 'kubio_newsletter', 'kubio_newsletter_shortcode' );


function kubio_newsletter_shortcode( $attrs ) {
	$attrs = shortcode_atts(
		array(
			'email_label'             => 'Email address: ',
			'email_placeholder'       => 'Your email address',
			'submit_button_label'     => 'Subscribe',
			'submit_button_icon_name' => '',
			'submit_button_use_icon'  => '0',
			'use_agree_terms'         => '0',
			'agree_terms_label'       => 'I have read and agree to the terms & conditions',
			'shortcode'               => '',
			'use_shortcode_layout'    => '0',
			'decode_data'             => '1',
		),
		$attrs
	);
	if ( $attrs['decode_data'] == '1' ) {
		$attrs['shortcode'] = Utils::shortcodeDecode( $attrs['shortcode'] );
		//$attrs['agree_terms_label'] = Utils::shortcodeDecode( $attrs['agree_terms_label'] );
	}
	Utils::kubioCacheSet( 'kubio_newsletter_attrs', $attrs );
	return do_shortcode( $attrs['shortcode'] );
}
