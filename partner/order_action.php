<?php
// /partner/order_action.php — 파트너 주문 액션 (송장 저장/배송중)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('partner');

$pdo = db();
$me  = $_SESSION['user'] ?? null;
$partnerId = (int)($me['id'] ?? 0);

// 안전 리다이렉트 헬퍼
$append = function(string $url, string $q) {
  if ($url === '' || stripos($url, 'http') === 0) $url = '/partner/index.php';
  return (strpos($url, '?') !== false) ? ($url . '&' . $q) : ($url . '?' . $q);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $ret = $_POST['return'] ?? '/partner/index.php';
  header('Location: ' . $append($ret, 'err=method_not_allowed'));
  exit;
}

if (!csrf_verify($_POST['csrf'] ?? null)) {
  $ret = $_POST['return'] ?? '/partner/index.php';
  header('Location: ' . $append($ret, 'err=bad_csrf'));
  exit;
}

$action = $_POST['action'] ?? '';
$orderId = (int)($_POST['order_id'] ?? 0);
$tracking = trim((string)($_POST['tracking_number'] ?? ''));
$return  = (string)($_POST['return'] ?? '/partner/index.php');
$debug = isset($_POST['debug']) || (isset($_GET['debug']) && $_GET['debug'] == '1');

try {
  if ($orderId <= 0) {
    header('Location: ' . $append($return, 'err=invalid_order'));
    exit;
  }

  // 주문 + 상품 정보 조회 (소유권 확인)
  $st = $pdo->prepare("SELECT o.id, o.status, o.product_id, o.user_id,
                               p.seller_id AS product_seller_id
                        FROM orders o
                        LEFT JOIN products p ON p.id = o.product_id
                        WHERE o.id = :id LIMIT 1");
  $st->execute([':id' => $orderId]);
  $o = $st->fetch();

  if (!$o) {
    header('Location: ' . $append($return, 'err=order_not_found'));
    exit;
  }

  $ownerByProduct = (int)($o['product_seller_id'] ?? 0) === $partnerId;
  if (!$ownerByProduct) {
    header('Location: ' . $append($return, 'err=not_owner'));
    exit;
  }

  // 허용 액션: set_shipping (신규), set_tracking (레거시 호환)
  if ($action === 'set_shipping' || $action === 'set_tracking') {
    if ($tracking === '') {
      header('Location: ' . $append($return, 'err=tracking_required'));
      exit;
    }

    // 배송중으로 전환 (입금확인/paid가 아니어도 저장은 허용 — 요구사항에 맞게 수정 가능)
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE orders SET tracking_number = :t, status = 'shipping' WHERE id = :id")
        ->execute([':t' => $tracking, ':id' => $orderId]);

    // 제품 상태는 구매 진행중으로 표준화 시도 (실패해도 무시)
    if (!empty($o['product_id'])) {
      try {
        $pdo->prepare("UPDATE products SET status = 'negotiating' WHERE id = :pid")
            ->execute([':pid' => (int)$o['product_id']]);
      } catch (Throwable $e) { /* ignore */ }
    }

    $pdo->commit();
    if ($debug) { echo 'OK: tracking_saved'; exit; }
    header('Location: ' . $append($return, 'msg=tracking_saved'));
    exit;
  }

  // 알 수 없는 액션
  header('Location: ' . $append($return, 'err=unknown_action'));
  exit;

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
  $msg = substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 180);
  error_log('[partner/order_action] exception: ' . $msg);
  $redir = $append($return, 'err=exception&reason=' . rawurlencode($msg));
  header('Location: ' . $redir);
  exit;
}