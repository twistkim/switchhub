<?php
// 헤더가 단독으로 불려도 안전하게 i18n이 보장되도록
if (!function_exists('lang_url')) {
  require_once __DIR__ . '/../i18n/bootstrap.php';
}
$me = $_SESSION['user'] ?? null;
$role = strtolower($me['role'] ?? '');
$curClean = i18n_current_path_clean(); // 현재 경로에서 언어 프리픽스 제거
?>
<?php
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  // 장바구니 구조에 따라 조정: 여기선 단순 개수 합산/혹은 item 수
  $cartCount = count($_SESSION['cart']);
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LANG, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'PhoneSwitchHub', ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              DEFAULT: '#2563eb',
              50: '#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',
              500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-50 text-gray-900">
  <!-- Header -->
  <header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <!-- 로고 -->
        <a href="<?= htmlspecialchars(lang_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-2">
          <span class="text-xl font-extrabold">
            <img src="./img/logo.svg">
          </span>
        </a>

        <!-- 우측 컨트롤: 장바구니 + 햄버거 (모든 해상도 공통) -->
        <div class="flex items-center gap-2">
          <!-- Cart button -->
          <a href="<?= htmlspecialchars(lang_url('/cart.php'), ENT_QUOTES, 'UTF-8') ?>"
             class="relative inline-flex items-center justify-center p-2 rounded-md border hover:bg-gray-100"
             aria-label="Cart">
            <!-- cart icon -->
            <svg class="w-6 h-6 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 12.39a2 2 0 0 0 2 1.61h7.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <?php if ($cartCount > 0): ?>
              <span class="absolute -top-1 -right-1 min-w-[1.1rem] h-5 px-1 rounded-full bg-primary text-white text-[11px] leading-5 text-center">
                <?= (int)$cartCount ?>
              </span>
            <?php endif; ?>
          </a>

          <!-- Hamburger button -->
          <button id="btnMobile"
                  class="inline-flex items-center justify-center p-2 rounded-md border hover:bg-gray-100"
                  aria-label="menu" aria-controls="mobileMenu" aria-expanded="false">
            <!-- hamburger icon -->
            <svg class="w-6 h-6 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- 모바일 드롭다운 -->
    <div id="mobileMenu" class="hidden border-t bg-white md:fixed md:right-4 md:top-16 md:w-80 md:shadow-lg md:rounded-lg md:border md:z-50">
      <nav class="px-4 py-2 space-y-1">
        <a href="<?= htmlspecialchars(lang_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.home') ?: 'Home' ?></a>
        <a href="<?= htmlspecialchars(lang_url('/cart.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.cart') ?: 'Cart' ?></a>

        <?php if ($role === 'partner'): ?>
          <a href="<?= htmlspecialchars(lang_url('/partner/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.partner_dashboard') ?: 'Partner Dashboard' ?></a>
        <?php else: ?>
          <a href="<?= htmlspecialchars(lang_url('/partner_apply.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.partner_apply') ?: 'Partner Apply' ?></a>
        <?php endif; ?>

        <a href="<?= htmlspecialchars(lang_url('/partners.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.partner_stores') ?: 'Partner Stores' ?></a>

        <?php if ($me): ?>
          <?php if ($role === 'admin'): ?>
            <a href="<?= htmlspecialchars(lang_url('/admin/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.admin') ?: 'Admin' ?></a>
          <?php endif; ?>
          <a href="<?= htmlspecialchars(lang_url('/my.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.mypage') ?: 'My Page' ?></a>
          <?php if ($role === 'partner'): ?>
            <a href="<?= htmlspecialchars(lang_url('/partner/messages.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.messages') ?: 'Messages' ?></a>
          <?php else: ?>
            <a href="<?= htmlspecialchars(lang_url('/my_messages.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.messages') ?: 'Messages' ?></a>
          <?php endif; ?>
          <a href="<?= htmlspecialchars(lang_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.logout') ?: 'Logout' ?></a>
        <?php else: ?>
          <a href="<?= htmlspecialchars(lang_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.login') ?: 'Login' ?></a>
          <a href="<?= htmlspecialchars(lang_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.register') ?: 'Register' ?></a>
        <?php endif; ?>

        <!-- 언어 스위처 모바일 -->
        <div class="pt-2">
          <label for="langMobile" class="sr-only">Language</label>
          <select id="langMobile" class="w-full px-3 py-2 rounded-md border text-sm bg-white">
            <option value="<?= htmlspecialchars(lang_url($curClean, 'ko'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='ko'?'selected':'' ?>>Korean</option>
            <option value="<?= htmlspecialchars(lang_url($curClean, 'th'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='th'?'selected':'' ?>>Thai</option>
            <option value="<?= htmlspecialchars(lang_url($curClean, 'en'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='en'?'selected':'' ?>>English</option>
            <option value="<?= htmlspecialchars(lang_url($curClean, 'my'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='my'?'selected':'' ?>>Myanmar</option>
          </select>
        </div>
      </nav>
    </div>
  </header>

  <script>
    // 모바일 메뉴 토글
    document.addEventListener('DOMContentLoaded', () => {
      const btn = document.getElementById('btnMobile');
      const menu = document.getElementById('mobileMenu');
      if (btn && menu) {
        btn.addEventListener('click', () => {
          const hidden = menu.classList.toggle('hidden');
          btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        });
      }

      const hook = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', () => {
          const url = el.value;
          if (url && typeof url === 'string') {
            window.location.href = url;
          }
        });
      };
      hook('langDesktop');
      hook('langMobile');
    });
  </script>

  <!-- 메인 컨테이너 시작 -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">