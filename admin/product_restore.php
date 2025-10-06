<?php
// /admin/product_restore.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';

require_role('admin');
if (function_exists('csrf_require')) csrf_require();

$pdo = db();
$productId = (int)($_POST['product_id'] ?? 0);
$back = $_POST['return'] ?? '/admin/index.php?tab=products&show=deleted';

if ($productId <= 0) {
  header('Location: ' . $back . '&err=bad_product_id');
  exit;
}

try {
  $st = $pdo->prepare("UPDATE products SET is_deleted=0, updated_at=NOW() WHERE id=:id LIMIT 1");
  $st->execute([':id' => $productId]);
  header('Location: ' . $back . '&msg=restored');
  exit;
} catch (Throwable $e) {
  $reason = urlencode($e->getMessage());
  header('Location: ' . $back . '&err=exception&reason=' . $reason);
  exit;
}