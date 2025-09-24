<?php
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/cart.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /cart.php'); exit;
}
cart_clear();
header('Location: /cart.php?msg='.urlencode('장바구니를 비웠습니다.'));
exit;