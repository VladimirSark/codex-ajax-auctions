<?php
/**
 * Admin statistics view for Codex auctions.
 *
 * @var array $groups Grouped auctions data.
 */

use Codfaa\Auctions\Bidding_Service;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'codfaa_render_table' ) ) {
	function codfaa_render_table( $title, $rows, $state ) {
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'Nothing to show yet.', 'codex-ajax-auctions' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<table class="widefat striped codfaa-dashboard-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Auction', 'codex-ajax-auctions' ); ?></th>
				<th><?php esc_html_e( 'Product', 'codex-ajax-auctions' ); ?></th>
				<th><?php esc_html_e( 'Registration', 'codex-ajax-auctions' ); ?></th>
				<th><?php esc_html_e( 'Participants', 'codex-ajax-auctions' ); ?></th>
				<?php if ( Bidding_Service::STATE_UPCOMING === $state ) : ?>
					<?php /* upcoming has threshold only */ ?>
				<?php elseif ( Bidding_Service::STATE_LIVE === $state ) : ?>
					<th><?php esc_html_e( 'Highest Bidder', 'codex-ajax-auctions' ); ?></th>
					<th><?php esc_html_e( 'Highest Bid Total', 'codex-ajax-auctions' ); ?></th>
				<?php elseif ( Bidding_Service::STATE_ENDED === $state ) : ?>
					<th><?php esc_html_e( 'Registration Fees', 'codex-ajax-auctions' ); ?></th>
					<th><?php esc_html_e( 'Winner', 'codex-ajax-auctions' ); ?></th>
					<th><?php esc_html_e( 'Winner Bid Fees', 'codex-ajax-auctions' ); ?></th>
					<th><?php esc_html_e( 'Total (Reg + Bid)', 'codex-ajax-auctions' ); ?></th>
					<th><?php esc_html_e( 'Claimed', 'codex-ajax-auctions' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Actions', 'codex-ajax-auctions' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( $row['edit_link'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></strong>
					</td>
					<td><?php echo esc_html( $row['product'] ); ?></td>
					<td><?php echo esc_html( $row['registration'] ); ?></td>
					<td><?php echo esc_html( sprintf( '%d / %s', $row['participants'], ( $row['required'] ? $row['required'] : '∞' ) ) ); ?></td>

					<?php if ( Bidding_Service::STATE_LIVE === $state ) : ?>
						<td><?php echo esc_html( $row['last_bidder'] ); ?></td>
						<td><?php echo wp_kses_post( $row['last_bid_total_display'] ); ?></td>
					<?php elseif ( Bidding_Service::STATE_ENDED === $state ) : ?>
						<?php $combined_minor = ( $row['registration_total_minor'] ?? 0 ) + ( $row['winner_total_minor'] ?? 0 ); ?>
						<td><?php echo wp_kses_post( $row['registration_total_display'] ); ?></td>
						<td><?php echo esc_html( $row['winner_display'] ); ?></td>
						<td><?php echo wp_kses_post( $row['winner_total_display'] ); ?></td>
						<td><?php echo $combined_minor ? wp_kses_post( wc_price( $combined_minor / 100 ) ) : esc_html( '—' ); ?></td>
						<td><?php echo $row['winner_claimed'] ? esc_html__( 'Yes', 'codex-ajax-auctions' ) : esc_html__( 'No', 'codex-ajax-auctions' ); ?></td>
					<?php else : ?>
						<?php /* upcoming no extra columns */ ?>
					<?php endif; ?>

					<td class="codfaa-dashboard-actions">
						<a class="button button-link" href="<?php echo esc_url( $row['edit_link'] ); ?>"><?php esc_html_e( 'Edit', 'codex-ajax-auctions' ); ?></a>
						<?php if ( Bidding_Service::STATE_UPCOMING === $state ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'codfaa_start_auction_' . $row['id'] ); ?>
								<input type="hidden" name="action" value="codfaa_start_auction" />
								<input type="hidden" name="auction_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Start auction', 'codex-ajax-auctions' ); ?></button>
							</form>
					<?php elseif ( Bidding_Service::STATE_LIVE === $state ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<?php wp_nonce_field( 'codfaa_end_auction_' . $row['id'] ); ?>
							<input type="hidden" name="action" value="codfaa_end_auction" />
							<input type="hidden" name="auction_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'End auction', 'codex-ajax-auctions' ); ?></button>
						</form>
					<?php elseif ( Bidding_Service::STATE_ENDED === $state ) : ?>
						<?php if ( $row['winner_claimed'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'codfaa_reset_claim_' . $row['id'] ); ?>
								<input type="hidden" name="action" value="codfaa_reset_claim" />
								<input type="hidden" name="auction_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
								<button type="submit" class="button"><?php esc_html_e( 'Reset claim', 'codex-ajax-auctions' ); ?></button>
							</form>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<?php wp_nonce_field( 'codfaa_restart_auction_' . $row['id'] ); ?>
							<input type="hidden" name="action" value="codfaa_restart_auction" />
							<input type="hidden" name="auction_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Restart auction', 'codex-ajax-auctions' ); ?></button>
						</form>
					<?php endif; ?>
				</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

?>
<div class="wrap codfaa-dashboard">
	<h1><?php esc_html_e( 'Auction Statistics', 'codex-ajax-auctions' ); ?></h1>
	<p><?php esc_html_e( 'High-level overview of every auction, grouped by state. Use the Upcoming/Live/Ended menus for deep participant management.', 'codex-ajax-auctions' ); ?></p>

	<?php
	codfaa_render_table(
		__( 'Upcoming auctions', 'codex-ajax-auctions' ),
		$groups[ Bidding_Service::STATE_UPCOMING ],
		Bidding_Service::STATE_UPCOMING
	);

	codfaa_render_table(
		__( 'Live auctions', 'codex-ajax-auctions' ),
		$groups[ Bidding_Service::STATE_LIVE ],
		Bidding_Service::STATE_LIVE
	);

	codfaa_render_table(
		__( 'Ended auctions', 'codex-ajax-auctions' ),
		$groups[ Bidding_Service::STATE_ENDED ],
		Bidding_Service::STATE_ENDED
	);
	?>
</div>
