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
	__( 'Progress: %s%%', 'codex-ajax-auctions' ),
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
		$result_text    = __( "You're the winner", 'codex-ajax-auctions' );
		$result_variant = 'win';
	} else {
		$result_text    = __( 'You lost this time. No further payments are required.', 'codex-ajax-auctions' );
		$result_variant = 'lost';
	}
}

$history_note = __( 'Every bid resets the timer. When it hits zero, the last bidder wins.', 'codex-ajax-auctions' );
$bid_history  = ! empty( $recent_bidders ) ? $recent_bidders : array();

$register_copy         = sprintf( __( 'Register for %s', 'codex-ajax-auctions' ), wp_strip_all_tags( $registration_price ) );
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
$share_message  = __( 'Wait for other participants. Share to reach 100%.', 'codex-ajax-auctions' );

$registration_stage_classes = 'codfaa-stage codfaa-stage--registration';
if ( $is_registered || $ended ) {
	$registration_stage_classes .= ' is-complete';
} else {
	$registration_stage_classes .= ' is-active';
}

$countdown_stage_classes = 'codfaa-stage codfaa-stage--countdown';
$countdown_completed     = ( \Codfaa\Auctions\Bidding_Service::STATE_LIVE === $current_state ) || $ended;
$countdown_active        = ! $countdown_completed && $ready && $is_registered;
if ( $countdown_completed ) {
	$countdown_stage_classes .= ' is-complete';
} elseif ( $countdown_active ) {
	$countdown_stage_classes .= ' is-active';
} else {
	$countdown_stage_classes .= ' is-locked';
}

$live_stage_classes = 'codfaa-stage codfaa-stage--live codfaa-bid-card';
if ( $ended ) {
	$live_stage_classes .= ' is-complete';
} elseif ( \Codfaa\Auctions\Bidding_Service::STATE_LIVE === $current_state ) {
	$live_stage_classes .= ' is-active';
} else {
	$live_stage_classes .= ' is-locked';
}

$ended_stage_classes = 'codfaa-stage codfaa-stage--result';
if ( $ended ) {
	$ended_stage_classes .= ' is-active is-complete';
} else {
	$ended_stage_classes .= ' is-locked';
}

$claim_value_text = $winner_claim_total_display ? wp_strip_all_tags( $winner_claim_total_display ) : wp_strip_all_tags( $registration_price );
$timer_label      = $timer ? sprintf( _n( '%d sec.', '%d sec.', $timer, 'codex-ajax-auctions' ), $timer ) : __( 'Timer TBD', 'codex-ajax-auctions' );

