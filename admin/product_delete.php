<?php
// /admin/product_delete.php — 관리자: 모든 상품 소프트 삭제 (is_deleted=1)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /admin/index.php?tab=products&err=bad_request'); exit;
}

$pdo    = db();
$pid    = (int)($_POST['product_id'] ?? 0);
$return = (isset($_POST['return']) && is_string($_POST['return']) && $_POST['return'] !== '') ? $_POST['return'] : '/admin/index.php?tab=products';
$append = fn(string $url, string $q) => (strpos($url,'?')!==false) ? "$url&$q" : "$url?$q";

// 존재 확인
$st = $pdo->prepare("SELECT id FROM products WHERE id=:id LIMIT 1");
$st->execute([':id'=>$pid]);
if (!$st->fetch()) {
  header('Location: '.$append($return, 'err=not_found')); exit;
}

try {
  // 소프트 삭제: is_deleted=1 (주문 유무와 무관)
  $pdo->prepare("UPDATE products SET is_deleted=1, updated_at=CURRENT_TIMESTAMP WHERE id=:id")
      ->execute([':id'=>$pid]);

  header('Location: '.$append($return, 'msg=product_soft_deleted')); exit;
} catch (Throwable $e) {
  header('Location: '.$append($return, 'err=exception')); exit;
}