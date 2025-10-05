<?php
// Partner header (mobile-friendly)
// Assumes: require pages set $pageTitle, $activeMenu
// Ensures i18n/session loaded even when included directly
if (!function_exists('lang_url')) {
  require_once __DIR__ . '/../i18n/bootstrap.php';
}
require_once __DIR__ . '/../auth/session.php';
$me   = $_SESSION['user'] ?? null;
$role = strtolower($me['role'] ?? '');

// 파트너 전용 보호는 각 페이지에서 require_role('partner') 사용 권장
$curClean = i18n_current_path_clean();
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LANG, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Partner Dashboard - PhoneSwitchHub', ENT_QUOTES, 'UTF-8') ?></title>
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
  <header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <!-- Logo -->
        <a href="<?= htmlspecialchars(lang_url('/partner/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-2">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-2xl bg-primary-100">📱</span>
          <span class="text-xl font-extrabold">파트너센터</span>
        </a>

        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-6">
          <a href="<?= htmlspecialchars(lang_url('/partner/index.php'), ENT_QUOTES, 'UTF-8') ?>"
             class="<?= ($activeMenu ?? '')==='dashboard' ? 'text-primary font-semibold' : 'text-gray-700 hover:text-primary' ?>">대시보드</a>

          <a href="<?= htmlspecialchars(lang_url('/partner/orders.php'), ENT_QUOTES, 'UTF-8') ?>"
             class="<?= ($activeMenu ?? '')==='orders' ? 'text-primary font-semibold' : 'text-gray-700 hover:text-primary' ?>">주문관리</a>

          <a href="<?= htmlspecialchars(lang_url('/partner/messages.php'), ENT_QUOTES, 'UTF-8') ?>"
             class="<?= ($activeMenu ?? '')==='messages' ? 'text-primary font-semibold' : 'text-gray-700 hover:text-primary' ?>">쪽지함</a>

          <a href="<?= htmlspecialchars(lang_url('/partner/product_new.php'), ENT_QUOTES, 'UTF-8') ?>"
             class="<?= ($activeMenu ?? '')==='product_new' ? 'text-primary font-semibold' : 'text-gray-700 hover:text-primary' ?>">상품등록</a>

          <!-- Optional: 관리자 링크 노출 -->
          <?php if ($role === 'admin'): ?>
            <a href="<?= htmlspecialchars(lang_url('/admin/index.php'), ENT_QUOTES, 'UTF-8') ?>"
               class="text-gray-700 hover:text-primary">관리자</a>
          <?php endif; ?>

          <!-- Back to store & common links -->
          <a href="<?= htmlspecialchars(lang_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary">스토어</a>

          <?php if ($me): ?>
            <a href="<?= htmlspecialchars(lang_url('/my.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary">마이페이지</a>
            <a href="<?= htmlspecialchars(lang_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary">로그아웃</a>
          <?php else: ?>
            <a href="<?= htmlspecialchars(lang_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-gray-700 hover:text-primary">로그인</a>
          <?php endif; ?>

          <!-- Language switcher -->
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

        <!-- Mobile menu button -->
        <button id="btnMobile" class="md:hidden inline-flex items-center justify-center p-2 rounded-md border hover:bg-gray-100">
          <span class="sr-only">menu</span>☰
        </button>
      </div>
    </div>

    <!-- Mobile dropdown -->
    <div id="mobileMenu" class="md:hidden hidden border-t bg-white">
      <nav class="px-4 py-2 space-y-1">
        <a href="<?= htmlspecialchars(lang_url('/partner/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">대시보드</a>
        <a href="<?= htmlspecialchars(lang_url('/partner/orders.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">주문관리</a>
        <a href="<?= htmlspecialchars(lang_url('/partner/messages.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">쪽지함</a>
        <a href="<?= htmlspecialchars(lang_url('/partner/product_new.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">상품등록</a>
        <?php if ($role === 'admin'): ?>
          <a href="<?= htmlspecialchars(lang_url('/admin/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">관리자</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(lang_url('/index.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">스토어</a>
        <?php if ($me): ?>
          <a href="<?= htmlspecialchars(lang_url('/my.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">마이페이지</a>
          <a href="<?= htmlspecialchars(lang_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">로그아웃</a>
        <?php else: ?>
          <a href="<?= htmlspecialchars(lang_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>" class="block px-3 py-2 rounded-md hover:bg-gray-50">로그인</a>
        <?php endif; ?>
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
    document.addEventListener('DOMContentLoaded', () => {
      const btn = document.getElementById('btnMobile');
      const menu = document.getElementById('mobileMenu');
      if (btn && menu) btn.addEventListener('click', () => menu.classList.toggle('hidden'));
      function hook(id){
        const el = document.getElementById(id);
        if(!el) return;
        el.addEventListener('change', ()=>{ const url = el.value; if(url) location.href = url; });
      }
      hook('langDesktop');
      hook('langMobile');
    });
  </script>

  <!-- 메인 컨테이너 시작 -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">