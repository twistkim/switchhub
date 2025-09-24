<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /admin/products_pending.php'); exit;
}

$pdo = db();
$pid = (int)($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';

$st = $pdo->prepare("SELECT id, approval_status FROM products WHERE id=:id LIMIT 1");
$st->execute([':id'=>$pid]);
$p = $st->fetch();
if (!$p) { header('Location: /admin/products_pending.php'); exit; }

if ($action === 'approve') {
  $pdo->prepare("UPDATE products SET approval_status='approved', updated_at=NOW() WHERE id=:id")->execute([':id'=>$pid]);
} elseif ($action === 'reject') {
  $pdo->prepare("UPDATE products SET approval_status='rejected', updated_at=NOW() WHERE id=:id")->execute([':id'=>$pid]);
}
header('Location: /admin/products_pending.php');
exit;