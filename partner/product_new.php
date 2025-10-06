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
// Build parent/child lists for two-level select
$parents = [];
$children = [];
foreach ($cats as $c) {
  if (empty($c['parent_id'])) {
    $parents[] = ['id'=>(int)$c['id'], 'name'=>$c['name']];
  } else {
    $pid = (int)$c['parent_id'];
    if (!isset($children[$pid])) $children[$pid] = [];
    $children[$pid][] = ['id'=>(int)$c['id'], 'name'=>$c['name']];
  }
}

include __DIR__ . '/../partials/header_partner.php';
?>
<div class="max-w-3xl mx-auto bg-white border rounded-xl shadow-sm p-6">
  <h1 class="text-2xl font-bold">상품 등록 (파트너)</h1>
  <p class="text-gray-600 mt-1">관리자 승인 후 노출됩니다.</p>

  <form class="mt-6 space-y-5" method="post" action="/partner/product_save.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">상품명</label>
        <input name="name" required class="mt-1 w-full border rounded px-3 py-2">
      </div>
    </div>

    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
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
        <select name="category_id" id="child_category_id" class="mt-1 w-full border rounded px-3 py-2" required disabled>
          <option value="">먼저 1차를 선택하세요</option>
        </select>
      </div>
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
          <option value="unused_like_new">미사용급</option>
          <option value="excellent">A급</option>
          <option value="good">B급</option>
          <option value="fair">C급</option>
          <option value="poor">D급</option>
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
      <label class="block text-sm font-medium">상세 설명</label>
      <textarea name="description" rows="4" class="mt-1 w-full border rounded px-3 py-2"></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium">메인 이미지 (최대 5장)</label>
      <input type="file" name="main_images[]" accept="image/*" multiple required class="mt-1 w-full border rounded px-3 py-2">
    </div>

    <div>
      <label class="block text-sm font-medium">상세 설명 이미지 (1장)</label>
      <input type="file" name="detail_image" accept="image/*" class="mt-1 w-full border rounded px-3 py-2">
    </div>

    
    <div class="pt-4 flex gap-3">
      <button type="submit" class="px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold">등록 요청</button>
      <a href="/partner/index.php" class="px-5 py-2.5 border rounded-lg">취소</a>
    </div>
  <script>
  (function(){
    const childrenMap = <?= json_encode($children, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const $parent = document.getElementById('parent_category_id');
    const $child  = document.getElementById('child_category_id');
    function fillChildren(pid){
      const list = childrenMap[pid] || [];
      $child.innerHTML = '';
      if (!pid || list.length === 0) {
        $child.innerHTML = '<option value="">하위 없음</option>';
        $child.disabled = true;
        $child.required = false;
        return;
      }
      $child.disabled = false;
      $child.required = true;
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = '선택';
      $child.appendChild(opt0);
      list.forEach(function(it){
        const o = document.createElement('option');
        o.value = it.id;
        o.textContent = it.name;
        $child.appendChild(o);
      });
    }
    if ($parent) {
      $parent.addEventListener('change', function(){ fillChildren(this.value); });
      fillChildren($parent.value);
    }
  })();
  </script>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>