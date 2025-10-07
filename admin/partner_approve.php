<?php
// 승인 = partner_applications.status='approved'
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('admin');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /admin/partners.php?err=method'); exit; }
if (!csrf_verify($_POST['csrf'] ?? null))           { header('Location: /admin/partners.php?err=csrf');   exit; }

$appId = (int)($_POST['id'] ?? 0);
if ($appId <= 0) { header('Location: /admin/partners.php?err=bad_id'); exit; }


$pdo = db();
$appStmt = $pdo->prepare("SELECT id, email FROM partner_applications WHERE id=:id");
$appStmt->execute([':id'=>$appId]);
$app = $appStmt->fetch(PDO::FETCH_ASSOC);
if (!$app) { header('Location: /admin/partners.php?err=not_found'); exit; }

try {
  $pdo->beginTransaction();

  // 1) 신청서 승인
  $st = $pdo->prepare("UPDATE partner_applications SET status='approved', updated_at=NOW() WHERE id=:id");
  $st->execute([':id'=>$appId]);

  // 2) users.role = 'partner'로 승격 (admin은 유지) — email 기준
  if (!empty($app['email'])) {
    $up = $pdo->prepare("UPDATE users SET role='partner', updated_at=NOW() WHERE email=:email AND role <> 'admin'");
    $up->execute([':email' => $app['email']]);

    // 3) partner_profiles 자동 생성 또는 업데이트 (승인 시 공개)
    $ins = $pdo->prepare("
      INSERT INTO partner_profiles (user_id, store_name, is_published, created_at, updated_at)
      SELECT u.id, COALESCE(pa.business_name, pa.name, u.name), 1, NOW(), NOW()
      FROM users u
      JOIN partner_applications pa ON pa.email = u.email
      WHERE pa.id = :appid
      ON DUPLICATE KEY UPDATE
        store_name = VALUES(store_name),
        is_published = 1,
        updated_at = NOW()
    ");
    $ins->execute([':appid' => $appId]);
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: /admin/partners.php?err=exception&reason=' . rawurlencode($e->getMessage()));
  exit;
}

// (선택) 리다이렉트 복귀
$back = $_POST['return'] ?? '/admin/partners.php';
header('Location: ' . $back . (str_contains($back,'?')?'&':'?') . 'msg=approved');