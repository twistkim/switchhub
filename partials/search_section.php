<?php
// /partials/search_section.php
// 기대 변수: $q (string), $cat (int), $categories (array of [id, name])
?>
<!-- 헤드라인 & 검색 -->
<section class="text-center mb-10">
  <h1 class="text-3xl sm:text-4xl font-extrabold"><?= __('home.headline') ?></h1>
  <p class="mt-3 text-gray-600"><?= __('home.subhead') ?></p>

  <!-- 검색폼 -->
  <form id="searchForm" method="get" action="/index.php" class="mt-6 max-w-2xl mx-auto">
    <div class="flex items-stretch gap-2">
      <input
        type="text"
        name="q"
        value="<?= htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8') ?>"
        placeholder="<?= __('home.search_placeholder') ?>"
        class="flex-1 px-5 py-3 border border-gray-300 rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-primary"
      />
      <?php if (!empty($cat)): ?>
        <input type="hidden" name="cat" value="<?= (int)$cat ?>">
      <?php endif; ?>
      <?php
        $pay = $pay ?? ($_GET['pay'] ?? ''); // normal|cod|''
        if (in_array($pay, ['normal','cod'], true)):
      ?>
        <input type="hidden" name="pay" value="<?= htmlspecialchars($pay, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>
      <button type="submit" class="px-5 py-3 rounded-full bg-primary text-white font-semibold">
        <?= __('home.search_button') ?>
      </button>
    </div>
  </form>
</section>

<!-- 결제 방식 필터: 일반결제 / COD -->
<section class="mb-4">
  <?php
    $qv = trim($q ?? '');
    $cv = (int)($cat ?? 0);
    $pay = $pay ?? ($_GET['pay'] ?? '');
    $mk = function($newPay) use ($qv,$cv) {
      $params = [];
      if ($qv !== '') $params['q'] = $qv;
      if ($cv > 0)    $params['cat'] = $cv;
      if ($newPay !== '') $params['pay'] = $newPay; // '' means all
      return '/index.php' . ($params ? ('?'.http_build_query($params)) : '');
    };
  ?>
  <div class="flex items-center justify-center gap-2">
    <a href="<?= $mk('') ?>"
       class="px-3 py-1.5 rounded-full border min-w-fit <?= ($pay==='') ? 'bg-primary text-white border-primary' : 'bg-white hover:bg-gray-50' ?>">전체결제</a>
    <a href="<?= $mk('normal') ?>"
       class="px-3 py-1.5 rounded-full border min-w-fit <?= ($pay==='normal') ? 'bg-primary text-white border-primary' : 'bg-white hover:bg-gray-50' ?>">일반결제</a>
    <a href="<?= $mk('cod') ?>"
       class="px-3 py-1.5 rounded-full border min-w-fit <?= ($pay==='cod') ? 'bg-primary text-white border-primary' : 'bg-white hover:bg-gray-50' ?>">COD</a>
  </div>
</section>

<!-- 카테고리 필터: 가로 한 줄 + 슬라이드 -->
<section class="mb-6 relative">
  <style>
    /* 스크롤바 숨김 */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  </style>

  <?php
    $link = '/index.php';
    $isAll = (int)($cat ?? 0) === 0;
  ?>

  <!-- 좌우 스크롤 버튼 -->
  <button type="button" id="catPrev"
          class="hidden sm:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 h-9 w-9 items-center justify-center rounded-full border bg-white shadow"
          aria-label="이전">
    ‹
  </button>

  <div id="catScroller"
       class="flex gap-2 overflow-x-auto no-scrollbar whitespace-nowrap snap-x snap-mandatory px-10">
    <?php
      $payForAll = $pay ?? ($_GET['pay'] ?? '');
      $paramsAll = [];
      if (($q ?? '') !== '') $paramsAll['q'] = $q;
      if (in_array($payForAll, ['normal','cod'], true)) $paramsAll['pay'] = $payForAll;
      $hrefAll = '/index.php' . ($paramsAll ? ('?'.http_build_query($paramsAll)) : '');
    ?>
    <a href="<?= $hrefAll ?>"
       class="inline-flex snap-start px-4 py-2 rounded-full border <?= $isAll ? 'bg-primary text-white border-primary' : 'bg-white hover:bg-gray-50' ?> min-w-fit">
      전체
    </a>

    <?php if (!empty($categories)): ?>
      <?php foreach ($categories as $c):
        $isActive = ((int)($cat ?? 0) === (int)$c['id']);
        $pay = $pay ?? ($_GET['pay'] ?? '');
        $params = [];
        if (($q ?? '') !== '') $params['q'] = $q;
        $params['cat'] = (int)$c['id'];
        if (in_array($pay, ['normal','cod'], true)) $params['pay'] = $pay;
        $qs = http_build_query($params);
      ?>
        <a href="/index.php?<?= $qs ?>"
           class="inline-flex snap-start px-4 py-2 rounded-full border <?= $isActive ? 'bg-primary text-white border-primary' : 'bg-white hover:bg-gray-50' ?> min-w-fit">
          <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <button type="button" id="catNext"
          class="hidden sm:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 h-9 w-9 items-center justify-center rounded-full border bg-white shadow"
          aria-label="다음">
    ›
  </button>

  <script>
  (function(){
    const scroller = document.getElementById('catScroller');
    const prev = document.getElementById('catPrev');
    const next = document.getElementById('catNext');
    if (!scroller || !prev || !next) return;

    // 버튼 보이기 (카테고리가 넘치면)
    const toggleArrows = () => {
      const overflow = scroller.scrollWidth > scroller.clientWidth + 4;
      prev.classList.toggle('hidden', !overflow);
      next.classList.toggle('hidden', !overflow);
    };
    toggleArrows();
    window.addEventListener('resize', toggleArrows);

    const step = () => Math.max(160, Math.floor(scroller.clientWidth * 0.8));
    prev.addEventListener('click', () => scroller.scrollBy({left: -step(), behavior: 'smooth'}));
    next.addEventListener('click', () => scroller.scrollBy({left:  step(), behavior: 'smooth'}));
  })();
  </script>
</section>

<!-- 입력 즉시 검색 폼 자동 제출(300ms 디바운스) -->
<script>
(function(){
  const form = document.getElementById('searchForm');
  if (!form) return;
  const input = form.querySelector('input[name="q"]');
  let t = null;
  input.addEventListener('input', () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => form.submit(), 300);
  });
})();
</script>