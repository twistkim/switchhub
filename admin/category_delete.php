<?php
// /admin/category_delete.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';

require_role('admin');
if (function_exists('csrf_require')) csrf_require();

$pdo = db();
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: /admin/categories.php?err=bad_id'); exit; }

try {
  // 소프트 숨김
  $st = $pdo->prepare("UPDATE categories SET is_active=0, updated_at=NOW() WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$id]);
  header('Location: /admin/categories.php?msg=hidden'); exit;
} catch (Throwable $e) {
  $reason = urlencode($e->getMessage());
  header('Location: /admin/categories.php?err=exception&reason='.$reason); exit;
}