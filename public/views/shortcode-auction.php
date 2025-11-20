<?php
/**
 * Standalone auction demo template matching the provided Tailwind markup.
 */

defined( 'ABSPATH' ) || exit;

$auction_id = ( isset( $auction ) && $auction instanceof WP_Post ) ? $auction->ID : 0;

$product_obj      = ( isset( $product ) && $product instanceof WC_Product ) ? $product : null;
$registration_obj = ( isset( $registration ) && $registration instanceof WC_Product ) ? $registration : null;
$bid_product_obj  = ( isset( $bid_product ) && $bid_product instanceof WC_Product ) ? $bid_product : null;

$product_name = $product_obj ? $product_obj->get_name() : ( $auction instanceof WP_Post ? get_the_title( $auction ) : __( 'Auction product', 'codex-ajax-auctions' ) );

$product_price_html = $product_obj ? $product_obj->get_price_html() : __( 'Not available', 'codex-ajax-auctions' );
$registration_price  = $registration_obj ? $registration_obj->get_price_html() : __( 'Not set', 'codex-ajax-auctions' );
$bid_fee_price       = $bid_product_obj ? $bid_product_obj->get_price_html() : __( 'Not set', 'codex-ajax-auctions' );

$product_price_plain = trim( wp_strip_all_tags( $product_price_html ) );
$registration_plain  = trim( wp_strip_all_tags( $registration_price ) );
$bid_fee_plain       = trim( wp_strip_all_tags( $bid_fee_price ) );

$product_image_url = '';
if ( $product_obj && $product_obj->get_image_id() ) {
	$product_image_url = wp_get_attachment_image_url( $product_obj->get_image_id(), 'large' );
}
if ( ! $product_image_url ) {
	$product_image_url = wc_placeholder_img_src();
}

$product_excerpt = '';
if ( $product_obj ) {
	$product_excerpt = $product_obj->get_short_description();
	if ( '' === trim( $product_excerpt ) ) {
		$product_excerpt = $product_obj->get_description();
	}
}
$product_excerpt = $product_excerpt ? wpautop( $product_excerpt ) : '';

$product_view_url = ! empty( $product_link ) ? $product_link : ( $product_obj ? $product_obj->get_permalink() : '' );
$modal_id         = 'codfaa-product-modal-' . ( $auction_id ? $auction_id : wp_generate_uuid4() );

$progress_percent = isset( $progress_percent ) ? max( 0, min( 100, (float) $progress_percent ) ) : 0;
$progress_label   = sprintf(
	__( 'Lobby progress: %s%%', 'codex-ajax-auctions' ),
	number_format_i18n( $progress_percent )
);

$register_copy = sprintf(
	__( 'Register & Reserve Spot (%s)', 'codex-ajax-auctions' ),
	$registration_plain ? $registration_plain : __( 'Fee not set', 'codex-ajax-auctions' )
);

$register_status_label = $is_registered
	? __( 'Registered', 'codex-ajax-auctions' )
	: ( $registration_pending ? __( 'Pending approval', 'codex-ajax-auctions' ) : __( 'Not registered', 'codex-ajax-auctions' ) );

$register_status_state = $is_registered ? 'success' : ( $registration_pending ? 'pending' : 'muted' );

$pending_notice = __( 'Registration received. Waiting for admin confirmation.', 'codex-ajax-auctions' );
$share_notice   = __( 'Registration complete. Lobby is filling—share the auction to reach 100%.', 'codex-ajax-auctions' );

$can_join  = $registration_obj && $is_logged_in && ! $is_registered && ! $registration_pending;
$login_url = wp_login_url( isset( $display_url ) ? $display_url : home_url() );

$lobby_full_blocking = ! $is_registered && ! $registration_pending && $progress_percent >= 100;
$effective_ready     = $ready || $lobby_full_blocking;
$consent_disabled    = $is_registered || $registration_pending || $effective_ready;

$prelive_initial_secs     = max( 0, (int) $prelive_remaining );
$prelive_initial_display  = gmdate( 'i:s', $prelive_initial_secs );
$prelive_bar_initial      = ( $prelive_duration > 0 && $prelive_initial_secs < $prelive_duration )
	? ( ( ( $prelive_duration - $prelive_initial_secs ) / max( 1, $prelive_duration ) ) * 100 )
	: 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Pay-Per-Bid Auctions</title>

  <style>
    [data-codfaa-register-card].is-hidden { display: none !important; }
  </style>
