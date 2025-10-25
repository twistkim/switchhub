<?php
// /admin/product_edit.php — 간단/일관 폼 (product_new.php 스타일)
// 관리자만 접근
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_role('admin');
$DEBUG = isset($_GET['debug']);

// ---- CSRF 토큰 생성 및 세션 동기화 ----
// 항상 현재 세션의 CSRF 토큰을 한 번만 생성/할당하고, 두 세션 키 모두에 저장합니다.
// csrf_token()은 이미 세션에 있으면 재사용, 없으면 생성합니다.
// 이 값이 폼 hidden input에 그대로 들어갑니다.
$_SESSION['csrf'] = $_SESSION['csrf_token'] = csrf_token();
$formToken = $_SESSION['csrf'];

 
// 파라미터
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo "Invalid product id.";
  exit;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 디버그 헬퍼 (?debug=1 붙이면 DB 에러 메시지 표시)
$DEBUG = isset($_GET['debug']);
if ($DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
  set_error_handler(function($severity, $message, $file, $line) {
    // Convert warnings/notices to exceptions for easier debugging
    throw new ErrorException($message, 0, $severity, $file, $line);
  });
}

try {
  // 제품 로드
  $stmt = $pdo->prepare("SELECT * FROM products WHERE id=:id LIMIT 1");
  $stmt->execute([':id' => $id]);
  $product = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$product) {
    http_response_code(404);
    echo "Product not found.";
    exit;
  }

  // 카테고리 로드 (is_deleted 컬럼이 없는 설치도 대비)
  $cats = [];
  try {
    $q = $pdo->query("SELECT id, name FROM categories WHERE COALESCE(is_deleted,0)=0 ORDER BY name ASC, id ASC");
    $cats = $q->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $inner) {
    // 42S22: Unknown column 'is_deleted'
    if ($DEBUG) {
      // 화면 디버그에 힌트 표시
      echo "<!-- categories fallback due to: " . htmlspecialchars($inner->getMessage(), ENT_QUOTES, 'UTF-8') . " -->\n";
    }
    $q2 = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC, id ASC");
    $cats = $q2->fetchAll(PDO::FETCH_ASSOC);
  }

  // --- 3단계 카테고리 셋업 (id, name, parent_id) ---
  // $catsAll: 모든 카테고리 (id, name, parent_id)
  $catsAll = [];
  try {
    $qAll = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC, id ASC");
    $catsAll = $qAll->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $eAll) {
    // parent_id 없는 설치 대비: 모든 것을 1차로 취급
    $catsAll = array_map(function($row){ return ['id'=>$row['id'],'name'=>$row['name'],'parent_id'=>null]; }, $cats);
  }

  // parent map
  $catByParent = [];
  $parentOf = [];
  foreach ($catsAll as $row) {
    $pid = $row['parent_id'] === null ? 0 : (int)$row['parent_id'];
    $row['parent_id'] = $pid;
    $catByParent[$pid][] = $row; // children list
    $parentOf[(int)$row['id']] = $pid;
  }

  // 선택된 카테고리의 조상 경로 계산 (lv1, lv2, lv3)
  $selId = (int)($product['category_id'] ?? 0);
  $selPath = [];
  if ($selId > 0) {
    $cur = $selId;
    $guard = 0;
    while ($cur && $guard < 10) {
      $selPath[] = $cur;
      $cur = $parentOf[$cur] ?? 0;
      $guard++;
    }
    $selPath = array_reverse($selPath); // root -> leaf
  }
  $selLv1 = 0; $selLv2 = 0; $selLv3 = 0;
  if (!empty($selPath)) {
    $selLv1 = (int)$selPath[0];
    if (isset($selPath[1])) $selLv2 = (int)$selPath[1];
    if (isset($selPath[2])) $selLv3 = (int)$selPath[2];
  }

  // JSON으로 JS에 전달
  $catsJson = json_encode($catsAll, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if ($DEBUG) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "DB ERROR: ".$e->getMessage()."\n";
    echo $e->getTraceAsString();
    exit;
  }
  http_response_code(500);
  echo "Internal Server Error";
  exit;
}

