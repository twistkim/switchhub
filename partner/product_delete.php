<?php
// /partner/product_delete.php — 파트너: 본인 상품 소프트 삭제 (is_deleted=1)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('partner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /partner/index.php?err=bad_request'); exit;
}

$pdo    = db();
$me     = $_SESSION['user'];
$pid    = (int)($_POST['product_id'] ?? 0);
$return = (isset($_POST['return']) && is_string($_POST['return']) && $_POST['return'] !== '') ? $_POST['return'] : '/partner/index.php';
$append = fn(string $url, string $q) => (strpos($url,'?')!==false) ? "$url&$q" : "$url?$q";

// 1) 내 상품인지 확인
$st = $pdo->prepare("SELECT id FROM products WHERE id=:id AND seller_id=:sid LIMIT 1");
$st->execute([':id'=>$pid, ':sid'=>(int)$me['id']]);
if (!$st->fetch()) {
  header('Location: '.$append($return, 'err=not_owner')); exit;
}

try {
  // 2) 소프트 삭제 (주문 존재 여부와 무관)
  $pdo->prepare("UPDATE products SET is_deleted=1, updated_at=CURRENT_TIMESTAMP WHERE id=:id")
      ->execute([':id'=>$pid]);

  header('Location: '.$append($return, 'msg=product_soft_deleted')); exit;
} catch (Throwable $e) {
  header('Location: '.$append($return, 'err=exception')); exit;
}