</head>
<body class="bg-white text-gray-900">

<div
  class="codfaa-auction-experience codfaa-auction-card"
  data-auction="<?php echo esc_attr( $auction_id ); ?>"
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
  data-ready="<?php echo esc_attr( $effective_ready ? 1 : 0 ); ?>"
  data-prelive="<?php echo esc_attr( $prelive_remaining ); ?>"
  data-prelive-total="<?php echo esc_attr( $prelive_duration ); ?>"
  data-go-live="<?php echo esc_attr( $go_live_at ); ?>"
  data-user-registered="<?php echo esc_attr( $is_registered ? 1 : 0 ); ?>"
  data-user-winner="<?php echo esc_attr( $user_is_winner ? 1 : 0 ); ?>"
  data-registration-pending="<?php echo esc_attr( $registration_pending ? 1 : 0 ); ?>"
  data-winner-claimed="<?php echo esc_attr( $winner_claimed ? 1 : 0 ); ?>"
  data-winner-total-display="<?php echo esc_attr( wp_strip_all_tags( $winner_total_display ) ); ?>"
  data-winner-bid-count="<?php echo esc_attr( $winner_bid_count ); ?>"
>

  <!-- HOW IT WORKS – top -->
  <section id="how" class="bg-gray-50">
    <div class="mx-auto max-w-6xl px-4 py-16">
      <h2 class="text-2xl font-bold">How It Works</h2>
      <div class="mt-8 grid md:grid-cols-4 gap-6">
        <div class="p-6 border rounded-2xl">
          <h3 class="font-semibold">1) Register</h3>
          <p class="text-sm text-gray-700 mt-2">Pay a small registration fee and secure your spot.</p>
        </div>
        <div class="p-6 border rounded-2xl">
          <h3 class="font-semibold">2) Pre-Live</h3>
          <p class="text-sm text-gray-700 mt-2">When the lobby is full, a short countdown starts.</p>
        </div>
        <div class="p-6 border rounded-2xl">
          <h3 class="font-semibold">3) Bid Live</h3>
          <p class="text-sm text-gray-700 mt-2">Each bid costs a preset amount. Timer resets on each bid.</p>
        </div>
        <div class="p-6 border rounded-2xl">
          <h3 class="font-semibold">4) Win or Keep</h3>
          <p class="text-sm text-gray-700 mt-2">If the timer hits 0 after your bid, you win. If outbid, you don’t pay (demo).</p>
        </div>
      </div>
    </div>
  </section>

  <!-- PRODUCT + STAGES -->
  <section class="mx-auto max-w-6xl px-4 py-16">
    <div class="grid md:grid-cols-2 gap-10 items-start md:items-stretch">
      <!-- LEFT: Product window -->
      <div class="flex flex-col h-full">
        <div class="border rounded-2xl p-6 shadow-sm flex-1">
          <div class="flex items-center justify-between">
            <strong><?php echo esc_html( $product_name ); ?></strong>
            <span class="text-sm text-gray-500">
              <?php
              if ( $product_price_plain ) {
                  printf( esc_html__( 'Retail: %s', 'codex-ajax-auctions' ), esc_html( $product_price_plain ) );
              } else {
                  esc_html_e( 'Retail: N/A', 'codex-ajax-auctions' );
              }
              ?>
            </span>
          </div>

          <!-- Bigger image -->
          <div class="mt-4">
            <img
              decoding="async"
              src="<?php echo esc_url( $product_image_url ); ?>"
              alt="<?php echo esc_attr( $product_name ); ?>"
              class="w-full max-h-80 mx-auto rounded-xl object-contain border"
            >
          </div>

          <!-- Info under image -->
          <div class="mt-4 text-sm text-gray-700 space-y-1">
            <p><span class="font-medium"><?php esc_html_e( 'Retail:', 'codex-ajax-auctions' ); ?></span> <?php echo wp_kses_post( $product_price_html ); ?></p>
            <p>• <?php printf( esc_html__( 'Claim it for %s at checkout if you win.', 'codex-ajax-auctions' ), $registration_plain ? esc_html( $registration_plain ) : esc_html__( '—', 'codex-ajax-auctions' ) ); ?></p>
            <p>• <?php esc_html_e( 'Registration fee:', 'codex-ajax-auctions' ); ?> <?php echo wp_kses_post( $registration_price ); ?></p>
            <p>• <?php esc_html_e( 'Bid fee:', 'codex-ajax-auctions' ); ?> <?php echo wp_kses_post( $bid_fee_price ); ?></p>
            <p class="pt-2">
              <?php
              if ( $product_excerpt ) {
                  echo wp_kses_post( $product_excerpt );
              } else {
                  esc_html_e( 'Compact mini bike trainer with LCD display for time, distance and calories. Perfect for under-desk pedaling or low-impact home workouts.', 'codex-ajax-auctions' );
              }
              ?>
            </p>
          </div>

          <!-- Buttons under description -->
          <div class="mt-4 flex items-center gap-4">
            <button
              type="button"
              class="px-4 py-2 rounded-xl bg-black text-white text-sm"
              data-quick-view="open"
              data-modal-target="<?php echo esc_attr( $modal_id ); ?>"
            >
              <?php esc_html_e( 'Quick View', 'codex-ajax-auctions' ); ?>
            </button>
            <?php if ( $product_view_url ) : ?>
              <a href="<?php echo esc_url( $product_view_url ); ?>" target="_blank" rel="noopener" class="text-sm text-gray-800 underline">
                <?php esc_html_e( 'View product', 'codex-ajax-auctions' ); ?>
              </a>
            <?php else : ?>
              <span class="text-sm text-gray-400 underline decoration-dotted cursor-not-allowed" aria-disabled="true">
                <?php esc_html_e( 'View product', 'codex-ajax-auctions' ); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-6 flex gap-6 text-sm text-gray-600">
          <span>Secure payments</span><span>•</span><span>Anonymized bidders</span><span>•</span><span>Transparent timers</span>
        </div>
      </div>

      <!-- RIGHT: 4-step demo flow (folded & locked stages) -->
      <div id="demo" class="space-y-6">
        <!-- STEP 1: Registration -->
        <div class="border rounded-2xl p-6 shadow-sm<?php echo $effective_ready ? " is-hidden" : ""; ?>" data-codfaa-register-card data-codfaa-stage="registration">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-black text-white text-xs font-semibold">1</span>
              <h2 class="text-lg font-semibold">Registration</h2>
            </div>
            <div class="flex items-center gap-2">
              <span id="s1Check" class="hidden text-green-600">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              <span id="s1Lock" class="hidden text-gray-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        d="M17 11V8a5 5 0 10-10 0v3"/>
                  <rect x="6" y="11" width="12" height="9" rx="2" ry="2" stroke-width="2"/>
                </svg>
              </span>
            </div>
          </div>

          <div id="step1Body" class="mt-4 space-y-4">
            <div>
              <div class="h-3 w-full bg-gray-200 rounded">
                <div class="h-3 bg-black rounded" data-codfaa-progress-bar style="width: <?php echo esc_attr( $progress_percent ); ?>%"></div>
              </div>
              <p class="mt-1 text-sm text-gray-700" data-codfaa-progress-label><?php echo esc_html( $progress_label ); ?></p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div class="p-4 border rounded-xl">
                <div class="text-sm text-gray-600"><?php esc_html_e( 'Registration fee', 'codex-ajax-auctions' ); ?></div>
                <div class="text-xl font-bold"><?php echo wp_kses_post( $registration_price ); ?></div>
              </div>
              <div class="p-4 border rounded-xl <?php echo esc_attr( 'success' === $register_status_state ? 'bg-green-50' : ( 'pending' === $register_status_state ? 'bg-amber-50' : 'bg-rose-50' ) ); ?>">
                <div class="text-sm text-gray-600"><?php esc_html_e( 'Status', 'codex-ajax-auctions' ); ?></div>
                <div class="text-xl font-semibold <?php echo esc_attr( 'success' === $register_status_state ? 'text-green-700' : ( 'pending' === $register_status_state ? 'text-amber-700' : 'text-rose-700' ) ); ?>">
                  <?php echo esc_html( $register_status_label ); ?>
                </div>
              </div>
            </div>

            <p class="text-sm text-gray-700" data-codfaa-register-success <?php echo $is_registered ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $share_notice ); ?></p>
            <p class="text-sm text-gray-700" data-codfaa-registration-pending <?php echo ( $registration_pending && ! $is_registered ) ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $pending_notice ); ?></p>
            <p class="text-sm text-rose-600" data-codfaa-register-error style="display:none;"></p>
            <?php if ( $lobby_full_blocking ) : ?>
              <p class="text-sm text-rose-600" data-codfaa-lobby-full><?php esc_html_e( 'Lobby is full. Registration is currently closed.', 'codex-ajax-auctions' ); ?></p>
            <?php endif; ?>

            <label class="flex items-start gap-3 text-sm text-gray-700">
              <input
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-gray-300"
                data-codfaa-consent="1"
                <?php echo $consent_disabled ? 'checked disabled' : ''; ?>
              >
              <span>
                <?php esc_html_e( 'I accept the Terms & Conditions.', 'codex-ajax-auctions' ); ?>
                <?php if ( ! empty( $terms_content ) ) : ?>
                  <button type="button" class="underline" data-codfaa-terms-open="1"><?php esc_html_e( 'Read terms', 'codex-ajax-auctions' ); ?></button>
                <?php endif; ?>
              </span>
            </label>
            <p class="text-sm text-rose-600" data-codfaa-consent-hint <?php echo $consent_disabled ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Please accept the Terms & Conditions to enable registration.', 'codex-ajax-auctions' ); ?></p>

            <?php if ( $lobby_full_blocking ) : ?>
              <button class="w-full px-4 py-3 rounded-xl bg-gray-200 text-gray-500 text-sm font-medium" disabled>
                <?php esc_html_e( 'Lobby full. Registration closed.', 'codex-ajax-auctions' ); ?>
              </button>
            <?php elseif ( ! $is_logged_in ) : ?>
              <a class="w-full inline-flex justify-center px-4 py-3 rounded-xl bg-black text-white text-sm font-medium" href="<?php echo esc_url( $login_url ); ?>">
                <?php esc_html_e( 'Log in to join', 'codex-ajax-auctions' ); ?>
              </a>
            <?php elseif ( ! $registration_obj ) : ?>
              <button class="w-full px-4 py-3 rounded-xl bg-gray-200 text-gray-500 text-sm font-medium" disabled>
                <?php esc_html_e( 'Registration unavailable', 'codex-ajax-auctions' ); ?>
              </button>
            <?php elseif ( $is_registered ) : ?>
              <button class="w-full px-4 py-3 rounded-xl bg-green-600 text-white text-sm font-medium" disabled>
                <?php esc_html_e( 'Registered successfully', 'codex-ajax-auctions' ); ?>
              </button>
            <?php elseif ( $registration_pending ) : ?>
              <button class="w-full px-4 py-3 rounded-xl bg-amber-500 text-white text-sm font-medium" disabled>
                <?php esc_html_e( 'Awaiting confirmation', 'codex-ajax-auctions' ); ?>
              </button>
            <?php else : ?>
              <button
                type="button"
                class="w-full px-4 py-3 rounded-xl bg-black text-white text-sm font-medium codfaa-register"
                data-auction="<?php echo esc_attr( $auction_id ); ?>"
                data-return="<?php echo esc_url( isset( $display_url ) ? $display_url : home_url() ); ?>"
                aria-disabled="true"
                disabled
              >
                <?php echo esc_html( $register_copy ); ?>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- STEP 2: Countdown to live -->
        <div class="border rounded-2xl p-6 shadow-sm" data-codfaa-stage="countdown">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-black text-white text-xs font-semibold">2</span>
              <h2 class="text-lg font-semibold">Countdown to live</h2>
            </div>
            <div class="flex items-center gap-2">
              <span id="s2Check" class="hidden text-green-600">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              <span id="s2Lock" class="text-gray-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        d="M17 11V8a5 5 0 10-10 0v3"/>
                  <rect x="6" y="11" width="12" height="9" rx="2" ry="2" stroke-width="2"/>
                </svg>
              </span>
            </div>
          </div>

          <div id="step2Body" class="mt-4<?php echo $effective_ready ? "" : " hidden"; ?>" data-codfaa-prelive-wrapper>
            <div class="flex items-center justify-between">
              <p class="text-sm text-gray-700">
                We’re giving all participants some time to get ready.
              </p>
              <div class="text-right">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Starts in</div>
                <div class="text-2xl font-extrabold"><span id="preliveSec" data-codfaa-prelive-timer><?php echo esc_html( $prelive_initial_display ); ?></span><span class="text-base font-normal">s</span></div>
              </div>
            </div>

            <div class="mt-4 h-2 bg-gray-200 rounded">
              <div id="preliveBar" class="h-2 bg-black rounded" data-codfaa-timer-progress style="width: <?php echo esc_attr( $prelive_bar_initial ); ?>%"></div>
            </div>
          </div>
        </div>

        <!-- STEP 3: Live bidding -->
        <div class="border rounded-2xl p-6 shadow-sm" data-codfaa-stage="live">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items center gap-2">
              <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-black text-white text-xs font-semibold">3</span>
              <h2 class="text-lg font-semibold">Live Bidding</h2>
            </div>
            <div class="flex items-center gap-2">
              <span id="s3Check" class="hidden text-green-600">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              <span id="s3Lock" class="text-gray-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        d="M17 11V8a5 5 0 10-10 0v3"/>
                  <rect x="6" y="11" width="12" height="9" rx="2" ry="2" stroke-width="2"/>
                </svg>
              </span>
            </div>
          </div>

          <div id="step3Body" class="mt-4 hidden">
            <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
              <span>Live timer</span>
              <span>Timer: <span id="liveTimer">10</span>s</span>
            </div>

            <div class="h-2 bg-gray-200 rounded">
              <div id="liveBar" class="h-2 bg-black rounded" style="width:100%"></div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-4 text-sm text-gray-800">
              <div class="p-3 border rounded-xl">
                <div class="text-xs text-gray-500">Your bids</div>
                <div class="text-lg font-semibold"><span id="myBids">0</span></div>
              </div>
              <div class="p-3 border rounded-xl">
                <div class="text-xs text-gray-500">Your current cost</div>
                <div class="text-lg font-semibold">€<span id="myCost">0</span></div>
              </div>
              <div id="statusPill" class="p-3 border rounded-xl bg-red-50">
                <div class="text-xs text-gray-500">Status</div>
                <div id="statusText" class="text-lg font-semibold text-red-700">Outbid</div>
              </div>
            </div>

            <ul id="history" class="mt-4 space-y-1 text-sm text-gray-700 border rounded-xl p-3">
              <!-- History rows injected here -->
            </ul>

            <button id="bidBtn"
                    class="mt-4 w-full px-4 py-3 rounded-xl bg-black text-white text-sm font-medium"
                    disabled>
              Place Bid (€1)
            </button>
          </div>
        </div>

        <!-- STEP 4: Auction ended -->
        <div class="border rounded-2xl p-6 shadow-sm" data-codfaa-stage="ended">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-black text-white text-xs font-semibold">4</span>
              <h2 class="text-lg font-semibold">Auction Ended</h2>
            </div>
            <div class="flex items-center gap-2">
              <span id="s4Check" class="hidden text-green-600">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              <span id="s4Lock" class="text-gray-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        d="M17 11V8a5 5 0 10-10 0v3"/>
                  <rect x="6" y="11" width="12" height="9" rx="2" ry="2" stroke-width="2"/>
                </svg>
              </span>
            </div>
          </div>

          <div id="step4Body" class="mt-4 hidden">
            <div id="resultWin" class="hidden mt-4 p-4 rounded-xl bg-green-100 border border-green-400 text-green-800 font-semibold text-center">
              You’re the winner! You can claim your reward for <span id="claimAmount">€0</span>.
            </div>
            <div id="resultLose" class="hidden mt-4 p-4 rounded-xl bg-red-100 border border-red-400 text-red-700 font-semibold text-center">
              You were outbid. You don’t pay for your bids.
            </div>

            <button id="claimBtn"
                    class="mt-4 w-full px-4 py-3 rounded-xl bg-black text-white text-sm font-medium hidden">
              Claim now
            </button>

            <button id="restartBtn"
                    class="mt-3 w-full px-4 py-3 rounded-xl border border-gray-400 text-gray-700 text-sm">
              Restart Demo
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="border-t">
    <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-gray-600 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <p>© YourBrand</p>
      <nav class="flex gap-6">
        <a href="/terms" class="hover:underline">Terms</a>
        <a href="/privacy" class="hover:underline">Privacy</a>
        <a href="/contact" class="hover:underline">Contact</a>
      </nav>
    </div>
  </footer>

