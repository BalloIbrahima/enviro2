<section id="testimony">
	<div class="carousel text-center">
		<div class="slide-div text-center">				
			<input type="radio" name="slides" id="radio-1" checked>
			<input type="radio" name="slides" id="radio-2">
			<input type="radio" name="slides" id="radio-3">
			<input type="radio" name="slides" id="radio-4">
			<?php
				if ($this->bvinfo->isMalcare()) {
					require_once dirname( __FILE__ ) . "/mc_testimony.php";
				} else {
					require_once dirname( __FILE__ ) . "/bv_testimony.php";
				}
			?>
			<div class="slidesNavigation text-center">
				<label for="radio-1" id="dotForRadio-1"></label>
				<label for="radio-2" id="dotForRadio-2"></label>
				<label for="radio-3" id="dotForRadio-3"></label>
				<label for="radio-4" id="dotForRadio-4"></label>
			</div>
		</div>
	</div>
</section>