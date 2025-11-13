<?php
/**
 * Auction shortcode view tailored to the UI mockups.
 */

defined( 'ABSPATH' ) || exit;

$product_image      = $product ? $product->get_image() : '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="" />';
$product_name       = $product ? $product->get_name() : get_the_title( $auction );
$product_price      = $product ? $product->get_price_html() : __( 'Price not available', 'codex-ajax-auctions' );
$registration_price = $registration ? $registration->get_price_html() : __( 'Not set', 'codex-ajax-auctions' );
$bid_fee_price      = $bid_product ? $bid_product->get_price_html() : __( 'Not set', 'codex-ajax-auctions' );
$progress_percent   = max( 0, min( 100, round( $progress_percent ) ) );
$progress_label     = sprintf(
	__( 'Now Registered %s%%. Share this auction to reach 100%%', 'codex-ajax-auctions' ),
	number_format_i18n( $progress_percent )
);

if ( ! isset( $prelive_initial_formatted ) ) {
	$prelive_initial_formatted = $prelive_remaining > 0 ? gmdate( 'i:s', $prelive_remaining ) : '00:00';
}

$product_short_description = '';

if ( $product instanceof WC_Product ) {
	$product_short_description = $product->get_short_description();

	if ( '' === trim( $product_short_description ) ) {
		$product_short_description = $product->get_description();
	}
}

$product_short_description = $product_short_description ? wpautop( $product_short_description ) : '';
$modal_id                 = 'codfaa-product-modal-' . $auction->ID;

$initial_timer_formatted = isset( $initial_remaining_formatted )
	? $initial_remaining_formatted
	: gmdate( 'i:s', max( 0, (int) $initial_remaining ) );

$prelive_total = isset( $prelive_duration ) ? max( 0, (int) $prelive_duration ) : 0;
if ( $prelive_total <= 0 && $prelive_remaining > 0 ) {
	$prelive_total = $prelive_remaining;
}
$prelive_progress = $prelive_total > 0 ? max( 0, min( 100, ( $prelive_remaining / $prelive_total ) * 100 ) ) : 0;
$show_prelive     = false; // Countdown now handled inside the bid card overlay.

$timer_progress = ( $timer > 0 && $initial_remaining >= 0 ) ? max( 0, min( 100, ( $initial_remaining / $timer ) * 100 ) ) : 0;
$show_live_timer = ( isset( $current_state ) && \Codfaa\Auctions\Bidding_Service::STATE_LIVE === $current_state ) || $ended;

$status_card_variant = $initial_status_variant ? $initial_status_variant : 'info';
$status_card_classes = 'codfaa-bid-stat codfaa-bid-stat--state codfaa-bid-stat--' . sanitize_html_class( $status_card_variant );
$status_card_hidden  = trim( (string) $initial_status_message ) === '' ? 'style="display:none;"' : '';

$ready_lock_copy      = __( 'Well done, lobby is full. We are giving everyone time to get ready.', 'codex-ajax-auctions' );
$registered_lock_copy = __( 'You are registered. Wait for the pre-live countdown.', 'codex-ajax-auctions' );
$lock_message         = '';
if ( ! $ended && \Codfaa\Auctions\Bidding_Service::STATE_LIVE !== $current_state ) {
	if ( $ready && $prelive_remaining > 0 ) {
		$lock_message = $ready_lock_copy;
	} elseif ( $is_registered ) {
		$lock_message = $registered_lock_copy;
	} else {
		$lock_message = __( 'Complete Step 1 & wait for the pre-live countdown.', 'codex-ajax-auctions' );
	}
}
$lock_overlay_copy = $lock_message ? $lock_message : __( 'Complete Step 1 & wait for the pre-live countdown.', 'codex-ajax-auctions' );
$is_locked         = ! $ended && \Codfaa\Auctions\Bidding_Service::STATE_LIVE !== $current_state;

$winner_claim_total_display = isset( $winner_claim_total_display ) && $winner_claim_total_display ? $winner_claim_total_display : $winner_total_display;

