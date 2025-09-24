<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /admin/orders.php');
  exit;
}
$pdo = db();
$action = $_POST['action'] ?? '';
$order_id = (int)($_POST['order_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, status, product_id FROM orders WHERE id=:id LIMIT 1");
$stmt->execute([':id'=>$order_id]);
$o = $stmt->fetch();
if (!$o) {
  header('Location: /admin/orders.php');
  exit;
}

if ($action === 'confirm_payment' && $o['status']==='awaiting_payment') {
  $pdo->prepare("UPDATE orders SET status='payment_confirmed', updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':id'=>$order_id]);
  // 입금 확인 시 해당 상품을 구매 진행중으로 전환
  if (!empty($o['product_id'])) {
    $pdo->prepare("UPDATE products SET status='negotiating', updated_at=CURRENT_TIMESTAMP WHERE id=:pid")
        ->execute([':pid' => (int)$o['product_id']]);
  }
} elseif ($action === 'set_tracking' && in_array($o['status'], ['payment_confirmed','shipping'])) {
  $tracking = trim($_POST['tracking_number'] ?? '');
  $pdo->prepare("UPDATE orders SET status='shipping', tracking_number=:t, updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':id'=>$order_id, ':t'=>$tracking]);
} elseif ($action === 'mark_delivered' && $o['status']==='shipping') {
  $pdo->prepare("UPDATE orders SET status='delivered', updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':id'=>$order_id]);
  // 배송 완료 시 해당 상품을 판매 완료로 전환
  if (!empty($o['product_id'])) {
    $pdo->prepare("UPDATE products SET status='sold', updated_at=CURRENT_TIMESTAMP WHERE id=:pid")
        ->execute([':pid' => (int)$o['product_id']]);
  }
}
header('Location: /admin/orders.php');
exit;