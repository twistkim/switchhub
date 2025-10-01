<?php
// /reviews/save.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../lib/reviews.php';

require_login();

$pdo = db();
$me  = $_SESSION['user'];
$userId = (int)$me['id'];

$append = function(string $url, array $params){
  if ($url === '' || stripos($url, 'http') === 0) $url = '/index.php';
  $q = array_filter($params, function($v){ return $v !== null && $v !== ''; });
  $qs = http_build_query($q);
  return $url . (strpos($url,'?')!==false ? '&' : '?') . $qs;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  $back = $_POST['return'] ?? '/index.php';
  header('Location: ' . $append($back, ['err'=>'bad_request']));
  exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$orderId   = (int)($_POST['order_id'] ?? 0);
$rating    = (int)($_POST['rating'] ?? 0);
$body      = trim((string)($_POST['body'] ?? ''));
$back      = (string)($_POST['return'] ?? '/product.php?id='.$productId);

try {
  if ($productId <= 0) {
    header('Location: ' . $append($back, ['err'=>'invalid_product'])); exit;
  }

  // 권한: 배송완료 주문 보유자만
  $can = reviews_can_review($pdo, $userId, $productId);
  if (!$can['allowed']) {
    header('Location: ' . $append($back, ['err'=>'not_allowed'])); exit;
  }
  // form에서 준 order_id가 있으면 우선, 없으면 can의 order 사용
  if ($orderId <= 0) $orderId = (int)$can['order_id'];

  reviews_save($pdo, $userId, $productId, $orderId, $rating, $body);

  header('Location: ' . $append($back, ['msg'=>'review_saved','#'=>'reviews']));
  exit;

} catch (Throwable $e) {
  error_log('[reviews/save] '.$e->getMessage());
  header('Location: ' . $append($back, ['err'=>'exception']));
  exit;
}