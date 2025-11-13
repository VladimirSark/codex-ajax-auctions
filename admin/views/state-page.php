<?php
/**
 * State-specific management view.
 *
 * @var array       $rows            Auctions for the given state.
 * @var array|null  $selected        Detail context for a single auction.
 * @var string      $current_url     Base URL for this submenu.
 * @var array|null  $email_recipient Selected participant data for email.
 * @var int         $email_user_id   Selected participant ID for email.
 * @var string      $page_title      Heading text.
 * @var string      $description     Intro text.
 * @var string      $page_slug_var   Menu slug.
 */

defined( 'ABSPATH' ) || exit;

use Codfaa\Auctions\Bidding_Service;

$manage_base = $current_url;
?>
<div class="wrap codfaa-dashboard codfaa-state-page">
	<h1><?php echo esc_html( $page_title ); ?></h1>
	<p><?php echo esc_html( $description ); ?></p>

	<div class="codfaa-state-layout">
		<div class="codfaa-state-list">
			<h2><?php esc_html_e( 'Auctions', 'codex-ajax-auctions' ); ?></h2>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No auctions match this state yet.', 'codex-ajax-auctions' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Auction', 'codex-ajax-auctions' ); ?></th>
						<th><?php esc_html_e( 'Participants', 'codex-ajax-auctions' ); ?></th>
						<th><?php esc_html_e( 'Manage', 'codex-ajax-auctions' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) :
						$detail_link = add_query_arg( array( 'auction' => $row['id'] ), $manage_base );
						$active      = ( isset( $selected['id'] ) && (int) $selected['id'] === (int) $row['id'] );
						?>
						<tr class="<?php echo $active ? 'current' : ''; ?>">
							<td>
								<strong><a href="<?php echo esc_url( $row['edit_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['title'] ); ?></a></strong>
							</td>
							<td><?php echo esc_html( sprintf( '%d / %s', $row['participants'], ( $row['required'] ? $row['required'] : '∞' ) ) ); ?></td>
							<td><a class="button" href="<?php echo esc_url( $detail_link ); ?>"><?php esc_html_e( 'View details', 'codex-ajax-auctions' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="codfaa-state-detail">
			<?php if ( empty( $selected ) ) : ?>
				<p><?php esc_html_e( 'Select an auction from the list to inspect participants, bids, and admin actions.', 'codex-ajax-auctions' ); ?></p>
			<?php else :
				$detail_url = add_query_arg( array( 'auction' => $selected['id'] ), $manage_base );
				?>
			<header class="codfaa-detail-header">
				<div>
					<h2><?php echo esc_html( $selected['title'] ); ?></h2>
					<?php if ( ! empty( $selected['permalink'] ) ) : ?>
						<a href="<?php echo esc_url( $selected['permalink'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-link"><?php esc_html_e( 'View auction page', 'codex-ajax-auctions' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="codfaa-detail-actions">
					<?php if ( Bidding_Service::STATE_ENDED === $selected['state'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="codfaa-inline-form">
							<?php wp_nonce_field( 'codfaa_restart_auction_' . $selected['id'] ); ?>
							<input type="hidden" name="action" value="codfaa_restart_auction" />
							<input type="hidden" name="auction_id" value="<?php echo esc_attr( $selected['id'] ); ?>" />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Restart auction', 'codex-ajax-auctions' ); ?></button>
						</form>
						<?php if ( ! empty( $selected['winner_claimed'] ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="codfaa-inline-form">
								<?php wp_nonce_field( 'codfaa_reset_claim_' . $selected['id'] ); ?>
								<input type="hidden" name="action" value="codfaa_reset_claim" />
								<input type="hidden" name="auction_id" value="<?php echo esc_attr( $selected['id'] ); ?>" />
								<button type="submit" class="button"><?php esc_html_e( 'Reset claim', 'codex-ajax-auctions' ); ?></button>
							</form>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</header>

				<ul class="codfaa-detail-meta">
					<li><strong><?php esc_html_e( 'State', 'codex-ajax-auctions' ); ?>:</strong> <?php echo esc_html( ucfirst( $selected['state'] ) ); ?></li>
					<li><strong><?php esc_html_e( 'Participants', 'codex-ajax-auctions' ); ?>:</strong> <?php echo esc_html( sprintf( '%d active / %d removed / %s required', $selected['counts']['active'], $selected['counts']['removed'], $selected['counts']['required'] ? $selected['counts']['required'] : '∞' ) ); ?></li>
					<li><strong><?php esc_html_e( 'Product', 'codex-ajax-auctions' ); ?>:</strong> <?php echo esc_html( $selected['product']['label'] ); ?><?php if ( $selected['product']['edit_link'] ) : ?> (<a href="<?php echo esc_url( $selected['product']['edit_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit', 'codex-ajax-auctions' ); ?></a>)<?php endif; ?></li>
					<li><strong><?php esc_html_e( 'Registration fee', 'codex-ajax-auctions' ); ?>:</strong> <?php echo esc_html( $selected['registration']['label'] ); ?><?php if ( $selected['registration']['edit_link'] ) : ?> (<a href="<?php echo esc_url( $selected['registration']['edit_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit', 'codex-ajax-auctions' ); ?></a>)<?php endif; ?></li>
					<?php if ( ! empty( $selected['ready_at'] ) ) : ?>
						<li><strong><?php esc_html_e( 'Countdown started', 'codex-ajax-auctions' ); ?>:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $selected['ready_at'] ) ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $selected['go_live_at'] ) ) : ?>
						<li><strong><?php esc_html_e( 'Scheduled go-live', 'codex-ajax-auctions' ); ?>:</strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $selected['go_live_at'] ) ); ?></li>
					<?php endif; ?>
				</ul>

				<section class="codfaa-detail-section">
					<header>
						<h3><?php esc_html_e( 'Participants', 'codex-ajax-auctions' ); ?></h3>
						<?php if ( ! $selected['allow_removal'] ) : ?>
							<p class="description"><?php esc_html_e( 'Countdown already started; removals are now locked.', 'codex-ajax-auctions' ); ?></p>
						<?php endif; ?>
					</header>

					<?php if ( empty( $selected['participants'] ) ) : ?>
						<p><?php esc_html_e( 'No confirmed participants yet.', 'codex-ajax-auctions' ); ?></p>
					<?php else : ?>
						<table class="widefat striped codfaa-participants">
							<thead>
							<tr>
								<th><?php esc_html_e( 'Participant', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Email', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Registered', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Order', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Bid fees', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Status', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'codex-ajax-auctions' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $selected['participants'] as $participant ) :
								$email_link = add_query_arg(
									array(
										'auction'    => $selected['id'],
										'email_user' => $participant['user_id'],
									),
									$manage_base
								);
								$redirect_url = $detail_url;
								?>
								<tr>
									<td><?php echo esc_html( $participant['name'] ); ?></td>
									<td><?php echo $participant['email'] ? esc_html( $participant['email'] ) : '&mdash;'; ?></td>
									<td><?php echo esc_html( $participant['registered_at'] ); ?></td>
									<td>
										<?php if ( $participant['order_link'] ) : ?>
											<a href="<?php echo esc_url( $participant['order_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $participant['order_label'] ); ?></a>
										<?php else : ?>
											<?php esc_html_e( '—', 'codex-ajax-auctions' ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo wp_kses_post( $participant['total_display'] ); ?></td>
									<td>
										<?php echo esc_html( $participant['status_label'] ); ?>
										<?php if ( $participant['removed_at'] ) : ?>
											<br><small><?php printf( esc_html__( 'Removed %1$s by %2$s', 'codex-ajax-auctions' ), esc_html( $participant['removed_at'] ), esc_html( $participant['removed_by'] ) ); ?></small>
										<?php endif; ?>
										<?php if ( $participant['removed_reason'] ) : ?>
											<br><small><?php echo esc_html( $participant['removed_reason'] ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<div class="codfaa-participant-actions">
											<a class="button button-link" href="<?php echo esc_url( $email_link ); ?>"><?php esc_html_e( 'Email', 'codex-ajax-auctions' ); ?></a>
											<?php if ( $participant['can_remove'] ) : ?>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
													<?php wp_nonce_field( 'codfaa_remove_participant_' . $selected['id'] . '_' . $participant['user_id'] ); ?>
													<input type="hidden" name="action" value="codfaa_remove_participant" />
													<input type="hidden" name="auction_id" value="<?php echo esc_attr( $selected['id'] ); ?>" />
													<input type="hidden" name="user_id" value="<?php echo esc_attr( $participant['user_id'] ); ?>" />
													<input type="hidden" name="redirect" value="<?php echo esc_attr( $redirect_url ); ?>" />
													<input type="text" class="codfaa-reason-field" name="reason" placeholder="<?php esc_attr_e( 'Reason (optional)', 'codex-ajax-auctions' ); ?>" />
													<button type="submit" class="button button-small button-secondary"><?php esc_html_e( 'Remove', 'codex-ajax-auctions' ); ?></button>
												</form>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</section>

				<section class="codfaa-detail-section">
					<header><h3><?php esc_html_e( 'Recent bids', 'codex-ajax-auctions' ); ?></h3></header>
					<?php if ( empty( $selected['bids'] ) ) : ?>
						<p><?php esc_html_e( 'No bid activity yet.', 'codex-ajax-auctions' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
							<tr>
								<th><?php esc_html_e( 'Bidder', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Placed at', 'codex-ajax-auctions' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $selected['bids'] as $bid ) : ?>
								<tr>
									<td><?php echo esc_html( $bid['user'] ); ?></td>
									<td><?php echo wp_kses_post( $bid['amount'] ); ?></td>
									<td><?php echo esc_html( $bid['created_at'] ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</section>

				<section class="codfaa-detail-section">
					<header><h3><?php esc_html_e( 'Admin activity', 'codex-ajax-auctions' ); ?></h3></header>
					<?php if ( empty( $selected['logs'] ) ) : ?>
						<p><?php esc_html_e( 'No admin actions recorded yet.', 'codex-ajax-auctions' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
							<tr>
								<th><?php esc_html_e( 'Action', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Details', 'codex-ajax-auctions' ); ?></th>
								<th><?php esc_html_e( 'Timestamp', 'codex-ajax-auctions' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $selected['logs'] as $log ) : ?>
								<tr>
									<td><?php echo esc_html( ucfirst( $log['type'] ) ); ?></td>
									<td>
										<?php if ( $log['actor'] ) : ?>
											<strong><?php esc_html_e( 'By:', 'codex-ajax-auctions' ); ?></strong> <?php echo esc_html( $log['actor'] ); ?><br>
										<?php endif; ?>
										<?php if ( $log['target'] ) : ?>
											<strong><?php esc_html_e( 'Target:', 'codex-ajax-auctions' ); ?></strong> <?php echo esc_html( $log['target'] ); ?><br>
										<?php endif; ?>
										<?php if ( $log['context'] ) : ?>
											<strong><?php esc_html_e( 'Note:', 'codex-ajax-auctions' ); ?></strong> <?php echo esc_html( $log['context'] ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $log['timestamp'] ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</section>

				<section class="codfaa-detail-section">
					<header><h3><?php esc_html_e( 'Email participant', 'codex-ajax-auctions' ); ?></h3></header>
					<?php if ( $email_user_id && $email_recipient ) : ?>
						<p><?php printf( esc_html__( 'Message to %s (%s).', 'codex-ajax-auctions' ), esc_html( $email_recipient['name'] ), esc_html( $email_recipient['email'] ) ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="codfaa-email-form">
							<?php wp_nonce_field( 'codfaa_email_participant_' . $selected['id'] . '_' . $email_user_id ); ?>
							<input type="hidden" name="action" value="codfaa_email_participant" />
							<input type="hidden" name="auction_id" value="<?php echo esc_attr( $selected['id'] ); ?>" />
							<input type="hidden" name="user_id" value="<?php echo esc_attr( $email_user_id ); ?>" />
							<input type="hidden" name="redirect" value="<?php echo esc_attr( $detail_url ); ?>" />
							<p>
								<label for="codfaa-email-subject" class="screen-reader-text"><?php esc_html_e( 'Subject', 'codex-ajax-auctions' ); ?></label>
								<input type="text" id="codfaa-email-subject" name="subject" class="widefat" required placeholder="<?php esc_attr_e( 'Subject', 'codex-ajax-auctions' ); ?>" />
							</p>
							<p>
								<label for="codfaa-email-body" class="screen-reader-text"><?php esc_html_e( 'Message', 'codex-ajax-auctions' ); ?></label>
								<textarea id="codfaa-email-body" name="message" rows="5" class="widefat" required placeholder="<?php esc_attr_e( 'Message to participant...', 'codex-ajax-auctions' ); ?>"></textarea>
							</p>
							<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Send email', 'codex-ajax-auctions' ); ?></button></p>
						</form>
					<?php else : ?>
						<p><?php esc_html_e( 'Pick “Email” next to a participant to compose a message.', 'codex-ajax-auctions' ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>
		</div>
	</div>
</div>
