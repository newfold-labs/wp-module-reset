<?php
/**
 * Factory reset results view.
 *
 * Expected variables:
 * - bool   $success
 * - string $redirect_url
 * - array  $steps
 * - array  $errors
 * - bool   $has_errors
 * - bool   $hide_chrome
 */
?>
<?php if ( $hide_chrome ) : ?>
	<style type="text/css">
		/* Hide admin bar, sidebar, and footer so user focuses on the two CTAs. */
		#wpadminbar,
		#adminmenumain,
		#adminmenuwrap,
		#wpfooter {
			display: none !important;
		}
		#wpcontent {
			margin-left: 0 !important;
		}
	</style>
<?php endif; ?>

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
		.nfd-reset-result {
			background: #fff;
			border: 1px solid <?php echo $has_errors ? '#dc3232' : '#00a32a'; ?>;
			border-left-width: 4px;
			border-radius: 4px;
			padding: 24px;
			margin: 0 0 24px;
		}
		.nfd-reset-result h2 {
			color: <?php echo $has_errors ? '#dc3232' : '#00a32a'; ?>;
			font-size: 1rem;
			font-weight: 600;
			margin: 0 0 12px;
		}
		.nfd-reset-result p {
			font-size: 14px;
			color: #1e1e1e;
			line-height: 1.6;
			margin: 0 0 8px;
		}
		.nfd-reset-result ul {
			list-style: disc;
			padding-left: 20px;
			margin: 8px 0 12px;
		}
		.nfd-reset-result ul li {
			font-size: 14px;
			color: #1e1e1e;
			margin-bottom: 6px;
			line-height: 1.5;
		}
		.nfd-reset-result .nfd-reset-preserved {
			color: #646970;
			font-size: 13px;
			margin-top: 12px;
		}
		.nfd-reset-steps {
			margin: 0;
		}
		.nfd-reset-steps table {
			border-collapse: collapse;
			width: 100%;
		}
		.nfd-reset-steps td {
			padding: 8px 12px;
			border-bottom: 1px solid #f0f0f1;
			font-size: 14px;
			line-height: 1.5;
		}
		.nfd-reset-step-success {
			color: #00a32a;
			font-weight: 600;
		}
		.nfd-reset-step-fail {
			color: #dc3232;
			font-weight: 600;
		}
		.nfd-reset-errors {
			margin-top: 16px;
			padding: 12px 16px;
			background: #fcf0f1;
			border-radius: 4px;
			font-size: 14px;
		}
		.nfd-reset-errors ul {
			margin: 6px 0 0 16px;
			list-style: disc;
		}
		.nfd-reset-cta-group {
			display: flex;
			gap: 12px;
			align-items: center;
			flex-wrap: wrap;
		}
		.nfd-reset-cta-primary {
			display: inline-block;
			padding: 10px 24px;
			background: #2271b1;
			color: #fff;
			text-decoration: none;
			border-radius: 4px;
			font-size: 14px;
			font-weight: 500;
			line-height: 1.5;
		}
		.nfd-reset-cta-primary:hover {
			background: #135e96;
			color: #fff;
		}
		.nfd-reset-cta-secondary {
			display: inline-block;
			padding: 10px 24px;
			background: transparent;
			color: #2271b1;
			text-decoration: none;
			border: 1px solid #2271b1;
			border-radius: 4px;
			font-size: 14px;
			font-weight: 500;
			line-height: 1.5;
		}
		.nfd-reset-cta-secondary:hover {
			background: #f0f0f1;
			color: #135e96;
			border-color: #135e96;
		}
	</style>

	<div class="nfd-reset-page">
		<h1><?php esc_html_e( 'Factory Reset Complete', 'wp-module-reset' ); ?></h1>
		<p class="nfd-reset-subtitle"><?php esc_html_e( 'Your website has been restored to a fresh WordPress installation.', 'wp-module-reset' ); ?></p>

		<?php if ( $has_errors ) : ?>
			<?php // Detailed technical view — shown only when errors occurred. ?>
			<div class="nfd-reset-result">
				<h2><?php esc_html_e( 'Reset completed with errors.', 'wp-module-reset' ); ?></h2>

				<?php if ( ! empty( $steps ) ) : ?>
					<div class="nfd-reset-steps">
						<table>
							<?php foreach ( $steps as $step_name => $step_data ) : ?>
								<tr>
									<td>
										<?php if ( ! empty( $step_data['success'] ) ) : ?>
											<span class="nfd-reset-step-success">&#10003;</span>
										<?php else : ?>
											<span class="nfd-reset-step-fail">&#10007;</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( \NewfoldLabs\WP\Module\Reset\Admin\ToolsPage::format_step_name( $step_name ) ); ?></td>
									<td><?php echo esc_html( ! empty( $step_data['message'] ) ? $step_data['message'] : '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endif; ?>

				<div class="nfd-reset-errors">
					<strong><?php esc_html_e( 'Errors:', 'wp-module-reset' ); ?></strong>
					<ul>
						<?php foreach ( $errors as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<div class="nfd-reset-cta-group">
				<a href="<?php echo esc_url( $redirect_url ); ?>" class="nfd-reset-cta-primary">
					<?php esc_html_e( 'Continue to Dashboard', 'wp-module-reset' ); ?>
				</a>
			</div>

		<?php else : ?>
			<?php // Simple success view — mirrors the confirmation screen style but green/positive. ?>
			<div class="nfd-reset-result">
				<h2><?php esc_html_e( 'Your website has been reset successfully.', 'wp-module-reset' ); ?></h2>
				<p><?php esc_html_e( 'The factory reset is complete. Your site has been restored to a clean state:', 'wp-module-reset' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'All database content has been cleared (posts, pages, comments, settings)', 'wp-module-reset' ); ?></li>
					<li><?php esc_html_e( 'Third-party plugins and themes have been removed', 'wp-module-reset' ); ?></li>
					<li><?php esc_html_e( 'Uploaded media files have been deleted', 'wp-module-reset' ); ?></li>
					<li><?php esc_html_e( 'WordPress core and default theme have been freshly reinstalled', 'wp-module-reset' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: brand plugin name, e.g. "The Bluehost Plugin" */
							esc_html__( '%s is active and connected', 'wp-module-reset' ),
							esc_html( \NewfoldLabs\WP\Module\Reset\Data\BrandConfig::get_brand_name() )
						);
						?>
					</li>
				</ul>
				<p class="nfd-reset-preserved"><?php esc_html_e( 'Your admin account and site URL have been preserved.', 'wp-module-reset' ); ?></p>
			</div>

			<div class="nfd-reset-cta-group">
				<a href="<?php echo esc_url( admin_url( 'index.php?page=nfd-onboarding' ) ); ?>" class="nfd-reset-cta-primary">
					<?php esc_html_e( 'Set up my site', 'wp-module-reset' ); ?>
				</a>
				<a href="<?php echo esc_url( $redirect_url ); ?>" class="nfd-reset-cta-secondary">
					<?php esc_html_e( 'Exit to dashboard', 'wp-module-reset' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>

