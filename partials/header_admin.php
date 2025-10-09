<?php
// 관리자 전용 헤더
$pageTitle  = $pageTitle  ?? '관리자 대시보드';
$activeMenu = $activeMenu ?? 'admin';
require_once __DIR__ . '/../auth/session.php';
$me = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <!-- Tailwind 로드 (이미 index.php 등에서 사용 중) -->
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
  <!-- Top Bar -->
  <header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <!-- Left: Logo / Title -->
      <div class="flex items-center gap-3">
        <a href="/admin/index.php" class="flex items-center gap-2">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary font-extrabold">A</span>
          <span class="hidden sm:inline text-lg font-bold">Admin Console</span>
        </a>
        <a href="/index.php" class="flex items-center gap-2">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary font-extrabold">H</span>
        </a>
      </div>

      <!-- Right: User -->
      <div class="hidden md:flex items-center gap-3">
        <?php if ($me): ?>
          <span class="text-sm text-gray-600"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (관리자)</span>
          <a href="/" class="text-sm hover:text-primary">스토어</a>
          <a href="/auth/logout.php" class="px-3 py-1.5 rounded-md border">로그아웃</a>
        <?php else: ?>
          <a href="/auth/login.php" class="px-3 py-1.5 rounded-md border">로그인</a>
        <?php endif; ?>
        <button id="adminMenuBtn" class="md:hidden inline-flex items-center justify-center h-9 w-9 rounded-md border" aria-label="open menu">
          ☰
        </button>
      </div>

      <!-- Mobile button (visible on small) -->
      <button id="adminMenuBtnSm" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-md border" aria-label="open menu">
        ☰
      </button>
    </div>

    <!-- Admin Nav -->
    <nav class="bg-white border-t md:border-0">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="hidden md:flex h-12 items-center gap-4 text-sm">
          <?php
            // 활성 클래스 헬퍼
            $is = fn($k)=> $activeMenu===$k ? 'text-primary font-semibold' : 'text-gray-700 hover:text-primary';
          ?>
          <a href="/admin/orders.php" class="<?= $is('orders') ?>">주문 관리</a>
          <a href="/admin/index.php?tab=issues" class="<?= $is('issues') ?>">하자 신청</a>
          <a href="/admin/index.php?tab=settlements" class="<?= $is('settlements') ?>">정산 요청</a>
          <a href="/admin/partners.php" class="<?= $is('partners') ?>">파트너 관리</a>
          <a href="/admin/products_pending.php" class="<?= $is('products_pending') ?>">상품 승인</a>
          <a href="/admin/product_new.php" class="<?= $is('product_new') ?>">상품 등록</a>
          <!-- /partials/header_admin.php 내 관리자 메뉴 영역에 -->
          <a href="/admin/categories.php" class="text-gray-700 hover:text-primary">카테고리</a>
        </div>
      </div>

      <!-- Mobile Drawer -->
      <div id="adminMobileNav" class="md:hidden hidden border-t">
        <div class="px-4 py-2 space-y-1">
          <a href="/admin/orders.php" class="block px-3 py-2 rounded-md <?= $activeMenu==='orders'?'bg-primary-50 text-primary':'hover:bg-gray-50'?>">주문 관리</a>
          <a href="/admin/index.php?tab=issues" class="block px-3 py-2 rounded-md <?= $activeMenu==='issues'?'bg-primary-50 text-primary':'hover:bg-gray-50'?>">하자 신청</a>
          <a href="/admin/index.php?tab=settlements" class="block px-3 py-2 rounded-md <?= $activeMenu==='settlements'?'bg-primary-50 text-primary':'hover:bg-gray-50'?>">정산 요청</a>
          <a href="/admin/partners.php" class="block px-3 py-2 rounded-md <?= $activeMenu==='partners'?'bg-primary-50 text-primary':'hover:bg-gray-50'?>">파트너 관리</a>
          <a href="/admin/products_pending.php" class="block px-3 py-2 rounded-md <?= $activeMenu==='products_pending'?'bg-primary-50 text-primary':'hover:bg-gray-50'?>">상품 승인</a>
          <a href="/admin/product_new.php" class="block px-3 py-2 rounded-md <?= $activeMenu==='product_new'?'bg-primary-50 text-primary':'hover:bg-gray-50'?>">상품 등록</a>
          <!-- /partials/header_admin.php 내 관리자 메뉴 영역에 -->
          <a href="/admin/categories.php" class="text-gray-700 hover:text-primary">카테고리</a>
          
          <div class="pt-2 mt-2 border-t">
            <?php if ($me): ?>
              <div class="px-3 py-2 text-sm text-gray-600"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (관리자)</div>
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

  <!-- Main Container -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <script>
    (function () {
      const btn1 = document.getElementById('adminMenuBtn');
      const btn2 = document.getElementById('adminMenuBtnSm');
      const nav = document.getElementById('adminMobileNav');
      function toggle() { if (nav) nav.classList.toggle('hidden'); }
      if (btn1) btn1.addEventListener('click', toggle);
      if (btn2) btn2.addEventListener('click', toggle);
    })();
  </script>