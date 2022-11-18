<?php

function kubio_template_autosave( $post_data, $type = 'preview' ) {

	$id = $post_data->wp_id ? $post_data->wp_id : $post_data->id;

	//TODO make a query that gets the autosave with the correct type so there are not made multiple autosaves for the same
	//post and type
	$old_autosave       = wp_get_post_autosave( $id );
	$same_autosave_type = true;
	$autosave_meta_key  = 'kubio_template_autosave_type';
	if ( $old_autosave ) {
		$autosave_type = get_post_meta( $id, $autosave_meta_key, true );

		//no meta is present or if the meta is present is the same type
		$same_autosave_type = ! $autosave_type || ( $autosave_type && $autosave_type === $type );
	}
	if ( $old_autosave && $same_autosave_type ) {
		wp_update_post(
			array(
				'ID'           => $old_autosave->ID,
				'post_content' => wp_slash( $post_data->content ),
			)
		);
		update_post_meta( $old_autosave->ID, $autosave_meta_key, $type );
		return $old_autosave;
	} else {
		$new_data               = get_post( $id );
		$new_data->post_content = $post_data->content;

		$autosave_id = _wp_put_post_revision( $new_data, true );

		if ( ! is_wp_error( $autosave_id ) ) {
			update_post_meta( $autosave_id, $autosave_meta_key, $type );
			return get_post( $autosave_id );
		}

		return $autosave_id;
	}

	return $post_data;
}


register_post_meta(
	'revision',
	'kubio_template_autosave_type',
	array(
		'show_in_rest'  => true,
		'single'        => true,
		'type'          => 'string',
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	)
);



add_filter( 'kubio/save-template-entity/page/autosave', 'kubio_template_autosave', 10, 2 );
add_filter( 'kubio/save-template-entity/wp_template/autosave', 'kubio_template_autosave', 10, 2 );
add_filter( 'kubio/save-template-entity/wp_template_part/autosave', 'kubio_template_autosave', 10, 2 );


