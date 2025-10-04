<?php
// /admin/partner_hide.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$csrf = $_POST['csrf'] ?? '';
if (!csrf_verify($csrf)) {
  header('Location: /admin/partners.php?err=csrf');
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$returnUrl = $_POST['return'] ?? '/admin/partners.php';
if ($id <= 0) {
  header('Location: ' . $returnUrl . '?err=invalid_id');
  exit;
}

try {
  $pdo = db();

  $pdo->beginTransaction();

  // partner_applications에서 user_id 찾기
  $st = $pdo->prepare("SELECT user_id FROM partner_applications WHERE id=:id");
  $st->execute([':id' => $id]);
  $userId = (int)$st->fetchColumn();

  if ($userId <= 0) {
    header('Location: ' . $returnUrl . '?err=user_not_found');
    exit;
  }

  // partner_profiles에서 soft hide
  $st = $pdo->prepare("
    UPDATE partner_profiles
    SET is_published = 0, updated_at = NOW()
    WHERE user_id = :uid
  ");
  $st->execute([':uid' => $userId]);

  // 파트너 자격 해제: users.role -> customer
  $pdo->prepare("UPDATE users SET role='customer', updated_at=CURRENT_TIMESTAMP WHERE id=:uid")
      ->execute([':uid' => $userId]);

  $pdo->commit();

  header('Location: ' . $returnUrl . '?msg=hidden');
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  error_log('Partner hide error: ' . $e->getMessage());
  header('Location: ' . $returnUrl . '?err=exception');
  exit;
}