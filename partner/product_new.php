<?php
$pageTitle = '파트너 - 상품 등록';
$activeMenu = 'product_new';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../partials/product_payment_methods_field.php';
require_role('partner');

$pdo = db();
$cats = $pdo->query("SELECT id, name, parent_id FROM categories WHERE is_active=1 ORDER BY COALESCE(parent_id,0), sort_order, name")->fetchAll();
// Build maps for 3-level select: parents (level-1) and byParent (parent_id -> direct children)
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
    $byParent[$pid][] = ['id'=>$id, 'name'=>$c['name']];
  }
}

include __DIR__ . '/../partials/header_partner.php';
?>
<div class="max-w-3xl mx-auto bg-white border rounded-xl shadow-sm p-6">
  <h1 class="text-2xl font-bold"><?= __('partner_product_new.1') ?: '상품 등록 (파트너)' ?></h1>
  <p class="text-gray-600 mt-1"><?= __('partner_product_new.2') ?: '관리자 승인 후 노출됩니다.' ?></p>

  <form class="mt-6 space-y-5" method="post" action="/partner/product_save.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium"><?= __('partner_product_new.3') ?: '상품명' ?></label>
        <input name="name" required class="mt-1 w-full border rounded px-3 py-2">
      </div>
    </div>

    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium"><?= __('partner_product_new.4') ?: '1차 카테고리' ?></label>
        <select id="parent_category_id" class="mt-1 w-full border rounded px-3 py-2" required>
          <option value=""><?= __('partner_product_new.5') ?: '선택' ?></option>
          <?php foreach ($parents as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium"><?= __('partner_product_new.6') ?: '2차 카테고리' ?></label>
        <select id="child_category_id" class="mt-1 w-full border rounded px-3 py-2" disabled>
          <option value=""><?= __('partner_product_new.7') ?: '먼저 1차를 선택하세요' ?></option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium"><?= __('partner_product_new.8') ?: '3차 카테고리' ?></label>
        <select id="grand_category_id" class="mt-1 w-full border rounded px-3 py-2" disabled>
          <option value=""><?= __('partner_product_new.9') ?: '먼저 2차를 선택하세요' ?></option>
        </select>
      </div>
      <!-- 최종 제출용 hidden: 가장 깊게 선택된 id를 저장 -->
      <input type="hidden" name="category_id" id="final_category_id" value="">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium"><?= __('partner_product_new.10') ?: '가격' ?>(THB)</label>
        <input type="number" step="0.01" name="price" required class="mt-1 w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium"><?= __('partner_product_new.11') ?: '출시년도' ?></label>
        <input type="number" name="release_year" min="2000" max="<?= date('Y') ?>" class="mt-1 w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium"><?= __('partner_product_new.12') ?: '상태' ?></label>
        <select name="condition" class="mt-1 w-full border rounded px-3 py-2">
          <option value="unused_like_new"><?= __('partner_product_new.13') ?: '미사용급' ?></option>
          <option value="excellent">A</option>
          <option value="good">B</option>
          <option value="fair">C</option>
          <option value="poor">D</option>
        </select>
      </div>
    </div>
    <div class="space-y-4">
      <?php
        // 결제 방식 체크박스 (단일 폼 안에서 함께 전송)
        render_payment_methods_field($product ?? null);
      ?>
    </div>
    
    <div>
      <label class="block text-sm font-medium"><?= __('partner_product_new.14') ?: '상세 설명' ?></label>
      <textarea name="description" rows="4" class="mt-1 w-full border rounded px-3 py-2"></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium"><?= __('partner_product_new.15') ?: '메인 이미지 (최대 5장)' ?></label>
      <div id="image-upload-area" class="grid grid-cols-2 gap-2">
        <?php for ($i = 0; $i < 5; $i++): ?>
          <div class="border p-2 rounded relative">
            <input type="file" name="images[]" accept="image/*" class="w-full mb-2">
            <label class="flex items-center space-x-1 text-sm">
              <input type="radio" name="is_primary" value="<?= $i ?>" <?= $i===0 ? 'checked' : '' ?>>
              <span><?= __('partner_product_new.16') ?: '대표 이미지로 설정' ?></span>
            </label>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium"><?= __('partner_product_new.17') ?: '상세 설명 이미지 (1장)' ?></label>
      <input type="file" name="detail_image" accept="image/*" class="mt-1 w-full border rounded px-3 py-2">
    </div>

    
    <div class="pt-4 flex gap-3">
      <button type="submit" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold"><?= __('partner_product_new.18') ?: '등록 요청' ?></button>
      <a href="/partner/index.php" class="px-5 py-2.5 border rounded-lg"><?= __('partner_product_new.19') ?: '취소' ?></a>
    </div>
  <script>
  (function(){
    const allMap = <?= json_encode($byParent, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const $p = document.getElementById('parent_category_id');
    const $c = document.getElementById('child_category_id');
    const $g = document.getElementById('grand_category_id');
    const $final = document.getElementById('final_category_id');

    function setFinalCategory() {
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

    // 초기화
    onParentChange();
  })();
  </script>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>