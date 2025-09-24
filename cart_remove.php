<?php
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/cart.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /cart.php'); exit;
}
$product_id = (int)($_POST['product_id'] ?? 0);
if ($product_id > 0) { cart_remove($product_id); }
header('Location: /cart.php?msg='.urlencode('상품을 장바구니에서 제거했습니다.'));
exit;