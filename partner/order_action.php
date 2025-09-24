<?php
// /partner/order_action.php — 파트너 배송 처리 전용 (POST)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('partner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

// CSRF 검증(프로젝트 내 함수명 호환 처리)
$csrfToken = $_POST['csrf'] ?? null;
$validCsrf = false;
if (function_exists('csrf_verify')) {
  $validCsrf = csrf_verify($csrfToken);
} elseif (function_exists('csrf_check')) {
  $validCsrf = csrf_check($csrfToken ?? '');
}
if (!$validCsrf) {
  http_response_code(400);
  exit('Invalid CSRF token');
}

$me      = $_SESSION['user'];
$pdo     = db();
$orderId = (int)($_POST['order_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$return  = (isset($_POST['return']) && is_string($_POST['return']) && $_POST['return'] !== '') ? $_POST['return'] : '/partner/index.php';

// 안전한 리다이렉트 URL 조립 헬퍼
$append = function(string $url, string $q) {
  if (strpos($url, '?') !== false) return $url . '&' . $q;
  return $url . '?' . $q;
};

// 오너십 체크
$st = $pdo->prepare("SELECT o.id, o.status, o.product_id FROM orders o JOIN products p ON p.id=o.product_id WHERE o.id=:oid AND p.seller_id=:sid LIMIT 1");
$st->execute([':oid'=>$orderId, ':sid'=>(int)$me['id']]);
$o = $st->fetch();
if (!$o) { header('Location: ' . $append($return, 'err=not_owner')); exit; }

try {
  if ($action === 'set_tracking') {
    if ($o['status'] !== 'payment_confirmed' && $o['status'] !== 'shipping') {
      header('Location: ' . $append($return, 'err=invalid_state')); exit;
    }
    $tracking = trim((string)($_POST['tracking_number'] ?? ''));
    if ($tracking === '') { header('Location: ' . $append($return, 'err=empty_tracking')); exit; }

    $pdo->prepare("UPDATE orders SET tracking_number=:tn, status='shipping', updated_at=CURRENT_TIMESTAMP WHERE id=:id")
        ->execute([':tn'=>$tracking, ':id'=>$orderId]);

    header('Location: ' . $append($return, 'msg=tracking_saved')); exit;
  }
  elseif ($action === 'mark_delivered') {
    if ($o['status'] !== 'shipping') { header('Location: ' . $append($return, 'err=invalid_state')); exit; }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE orders SET status='delivered', updated_at=CURRENT_TIMESTAMP WHERE id=:id")
        ->execute([':id'=>$orderId]);
    if (!empty($o['product_id'])) {
      $pdo->prepare("UPDATE products SET status='sold', updated_at=CURRENT_TIMESTAMP WHERE id=:pid")
          ->execute([':pid'=>(int)$o['product_id']]);
    }
    $pdo->commit();

    header('Location: ' . $append($return, 'msg=delivered')); exit;
  }
  else {
    header('Location: ' . $append($return, 'err=unknown_action')); exit;
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ' . $append($return, 'err=exception')); exit;
}