</div>

  <div
    id="<?php echo esc_attr( $modal_id ); ?>"
    class="fixed inset-0 z-50 hidden"
    data-modal
    aria-hidden="true"
    role="dialog"
  >
    <button type="button" class="absolute inset-0 bg-black/60" data-modal-close aria-label="<?php esc_attr_e( 'Close quick view', 'codex-ajax-auctions' ); ?>"></button>
    <div class="relative mx-auto flex min-h-full items-center justify-center px-4">
      <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900"><?php echo esc_html( $product_name ); ?></h3>
            <p class="text-sm text-gray-500 mt-1"><?php echo wp_kses_post( $product_price_html ); ?></p>
          </div>
          <button type="button" class="text-gray-500 hover:text-gray-900" data-modal-close>
            <span class="sr-only"><?php esc_html_e( 'Close quick view', 'codex-ajax-auctions' ); ?></span>
            &times;
          </button>
        </div>

        <div class="mt-4">
          <img src="<?php echo esc_url( $product_image_url ); ?>" alt="<?php echo esc_attr( $product_name ); ?>" class="w-full rounded-xl border object-contain max-h-72">
        </div>

        <div class="mt-4 text-sm text-gray-700 space-y-2">
          <?php
          if ( $product_excerpt ) {
              echo wp_kses_post( $product_excerpt );
          } else {
              esc_html_e( 'Full product details coming soon.', 'codex-ajax-auctions' );
          }
          ?>
        </div>

        <?php if ( $product_view_url ) : ?>
          <div class="mt-6 text-right">
            <a href="<?php echo esc_url( $product_view_url ); ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-black text-white text-sm font-medium">
              <?php esc_html_e( 'Open product page', 'codex-ajax-auctions' ); ?>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var card = document.querySelector('.codfaa-auction-card');

    function openModal(modal) {
      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('overflow-hidden');
    }

    function closeModal(modal) {
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('[data-quick-view]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var targetId = btn.getAttribute('data-modal-target');
        if (!targetId) {
          return;
        }
        var modal = document.getElementById(targetId);
        if (modal) {
          openModal(modal);
        }
      });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function(closeBtn) {
      closeBtn.addEventListener('click', function() {
        var modal = closeBtn.closest('[data-modal]');
        if (modal) {
          closeModal(modal);
        }
      });
    });

    document.querySelectorAll('[data-modal]').forEach(function(modal) {
      modal.addEventListener('click', function(event) {
        if (event.target === modal) {
          closeModal(modal);
        }
      });
    });

  document.addEventListener('keydown', function(event) {
      if (event.key !== 'Escape') {
        return;
      }
      document.querySelectorAll('[data-modal]').forEach(function(modal) {
        if (!modal.classList.contains('hidden')) {
          closeModal(modal);
        }
      });
    });
    if ( ! window.CodfaaAuctionRegistration && card ) {
      var preWrapper = card.querySelector('[data-codfaa-prelive-wrapper]');
      var preTimer = card.querySelector('[data-codfaa-prelive-timer]');
      var preBar = card.querySelector('[data-codfaa-timer-progress]');
      var cardPreSeconds = parseInt(card.getAttribute('data-prelive'), 10) || 0;
      var cardPreTotal = parseInt(card.getAttribute('data-prelive-total'), 10) || cardPreSeconds;
      if ( preWrapper && !preWrapper.classList.contains('hidden') && cardPreSeconds > 0 && preTimer ) {
        var fallbackTimer = setInterval(function() {
          cardPreSeconds = Math.max(0, cardPreSeconds - 1);
          var minutes = Math.floor(cardPreSeconds / 60).toString().padStart(2, '0');
          var seconds = (cardPreSeconds % 60).toString().padStart(2, '0');
          preTimer.textContent = minutes + ':' + seconds;
          if ( preBar && cardPreTotal > 0 ) {
            var pct = ((cardPreTotal - cardPreSeconds) / cardPreTotal) * 100;
            preBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
          }
          if ( cardPreSeconds <= 0 ) {
            clearInterval(fallbackTimer);
          }
        }, 1000);
        window.addEventListener('beforeunload', function() { clearInterval(fallbackTimer); });
      }
    }
  });
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var card = document.querySelector('.codfaa-auction-card');
    if ( ! card ) {
      return;
    }

    var consent      = card.querySelector('[data-codfaa-consent]');
    var consentHint  = card.querySelector('[data-codfaa-consent-hint]');
    var registerBtn  = card.querySelector('.codfaa-register');
    var shareNote    = card.querySelector('[data-codfaa-register-success]');
    var pendingNote  = card.querySelector('[data-codfaa-registration-pending]');
    var errorNote    = card.querySelector('[data-codfaa-register-error]');
    var progressBar  = card.querySelector('[data-codfaa-progress-bar]');
    var progressText = card.querySelector('[data-codfaa-progress-label]');
    var statusText   = card.querySelector('[data-codfaa-register-card] .text-xl');
    var s1Check      = document.getElementById('s1Check');
    var s1Lock       = document.getElementById('s1Lock');
    var step2Body    = document.getElementById('step2Body');

    if ( ! registerBtn ) {
      return;
    }

    var initialLabel = registerBtn.textContent.trim();

    function toggleRegisterAvailability() {
      if ( ! consent ) {
        registerBtn.disabled = false;
        registerBtn.setAttribute('aria-disabled', 'false');
        return;
      }

      var disabled = consent.disabled ? false : ! consent.checked;
      registerBtn.disabled = disabled;
      registerBtn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      if ( consentHint && ! consent.disabled ) {
        consentHint.style.display = disabled ? 'block' : 'none';
      }
    }

    if ( consent ) {
      consent.addEventListener('change', toggleRegisterAvailability);
    }
    toggleRegisterAvailability();

    registerBtn.addEventListener('click', function( event ) {
      event.preventDefault();

      if ( registerBtn.disabled || registerBtn.dataset.processing === '1' ) {
        return;
      }

      var config = window.CodfaaAuctionRegistration || null;
      if ( ! config ) {
        console.warn( 'CodfaaAuctionRegistration config missing.' );
        return;
      }

      registerBtn.dataset.processing = '1';
      registerBtn.classList.add('opacity-70');
      registerBtn.textContent = registerBtn.dataset.loading || '<?php echo esc_js( __( 'Processing…', 'codex-ajax-auctions' ) ); ?>';
      var registrationCompleted = false;

      var payload = new URLSearchParams();
      payload.append( 'action', 'codfaa_register' );
      payload.append( 'nonce', config.nonce );
      payload.append( 'auction_id', registerBtn.getAttribute( 'data-auction' ) );
      payload.append( 'source_url', registerBtn.getAttribute( 'data-return' ) || window.location.href );

      fetch( config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: payload.toString()
      } ).then( function( response ) {
        return response.json().catch( function() { return {}; } );
      } ).then( function( data ) {
        if ( data && data.success ) {
          registrationCompleted = true;
          handleRegistrationSuccess( data.data || {} );
          return;
        }
        handleRegistrationError( data && data.data ? data.data : {} );
      } ).catch( function( error ) {
        console.error( error );
        handleRegistrationError( {} );
      } ).finally( function() {
        registerBtn.dataset.processing = '';
        registerBtn.classList.remove('opacity-70');
        if ( ! registrationCompleted ) {
          registerBtn.textContent = initialLabel;
        }
      } );
    } );

    function handleRegistrationSuccess( payload ) {
      if ( shareNote ) {
        shareNote.style.display = 'block';
      }
      if ( pendingNote ) {
        pendingNote.style.display = 'none';
      }
      if ( errorNote ) {
        errorNote.style.display = 'none';
      }
      if ( progressBar ) {
        progressBar.style.width = '100%';
      }
      if ( progressText ) {
        progressText.textContent = '<?php echo esc_js( __( 'Lobby progress: 100%', 'codex-ajax-auctions' ) ); ?>';
      }
      if ( statusText ) {
        statusText.textContent = '<?php echo esc_js( __( 'Registered', 'codex-ajax-auctions' ) ); ?>';
        statusText.classList.remove('text-rose-700', 'text-amber-700');
        statusText.classList.add('text-green-700');
      }
      registerBtn.disabled = true;
      registerBtn.setAttribute( 'aria-disabled', 'true' );
      registerBtn.textContent = '<?php echo esc_js( __( 'Registered successfully', 'codex-ajax-auctions' ) ); ?>';

      if ( s1Check ) {
        s1Check.classList.remove('hidden');
      }
      if ( s1Lock ) {
        s1Lock.classList.add('hidden');
      }
      if ( step2Body ) {
        step2Body.classList.remove('hidden');
      }

      if ( payload && payload.redirect ) {
        window.location.href = payload.redirect;
      }
    }

    function handleRegistrationError( payload ) {
      if ( errorNote ) {
        errorNote.textContent = payload && payload.message ? payload.message : '<?php echo esc_js( __( 'Unable to register right now. Please try again.', 'codex-ajax-auctions' ) ); ?>';
        errorNote.style.display = 'block';
      }

      if ( payload && payload.redirect ) {
        window.location.href = payload.redirect;
      }
    }
  });
  </script>
</body>
</html>
