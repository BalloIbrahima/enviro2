<?php
function kubio_3rd_party_theme_print_zero_block_gap() {
	?>
	<style>
		body{
			--wp--style--block-gap:0;
		}
	</style>
	<?php
}

function kubio_3rd_party_theme_reset_block_gap_on_kubio_templates() {
	add_action( 'wp_head', 'kubio_3rd_party_theme_print_zero_block_gap', 100 );
}

add_action( 'kubio/dequeue-theme-styles', 'kubio_3rd_party_theme_reset_block_gap_on_kubio_templates' );
