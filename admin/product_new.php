<?php
$pageTitle = '관리자 - 상품 등록';
$activeMenu = 'product_new';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../partials/product_payment_methods_field.php'; // 추가

require_role('admin');

$pdo = db();
$cats = $pdo->query("SELECT id, name, parent_id FROM categories WHERE is_active=1 ORDER BY COALESCE(parent_id,0), sort_order, name")->fetchAll();
$partners = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('partner','admin') AND is_active=1 ORDER BY role DESC, name")->fetchAll();

// Build maps for 3-level select: parents (level-1) and byParent (id -> its direct children)
$parents = [];
$byParent = [];
foreach ($cats as $c) {
  $id  = (int)$c['id'];
  $pid = isset($c['parent_id']) ? (int)$c['parent_id'] : 0;
  if ($pid === 0) {
    $parents[] = ['id'=>$id, 'name'=>$c['name']];
  }
  if (!isset($byParent[$pid])) $byParent[$pid] = [];
  if ($pid !== 0) {
    if (!isset($byParent[$pid])) $byParent[$pid] = [];
  }
  if (!isset($byParent[$id])) $byParent[$id] = $byParent[$id] ?? [];
  if ($pid !== 0) {
    $byParent[$pid][] = ['id'=>$id, 'name'=>$c['name']];
  }
}

include __DIR__ . '/../partials/header_admin.php';
?>
<div class="max-w-3xl mx-auto bg-white border rounded-xl shadow-sm p-6">
  <h1 class="text-2xl font-bold">상품 등록 (관리자)</h1>
  <form class="mt-6 space-y-5" method="post" action="/admin/product_save.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">상품명</label>
        <input name="name" required class="mt-1 w-full border rounded px-3 py-2">
      </div>
    </div>

    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium">1차 카테고리</label>
        <select id="parent_category_id" class="mt-1 w-full border rounded px-3 py-2" required>
          <option value="">선택</option>
          <?php foreach ($parents as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium">2차 카테고리</label>
        <select id="child_category_id" class="mt-1 w-full border rounded px-3 py-2" disabled>
          <option value="">먼저 1차를 선택하세요</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium">3차 카테고리</label>
        <select id="grand_category_id" class="mt-1 w-full border rounded px-3 py-2" disabled>
          <option value="">먼저 2차를 선택하세요</option>
        </select>
      </div>
      <!-- 최종 제출용 hidden: 가장 깊게 선택된 id를 저장 -->
      <input type="hidden" name="category_id" id="final_category_id" value="">
    </div>

    <div>
      <label class="block text-sm font-medium">판매자(파트너/관리자)</label>
      <select name="seller_id" required class="mt-1 w-full border rounded px-3 py-2">
        <?php foreach ($partners as $pt): ?>
          <option value="<?= (int)$pt['id'] ?>"><?= htmlspecialchars($pt['name'].' ('.$pt['email'].')', ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium">가격(THB)</label>
        <input type="number" step="0.01" name="price" required class="mt-1 w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">출시년도</label>
        <input type="number" name="release_year" min="2000" max="<?= date('Y') ?>" class="mt-1 w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">상태</label>
        <select name="condition" class="mt-1 w-full border rounded px-3 py-2">
          <option value="new">새상품</option>
          <option value="unused_like_new">미사용급</option>
          <option value="excellent">A급</option>
          <option value="good">B급</option>
          <option value="fair">C급</option>
          <option value="poor">D급</option>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium">상세 설명</label>
      <textarea name="description" rows="4" class="mt-1 w-full border rounded px-3 py-2"></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium">메인 이미지 (최대 5장)</label>
      <div id="image-upload-area" class="grid grid-cols-2 gap-2">
        <?php for ($i = 0; $i < 5; $i++): ?>
          <div class="border p-2 rounded relative">
            <input type="file" name="images[]" accept="image/*" class="w-full mb-2">
            <label class="flex items-center space-x-1 text-sm">
              <input type="radio" name="is_primary" value="<?= $i ?>" <?= $i===0 ? 'checked' : '' ?>>
              <span>대표 이미지로 설정</span>
            </label>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium">상세 설명 이미지 (1장)</label>
      <input type="file" name="detail_image" accept="image/*" class="mt-1 w-full border rounded px-3 py-2">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">판매 상태</label>
        <select name="status" class="mt-1 w-full border rounded px-3 py-2">
          <option value="on_sale">판매중</option>
          <option value="negotiating">구매 진행중</option>
          <option value="sold">판매 완료</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium">승인 상태</label>
        <select name="approval_status" class="mt-1 w-full border rounded px-3 py-2">
          <option value="approved">승인</option>
          <option value="pending">대기중</option>
          <option value="rejected">거절</option>
        </select>
      </div>
    </div>

    <?php
      // 판매 방식(일반판매/COD) 선택 — 최소 1개 필수
      // admin 신규 등록 페이지에서는 $product 변수가 없으므로 null 전달
      render_payment_methods_field(null);
    ?>
    <div class="pt-4 flex gap-3">
      <button class="px-5 py-2.5 bg-primary text-white rounded-lg font-semibold">등록</button>
      <a href="/admin/orders.php" class="px-5 py-2.5 border rounded-lg">취소</a>
    </div>
    <script>
      (function(){
        // PHP에서 내려준: 부모id -> 직계 children 배열 (id, name)
        const allMap = <?= json_encode($byParent, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
        const $p = document.getElementById('parent_category_id');
        const $c = document.getElementById('child_category_id');
        const $g = document.getElementById('grand_category_id');
        const $final = document.getElementById('final_category_id');

        function setFinalCategory() {
          // 가장 깊은 선택값을 hidden에 저장
          if ($g.value) {
            $final.value = $g.value;
          } else if ($c.value) {
            $final.value = $c.value;
          } else if ($p.value) {
            $final.value = $p.value;
          } else {
            $final.value = '';
          }
        }

        function fillSelect($sel, list, placeholder) {
          $sel.innerHTML = '';
          if (!list || list.length === 0) {
            $sel.innerHTML = `<option value="">${placeholder}</option>`;
            $sel.disabled = true;
            return;
          }
          $sel.disabled = false;
          const opt0 = document.createElement('option');
          opt0.value = '';
          opt0.textContent = '선택';
          $sel.appendChild(opt0);
          list.forEach(it => {
            const o = document.createElement('option');
            o.value = it.id;
            o.textContent = it.name;
            $sel.appendChild(o);
          });
        }

        function onParentChange() {
          const pid = parseInt($p.value || '0', 10);
          const lv2 = allMap[pid] || [];
          fillSelect($c, lv2, '하위 없음');
          // 2차가 하나뿐이면 자동 선택
          if (lv2.length === 1) {
            $c.value = String(lv2[0].id);
          } else {
            $c.value = '';
          }
          onChildChange();
          setFinalCategory();
        }

        function onChildChange() {
          const cid = parseInt($c.value || '0', 10);
          const lv3 = allMap[cid] || [];
          fillSelect($g, lv3, '하위 없음');
          // 3차가 하나뿐이면 자동 선택
          if (lv3.length === 1) {
            $g.value = String(lv3[0].id);
          } else {
            $g.value = '';
          }
          setFinalCategory();
        }

        function onGrandChange() {
          setFinalCategory();
        }

        $p.addEventListener('change', onParentChange);
        $c.addEventListener('change', onChildChange);
        $g.addEventListener('change', onGrandChange);

        // 초기화(새로고침 대비)
        onParentChange();
      })();
    </script>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>