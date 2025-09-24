<?php
// 파트너 전용 헤더
$pageTitle  = $pageTitle  ?? '파트너 대시보드';
$activeMenu = $activeMenu ?? 'partner';
require_once __DIR__ . '/../auth/session.php';
$me = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
  <header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="/partner/index.php" class="flex items-center gap-2">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 font-extrabold">P</span>
          <span class="hidden sm:inline text-lg font-bold">Partner Console</span>
        </a>
      </div>

      <div class="hidden md:flex items-center gap-3">
        <?php if ($me): ?>
          <span class="text-sm text-gray-600"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (파트너)</span>
          <a href="/" class="text-sm hover:text-emerald-700">스토어</a>
          <a href="/auth/logout.php" class="px-3 py-1.5 rounded-md border">로그아웃</a>
        <?php else: ?>
          <a href="/auth/login.php" class="px-3 py-1.5 rounded-md border">로그인</a>
        <?php endif; ?>
        <button id="partnerMenuBtn" class="md:hidden inline-flex items-center justify-center h-9 w-9 rounded-md border" aria-label="open menu">
          ☰
        </button>
      </div>

      <button id="partnerMenuBtnSm" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-md border" aria-label="open menu">
        ☰
      </button>
    </div>

    <nav class="bg-white border-t md:border-0">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="hidden md:flex h-12 items-center gap-4 text-sm">
          <?php $is = fn($k)=> $activeMenu===$k ? 'text-emerald-700 font-semibold' : 'text-gray-700 hover:text-emerald-700'; ?>
          <a href="/partner/index.php" class="<?= $is('partner') ?>">대시보드</a>
          <a href="/partner/product_new.php" class="<?= $is('product_new') ?>">상품 등록</a>
          <a href="/partner/index.php#sales" class="<?= $is('sales') ?>">판매 내역</a>
          <a href="/partner/index.php#settlements" class="<?= $is('settlement') ?>">정산 현황</a>
        </div>
      </div>

      <!-- Mobile -->
      <div id="partnerMobileNav" class="md:hidden hidden border-t">
        <div class="px-4 py-2 space-y-1">
          <a href="/partner/index.php" class="block px-3 py-2 rounded-md <?= $activeMenu==='partner'?'bg-emerald-50 text-emerald-700':'hover:bg-gray-50'?>">대시보드</a>
          <a href="/partner/product_new.php" class="block px-3 py-2 rounded-md <?= $activeMenu==='product_new'?'bg-emerald-50 text-emerald-700':'hover:bg-gray-50'?>">상품 등록</a>
          <a href="/partner/index.php#sales" class="block px-3 py-2 rounded-md <?= $activeMenu==='sales'?'bg-emerald-50 text-emerald-700':'hover:bg-gray-50'?>">판매 내역</a>
          <a href="/partner/index.php#settlement" class="block px-3 py-2 rounded-md <?= $activeMenu==='settlements'?'bg-emerald-50 text-emerald-700':'hover:bg-gray-50'?>">정산 현황</a>
          <div class="pt-2 mt-2 border-t">
            <?php if ($me): ?>
              <div class="px-3 py-2 text-sm text-gray-600"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (파트너)</div>
              <a href="/" class="block px-3 py-2 rounded-md hover:bg-gray-50">스토어</a>
              <a href="/auth/logout.php" class="block px-3 py-2 rounded-md hover:bg-gray-50">로그아웃</a>
            <?php else: ?>
              <a href="/auth/login.php" class="block px-3 py-2 rounded-md hover:bg-gray-50">로그인</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </nav>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <script>
    (function () {
      const btn1 = document.getElementById('partnerMenuBtn');
      const btn2 = document.getElementById('partnerMenuBtnSm');
      const nav = document.getElementById('partnerMobileNav');
      function toggle() { if (nav) nav.classList.toggle('hidden'); }
      if (btn1) btn1.addEventListener('click', toggle);
      if (btn2) btn2.addEventListener('click', toggle);
    })();
  </script>