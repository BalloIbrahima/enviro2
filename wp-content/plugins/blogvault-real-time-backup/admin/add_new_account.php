<?php $parent_class = $this->bvinfo->isMalcare() ? "malcare" : "blogvault"; ?>
<div class="<?php echo esc_attr($parent_class); ?>">
	<div id="add-new-account">
		<section id="header">
			<div class="custom-container">
				<?php
					require_once dirname( __FILE__ ) . "/components/header_top.php";
					require_once dirname( __FILE__ ) . "/components/form.php";
				?>
			</div>
		</section>
		<?php
			require_once dirname( __FILE__ ) . "/components/features_list.php";
			require_once dirname( __FILE__ ) . "/components/testimony.php";
			require_once dirname( __FILE__ ) . "/components/footer.php";
		?>
	</div>
</div>