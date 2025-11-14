<?php
/**
 * Standalone auction demo template matching the provided Tailwind markup.
 */

defined( 'ABSPATH' ) || exit;
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
            <strong>Gymtek Mini Bike XMB200 (LCD)</strong>
            <span class="text-sm text-gray-500">Retail: €50</span>
          </div>

          <!-- Bigger image -->
          <div class="mt-4">
            <img
              decoding="async"
              src="https://www.1ba.lt/wp-content/uploads/2025/11/Gymtek-Mini-Bike-XMB200-LCD-1400x1400-1.webp"
              alt="Gymtek Mini Bike XMB200"
              class="w-full max-h-80 mx-auto rounded-xl object-contain border"
            >
          </div>

          <!-- Info under image -->
          <div class="mt-4 text-sm text-gray-700 space-y-1">
            <p><span class="font-medium">Retail:</span> €50</p>
            <p>• Claim it for €1 at checkout if you win.</p>
            <p>• Registration fee: €1</p>
            <p>• Bid fee: €1 per bid</p>
            <p class="pt-2">
              Compact mini bike trainer with LCD display for time, distance and calories. Perfect for
              under-desk pedaling or low-impact home workouts.
            </p>
          </div>

          <!-- Buttons under description -->
          <div class="mt-4 flex items-center gap-4">
            <button class="px-4 py-2 rounded-xl bg-black text-white text-sm">
              Quick View
            </button>
            <a href="#" class="text-sm text-gray-800 underline">
              View product
            </a>
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
  })();
  </script>
</body>
</html>