if ( ! function_exists( 'codfaa_stage_icon_markup' ) ) {
	function codfaa_stage_icon_markup( $type ) {
		if ( 'check' === $type ) {
			return '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8.1 13.2l-3.3-3.3-1.4 1.4 4.7 4.7 8-8-1.4-1.4z" /></svg>';
		}

		if ( 'lock' === $type ) {
			return '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14 8h-1V6a3 3 0 0 0-6 0v2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2zm-6-2a2 2 0 0 1 4 0v2H8zm6 10H6v-6h8z" /></svg>';
		}

		return '';
	}
}
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
		<div class="codfaa-auction-layout">
			<section class="codfaa-product-panel">
				<div class="codfaa-product-frame">
					<div class="codfaa-product-media"><?php echo wp_kses_post( $product_image ); ?></div>
					<div class="codfaa-product-summary">
						<h2><?php echo esc_html( $product_name ); ?></h2>
						<ul class="codfaa-product-bullets">
							<li>
								<strong><?php esc_html_e( 'Retail price:', 'codex-ajax-auctions' ); ?></strong>
								<?php echo wp_kses_post( $product_price ); ?>
							</li>
							<li>
								<?php printf( esc_html__( 'Claim it for %s at checkout if you win.', 'codex-ajax-auctions' ), esc_html( $claim_value_text ) ); ?>
							</li>
						</ul>
						<div class="codfaa-product-cta">
							<button type="button" class="codfaa-btn codfaa-btn--ghost" data-codfaa-modal-open="<?php echo esc_attr( $modal_id ); ?>"><?php esc_html_e( 'Quick view', 'codex-ajax-auctions' ); ?></button>
							<?php if ( ! empty( $product_link ) ) : ?>
								<a class="codfaa-product-link" target="_blank" rel="noopener" href="<?php echo esc_url( $product_link ); ?>"><?php esc_html_e( 'View product', 'codex-ajax-auctions' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="codfaa-product-meta">
					<article>
						<p><?php esc_html_e( 'Bid fee', 'codex-ajax-auctions' ); ?></p>
						<strong><?php echo wp_kses_post( $bid_fee_price ); ?></strong>
					</article>
					<article>
						<p><?php esc_html_e( 'Timer', 'codex-ajax-auctions' ); ?></p>
						<strong><?php echo esc_html( $timer_label ); ?></strong>
					</article>
				</div>
			</section>

			<section class="codfaa-stage-stack">
				<article class="<?php echo esc_attr( $registration_stage_classes ); ?>" data-codfaa-stage="registration" data-codfaa-register-card>
					<header class="codfaa-stage__header">
						<div class="codfaa-stage__title">
							<span class="codfaa-stage__badge">1</span>
							<div>
								<p><?php esc_html_e( 'Registration', 'codex-ajax-auctions' ); ?></p>
								<small data-codfaa-progress-label><?php echo esc_html( $progress_label ); ?></small>
							</div>
						</div>
						<div class="codfaa-stage__status">
							<span class="codfaa-stage__status-icon codfaa-stage__status-icon--check" aria-hidden="true"><?php echo codfaa_stage_icon_markup( 'check' ); ?></span>
							<span class="codfaa-stage__status-icon codfaa-stage__status-icon--lock" aria-hidden="true"><?php echo codfaa_stage_icon_markup( 'lock' ); ?></span>
						</div>
					</header>
					<div class="codfaa-progress codfaa-progress--registration">
						<div class="codfaa-progress__bar" data-codfaa-progress-bar style="width: <?php echo esc_attr( $progress_percent ); ?>%;"></div>
					</div>
					<div class="codfaa-register-pills">
						<article>
							<p><?php esc_html_e( 'Registration fee', 'codex-ajax-auctions' ); ?></p>
							<strong><?php echo wp_kses_post( $registration_price ); ?></strong>
						</article>
						<article class="codfaa-register-pill codfaa-register-pill--<?php echo esc_attr( $register_status_class ); ?>">
							<p><?php esc_html_e( 'Status', 'codex-ajax-auctions' ); ?></p>
							<strong class="codfaa-register-state codfaa-register-state--<?php echo esc_attr( $is_registered ? 'success' : ( $registration_pending ? 'pending' : 'muted' ) ); ?>">
								<?php echo esc_html( $register_status_label ); ?>
							</strong>
						</article>
					</div>

					<p class="codfaa-register-note" data-codfaa-registration-pending <?php echo $registration_pending && ! $is_registered ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $pending_notice ); ?></p>
					<p class="codfaa-register-note codfaa-register-note--success" data-codfaa-register-success <?php echo $is_registered ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $share_message ); ?></p>

<?php $consent_disabled = $is_registered || $registration_pending; ?>
					<label class="codfaa-legal-checkbox">
						<input type="checkbox" class="codfaa-register-consent" data-codfaa-consent="1" <?php echo $consent_disabled ? 'checked="checked" disabled="disabled"' : ''; ?> />
						<span>
							<?php esc_html_e( 'Accept Terms & Conditions', 'codex-ajax-auctions' ); ?>
							<?php if ( $terms_content ) : ?>
								<button type="button" class="codfaa-terms-link" data-codfaa-terms-open="1"><?php esc_html_e( 'Read', 'codex-ajax-auctions' ); ?></button>
							<?php endif; ?>
						</span>
					</label>
