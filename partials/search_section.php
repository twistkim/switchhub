<!-- Toggle Search Button -->
<div class="flex justify-center my-12">
  <button id="toggleSearchBtn" class="px-6 py-3 bg-primary text-white rounded-lg font-semibold shadow hover:bg-primary/90 active:scale-[0.98] transition">
    <!-- SparklesIcon -->
    <svg xmlns="http://www.w3.org/2000/svg"
        class="w-6 h-6 text-yellow-500 inline-block"
        fill="none" viewBox="0 0 28 28" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" 
            d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414M16.95 16.95l1.414 1.414M7.05 7.05L5.636 5.636M12 8l1.176 3.176L16.5 12l-3.324.824L12 16l-.824-3.176L7.5 12l3.676-.824L12 8z" />
    </svg>
  
  원하는 폰 찾아보기
  </button>
</div>
<!-- Anchor to allow #search to scroll here -->
<div id="search" class="sr-only" aria-hidden="true"></div>
<div id="searchArea" class="hidden transition-all duration-500 ease-in-out overflow-hidden overflow-y-auto max-h-0" style="max-height:0">
<section class="mb-6">
  <?php
    // $categories: SELECT * FROM categories 결과 배열을 index.php 등에서 넘겨주세요.
    $list = is_array($categories ?? null) ? $categories : [];

    // 1) parent 키 후보 (스키마 다양성 대응)
    $PARENT_KEYS = ['parent_id','parentId','parentID','parentid','parent','parent_category_id','parent_category','pid','p_id'];

    // 2) 인덱스 구축: id->row, id->parent, parent->children[]
    $rowsById   = [];
    $parentOf   = [];
    $childrenOf = [];

    foreach ($list as $c) {
      $cid = (int)($c['id'] ?? 0);
      if ($cid <= 0) continue;
      $rowsById[$cid] = $c;

      // parent 탐지 (NULL, 'NULL', '', 공백 → 0으로 간주)
      $pid = 0;
      foreach ($PARENT_KEYS as $k) {
        if (array_key_exists($k, $c)) {
          $raw = $c[$k];
          if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim === '' || strcasecmp($trim, 'null') === 0) { $raw = 0; }
          }
          if ($raw === null) { $raw = 0; }

          if (is_numeric($raw)) {
            $tmp = (int)$raw;
            if ($tmp > 0) { $pid = $tmp; break; }
          }
        }
      }
      $parentOf[$cid] = $pid;
      if (!isset($childrenOf[$pid])) $childrenOf[$pid] = [];
      $childrenOf[$pid][] = ['id'=>$cid, 'name'=>(string)($c['name'] ?? '')];
    }

    // ---- DEBUG (?debugcat=1) ----
    if (isset($_GET['debugcat'])) {
      echo '<div class="max-w-4xl mx-auto my-4 p-3 border rounded bg-white text-sm">';
      echo '<div class="font-semibold mb-2">CAT DEBUG</div>';
      echo '<div class="mb-2">total=' . count($list) . ' · lv1=' . count($childrenOf[0] ?? []) . ' · parents=' . count($childrenOf) . '</div>';
      echo '<details open><summary class="cursor-pointer mb-2">샘플 30개 (id, name, parent-key/value, computed pid)</summary>';
      echo '<div class="overflow-x-auto"><table class="min-w-full text-xs"><thead><tr><th class="px-2 py-1 text-left">#</th><th class="px-2 py-1 text-left">id</th><th class="px-2 py-1 text-left">name</th><th class="px-2 py-1 text-left">detected key</th><th class="px-2 py-1 text-left">raw</th><th class="px-2 py-1 text-left">pid</th></tr></thead><tbody>';
      $i = 0;
      foreach ($list as $c) {
        if ($i++ >= 30) break;
        $cid = (int)($c['id'] ?? 0);
        $cname = htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $detKey = '(none)'; $rawShow = ''; $pidShow = 0;
        foreach ($PARENT_KEYS as $k) {
          if (array_key_exists($k, $c)) {
            $raw = $c[$k];
            $rawShow = is_scalar($raw) ? (string)$raw : json_encode($raw);
            if (is_string($raw)) { $t = trim($raw); if ($t === '' || strcasecmp($t,'null')===0) { $raw = 0; } }
            if ($raw === null) { $raw = 0; }
            if (is_numeric($raw)) {
              $tmp = (int)$raw; if ($tmp > 0) { $pidShow = $tmp; $detKey = $k; break; }
            }
            $detKey = $k;
          }
        }
        echo '<tr><td class="px-2 py-1">' . $i . '</td><td class="px-2 py-1">' . $cid . '</td><td class="px-2 py-1">' . $cname . '</td><td class="px-2 py-1">' . htmlspecialchars($detKey, ENT_QUOTES, 'UTF-8') . '</td><td class="px-2 py-1">' . htmlspecialchars($rawShow, ENT_QUOTES, 'UTF-8') . '</td><td class="px-2 py-1">' . $pidShow . '</td></tr>';
      }
      echo '</tbody></table></div>';
      $keys = [];
      if (!empty($list)) { $keys = array_keys((array)$list[0]); }
      echo '<div class="mt-2">first row keys: <code>' . htmlspecialchars(implode(', ', $keys), ENT_QUOTES, 'UTF-8') . '</code></div>';
      echo '</details>';
      echo '</div>';
    }
    // ---- /DEBUG ----

    // GET 파라미터 유지(검색어/결제유형)
    $q   = isset($_GET['q'])   ? (string)$_GET['q']   : '';
    $pay = isset($_GET['pay']) ? (string)$_GET['pay'] : '';

    // 1차 후보: parent==0
    $lv1Options = $childrenOf[0] ?? [];

    // 선택값 복원: cat_lv3 > cat_lv2 > cat_lv1 > cat
    $g1 = (int)($_GET['cat_lv1'] ?? 0);
    $g2 = (int)($_GET['cat_lv2'] ?? 0);
    $g3 = (int)($_GET['cat_lv3'] ?? 0);
    $currentCat = (int)($_GET['cat'] ?? 0);

    $selectedLv1 = 0; $selectedLv2 = 0; $selectedLv3 = 0;

    if ($g3 > 0 && isset($rowsById[$g3])) {
      $selectedLv3 = $g3;
      $selectedLv2 = (int)($parentOf[$g3] ?? 0);
      $selectedLv1 = (int)($parentOf[$selectedLv2] ?? 0);
      $currentCat  = $g3;
    } elseif ($g2 > 0 && isset($rowsById[$g2])) {
      $selectedLv2 = $g2;
      $selectedLv1 = (int)($parentOf[$g2] ?? 0);
      $currentCat  = $g2;
    } elseif ($g1 > 0 && isset($rowsById[$g1])) {
      $selectedLv1 = $g1;
      $currentCat  = $g1;
    } elseif ($currentCat > 0 && isset($rowsById[$currentCat])) {
      // cat이 3/2/1차 어느 레벨이든 전체 체인 복원
      $chain = [];
      $cursor = $currentCat;
      $guard  = 0;
      while ($cursor > 0 && isset($rowsById[$cursor]) && $guard < 10) {
        $chain[] = $cursor;
        $cursor = (int)($parentOf[$cursor] ?? 0);
        $guard++;
      }
      $chain = array_reverse($chain);
      $selectedLv1 = $chain[0] ?? 0;
      $selectedLv2 = $chain[1] ?? 0;
      $selectedLv3 = $chain[2] ?? 0;
    }

    // 초기 렌더용 Lv2/Lv3
    $lv2Options = $selectedLv1 ? ($childrenOf[$selectedLv1] ?? []) : [];
    $lv3Options = $selectedLv2 ? ($childrenOf[$selectedLv2] ?? []) : [];
  ?>

  <form id="catForm" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] ?? '/index.php', ENT_QUOTES, 'UTF-8') ?>" class="max-w-4xl mx-auto px-4">
    <!-- 키워드 검색 -->
    <div class="mb-4">
      <label for="q" class="block text-sm font-medium text-gray-800 mb-2">
        <?= function_exists('__') ? __('home.search_label') : '검색' ?>
      </label>
      <div class="relative">
        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input
          type="text"
          name="q"
          id="q"
          value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="<?= function_exists('__') ? __('home.search_placeholder') : '원하는 기종을 검색해보세요 (예: 아이폰 14)' ?>"
          class="w-full h-11 pl-10 pr-3 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-4 focus:ring-primary/20 focus:border-primary text-[15px]"
        />
      </div>
    </div>
    <input type="hidden" name="pay" id="payHidden" value="<?= in_array($pay, ['normal','cod'], true) ? htmlspecialchars($pay, ENT_QUOTES, 'UTF-8') : '' ?>">
    <input type="hidden" name="cat" id="catHidden" value="<?= (int)$currentCat ?>">
    <input type="hidden" name="cat_level" id="catLevelHidden" value="0">
    <input type="hidden" name="cat_mode"  id="catModeHidden"  value="tree">

    <!-- 결제 방식 선택 (일반결제 / COD) -->
    <div class="mb-3">
      <label class="block text-sm font-medium text-gray-800 mb-2">결제 방식</label>
      <div class="flex items-center gap-6">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="payNormal" class="w-4 h-4" <?= ($pay === 'normal') ? 'checked' : '' ?>>
          <span>일반결제</span>
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="payCOD" class="w-4 h-4" <?= ($pay === 'cod') ? 'checked' : '' ?>>
          <span>COD</span>
        </label>
      
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <!-- Lv1 -->
      <div>
        <label class="block text-sm font-medium text-gray-800 mb-2">1차 카테고리</label>
        <select name="cat_lv1" id="catLv1" class="w-full h-11 rounded-xl border border-gray-200 bg-white px-3 focus:outline-none focus:ring-4 focus:ring-primary/20 focus:border-primary text-[15px]" onchange="window.CAT_onLv1Change && window.CAT_onLv1Change()">
          <option value="0">카테고리를 선택하세요</option>
          <?php foreach ($lv1Options as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= ($selectedLv1 === (int)$r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Lv2 -->
      <div id="wrapLv2" class="<?= $selectedLv1 ? '' : 'hidden' ?>">
        <label class="block text-sm font-medium text-gray-800 mb-2">2차 카테고리</label>
        <select name="cat_lv2" id="catLv2" class="w-full h-11 rounded-xl border border-gray-200 bg-white px-3 focus:outline-none focus:ring-4 focus:ring-primary/20 focus:border-primary text-[15px]" <?= $selectedLv1 ? '' : 'disabled' ?> onchange="window.CAT_onLv2Change && window.CAT_onLv2Change()">
          <?php if (!$selectedLv1): ?>
            <option value="0">1차 카테고리를 먼저 선택하세요</option>
          <?php else: ?>
            <option value="0">세부 카테고리를 선택하세요</option>
            <?php foreach ($lv2Options as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ($selectedLv2 === (int)$r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
      <!-- Lv3 -->
      <div id="wrapLv3" class="<?= $selectedLv2 ? '' : 'hidden' ?>">
        <label class="block text-sm font-medium text-gray-800 mb-2">3차 카테고리</label>
        <select name="cat_lv3" id="catLv3" class="w-full h-11 rounded-xl border border-gray-200 bg-white px-3 focus:outline-none focus:ring-4 focus:ring-primary/20 focus:border-primary text-[15px]" <?= $selectedLv2 ? '' : 'disabled' ?> onchange="window.CAT_syncHidden && window.CAT_syncHidden()">
          <?php if (!$selectedLv2): ?>
            <option value="0">2차 카테고리를 먼저 선택하세요</option>
          <?php else: ?>
            <option value="0">세부 카테고리를 선택하세요</option>
            <?php foreach ($lv3Options as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ($selectedLv3 === (int)$r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
    </div>

    <p class="mt-2 text-xs text-gray-500">
      상위 카테고리만 선택해도 하위 전체를 검색합니다. (예: 1차만 선택 시 1차의 모든 2·3차 포함)
    </p>
    <div class="mt-3 sticky bottom-0 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/60 border-t pt-3 flex justify-end">
      <button type="submit" class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-primary text-white font-semibold shadow-md hover:shadow-lg hover:bg-primary/90 active:scale-[0.99] transition">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <?= function_exists('__') ? __('home.search_button') : '검색' ?>
      </button>
    </div>
  </form>

  <script>
    (function(){
      // NOTE: 객체 강제 옵션 제거 (JSON_UNESCAPED_UNICODE만)
      const RAW_MAP = <?php echo json_encode($childrenOf, JSON_UNESCAPED_UNICODE); ?>;

      const lv1 = document.getElementById('catLv1');
      const lv2 = document.getElementById('catLv2');
      const lv3 = document.getElementById('catLv3');
      const wrapLv2 = document.getElementById('wrapLv2');
      const wrapLv3 = document.getElementById('wrapLv3');
      const hidden = document.getElementById('catHidden');
      const catLevelHidden = document.getElementById('catLevelHidden');
      const catModeHidden  = document.getElementById('catModeHidden'); // reserved (tree search)
      const form = document.getElementById('catForm');

      const payNormal = document.getElementById('payNormal');
      const payCOD    = document.getElementById('payCOD');
      const payHidden = document.getElementById('payHidden');

      // ---- 유틸: 어떤 형태든 안전하게 "배열"로 변환 ----
      function toArray(v){
        if (Array.isArray(v)) return v;
        if (v && typeof v === 'object') return Object.values(v);
        return [];
      }
      function esc(s){ return String(s).replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
      function list(pid){ return toArray(RAW_MAP[String(pid)]); }
      function fill(sel, items, placeholder){
        const arr = toArray(items);
        let html = `<option value="0">${esc(placeholder)}</option>`;
        for (const r of arr) html += `<option value="${r.id}">${esc(r.name)}</option>`;
        sel.innerHTML = html;
      }

      function syncHidden(){
        const v1 = parseInt(lv1?.value||'0',10)||0;
        const v2 = (!lv2?.disabled ? (parseInt(lv2.value||'0',10)||0) : 0);
        const v3 = (!lv3?.disabled ? (parseInt(lv3.value||'0',10)||0) : 0);
        if (v3 > 0) {
          hidden.value = v3;
          if (catLevelHidden) catLevelHidden.value = 3;
        } else if (v2 > 0) {
          hidden.value = v2;
          if (catLevelHidden) catLevelHidden.value = 2;
        } else if (v1 > 0) {
          hidden.value = v1;
          if (catLevelHidden) catLevelHidden.value = 1;
        } else {
          hidden.value = 0;
          if (catLevelHidden) catLevelHidden.value = 0;
        }
      }

      function syncPay(){
        if (!payHidden) return;
        const n = !!(payNormal && payNormal.checked);
        const c = !!(payCOD && payCOD.checked);
        // 단일 선택만 쿼리로 전달. 둘 다이거나 둘 다 아니면 공백(전체)
        payHidden.value = (n ^ c) ? (n ? 'normal' : 'cod') : '';
      }

      function onLv1Change(){
        const pid = parseInt(lv1.value||'0',10)||0;
        const children2 = list(pid);
        lv2.disabled = (pid===0 || children2.length===0);
        if (wrapLv2) wrapLv2.classList.toggle('hidden', lv2.disabled);
        fill(lv2, children2, '세부 카테고리를 선택하세요');

        // reset lv3
        lv3.disabled = true;
        fill(lv3, [], '2차 카테고리를 먼저 선택하세요');
        if (wrapLv3) wrapLv3.classList.add('hidden');

        // 2차가 1개뿐이면 자동 선택
        if (!lv2.disabled && children2.length === 1) {
          lv2.value = String(children2[0].id);
          onLv2Change();
        }
        syncHidden();
      }

      function onLv2Change(){
        const pid2 = parseInt(lv2.value||'0',10)||0;
        const children3 = list(pid2);
        lv3.disabled = (pid2===0 || children3.length===0);
        if (wrapLv3) wrapLv3.classList.toggle('hidden', lv3.disabled);
        fill(lv3, children3, '세부 카테고리를 선택하세요');

        // 3차가 1개뿐이면 자동 선택
        if (!lv3.disabled && children3.length === 1) {
          lv3.value = String(children3[0].id);
        }
        syncHidden();
      }

      // inline fallback에서 호출 가능하도록 노출
      window.CAT_onLv1Change = onLv1Change;
      window.CAT_onLv2Change = onLv2Change;
      window.CAT_syncHidden  = syncHidden;

      // 이벤트 바인딩
      lv1 && lv1.addEventListener('change', onLv1Change);
      lv2 && lv2.addEventListener('change', onLv2Change);
      lv3 && lv3.addEventListener('change', syncHidden);
      if (form) {
        form.addEventListener('submit', function(){
          syncHidden();
          syncPay();
        });
      }
      payNormal && payNormal.addEventListener('change', syncPay);
      payCOD    && payCOD.addEventListener('change', syncPay);

      if (wrapLv2) wrapLv2.classList.toggle('hidden', lv2.disabled || !lv1 || parseInt(lv1.value||'0',10)===0);
      if (wrapLv3) wrapLv3.classList.toggle('hidden', lv3.disabled || !lv2 || parseInt(lv2.value||'0',10)===0);

      // 초기 선택값이 있으면 체인 채우기
      if (lv1 && parseInt(lv1.value||'0',10) > 0) {
        onLv1Change();
        if (lv2 && parseInt(lv2.value||'0',10) > 0) onLv2Change();
      }
      syncPay();
    })();
  </script>
</section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const btn  = document.getElementById('toggleSearchBtn');
  const area = document.getElementById('searchArea');
  if (!btn || !area) return;

  // helpers
  function openPanel() {
    if (!area.classList.contains('hidden')) return;
    area.classList.remove('hidden');
    requestAnimationFrame(() => {
      area.style.maxHeight = '65vh';
    });
    // pressed visual
    btn.setAttribute('aria-pressed','true');
    btn.classList.add('ring-2','ring-primary/40');
  }
  function closePanel() {
    area.style.maxHeight = '0';
    setTimeout(() => area.classList.add('hidden'), 400);
    btn.removeAttribute('aria-pressed');
    btn.classList.remove('ring-2','ring-primary/40');
  }
  function togglePanel() {
    if (area.classList.contains('hidden')) openPanel();
    else closePanel();
  }

  // click toggle
  btn.addEventListener('click', togglePanel);

  // --- Auto open cases ---
  // 1) ?open_search=1  2) #search  3) has any search-related param (?q, ?cat, ?cat_lv1/2/3, ?pay)
  try {
    const qp = new URLSearchParams(location.search);
    const hasOpenFlag = qp.get('open_search') === '1';
    const hasHash     = (location.hash || '').toLowerCase().includes('search');
    const hasParams   = ['q','cat','cat_lv1','cat_lv2','cat_lv3','pay'].some(k => qp.has(k) && qp.get(k));
    if (hasOpenFlag || hasHash || hasParams) {
      openPanel();
      // Optionally scroll the anchor into view if hash absent
      if (!hasHash) {
        const anchor = document.getElementById('search');
        if (anchor) anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  } catch(e) {
    console.warn('search auto-open error', e);
  }
});
</script>