<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/cart.php';

$product_id = (int)($_GET['product_id'] ?? 0);
if ($product_id <= 0) { header('Location: /'); exit; }

$pdo = db();
// 승인된 상품 + 판매중만 담기 허용
$st = $pdo->prepare("SELECT id, status, approval_status FROM products WHERE id=:id LIMIT 1");
$st->execute([':id'=>$product_id]);
$p = $st->fetch();

if (!$p || $p['approval_status'] !== 'approved' || $p['status'] !== 'on_sale') {
  header('Location: /?err='.urlencode('장바구니에 담을 수 없는 상품입니다.')); exit;
}

cart_add($product_id);
header('Location: /cart.php?msg='.urlencode('장바구니에 담았습니다.'));
exit;