$result_text    = '';
$result_variant = '';
if ( $ended ) {
	if ( $user_is_winner ) {
		$winner_total_to_show = ! empty( $winner_claim_total_display ) ? $winner_claim_total_display : ( $winner_total_display ? $winner_total_display : $bid_fee_price );
		$result_text          = sprintf( __( 'Claim your reward for %s', 'codex-ajax-auctions' ), $winner_total_to_show );
		$result_variant       = 'win';
	} else {
		$result_text    = __( 'You lost this time. No further payments are required.', 'codex-ajax-auctions' );
		$result_variant = 'lost';
	}
}

$history_note = __( 'Every bid resets the timer. When it hits zero, the last bidder wins.', 'codex-ajax-auctions' );
$bid_history  = ! empty( $recent_bidders ) ? $recent_bidders : array();

$register_copy         = sprintf( __( 'Join for %s', 'codex-ajax-auctions' ), wp_strip_all_tags( $registration_price ) );
$can_join              = $registration && $is_logged_in && ! $is_registered && ! $registration_pending;
$login_url             = wp_login_url( $display_url );
$register_status_class = $is_registered ? 'is-success' : ( $registration_pending ? 'is-warning' : 'is-muted' );
$register_status_label = $is_registered ? __( 'Registered', 'codex-ajax-auctions' ) : ( $registration_pending ? __( 'Pending approval', 'codex-ajax-auctions' ) : __( 'Not registered', 'codex-ajax-auctions' ) );

$bid_button_classes = 'codfaa-btn codfaa-btn--dark codfaa-place-bid';
if ( $can_bid ) {
	$bid_button_classes .= ' is-active';
}

$claim_classes = 'codfaa-btn codfaa-claim-prize codfaa-claim-btn';
if ( $ended && $user_is_winner && ! $winner_claimed ) {
	$claim_classes .= ' is-visible';
}

$pending_notice = __( 'Registration received. Waiting for admin confirmation.', 'codex-ajax-auctions' );
?>
<div class="codfaa-auction-experience codfaa-auction-card"
	data-auction="<?php echo esc_attr( $auction->ID ); ?>"
	data-timer="<?php echo esc_attr( $timer ); ?>"
	data-remaining="<?php echo esc_attr( max( 0, (int) $initial_remaining ) ); ?>"
	data-participants="<?php echo esc_attr( $participant_count ); ?>"
	data-required="<?php echo esc_attr( $required ); ?>"
	data-progress="<?php echo esc_attr( $progress_percent ); ?>"
	data-state="<?php echo esc_attr( $current_state ); ?>"
	data-last-bid-user="<?php echo esc_attr( $last_bid_user ); ?>"
	data-last-bid-display="<?php echo esc_attr( $last_bid_display ); ?>"
	data-status-variant="<?php echo esc_attr( $initial_status_variant ); ?>"
	data-initial-status="<?php echo esc_attr( $initial_status_message ); ?>"
	data-can-bid="<?php echo esc_attr( $can_bid ? 1 : 0 ); ?>"
	data-ended="<?php echo esc_attr( $ended ? 1 : 0 ); ?>"
	data-ready="<?php echo esc_attr( $ready ? 1 : 0 ); ?>"
	data-prelive="<?php echo esc_attr( $prelive_remaining ); ?>"
	data-prelive-total="<?php echo esc_attr( $prelive_total ); ?>"
	data-go-live="<?php echo esc_attr( $go_live_at ); ?>"
	data-user-registered="<?php echo esc_attr( $is_registered ? 1 : 0 ); ?>"
	data-user-winner="<?php echo esc_attr( $user_is_winner ? 1 : 0 ); ?>"
	data-registration-pending="<?php echo esc_attr( $registration_pending ? 1 : 0 ); ?>"
	data-winner-claimed="<?php echo esc_attr( $winner_claimed ? 1 : 0 ); ?>"
	data-winner-total-display="<?php echo esc_attr( wp_strip_all_tags( $winner_total_display ) ); ?>"
	data-winner-bid-count="<?php echo esc_attr( $winner_bid_count ); ?>"
