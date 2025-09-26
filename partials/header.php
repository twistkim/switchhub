<?php
// 헤더가 단독으로 불려도 안전하게 i18n이 보장되도록
if (!function_exists('lang_url')) {
  require_once __DIR__ . '/../i18n/bootstrap.php';
}
$me = $_SESSION['user'] ?? null;
$role = strtolower($me['role'] ?? '');
$curClean = i18n_current_path_clean(); // 현재 경로에서 언어 프리픽스 제거
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
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-2xl bg-primary-100">📱</span>
          <span class="text-xl font-extrabold">폰스위치허브</span>
        </a>

        <!-- 데스크탑 메뉴 -->
        <nav class="hidden md:flex items-center gap-6">
          <a href="<?= htmlspecialchars(lang_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.home') ?: 'Home' ?></a>
          <a href="<?= htmlspecialchars(lang_url('/cart.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.cart') ?: 'Cart' ?></a>

          <?php if ($role === 'partner'): ?>
            <a href="<?= htmlspecialchars(lang_url('/partner/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.partner_dashboard') ?: 'Partner Dashboard' ?></a>
          <?php else: ?>
            <a href="<?= htmlspecialchars(lang_url('/partner_apply.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.partner_apply') ?: 'Partner Apply' ?></a>
          <?php endif; ?>

          <?php if ($me): ?>
            <?php if ($role === 'admin'): ?>
              <a href="<?= htmlspecialchars(lang_url('/admin/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.admin') ?: 'Admin' ?></a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars(lang_url('/my.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.mypage') ?: 'My Page' ?></a>

            <?php if ($role === 'partner'): ?>
              <a href="<?= htmlspecialchars(lang_url('/partner/messages.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.messages') ?: 'Messages' ?></a>
            <?php else: ?>
              <a href="<?= htmlspecialchars(lang_url('/my_messages.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.messages') ?: 'Messages' ?></a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars(lang_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.logout') ?: 'Logout' ?></a>
          <?php else: ?>
            <a href="<?= htmlspecialchars(lang_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.login') ?: 'Login' ?></a>
            <a href="<?= htmlspecialchars(lang_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary"><?= __('nav.register') ?: 'Register' ?></a>
          <?php endif; ?>

          <!-- 언어 스위처 (현재 경로 유지) -->
          <div class="relative">
            <label for="langDesktop" class="sr-only">Language</label>
            <select id="langDesktop" class="px-3 py-1.5 rounded-md border text-sm bg-white hover:border-gray-400">
              <option value="<?= htmlspecialchars(lang_url($curClean, 'ko'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='ko'?'selected':'' ?>>한국어</option>
              <option value="<?= htmlspecialchars(lang_url($curClean, 'th'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='th'?'selected':'' ?>>ไทย</option>
              <option value="<?= htmlspecialchars(lang_url($curClean, 'en'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='en'?'selected':'' ?>>English</option>
              <option value="<?= htmlspecialchars(lang_url($curClean, 'my'), ENT_QUOTES, 'UTF-8') ?>" <?= APP_LANG==='my'?'selected':'' ?>>မြန်မာ</option>
            </select>
          </div>
        </nav>

        <!-- 모바일 메뉴 버튼 -->
        <button id="btnMobile" class="md:hidden inline-flex items-center justify-center p-2 rounded-md border hover:bg-gray-100">
          <span class="sr-only">menu</span>☰
        </button>
      </div>
    </div>

    <!-- 모바일 드롭다운 -->
    <div id="mobileMenu" class="md:hidden hidden border-t bg-white">
      <nav class="px-4 py-2 space-y-1">
        <a href="<?= htmlspecialchars(lang_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.home') ?: 'Home' ?></a>
        <a href="<?= htmlspecialchars(lang_url('/cart.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.cart') ?: 'Cart' ?></a>

        <?php if ($role === 'partner'): ?>
          <a href="<?= htmlspecialchars(lang_url('/partner/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.partner_dashboard') ?: 'Partner Dashboard' ?></a>
        <?php else: ?>
          <a href="<?= htmlspecialchars(lang_url('/partner_apply.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50"><?= __('nav.partner_apply') ?: 'Partner Apply' ?></a>
        <?php endif; ?>

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
      if (btn && menu) btn.addEventListener('click', () => menu.classList.toggle('hidden'));

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