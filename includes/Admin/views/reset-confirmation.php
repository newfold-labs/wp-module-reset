<?php
/**
 * Factory reset confirmation view.
 *
 * Expected variables:
 * - string       $home_url Site home URL.
 * - string|false $error    Optional error message.
 */
?>
<div class="wrap">
	<style>
		.nfd-reset-page {
			max-width: 900px;
			margin: 0 auto;
			padding: 2rem;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		}
		.nfd-reset-page h1 {
			font-size: 1.5rem;
			font-weight: 700;
			margin: 0 0 0.5rem;
		}
		.nfd-reset-page .nfd-reset-subtitle {
			color: #646970;
			font-size: 14px;
			margin: 0 0 2rem;
		}
		.nfd-reset-warning {
			background: #fff;
			border: 1px solid #dc3232;
			border-left-width: 4px;
			border-radius: 4px;
			padding: 24px;
			margin: 0 0 24px;
		}
		.nfd-reset-warning h2 {
			color: #dc3232;
			font-size: 1rem;
			font-weight: 600;
			margin: 0 0 12px;
		}
		.nfd-reset-warning p {
			font-size: 14px;
			color: #1e1e1e;
			line-height: 1.6;
			margin: 0 0 8px;
		}
		.nfd-reset-warning ul {
			list-style: disc;
			padding-left: 20px;
			margin: 8px 0 12px;
		}
		.nfd-reset-warning ul li {
			font-size: 14px;
			color: #1e1e1e;
			margin-bottom: 6px;
			line-height: 1.5;
		}
		.nfd-reset-warning .nfd-reset-preserved {
			color: #646970;
			font-size: 13px;
			margin-top: 12px;
		}
		.nfd-reset-confirm-field {
			margin: 0 0 24px;
		}
		.nfd-reset-confirm-field label {
			display: block;
			font-weight: 600;
			font-size: 14px;
			margin-bottom: 8px;
		}
		.nfd-reset-confirm-field input[type="text"] {
			width: 100%;
			max-width: 420px;
			padding: 10px 12px;
			font-size: 14px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			box-shadow: none;
		}
		.nfd-reset-confirm-field code {
			background: #f0f0f1;
			padding: 2px 6px;
			font-size: 13px;
			border-radius: 2px;
		}
		.nfd-reset-submit {
			background: #d63638 !important;
			border-color: #d63638 !important;
			color: #fff !important;
			padding: 8px 24px !important;
			font-size: 14px !important;
			font-weight: 500 !important;
			border-radius: 4px !important;
			cursor: pointer;
			line-height: 1.5 !important;
			min-height: 40px !important;
		}
		.nfd-reset-submit:disabled {
			background: #e6e6e6 !important;
			border-color: #babac3 !important;
			color: #7d7d7d !important;
			cursor: not-allowed;
		}
		.nfd-reset-submit.nfd-resetting:disabled {
			color: #000000 !important;
		}
		.nfd-reset-submit .nfd-spinner {
			display: inline-block;
			width: 14px;
			height: 14px;
			border: 2px solid rgba(0, 0, 0, 0.2);
			border-top-color: #000;
			border-radius: 50%;
			animation: nfd-spin 0.6s linear infinite;
			vertical-align: middle;
			margin-right: 6px;
		}
		@keyframes nfd-spin {
			to { transform: rotate(360deg); }
		}
		.nfd-reset-submit:hover:not(:disabled) {
			background: #b32d2e !important;
			border-color: #b32d2e !important;
		}
		.nfd-reset-error {
			background: #fcf0f1;
			border: 1px solid #dc3232;
			border-left-width: 4px;
			border-radius: 4px;
			padding: 12px 16px;
			margin: 0 0 24px;
			font-size: 14px;
		}
	</style>

	<div class="nfd-reset-page">
		<h1><?php esc_html_e( 'Factory Reset Website', 'wp-module-reset' ); ?></h1>
		<p class="nfd-reset-subtitle"><?php esc_html_e( 'Restore your website to a fresh WordPress installation.', 'wp-module-reset' ); ?></p>

		<?php if ( $error ) : ?>
			<div class="nfd-reset-error">
				<strong><?php echo esc_html( $error ); ?></strong>
			</div>
		<?php endif; ?>

		<div class="nfd-reset-warning">
			<h2><?php esc_html_e( 'This action is permanent and cannot be undone.', 'wp-module-reset' ); ?></h2>
			<p><?php esc_html_e( 'Performing a factory reset will:', 'wp-module-reset' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Delete all database content (posts, pages, comments, settings, custom tables)', 'wp-module-reset' ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s: brand plugin name, e.g. "The Bluehost Plugin" */
						esc_html__( 'Remove all plugins and themes (except %s and default theme)', 'wp-module-reset' ),
						esc_html( \NewfoldLabs\WP\Module\Reset\Data\BrandConfig::get_brand_name() )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Delete all uploaded media files', 'wp-module-reset' ); ?></li>
				<li><?php esc_html_e( 'Destroy any staging sites', 'wp-module-reset' ); ?></li>
			</ul>
			<p class="nfd-reset-preserved"><?php esc_html_e( 'Your admin account and site URL will be preserved.', 'wp-module-reset' ); ?></p>
		</div>

		<form method="post" id="nfd-reset-form">
			<?php wp_nonce_field( \NewfoldLabs\WP\Module\Reset\Admin\ToolsPage::NONCE_ACTION, \NewfoldLabs\WP\Module\Reset\Admin\ToolsPage::NONCE_NAME ); ?>

			<div class="nfd-reset-confirm-field">
				<label for="confirmation_url">
					<?php
					printf(
						/* translators: %s: The site URL the user must type to confirm */
						esc_html__( 'To confirm, type your website URL: %s', 'wp-module-reset' ),
						'<code>' . esc_html( $home_url ) . '</code>'
					);
					?>
				</label>
				<input
					type="text"
					id="confirmation_url"
					name="confirmation_url"
					autocomplete="off"
					spellcheck="false"
					placeholder=""
				/>
			</div>

			<button type="submit" class="button nfd-reset-submit" id="nfd-reset-button" disabled>
				<?php esc_html_e( 'Reset Website', 'wp-module-reset' ); ?>
			</button>
		</form>
	</div>

	<script>
	(function() {
		var input = document.getElementById('confirmation_url');
		var button = document.getElementById('nfd-reset-button');
		var form = document.getElementById('nfd-reset-form');
		var expected = <?php echo wp_json_encode( $home_url ); ?>;

		if (input && button) {
			input.addEventListener('input', function() {
				var value = this.value.replace(/\/+$/, '');
				button.disabled = (value !== expected);
			});
		}

		if (form && button) {
			form.addEventListener('submit', function() {
				button.disabled = true;
				button.classList.add('nfd-resetting');
				button.innerHTML = '<span class="nfd-spinner"></span> <?php echo esc_js( __( 'Resetting...', 'wp-module-reset' ) ); ?>';
				input.readOnly = true;
				input.style.opacity = '0.5';
			});
		}
	})();
	</script>
</div>