<?php $consent_hint_style = $consent_disabled ? 'style="display:none;"' : 'style="display:block;"'; ?>
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
				</article>
				<article class="<?php echo esc_attr( $countdown_stage_classes ); ?>" data-codfaa-stage="countdown">
					<header class="codfaa-stage__header">
						<div class="codfaa-stage__title">
							<span class="codfaa-stage__badge">2</span>
							<p><?php esc_html_e( 'Countdown to live', 'codex-ajax-auctions' ); ?></p>
						</div>
						<div class="codfaa-stage__status">
							<span class="codfaa-stage__status-icon codfaa-stage__status-icon--check" aria-hidden="true"><?php echo codfaa_stage_icon_markup( 'check' ); ?></span>
							<span class="codfaa-stage__status-icon codfaa-stage__status-icon--lock" aria-hidden="true"><?php echo codfaa_stage_icon_markup( 'lock' ); ?></span>
						</div>
					</header>
					<div class="codfaa-countdown-card">
						<div class="codfaa-countdown-card__clock" data-codfaa-lock-countdown <?php echo ( $ready && $prelive_remaining > 0 && ! $ended ) ? '' : 'style="display:none;"'; ?>>
							<span class="codfaa-countdown-card__value" data-codfaa-lock-timer><?php echo esc_html( $prelive_initial_formatted ); ?></span>
							<small><?php esc_html_e( 'remaining', 'codex-ajax-auctions' ); ?></small>
						</div>
						<p class="codfaa-countdown-card__note">
							<?php esc_html_e( "We're giving all participants some time to get ready!", 'codex-ajax-auctions' ); ?>
						</p>
					</div>
				</article>
				<article class="<?php echo esc_attr( $live_stage_classes ); ?>" data-codfaa-stage="live">
					<div class="codfaa-bid-card__body">
						<header class="codfaa-stage__header codfaa-stage__header--live">
							<div class="codfaa-stage__title">
								<span class="codfaa-stage__badge">3</span>
								<p><?php esc_html_e( 'Live bidding', 'codex-ajax-auctions' ); ?></p>
							</div>
							<div class="codfaa-stage__meta" data-codfaa-timer-wrapper <?php echo $show_live_timer ? '' : 'style="display:none;"'; ?>>
								<span><?php esc_html_e( 'Timer:', 'codex-ajax-auctions' ); ?></span>
								<strong data-codfaa-timer><?php echo esc_html( $initial_timer_formatted ); ?></strong>
							</div>
						</header>
						<p class="codfaa-stage__hint" data-codfaa-timer-pending <?php echo $show_live_timer || $ended ? 'style="display:none;"' : ''; ?>>
							<?php esc_html_e( 'Timer will appear once the auction is live.', 'codex-ajax-auctions' ); ?>
						</p>

						<div class="codfaa-progress codfaa-progress--live">
							<div class="codfaa-progress__bar" data-codfaa-live-progress style="width: <?php echo esc_attr( $timer_progress ); ?>%;"></div>
						</div>

						<button type="button" class="<?php echo esc_attr( $bid_button_classes ); ?>" data-auction="<?php echo esc_attr( $auction->ID ); ?>" <?php echo $can_bid ? '' : 'aria-disabled="true" disabled="disabled"'; ?>>
							<?php printf( esc_html__( 'Place bid (%s)', 'codex-ajax-auctions' ), wp_strip_all_tags( $bid_fee_price ) ); ?>
						</button>

						<div class="codfaa-bid-stats">
							<article class="codfaa-bid-stat">
								<p><?php esc_html_e( 'Your bids', 'codex-ajax-auctions' ); ?></p>
								<strong data-codfaa-bid-count><?php echo esc_html( $user_bid_count ); ?></strong>
							</article>
							<article class="codfaa-bid-stat">
								<p><?php esc_html_e( 'Your cost', 'codex-ajax-auctions' ); ?></p>
								<strong data-codfaa-bid-total data-minor="<?php echo esc_attr( $user_total_minor ); ?>"><?php echo wp_kses_post( $user_total_display ); ?></strong>
							</article>
							<article class="<?php echo esc_attr( $status_card_classes ); ?>" data-codfaa-status-card <?php echo $status_card_hidden; ?>>
								<p><?php esc_html_e( 'Status', 'codex-ajax-auctions' ); ?></p>
								<strong data-codfaa-status><?php echo esc_html( $initial_status_message ); ?></strong>
							</article>
						</div>

						<div class="codfaa-bid-history">
							<h3><?php esc_html_e( 'History', 'codex-ajax-auctions' ); ?></h3>
							<ul data-codfaa-recent-bidders>
								<?php if ( $bid_history ) : ?>
									<?php foreach ( $bid_history as $bidder ) : ?>
										<li>
											<span class="codfaa-recent__name"><?php echo esc_html( $bidder['name'] ); ?></span>
											<?php if ( ! empty( $bidder['timestamp'] ) ) : ?>
												<time datetime="<?php echo esc_attr( $bidder['timestampRaw'] ?? '' ); ?>"><?php echo esc_html( $bidder['timestamp'] ); ?></time>
											<?php endif; ?>
											<?php if ( ! empty( $bidder['totalDisplay'] ) ) : ?>
												<span class="codfaa-recent__amount"><?php echo wp_kses_post( $bidder['totalDisplay'] ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								<?php else : ?>
									<li class="codfaa-recent__empty"><?php esc_html_e( 'No bids yet.', 'codex-ajax-auctions' ); ?></li>
								<?php endif; ?>
							</ul>
							<p class="codfaa-bid-note"><?php echo esc_html( $history_note ); ?></p>
						</div>
					</div>

					<div class="codfaa-bid-card__overlay" data-codfaa-lock aria-hidden="<?php echo $is_locked ? 'false' : 'true'; ?>">
						<div class="codfaa-lock-icon" aria-hidden="true">
							<span class="codfaa-lock-icon__ring"></span>
							<span class="codfaa-lock-icon__body"></span>
						</div>
						<p class="codfaa-lock-message" data-codfaa-lock-message><?php echo esc_html( $lock_overlay_copy ); ?></p>
						<p class="codfaa-lock-countdown" data-codfaa-lock-countdown <?php echo ( $ready && $prelive_remaining > 0 && ! $ended ) ? '' : 'style="display:none;"'; ?>>
							<span><?php esc_html_e( 'Auction goes live in', 'codex-ajax-auctions' ); ?></span>
							<strong data-codfaa-lock-timer><?php echo esc_html( $prelive_initial_formatted ); ?></strong>
						</p>
						<p class="codfaa-lock-footer"><?php esc_html_e( 'Good luck!', 'codex-ajax-auctions' ); ?></p>
					</div>
				</article>
				<article class="<?php echo esc_attr( $ended_stage_classes ); ?>" data-codfaa-stage="ended">
					<header class="codfaa-stage__header">
						<div class="codfaa-stage__title">
							<span class="codfaa-stage__badge">4</span>
							<p><?php esc_html_e( 'Auction ended', 'codex-ajax-auctions' ); ?></p>
						</div>
						<div class="codfaa-stage__status">
							<span class="codfaa-stage__status-icon codfaa-stage__status-icon--check" aria-hidden="true"><?php echo codfaa_stage_icon_markup( 'check' ); ?></span>
							<span class="codfaa-stage__status-icon codfaa-stage__status-icon--lock" aria-hidden="true"><?php echo codfaa_stage_icon_markup( 'lock' ); ?></span>
						</div>
					</header>
					<div class="codfaa-result-card">
						<div class="codfaa-bid-result <?php echo esc_attr( $result_variant ? 'is-' . $result_variant : '' ); ?>" data-codfaa-winner-summary><?php echo $result_text ? wp_kses_post( $result_text ) : esc_html__( 'Results will appear once the auction ends.', 'codex-ajax-auctions' ); ?></div>
						<a href="#" class="<?php echo esc_attr( $claim_classes ); ?>" data-auction="<?php echo esc_attr( $auction->ID ); ?>" data-label="<?php echo esc_attr( $claim_label ); ?>" aria-hidden="<?php echo esc_attr( $ended && $user_is_winner && ! $winner_claimed ? 'false' : 'true' ); ?>"><?php echo esc_html( $claim_label ); ?></a>
					</div>
				</article>
			</section>
		</div>
	</div>
</div>
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
