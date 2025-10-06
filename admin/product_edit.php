<?php
// /admin/product_edit.php
$pageTitle  = '관리자 - 상품 수정';
$activeMenu = 'products';

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../i18n/bootstrap.php';

require_role('admin');

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /admin/index.php?tab=products&err=bad_id'); exit; }

$stmt = $pdo->prepare("SELECT * FROM products WHERE id=:id LIMIT 1");
$stmt->execute([':id'=>$id]);
$product = $stmt->fetch();
if (!$product) { header('Location: /admin/index.php?tab=products&err=not_found'); exit; }

$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../partials/header_admin.php'; // 기존 관리자 헤더 사용
?>
<section class="mb-6">
  <h1 class="text-2xl font-bold">상품 수정</h1>
  <p class="text-gray-600 mt-1">모든 필드를 수정할 수 있습니다.</p>
</section>

<form method="post" action="/admin/product_update.php" class="bg-white border rounded-xl shadow-sm p-5">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
  <?php
    $mode='admin';
    $categories=$cats;
    require __DIR__ . '/../partials/product_form_core.php';
  ?>
</form>

<?php include __DIR__ . '/../partials/footer.php'; ?>