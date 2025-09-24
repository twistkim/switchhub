<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/cart.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /cart.php'); exit;
}

// 로그인 필수
$me = $_SESSION['user'] ?? null;
if (!$me) { header('Location: /auth/login.php?redirect='.urlencode('/cart.php')); exit; }

$pdo = db();
$cart = cart_get();
if (empty($cart)) { header('Location: /cart.php?err='.urlencode('장바구니가 비어있습니다.')); exit; }

// 결제 완료 가정 → 각 상품 주문 생성
$pdo->beginTransaction();
try {
  foreach (array_keys($cart) as $pid) {
    $st = $pdo->prepare("SELECT id, price, status, approval_status FROM products WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$pid]);
    $p = $st->fetch();
    if (!$p || $p['approval_status']!=='approved' || $p['status']!=='on_sale') {
      // 유효하지 않은 상품은 스킵
      continue;
    }

    $ins = $pdo->prepare("
      INSERT INTO orders (user_id, product_id, status, created_at, updated_at)
      VALUES (:uid, :pid, 'awaiting_payment', NOW(), NOW())
    ");
    $ins->execute([':uid'=>$me['id'], ':pid'=>$pid]);

  }

  $pdo->commit();
  cart_clear();
  header('Location: /my.php?msg='.urlencode('주문이 생성되었습니다. (관리자 입금 확인 후 진행됩니다) 주문 내역에서 확인하세요.'));
} catch (Exception $e) {
  $pdo->rollBack();
  header('Location: /cart.php?err='.urlencode('주문 생성 실패: '.$e->getMessage()));
}
exit;