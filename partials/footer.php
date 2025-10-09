  </main>
  <!-- 메인 컨테이너 끝 -->

</footer>
<?php
  // ==== Fixed bottom nav (global) ====
  // Helper to prefix language segment if present
  if (!function_exists('lang_href')) {
    function lang_href(string $path): string {
      $prefix = defined('APP_LANG') && APP_LANG ? '/' . APP_LANG : '';
      // Avoid double slashes when $path already begins with '/'
      return $prefix . (strpos($path, '/') === 0 ? $path : '/' . $path);
    }
  }
  // Current user & role
  $role = $_SESSION['user']['role'] ?? 'guest';
  $loggedIn = isset($_SESSION['user']['id']);
  // Dynamic targets
  $homeUrl      = lang_href('/index.php');
  $myUrl        = $loggedIn ? lang_href('/my.php') : lang_href('/auth/login.php');
  // Messages URL varies by role
  if ($loggedIn) {
    if ($role === 'partner') {
      $messagesUrl = lang_href('/partner/messages.php');
    } elseif ($role === 'admin') {
      $messagesUrl = lang_href('/admin/messages.php');
    } else {
      $messagesUrl = lang_href('/my_messages.php');
    }
  } else {
    $messagesUrl = lang_href('/auth/login.php');
  }
  $storesUrl    = lang_href('/partners.php');
?>
<!-- layout spacer so fixed bar doesn't cover content -->
<div class="h-16"></div>

<!-- Fixed bottom navigation -->
<nav class="fixed left-0 right-0 bottom-0 z-50 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 border-t">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <ul class="grid grid-cols-4">
      <!-- My Page -->
      <li>
        <a href="<?= htmlspecialchars($myUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center justify-center py-2.5 text-xs text-gray-600 hover:text-primary">
          <!-- user icon -->
          <svg class="w-6 h-6 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 21a8 8 0 0 0-16 0"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <span><?= htmlspecialchars(__('nav.my') ?: 'My Page', ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </li>
      <!-- Messages -->
      <li>
        <a href="<?= htmlspecialchars($messagesUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center justify-center py-2.5 text-xs text-gray-600 hover:text-primary">
          <!-- message icon -->
          <svg class="w-6 h-6 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>
          </svg>
          <span><?= htmlspecialchars(__('nav.messages') ?: 'Messages', ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </li>
      <!-- Home -->
      <li>
        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center justify-center py-2.5 text-xs text-gray-600 hover:text-primary">
          <!-- home icon -->
          <svg class="w-6 h-6 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 11l9-8 9 8"></path>
            <path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"></path>
          </svg>
          <span><?= htmlspecialchars(__('nav.home') ?: 'Home', ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </li>
      <!-- Partner Stores -->
      <li>
        <a href="<?= htmlspecialchars($storesUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center justify-center py-2.5 text-xs text-gray-600 hover:text-primary">
          <!-- store icon -->
          <svg class="w-6 h-6 mb-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 7l2-3h12l2 3"></path>
            <path d="M3 7h18l-1 5a5 5 0 0 1-5 4H9a5 5 0 0 1-5-4L3 7z"></path>
            <path d="M7 21h10"></path>
          </svg>
          <span><?= htmlspecialchars(__('nav.partner_stores') ?: 'Partner Stores', ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </li>
    </ul>
  </div>
</nav>
<?php
  // ===== Floating widgets (Inquiry / Search) =====
  // Configure target links (edit as you like)
  if (!function_exists('lang_href')) {
    function lang_href(string $path): string {
      $prefix = defined('APP_LANG') && APP_LANG ? '/' . APP_LANG : '';
      return $prefix . (strpos($path, '/') === 0 ? $path : '/' . $path);
    }
  }
  // Default: inquiry -> a dedicated contact page (change to your desired link)
  $inquiryUrl = 'https://line.me/ti/p/PwM_Ru5fV9';
  // Default: search -> 홈 + 검색 패널 자동 오픈
  $searchUrl  = lang_href('/index.php?open_search=1#search');
?>
<!-- Floating action widgets -->
<div id="floatingWidgets"
     class="hidden fixed right-4 bottom-24 z-[60] flex flex-col gap-3">
  <!-- Inquiry -->
  <a href="<?= htmlspecialchars($inquiryUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"
     class="inline-flex items-center gap-2 px-4 py-2 rounded-full shadow-lg bg-primary text-white hover:bg-primary/90 active:scale-[0.98] transition"
     aria-label="문의하기">
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>
    </svg>
    <span class="text-sm font-semibold">문의하기</span>
  </a>

  <!-- Search -->
  <a href="<?= htmlspecialchars($searchUrl, ENT_QUOTES, 'UTF-8') ?>"
     class="inline-flex items-center gap-2 px-4 py-2 rounded-full shadow-lg bg-gray-900 text-white hover:bg-gray-800 active:scale-[0.98] transition"
     aria-label="검색하기">
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="7"></circle>
      <path d="M21 21l-4.35-4.35"></path>
    </svg>
    <span class="text-sm font-semibold">검색하기</span>
  </a>
</div>

<script>
(function() {
  const widgets = document.getElementById('floatingWidgets');
  if (!widgets) return;

  const THRESHOLD = 120; // px below which the widgets stay hidden
  const update = () => {
    if (window.scrollY > THRESHOLD) {
      widgets.classList.remove('hidden');
    } else {
      widgets.classList.add('hidden');
    }
  };
  // Initial & on scroll
  document.addEventListener('scroll', update, { passive: true });
  window.addEventListener('load', update);
})();
</script>
</body>
</html>