<?php $parentClassName = $this->bvinfo->isMalcare() ? "malcare" : "blogvault"; ?>
<div class="<?php echo esc_attr($parentClassName); ?>">
	<div id="main-page">
		<section id="header">
			<div class="custom-container">
				<?php require_once dirname( __FILE__ ) . "/components/header_top.php"; ?>
			</div>
		</section>
		<?php
			require_once dirname( __FILE__ ) . "/components/list_accounts.php";
			require_once dirname( __FILE__ ) . "/components/testimony.php";
			require_once dirname( __FILE__ ) . "/components/footer.php";
		?>
	</div>
</div>