>
	<div class="codfaa-container">
		<div class="codfaa-product-card">
			<div class="codfaa-product-card__image"><?php echo wp_kses_post( $product_image ); ?></div>
			<div class="codfaa-product-card__body">
				<p class="codfaa-product-card__eyebrow"><?php esc_html_e( 'Auction product', 'codex-ajax-auctions' ); ?></p>
				<h2><?php echo esc_html( $product_name ); ?></h2>
				<ul>
					<li>
						<?php esc_html_e( 'Retail price:', 'codex-ajax-auctions' ); ?>
						<?php echo wp_kses_post( $product_price ); ?>
					</li>
					<li><?php esc_html_e( 'Claim it for €1 at checkout if you win.', 'codex-ajax-auctions' ); ?></li>
				</ul>
				<div class="codfaa-product-card__cta-group">
					<button type="button" class="codfaa-btn codfaa-btn--ghost codfaa-product-card__cta" data-codfaa-modal-open="<?php echo esc_attr( $modal_id ); ?>"><?php esc_html_e( 'Quick view', 'codex-ajax-auctions' ); ?></button>
					<?php if ( ! empty( $product_link ) ) : ?>
						<a class="codfaa-btn codfaa-btn--outline codfaa-product-card__cta" target="_blank" rel="noopener" href="<?php echo esc_url( $product_link ); ?>"><?php esc_html_e( 'View product', 'codex-ajax-auctions' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<section class="codfaa-card codfaa-card--pricing codfaa-card--compact">
			<div class="codfaa-price-grid">
				<article>
					<p><?php esc_html_e( 'Registration', 'codex-ajax-auctions' ); ?></p>
					<strong><?php echo wp_kses_post( $registration_price ); ?></strong>
				</article>
				<article>
					<p><?php esc_html_e( 'Bid fee', 'codex-ajax-auctions' ); ?></p>
					<strong><?php echo wp_kses_post( $bid_fee_price ); ?></strong>
				</article>
				<article>
					<p><?php esc_html_e( 'Timer duration', 'codex-ajax-auctions' ); ?></p>
					<strong><?php echo esc_html( $timer ? sprintf( _n( '%d second', '%d seconds', $timer, 'codex-ajax-auctions' ), $timer ) : __( 'N/A', 'codex-ajax-auctions' ) ); ?></strong>
				</article>
			</div>
		</section>

	<?php
	$register_classes      = "codfaa-card codfaa-card--primary codfaa-card--register";
	$hide_register        = ( $ready || $ended );
	$status_stat_classes = "codfaa-register-stat codfaa-register-stat--status";
	if ( $is_registered ) {
		$status_stat_classes .= " is-success";
	} elseif ( ! $registration_pending ) {
		$status_stat_classes .= " is-warning";
	}
		if ( $hide_register ) {
			$register_classes .= " is-hidden";
		}
	?>
	<section class="<?php echo esc_attr( $register_classes ); ?>" data-codfaa-register-card>
			<header class="codfaa-register-header">
				<div class="codfaa-register-heading">
					<span class="codfaa-register-step">1</span>
					<h2><?php esc_html_e( 'Register', 'codex-ajax-auctions' ); ?></h2>
				</div>
			</header>

		<div class="codfaa-progress-line codfaa-progress-line--lobby">
			<div class="codfaa-progress">
				<div class="codfaa-progress__bar" data-codfaa-progress-bar style="width: <?php echo esc_attr( $progress_percent ); ?>%;"></div>
			</div>
			<p data-codfaa-progress-label><?php echo esc_html( $progress_label ); ?></p>
		</div>

		<div class="codfaa-register-grid codfaa-register-grid--cards">
			<article class="codfaa-register-stat">
				<p><?php esc_html_e( 'Registration fee', 'codex-ajax-auctions' ); ?></p>
				<strong><?php echo wp_kses_post( $registration_price ); ?></strong>
			</article>
			<article class="<?php echo esc_attr( $status_stat_classes ); ?>">
				<p><?php esc_html_e( 'Status', 'codex-ajax-auctions' ); ?></p>
				<strong class="codfaa-register-state codfaa-register-state--<?php echo esc_attr( $is_registered ? 'success' : ( $registration_pending ? 'pending' : 'muted' ) ); ?>">
					<?php echo esc_html( $is_registered ? __( 'Registered', 'codex-ajax-auctions' ) : ( $registration_pending ? __( 'Pending approval', 'codex-ajax-auctions' ) : __( 'Not registered', 'codex-ajax-auctions' ) ) ); ?>
				</strong>
			</article>
		</div>

			<p class="codfaa-register-note" data-codfaa-registration-pending <?php echo $registration_pending && ! $is_registered ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $pending_notice ); ?></p>

	<?php
		$consent_disabled = $is_registered || $registration_pending;
	?>
	<label class="codfaa-checkbox codfaa-checkbox--legal">
		<input type="checkbox" class="codfaa-register-consent" data-codfaa-consent="1" <?php echo $consent_disabled ? 'checked="checked" disabled="disabled"' : ''; ?> />
		<span>
			<?php esc_html_e( 'I accept the', 'codex-ajax-auctions' ); ?>&nbsp;
			<?php if ( $terms_content ) : ?>
				<button type="button" class="codfaa-terms-link" data-codfaa-terms-open="1"><?php esc_html_e( 'Terms & Conditions', 'codex-ajax-auctions' ); ?></button>
			<?php else : ?>
				<span class="codfaa-terms-link codfaa-terms-link--static"><?php esc_html_e( 'Terms & Conditions', 'codex-ajax-auctions' ); ?></span>
			<?php endif; ?>.
		</span>
	</label>
	<?php
		$consent_hint_style = $consent_disabled ? 'style="display:none;"' : 'style="display:block;"';
	?>
	<p class="codfaa-consent-hint" data-codfaa-consent-hint <?php echo $consent_hint_style; ?>><?php esc_html_e( 'Please accept the Terms & Conditions to enable registration.', 'codex-ajax-auctions' ); ?></p>

			<div class="codfaa-register-actions">
		<?php if ( ! $is_logged_in ) : ?>
			<a class="codfaa-btn codfaa-btn--dark codfaa-btn--full" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in to join', 'codex-ajax-auctions' ); ?></a>
		<?php elseif ( ! $registration ) : ?>
			<button class="codfaa-btn codfaa-btn--dark codfaa-btn--full" disabled="disabled"><?php esc_html_e( 'Registration unavailable', 'codex-ajax-auctions' ); ?></button>
	<?php elseif ( $is_registered ) : ?>
		<button class="codfaa-btn codfaa-btn--success codfaa-btn--full" disabled="disabled"><?php esc_html_e( 'Registered successfully', 'codex-ajax-auctions' ); ?></button>
		<?php elseif ( $registration_pending ) : ?>
			<button class="codfaa-btn codfaa-btn--dark codfaa-btn--full" disabled="disabled"><?php esc_html_e( 'Awaiting confirmation', 'codex-ajax-auctions' ); ?></button>
	<?php elseif ( $can_join ) : ?>
		<button type="button" class="codfaa-btn codfaa-btn--success codfaa-btn--full codfaa-register" data-auction="<?php echo esc_attr( $auction->ID ); ?>" data-return="<?php echo esc_url( $display_url ); ?>" aria-disabled="true" disabled="disabled"><?php echo esc_html( $register_copy ); ?></button>
		<?php else : ?>
			<button class="codfaa-btn codfaa-btn--dark codfaa-btn--full" disabled="disabled"><?php esc_html_e( 'Join unavailable', 'codex-ajax-auctions' ); ?></button>
		<?php endif; ?>
			</div>

		</section>

		<section class="codfaa-card codfaa-bid-card <?php echo $is_locked ? 'is-locked' : ''; ?>">
			<div class="codfaa-bid-card__body">
				<header class="codfaa-bid-card__header">
					<span class="codfaa-step-dot">2</span>
					<h2><?php esc_html_e( 'Bid live', 'codex-ajax-auctions' ); ?></h2>
				</header>

				<div class="codfaa-bid-card__timer" data-codfaa-timer-wrapper <?php echo $show_live_timer ? '' : 'style="display:none;"'; ?>>
					<span class="codfaa-bid-card__timer-label">
						<?php esc_html_e( 'Timer:', 'codex-ajax-auctions' ); ?>
						<strong data-codfaa-timer><?php echo esc_html( $initial_timer_formatted ); ?></strong>
					</span>
					<div class="codfaa-progress codfaa-progress--live">
						<div class="codfaa-progress__bar" data-codfaa-live-progress style="width: <?php echo esc_attr( $timer_progress ); ?>%;"></div>
					</div>
				</div>
				<p class="codfaa-bid-card__timer-pending" data-codfaa-timer-pending <?php echo $show_live_timer || $ended ? 'style="display:none;"' : ''; ?>>
					<?php if ( $timer ) : ?>
						<?php printf( esc_html__( 'Live timer set to %s seconds.', 'codex-ajax-auctions' ), esc_html( $timer ) ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Timer will appear once the auction is live.', 'codex-ajax-auctions' ); ?>
					<?php endif; ?>
				</p>

				<button type="button" class="<?php echo esc_attr( $bid_button_classes ); ?>" data-auction="<?php echo esc_attr( $auction->ID ); ?>" <?php echo $can_bid ? '' : 'aria-disabled="true" disabled="disabled"'; ?>>
					<?php printf( esc_html__( 'Place bid (%s)', 'codex-ajax-auctions' ), wp_strip_all_tags( $bid_fee_price ) ); ?>
				</button>

		<div class="codfaa-bid-stats">
			<article class="codfaa-bid-stat">
				<p><?php esc_html_e( 'Your bids', 'codex-ajax-auctions' ); ?></p>
				<strong data-codfaa-bid-count><?php echo esc_html( $user_bid_count ); ?></strong>
			</article>
			<article class="codfaa-bid-stat">
				<p><?php esc_html_e( 'Your current cost', 'codex-ajax-auctions' ); ?></p>
				<strong data-codfaa-bid-total data-minor="<?php echo esc_attr( $user_total_minor ); ?>"><?php echo wp_kses_post( $user_total_display ); ?></strong>
			</article>
			<article class="<?php echo esc_attr( $status_card_classes ); ?>" data-codfaa-status-card <?php echo $status_card_hidden; ?>>
				<p><?php esc_html_e( 'Bid status', 'codex-ajax-auctions' ); ?></p>
				<strong data-codfaa-status><?php echo esc_html( $initial_status_message ); ?></strong>
			</article>
		</div>

			<div class="codfaa-bid-history">
				<h3><?php esc_html_e( 'Recent bids', 'codex-ajax-auctions' ); ?></h3>
				<ul data-codfaa-recent-bidders>
					<?php if ( $bid_history ) : ?>
						<?php foreach ( $bid_history as $bidder ) : ?>
							<li>
								<strong class="codfaa-bid-history__name"><?php echo esc_html( $bidder['name'] ); ?></strong>
								<?php if ( ! empty( $bidder['timestamp'] ) ) : ?>
									<time datetime="<?php echo esc_attr( $bidder['timestampRaw'] ?? '' ); ?>"><?php echo esc_html( $bidder['timestamp'] ); ?></time>
								<?php endif; ?>
								<?php if ( ! empty( $bidder['totalDisplay'] ) ) : ?>
									<span class="codfaa-bid-history__amount"><?php echo wp_kses_post( $bidder['totalDisplay'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					<?php else : ?>
						<li class="codfaa-recent__empty"><?php esc_html_e( 'No bids yet.', 'codex-ajax-auctions' ); ?></li>
					<?php endif; ?>
				</ul>
				<p class="codfaa-bid-note"><?php echo esc_html( $history_note ); ?></p>
			</div>

			<div class="codfaa-bid-outcome">
		<div class="codfaa-bid-result <?php echo esc_attr( $result_variant ? 'is-' . $result_variant : '' ); ?>" data-codfaa-winner-summary><?php echo wp_kses_post( $result_text ); ?></div>
				<a href="#" class="<?php echo esc_attr( $claim_classes ); ?>" data-auction="<?php echo esc_attr( $auction->ID ); ?>" data-label="<?php echo esc_attr( $claim_label ); ?>" aria-hidden="<?php echo esc_attr( $ended && $user_is_winner && ! $winner_claimed ? 'false' : 'true' ); ?>"><?php echo esc_html( $claim_label ); ?></a>
			</div>
		</div>

			<div class="codfaa-bid-card__overlay" data-codfaa-lock aria-hidden="<?php echo $is_locked ? 'false' : 'true'; ?>">
				<span class="codfaa-lock-icon" aria-hidden="true">
					<svg viewBox="0 0 20 20" role="presentation" focusable="false">
						<path d="M14.5 9h-.75V6.5a3.75 3.75 0 0 0-7.5 0V9H5.5A1.5 1.5 0 0 0 4 10.5v6A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-6A1.5 1.5 0 0 0 14.5 9Zm-6.75-2.5a2.25 2.25 0 0 1 4.5 0V9h-4.5Zm6.5 10H5.75v-5.5h8.5Z" fill="currentColor" />
					</svg>
				</span>
				<p class="codfaa-lock-heading"><?php esc_html_e( 'Locked', 'codex-ajax-auctions' ); ?></p>
				<p class="codfaa-lock-message" data-codfaa-lock-message><?php echo esc_html( $lock_overlay_copy ); ?></p>
				<p class="codfaa-lock-countdown" data-codfaa-lock-countdown <?php echo ( $ready && $prelive_remaining > 0 && ! $ended ) ? '' : 'style="display:none;"'; ?>>
					<span><?php esc_html_e( 'Auction goes live in', 'codex-ajax-auctions' ); ?></span>
					<strong data-codfaa-lock-timer><?php echo esc_html( $prelive_initial_formatted ); ?></strong>
				</p>
				<p class="codfaa-lock-footer"><?php esc_html_e( 'Good luck!', 'codex-ajax-auctions' ); ?></p>
			</div>
		</section>

		<div class="codfaa-product-modal" data-codfaa-modal="<?php echo esc_attr( $modal_id ); ?>" aria-hidden="true">
			<div class="codfaa-product-modal__overlay" data-codfaa-modal-close></div>
			<div class="codfaa-product-modal__dialog" role="dialog" aria-modal="true">
				<button type="button" class="codfaa-product-modal__close" data-codfaa-modal-close aria-label="<?php esc_attr_e( 'Close quick view', 'codex-ajax-auctions' ); ?>">&times;</button>
				<div class="codfaa-product-modal__media"><?php echo wp_kses_post( $product_image ); ?></div>
				<div class="codfaa-product-modal__body">
					<h3><?php echo esc_html( $product_name ); ?></h3>
					<p><?php esc_html_e( 'Full product details available on the product page.', 'codex-ajax-auctions' ); ?></p>
					<p class="codfaa-product-modal__price"><?php echo wp_kses_post( $product_price ); ?></p>
					<?php if ( ! empty( $product_link ) ) : ?>
						<a class="codfaa-btn codfaa-btn--dark" target="_blank" rel="noopener" href="<?php echo esc_url( $product_link ); ?>"><?php esc_html_e( 'Open product page', 'codex-ajax-auctions' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>

<?php if ( $terms_content ) : ?>
	<div class="codfaa-terms-modal" data-codfaa-terms-modal aria-hidden="true">
		<div class="codfaa-terms-modal__overlay" data-codfaa-terms-close></div>
		<div class="codfaa-terms-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Terms & Conditions', 'codex-ajax-auctions' ); ?>">
			<button type="button" class="codfaa-terms-modal__close" data-codfaa-terms-close aria-label="<?php esc_attr_e( 'Close Terms & Conditions', 'codex-ajax-auctions' ); ?>">&times;</button>
			<div class="codfaa-terms-modal__content">
				<?php echo wp_kses_post( $terms_content ); ?>
			</div>
		</div>
	</div>
<?php endif; ?>


	</div>
</div>
