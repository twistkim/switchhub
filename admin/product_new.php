<?php
$pageTitle = '관리자 - 상품 등록';
$activeMenu = 'product_new';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

$pdo = db();
$cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$partners = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('partner','admin') AND is_active=1 ORDER BY role DESC, name")->fetchAll();

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
      <div>
        <label class="block text-sm font-medium">카테고리</label>
        <select name="category_id" required class="mt-1 w-full border rounded px-3 py-2">
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
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
      <input type="file" name="main_images[]" accept="image/*" multiple class="mt-1 w-full border rounded px-3 py-2">
      <p class="text-xs text-gray-500 mt-1">* 첫 번째 이미지를 대표로 사용합니다.</p>
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

    <div class="pt-4 flex gap-3">
      <button class="px-5 py-2.5 bg-primary text-white rounded-lg font-semibold">등록</button>
      <a href="/admin/orders.php" class="px-5 py-2.5 border rounded-lg">취소</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>