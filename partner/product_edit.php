<?php
// /partner/product_edit.php
$pageTitle  = '상품 수정';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../i18n/bootstrap.php';

require_role('partner');

$pdo = db();
$uid = (int)($_SESSION['user']['id'] ?? 0);
$id  = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /partner/index.php?err=bad_id'); exit; }

// 상품 로드 + 소유권 확인
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=:id LIMIT 1");
$stmt->execute([':id'=>$id]);
$product = $stmt->fetch();
if (!$product) { header('Location: /partner/index.php?err=not_found'); exit; }
if ((int)$product['seller_id'] !== $uid) { header('Location: /partner/index.php?err=forbidden'); exit; }

// 카테고리 로드
$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../partials/header_partner.php';
?>
<section class="mb-6">
  <h1 class="text-2xl font-bold">상품 수정</h1>
  <p class="text-gray-600 mt-1">내가 등록한 상품 정보를 수정합니다.</p>
</section>

<form method="post" action="/partner/product_update.php" class="bg-white border rounded-xl shadow-sm p-5">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
  <?php
    $mode='partner';
    $categories=$cats;
    require __DIR__ . '/../partials/product_form_core.php';
  ?>
</form>

<?php include __DIR__ . '/../partials/footer.php'; ?>