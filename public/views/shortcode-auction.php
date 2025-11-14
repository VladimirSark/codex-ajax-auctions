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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Pay-Per-Bid Auctions</title>
</head>
<body class="bg-white text-gray-900">

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
            <button type="button" class="px-4 py-2 rounded-xl bg-black text-white text-sm" data-quick-view="open" data-modal-target="<?php echo esc_attr( $modal_id ); ?>">
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
        <div class="border rounded-2xl p-6 shadow-sm">
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

          <div id="step1Body" class="mt-4">
            <div>
              <div class="h-3 w-full bg-gray-200 rounded">
                <div id="lobbyBar" class="h-3 bg-black rounded" style="width:70%"></div>
              </div>
              <p id="lobbyText" class="mt-1 text-sm text-gray-700">Lobby progress: 70%</p>
            </div>

            <div class="mt-4 grid sm:grid-cols-2 gap-4">
              <div class="p-4 border rounded-xl">
                <div class="text-sm text-gray-600">Registration fee</div>
                <div class="text-xl font-bold">€1</div>
              </div>
              <div id="statusBox" class="p-4 border rounded-xl bg-rose-50">
                <div class="text-sm text-gray-600">Status</div>
                <div id="regStatus" class="text-xl font-semibold text-rose-700">Not registered</div>
              </div>
            </div>

            <label class="mt-4 flex items-start gap-3 text-sm text-gray-700">
              <input id="terms" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300">
              <span>I accept the Terms &amp; Conditions (demo).</span>
            </label>

            <button id="registerBtn"
                    class="mt-4 w-full px-4 py-3 rounded-xl bg-black text-white text-sm font-medium">
              Register &amp; Reserve Spot (€1)
            </button>

            <p id="shareHint" class="mt-3 text-sm text-gray-700 hidden">
              Registration complete. Lobby is filling—share the auction to reach 100%.
            </p>
          </div>
        </div>

        <!-- STEP 2: Countdown to live -->
        <div class="border rounded-2xl p-6 shadow-sm">
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

          <div id="step2Body" class="mt-4 hidden">
            <div class="flex items-center justify-between">
              <p class="text-sm text-gray-700">
                We’re giving all participants some time to get ready.
              </p>
              <div class="text-right">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Starts in</div>
                <div class="text-2xl font-extrabold"><span id="preliveSec">10</span>s</div>
              </div>
            </div>

            <div class="mt-4 h-2 bg-gray-200 rounded">
              <div id="preliveBar" class="h-2 bg-black rounded" style="width:0%"></div>
            </div>
          </div>
        </div>

        <!-- STEP 3: Live bidding -->
        <div class="border rounded-2xl p-6 shadow-sm">
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
        <div class="border rounded-2xl p-6 shadow-sm">
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

  <div id="<?php echo esc_attr( $modal_id ); ?>" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/60 px-4" aria-hidden="true" role="dialog">
    <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl" data-modal-dialog>
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

  <script>
  (function(){
    const BID_PRICE = 1;

    // Step 1
    const terms       = document.getElementById('terms');
    const registerBtn = document.getElementById('registerBtn');
    const lobbyBar    = document.getElementById('lobbyBar');
    const lobbyText   = document.getElementById('lobbyText');
    const regStatus   = document.getElementById('regStatus');
    const statusBox   = document.getElementById('statusBox');
    const shareHint   = document.getElementById('shareHint');

    const s1Check     = document.getElementById('s1Check');
    const s1Lock      = document.getElementById('s1Lock');
    const step1Body   = document.getElementById('step1Body');

    // Step 2
    const preliveSec  = document.getElementById('preliveSec');
    const preliveBar  = document.getElementById('preliveBar');
    const s2Check     = document.getElementById('s2Check');
    const s2Lock      = document.getElementById('s2Lock');
    const step2Body   = document.getElementById('step2Body');

    // Step 3
    const liveTimer   = document.getElementById('liveTimer');
    const liveBar     = document.getElementById('liveBar');
    const myBidsEl    = document.getElementById('myBids');
    const myCostEl    = document.getElementById('myCost');
    const statusPill  = document.getElementById('statusPill');
    const statusText  = document.getElementById('statusText');
    const historyEl   = document.getElementById('history');
    const bidBtn      = document.getElementById('bidBtn');
    const s3Check     = document.getElementById('s3Check');
    const s3Lock      = document.getElementById('s3Lock');
    const step3Body   = document.getElementById('step3Body');

    // Step 4
    const resultWin   = document.getElementById('resultWin');
    const resultLose  = document.getElementById('resultLose');
    const claimBtn    = document.getElementById('claimBtn');
    const claimAmount = document.getElementById('claimAmount');
    const restartBtn  = document.getElementById('restartBtn');
    const s4Check     = document.getElementById('s4Check');
    const s4Lock      = document.getElementById('s4Lock');
    const step4Body   = document.getElementById('step4Body');

    const fakeNames = ['Anon***1','Anon***2','Anon***3','Anon***4'];

    const productModal = document.getElementById('<?php echo esc_js( $modal_id ); ?>');
    const quickViewTriggers = document.querySelectorAll('[data-modal-target="<?php echo esc_js( $modal_id ); ?>"]');

    let lobbyPct, registered, preSec, liveSec, liveInterval, lastBidder, myBids, autoOutbids, ended;
    let done1, done2, done3, done4;

    function setStage(active){
      const steps = [
        {body: step1Body, lock: s1Lock, check: s1Check, done: done1},
        {body: step2Body, lock: s2Lock, check: s2Check, done: done2},
        {body: step3Body, lock: s3Lock, check: s3Check, done: done3},
        {body: step4Body, lock: s4Lock, check: s4Check, done: done4}
      ];
      steps.forEach((s, i)=>{
        const idx = i+1;
        if(idx === active){
          s.body.classList.remove('hidden');
          s.lock.classList.add('hidden');
          if(s.done) s.check classList.remove('hidden'); else s.check.classList.add('hidden');
        }else{
          s.body.classList.add('hidden');
          if(s.done){
            s.check.classList.remove('hidden');
            s.lock.classList.add('hidden');
          }else{
            s.check.classList.add('hidden');
            s.lock.classList.remove('hidden');
          }
        }
      });
    }

    function resetAll(){
      lobbyPct   = 70;
      registered = false;
      preSec     = 10;
      liveSec    = 10;
      myBids     = 0;
      autoOutbids= 2;
      ended      = false;
      done1 = done2 = done3 = done4 = false;
      clearInterval(liveInterval);

      // Step 1 visuals
      lobbyBar.style.width = lobbyPct + '%';
      lobbyText.textContent = 'Lobby progress: ' + lobbyPct + '%';
      regStatus.textContent = 'Not registered';
      regStatus.classList.remove('text-green-700');
      regStatus.classList.add('text-rose-700');
      statusBox.classList.remove('bg-green-50');
      statusBox.classList.add('bg-rose-50');
      shareHint.classList.add('hidden');
      terms.checked = false;

      // Step 2 visuals
      preliveSec.textContent = preSec;
      preliveBar.style.width = '0%';

      // Step 3 visuals
      liveSec = 10;
      liveTimer.textContent = liveSec;
      liveBar.style.width = '100%';
      myBidsEl.textContent = '0';
      myCostEl.textContent = '0';
      statusPill.classList.remove('bg-green-50');
      statusPill.classList.add('bg-red-50');
      statusText.textContent = 'Outbid';
      statusText.classList.remove('text-green-700');
      statusText.classList.add('text-red-700');
      historyEl.innerHTML = '';
      bidBtn.disabled = true;

      // Step 4 visuals
      resultWin.classList.add('hidden');
      resultLose.classList.add('hidden');
      claimBtn.classList.add('hidden');

      setStage(1);
    }

    function addHistory(name, cost){
      const row = document.createElement('li');
      row.textContent = `Time: ${new Date().toLocaleTimeString()}  |  Name: ${name}  |  Cost: ${cost} Eur`;
      historyEl.prepend(row);
      while(historyEl.children.length > 5){
        historyEl.removeChild(historyEl.lastChild);
      }
    }

    function setWinning(isWinning){
      if(isWinning){
        statusPill.classList.remove('bg-red-50');
        statusPill.classList.add('bg-green-50');
        statusText.classList.remove('text-red-700');
        statusText.classList.add('text-green-700');
        statusText.textContent = 'Winning';
      }else{
        statusPill.classList.remove('bg-green-50');
        statusPill.classList.add('bg-red-50');
        statusText.classList.remove('text-green-700');
        statusText.classList.add('text-red-700');
        statusText.textContent = 'Outbid';
      }
    }

    function startPrelive(){
      done1 = true;
      setStage(2);
      const int = setInterval(()=>{
        preSec--;
        if(preSec < 0){
          clearInterval(int);
          done2 = true;
          startLive();
          return;
        }
        preliveSec.textContent = preSec;
        preliveBar.style.width = ((10 - preSec) / 10) * 100 + '%';
      }, 600); // fast demo
    }

    function startLive(){
      setStage(3);

      // reset live timer when going live
      liveSec = 10;
      liveTimer.textContent = liveSec;
      liveBar.style.width = '100%';

      // seed anon bids
      const n1 = fakeNames[0], n2 = fakeNames[1];
      addHistory(n1, BID_PRICE);
      addHistory(n2, BID_PRICE);
      lastBidder = n2;
      setWinning(false);
      bidBtn.disabled = false;

      liveInterval = setInterval(()=>{
        if(ended) return;
        liveSec--;
        if(liveSec <= 0){
          clearInterval(liveInterval);
          endAuction();
          return;
        }
        liveTimer.textContent = liveSec;
        liveBar.style.width = (liveSec / 10) * 100 + '%';
      }, 1000);
    }

    function endAuction(){
      ended = true;
      bidBtn.disabled = true;
      done3 = true;
      done4 = true;
      setStage(4);

      if(lastBidder === 'You'){
        const cost = myBids * BID_PRICE;
        claimAmount.textContent = '€' + cost;
        resultWin.classList.remove('hidden');
        claimBtn.classList.remove('hidden');
      }else{
        resultLose.classList.remove('hidden');
      }
    }

    registerBtn.addEventListener('click', ()=>{
      if(registered) return;
      if(!terms.checked){
        regStatus.textContent = 'Please accept Terms first';
        return;
      }
      registered = true;
      regStatus.textContent = 'Registered';
      regStatus.classList.remove('text-rose-700');
      regStatus.classList.add('text-green-700');
      statusBox.classList.remove('bg-rose-50');
      statusBox.classList.add('bg-green-50');
      shareHint.classList.remove('hidden');

      // animate lobby to 100 then start prelive
      const fill = setInterval(()=>{
        lobbyPct += 5;
        if(lobbyPct >= 100){
          lobbyPct = 100;
          clearInterval(fill);
          startPrelive();
        }
        lobbyBar.style.width = lobbyPct + '%';
        lobbyText.textContent = 'Lobby progress: ' + lobbyPct + '%';
      }, 180);
    });

    bidBtn.addEventListener('click', ()=>{
      if(ended) return;
      myBids++;
      myBidsEl.textContent = String(myBids);
      myCostEl.textContent = String(myBids * BID_PRICE);
      lastBidder = 'You';
      setWinning(true);
      addHistory('You', BID_PRICE);

      // reset timer on your bid
      liveSec = 10;
      liveTimer.textContent = liveSec;
      liveBar.style.width = '100%';

      if(autoOutbids > 0){
        const delay = 1000 + Math.random()*1500;
        const name = fakeNames[Math.floor(Math.random()*fakeNames.length)];
        autoOutbids--;
        setTimeout(()=>{
          if(ended) return;
          lastBidder = name;
          setWinning(false);
          addHistory(name, BID_PRICE);

          // reset timer on anon bid as well
          liveSec = 10;
          liveTimer.textContent = liveSec;
          liveBar.style.width = '100%';
        }, delay);
      }
    });

    restartBtn.addEventListener('click', resetAll);

    claimBtn.addEventListener('click', ()=>{
      alert('Demo only: in a real system you would be redirected to checkout.');
    });

    resetAll();

    if ( productModal && quickViewTriggers.length ) {
      const closeButtons = productModal.querySelectorAll('[data-modal-close]');

      const openModal = function() {
        productModal.classList.remove('hidden');
        productModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
      };

      const closeModal = function() {
        productModal.classList.add('hidden');
        productModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
      };

      quickViewTriggers.forEach((btn) => {
        btn.addEventListener('click', openModal);
      });

      closeButtons.forEach((btn) => {
        btn.addEventListener('click', closeModal);
      });

      productModal.addEventListener('click', (event) => {
        if (event.target === productModal) {
          closeModal();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !productModal.classList.contains('hidden')) {
          closeModal();
        }
      });
    }
  })();
  </script>
</body>
</html>
