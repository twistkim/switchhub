<?php
$pageTitle = '관리자 - 상품 승인 대기';
$activeMenu = 'products_pending';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

$pdo = db();

$rows = $pdo->query("
  SELECT p.id, p.name, p.price, p.status, p.approval_status, p.created_at,
         u.name AS seller_name, u.email AS seller_email,
         (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS primary_image_url
  FROM products p
  JOIN users u ON u.id = p.seller_id
  WHERE p.approval_status = 'pending' AND p.is_deleted = 0
  ORDER BY p.created_at DESC
  LIMIT 200
")->fetchAll();

include __DIR__ . '/../partials/header_admin.php';
?>
<section class="mb-6">
  <h1 class="text-2xl font-bold">승인 대기 상품</h1>
  <p class="text-gray-600 mt-1">파트너가 등록한 상품을 승인/거절 처리합니다.</p>
</section>

<?php if (empty($rows)): ?>
  <div class="bg-white border rounded-xl shadow-sm p-8 text-center text-gray-500">승인 대기 상품이 없습니다.</div>
<?php else: ?>
  <div class="space-y-4">
    <?php foreach ($rows as $r): ?>
      <div class="bg-white border rounded-xl shadow-sm p-4">
        <div class="flex gap-4">
          <div class="w-24 h-24 rounded-lg overflow-hidden border bg-gray-50">
            <img src="<?= htmlspecialchars($r['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image', ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover">
          </div>
          <div class="flex-1">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <a href="/product.php?id=<?= (int)$r['id'] ?>" class="text-lg font-semibold hover:underline"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></a>
              <div class="text-xl font-bold text-primary"><?= number_format((float)$r['price']) ?> THB</div>
            </div>
            <div class="mt-1 text-sm text-gray-600">
              판매자: <?= htmlspecialchars($r['seller_name'].' ('.$r['seller_email'].')', ENT_QUOTES, 'UTF-8') ?> · 등록일: <?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
              <form method="post" action="/admin/product_action.php">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="product_id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="px-3 py-1.5 rounded bg-green-600 text-white text-sm">승인</button>
              </form>
              <form method="post" action="/admin/product_action.php" onsubmit="return confirm('거절 처리하시겠습니까?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="product_id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="px-3 py-1.5 rounded bg-red-600 text-white text-sm">거절</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>