// 기존 썸네일 로드 (is_main 컬럼/테이블 유무에 따라 유연 처리)
$productImages = [];
try {
  $imgStmt = $pdo->prepare("SELECT id, image_url, COALESCE(is_primary, is_main, 0) AS is_main FROM product_images WHERE product_id = :pid ORDER BY is_main DESC, id ASC");
  $imgStmt->execute([':pid' => $product['id']]);
  $productImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e1) {
  // 42S22: Unknown column 'is_main' or even unknown table
  if ($DEBUG) {
    echo "<!-- product_images primary query failed: " . htmlspecialchars($e1->getMessage(), ENT_QUOTES, 'UTF-8') . " -->\n";
  }
  try {
    // 재시도: is_main 없이
    $imgStmt2 = $pdo->prepare("SELECT id, image_url FROM product_images WHERE product_id = :pid ORDER BY id ASC");
    $imgStmt2->execute([':pid' => $product['id']]);
    $tmp = $imgStmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tmp as $row) {
      $row['is_main'] = 0;
      $productImages[] = $row;
    }
  } catch (Throwable $e2) {
    // product_images 테이블 자체가 없는 경우 등
    if ($DEBUG) {
      echo "<!-- product_images fallback failed: " . htmlspecialchars($e2->getMessage(), ENT_QUOTES, 'UTF-8') . " -->\n";
    }
    $productImages = [];
  }
}

// 셀렉트 옵션들 (new와 동일하게 맞춤)
$conditions = [
  'new'      => '새상품',
  'like_new' => '미사용급',
  'good'     => '양호',
  'fair'     => '보통',
  'poor'     => '하',
];

$statuses = [
  'on_sale'         => '판매중',
  'pending_payment' => '구매 진행중',
  'sold'            => '판매 완료',
];

$approvals = [
  'pending'  => '승인대기',
  'approved' => '승인',
  'rejected' => '거절',
];

