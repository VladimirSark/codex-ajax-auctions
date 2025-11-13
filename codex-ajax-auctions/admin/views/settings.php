<?php
/**
 * Auction settings admin page.
 *
 * @package CodexAjaxAuctions
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap codfaa-settings">
	<h1><?php esc_html_e( 'Auction Settings', 'codex-ajax-auctions' ); ?></h1>
	<p class="codfaa-settings__intro">
		<?php esc_html_e( 'Configure shared auction utilities such as the Terms & Conditions copy shown to participants during registration.', 'codex-ajax-auctions' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( $current_url ); ?>" class="codfaa-settings-card">
		<?php wp_nonce_field( 'codfaa_save_settings' ); ?>
		<input type="hidden" name="codfaa_settings_action" value="save" />

		<table class="form-table codfaa-settings-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="codfaa_terms_content"><?php esc_html_e( 'Terms & Conditions Copy', 'codex-ajax-auctions' ); ?></label>
				</th>
				<td>
					<?php
						wp_editor(
							$terms_content,
							'codfaa_terms_content',
							array(
								'textarea_name' => 'codfaa_terms_content',
								'textarea_rows' => 10,
								'tinymce'       => false,
								'quicktags'     => true,
							)
						);
					?>
					<p class="description">
						<?php esc_html_e( 'This text appears in the Terms & Conditions modal on the frontend registration card.', 'codex-ajax-auctions' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'codex-ajax-auctions' ) ); ?>
	</form>
</div>

