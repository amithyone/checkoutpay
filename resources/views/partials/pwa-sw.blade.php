{{-- PWA: Service worker registration + Install prompt (Chrome/Edge) and iOS Add to Home Screen --}}
<div id="pwa-install-banner" class="hidden fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:max-w-sm z-[100] bg-gray-900 text-white rounded-xl shadow-lg p-4 flex items-center gap-3" role="dialog" aria-label="Install app">
    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-primary flex items-center justify-center">
        <i class="fas fa-download text-xl"></i>
    </div>
    <div class="flex-1 min-w-0">
        <p class="font-semibold text-sm">Install app</p>
        <p id="pwa-install-hint" class="text-xs text-gray-300">Use from home screen for a better experience.</p>
    </div>
    <div class="flex flex-col gap-1 flex-shrink-0">
        <button type="button" id="pwa-install-btn" class="px-3 py-2 bg-primary hover:bg-primary/90 rounded-lg text-sm font-medium whitespace-nowrap">
            Install
        </button>
        <button type="button" id="pwa-install-dismiss" class="text-xs text-gray-400 hover:text-white" aria-label="Dismiss">Not now</button>
    </div>
    <button type="button" id="pwa-install-close" class="absolute top-2 right-2 text-gray-400 hover:text-white p-1" aria-label="Close">
        <i class="fas fa-times text-sm"></i>
    </button>
</div>

<script>
(function() {
  var installBanner = document.getElementById('pwa-install-banner');
  var installBtn = document.getElementById('pwa-install-btn');
  var installHint = document.getElementById('pwa-install-hint');
  var dismissBtn = document.getElementById('pwa-install-dismiss');
  var closeBtn = document.getElementById('pwa-install-close');
  var deferredPrompt = null;
  var dismissedKey = 'pwa-install-dismissed';
  var dismissedExpiry = 7 * 24 * 60 * 60 * 1000; // 7 days

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true ||
           document.referrer.includes('android-app://');
  }

  function wasDismissed() {
    try {
      var v = localStorage.getItem(dismissedKey);
      if (!v) return false;
      var t = parseInt(v, 10);
      return !isNaN(t) && (Date.now() - t) < dismissedExpiry;
    } catch (e) { return false; }
  }

  function setDismissed() {
    try { localStorage.setItem(dismissedKey, String(Date.now())); } catch (e) {}
  }

  function showBanner() {
    if (!installBanner || isStandalone() || wasDismissed()) return;
    installBanner.classList.remove('hidden');
  }

  function hideBanner() {
    if (installBanner) installBanner.classList.add('hidden');
  }

  function isIos() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  if (installBtn) {
    installBtn.addEventListener('click', function() {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(choice) {
          if (choice.outcome === 'accepted') hideBanner();
          deferredPrompt = null;
        });
      }
    });
  }
  if (dismissBtn) dismissBtn.addEventListener('click', function() { setDismissed(); hideBanner(); });
  if (closeBtn) closeBtn.addEventListener('click', function() { setDismissed(); hideBanner(); });

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('{{ url("/sw.js") }}', { scope: '/' })
        .then(function(reg) { /* reg.update(); */ })
        .catch(function() {});
    });
  }

  window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferredPrompt = e;
    if (installHint) installHint.textContent = 'Use from home screen for a better experience.';
    showBanner();
  });

  if (isIos() && !isStandalone()) {
    if (installHint) installHint.textContent = 'Tap Share \u2192 Add to Home Screen';
    if (installBtn) installBtn.textContent = 'Got it';
    var iosHandler = function() { hideBanner(); installBtn.removeEventListener('click', iosHandler); };
    if (installBtn) installBtn.addEventListener('click', iosHandler);
    setTimeout(showBanner, 1500);
  }
})();
</script>