// 공용 헤더
$APP_LANG = $_SESSION['APP_LANG'] ?? 'ko';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">상품 수정</h1>
    <a href="/admin/index.php?tab=products" class="px-3 py-2 rounded border bg-white hover:bg-gray-50">← 목록으로</a>
  </div>

  <?php if (!empty($_GET['err'])): ?>
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 border">오류: <?= htmlspecialchars((string)$_GET['err'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['msg'])): ?>
    <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 border">알림: <?= htmlspecialchars((string)$_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <!-- CSRF 토큰은 세션에서 한 번 생성되어 아래 hidden input에 할당됩니다. -->
  <?php /* $formToken은 위에서 세션에 저장된 현재 CSRF 토큰입니다. 이 값이 폼에서 전송됩니다. */ ?>
  <form method="post" action="/admin/product_update.php" enctype="multipart/form-data" class="bg-white border rounded-xl shadow-sm p-5 space-y-6">
    <!-- CSRF 보호용 hidden 필드: 서버에서 생성되어 세션에 저장된 토큰을 그대로 전송합니다. -->
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
    <!-- ↑ 위 값은 /auth/csrf.php의 csrf_token()에서 생성되어 세션에 저장된 값을 사용합니다. -->

    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

    <!-- 기본 정보 -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="block text-sm font-medium">상품명</label>
        <input type="text" name="name" required
               value="<?= htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               class="mt-1 w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium">가격(THB)</label>
        <input type="number" name="price" required min="0" step="1"
               value="<?= (int)($product['price'] ?? 0) ?>"
               class="mt-1 w-full border rounded px-3 py-2">
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-medium">상세 설명</label>
        <textarea name="description" rows="4" class="mt-1 w-full border rounded px-3 py-2"
        ><?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium">상품 상태</label>
        <select name="condition" class="mt-1 w-full border rounded px-3 py-2">
          <?php $curCond = (string)($product['condition'] ?? ''); ?>
          <?php foreach ($conditions as $k=>$label): ?>
            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $curCond===$k?'selected':'' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium">출시년도(선택)</label>
        <input type="number" name="release_year" min="2000" max="2100"
               value="<?= htmlspecialchars((string)($product['release_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               class="mt-1 w-full border rounded px-3 py-2">
      </div>

      <?php
      // --- 3단계 카테고리 셋업 (id, name, parent_id) ---
      // $catsAll: 모든 카테고리 (id, name, parent_id)
      // 이미 위에서 준비됨
      ?>

      <div class="md:col-span-2">
        <label class="block text-sm font-medium">카테고리</label>
        <div class="mt-1 grid grid-cols-1 md:grid-cols-3 gap-3">
          <!-- 1차 -->
          <select id="cat_lv1" class="w-full border rounded px-3 py-2"></select>
          <!-- 2차 -->
          <select id="cat_lv2" class="w-full border rounded px-3 py-2" disabled></select>
          <!-- 3차 -->
          <select id="cat_lv3" class="w-full border rounded px-3 py-2" disabled></select>
        </div>
        <!-- 실제 전송되는 최종 카테고리 id (가장 깊이 선택값) -->
        <input type="hidden" name="category_id" id="category_id_final" value="<?= (int)$product['category_id'] ?>">
        <p class="mt-1 text-xs text-gray-500">1차→2차→3차 순으로 선택하세요. 2/3차가 없으면 1차 또는 2차까지 선택해도 됩니다.</p>
      </div>

      <script>
        (function(){
          const cats = <?= $catsJson ?: '[]' ?>;
          // normalize
          cats.forEach(c => { c.parent_id = (c.parent_id===null ? 0 : parseInt(c.parent_id,10)||0); c.id = parseInt(c.id,10)||0; });

          const byParent = {};
          cats.forEach(c => { (byParent[c.parent_id] ||= []).push(c); });
          Object.values(byParent).forEach(list => list.sort((a,b)=> (a.name||'').localeCompare(b.name||'')));

          const selLv1 = <?= (int)$selLv1 ?>;
          const selLv2 = <?= (int)$selLv2 ?>;
          const selLv3 = <?= (int)$selLv3 ?>;
          const selLeaf = <?= (int)$selId ?>;

          const $lv1 = document.getElementById('cat_lv1');
          const $lv2 = document.getElementById('cat_lv2');
          const $lv3 = document.getElementById('cat_lv3');
          const $final = document.getElementById('category_id_final');

          function fill($sel, items, placeholder){
            $sel.innerHTML = '';
            const opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = placeholder;
            $sel.appendChild(opt0);
            (items||[]).forEach(it => {
              const o = document.createElement('option');
              o.value = it.id;
              o.textContent = it.name;
              $sel.appendChild(o);
            });
            $sel.disabled = !items || items.length===0;
          }

          function childrenOf(pid){ return byParent[pid||0] || []; }

          function updateFinal(){
            const v3 = parseInt($lv3.value||'0',10);
            const v2 = parseInt($lv2.value||'0',10);
            const v1 = parseInt($lv1.value||'0',10);
            const leaf = v3 || v2 || v1 || 0;
            $final.value = String(leaf);
          }

          $lv1.addEventListener('change', () => {
            const v1 = parseInt($lv1.value||'0',10);
            fill($lv2, childrenOf(v1), '2차 카테고리');
            $lv2.value = '';
            fill($lv3, [], '3차 카테고리');
            updateFinal();
          });
          $lv2.addEventListener('change', () => {
            const v2 = parseInt($lv2.value||'0',10);
            fill($lv3, childrenOf(v2), '3차 카테고리');
            $lv3.value = '';
            updateFinal();
          });
          $lv3.addEventListener('change', updateFinal);

          // 초기 채우기 (1차 목록)
          fill($lv1, childrenOf(0), '1차 카테고리');

          // 초기 선택 복원
          if (selLv1) { $lv1.value = String(selLv1); fill($lv2, childrenOf(selLv1), '2차 카테고리'); }
          if (selLv2) { $lv2.value = String(selLv2); fill($lv3, childrenOf(selLv2), '3차 카테고리'); }
          if (selLv3) { $lv3.value = String(selLv3); }
          // 최종 카테고리
          updateFinal();
        })();
      </script>

      <div>
        <label class="block text-sm font-medium">판매 상태</label>
        <select name="status" class="mt-1 w-full border rounded px-3 py-2">
          <?php $curSt = (string)($product['status'] ?? 'on_sale'); ?>
          <?php foreach ($statuses as $k=>$label): ?>
            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $curSt===$k?'selected':'' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium">승인 상태</label>
        <select name="approval_status" class="mt-1 w-full border rounded px-3 py-2">
          <?php $curAp = (string)($product['approval_status'] ?? 'pending'); ?>
          <?php foreach ($approvals as $k=>$label): ?>
            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= $curAp===$k?'selected':'' ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-medium">결제 방식</label>
        <div class="mt-2 flex items-center gap-6">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="payment_normal" value="1" <?= ((int)($product['payment_normal'] ?? 0)===1)?'checked':'' ?>>
            <span>일반결제(QR)</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="payment_cod" value="1" <?= ((int)($product['payment_cod'] ?? 0)===1)?'checked':'' ?>>
            <span>현장결제(COD)</span>
          </label>
        </div>
      </div>
    </section>

    <hr class="my-6">

    <!-- 이미지 (대표 지정 가능) -->
    <section class="space-y-4">
      <h2 class="text-xl font-semibold">이미지</h2>
      <p class="text-sm text-gray-600">
        아래에서 <strong>대표 썸네일</strong>을 선택하거나, 새로 업로드하여 교체할 수 있습니다.
      </p>

      <?php
        // Determine current main image; if none, fallback to first image id
        $currentMainId = 0;
        if (!empty($productImages)) {
          foreach ($productImages as $pi) {
            if ((int)($pi['is_main'] ?? 0) === 1) { $currentMainId = (int)$pi['id']; break; }
          }
          if ($currentMainId === 0) {
            $currentMainId = (int)$productImages[0]['id'];
          }
        }
      ?>
      <!-- 기존 썸네일 목록 + 대표 선택 -->
      <div>
        <div class="text-sm font-medium mb-2">현재 썸네일</div>

        <?php if (!empty($productImages)): ?>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <?php
              $selectedSet = false;
              foreach ($productImages as $index => $pi):
                $pid = (int)$pi['id'];
                $isMain = (int)($pi['is_main'] ?? 0) === 1;
                // If no main flagged, select first one
                $checked = $isMain || (!$selectedSet && $currentMainId === $pid);
                if ($checked) { $selectedSet = true; }
            ?>
              <label class="block border rounded-lg overflow-hidden hover:shadow transition cursor-pointer">
                <img src="<?= htmlspecialchars($pi['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="thumb" class="w-full h-32 object-cover">
                <div class="p-2 flex items-center justify-between text-sm">
                  <span class="text-gray-700">대표로 선택</span>
                  <input type="radio" name="main_existing_id" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?>>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-sm text-gray-500">등록된 썸네일이 없습니다.</div>
        <?php endif; ?>

        <!-- 현재 대표 id(없으면 첫 이미지 id)를 서버로 전달 -->
        <input type="hidden" name="current_main_existing_id" value="<?= (int)$currentMainId ?>">
      </div>

      <hr class="my-4">

      <!-- 새 대표 썸네일 업로드 (선택) -->
      <div>
        <label class="block text-sm font-medium">새 대표 썸네일 업로드 (1장, 선택)</label>
        <input type="file" name="main_image" accept="image/*" class="mt-1 block w-full border rounded p-2">
        <p class="mt-1 text-xs text-gray-500">여기서 업로드하면 저장 시 <strong>대표 썸네일</strong>로 설정됩니다. (기존 대표 선택을 덮어씁니다)</p>
      </div>

      <!-- 추가 썸네일 업로드 (여러 장) -->
      <div>
        <label class="block text-sm font-medium">추가 썸네일 업로드 (여러 장)</label>
        <input type="file" name="sub_images[]" accept="image/*" multiple class="mt-1 block w-full border rounded p-2">
        <p class="mt-1 text-xs text-gray-500">여러 장 업로드 시 기존 썸네일 뒤에 이어 붙습니다.</p>
      </div>

      <hr class="my-4">

      <!-- 상세 이미지 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <div class="text-sm font-medium mb-1">현재 상세 이미지</div>
          <?php if (!empty($product['detail_image_url'])): ?>
            <img src="<?= htmlspecialchars($product['detail_image_url'], ENT_QUOTES, 'UTF-8') ?>" class="w-full max-w-md rounded border" alt="detail">
          <?php else: ?>
            <div class="text-sm text-gray-500">등록된 상세 이미지 없음</div>
          <?php endif; ?>
        </div>
        <div>
          <label class="block text-sm font-medium">새 상세 이미지 업로드 (선택)</label>
          <input type="file" name="detail_image" accept="image/*" class="mt-1 block w-full border rounded p-2">
        </div>
      </div>
    </section>

    <div class="pt-4 flex flex-col md:flex-row md:items-center md:justify-end gap-3">
      <p class="text-xs text-gray-500 md:mr-auto">
        * 새 대표 썸네일을 업로드하면 기존 대표 선택을 덮어씁니다.
      </p>
      <a href="/admin/index.php?tab=products" class="px-4 py-2 rounded border bg-white hover:bg-gray-50">취소</a>
      <button type="submit" class="px-4 py-2 rounded bg-primary text-white hover:bg-primary-700">저장</button>
    </div>
    <script>
      (function(){
        const form = document.currentScript.closest('form');
        const normal = form.querySelector('input[name="payment_normal"]');
        const cod = form.querySelector('input[name="payment_cod"]');
        form.addEventListener('submit', function(e){
          if ((normal && normal.checked) || (cod && cod.checked)) return true;
          alert('결제 방식은 최소 1개 이상 선택하세요. (일반결제 또는 COD)');
          e.preventDefault();
          return false;
        });
      })();
    </script>
  </form>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>