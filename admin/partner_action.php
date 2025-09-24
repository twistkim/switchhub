<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /admin/partners.php');
  exit;
}

$pdo = db();
$aid = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$return = (isset($_POST['return']) && is_string($_POST['return']) && $_POST['return'] !== '') ? $_POST['return'] : '/admin/partners.php';

$st = $pdo->prepare("SELECT * FROM partner_applications WHERE id=:id LIMIT 1");
$st->execute([':id'=>$aid]);
$app = $st->fetch();
if (!$app) { header('Location: /admin/partners.php'); exit; }

$adminId = (int)($_SESSION['user']['id'] ?? 0);

if ($action === 'approve') {
  // 1) 신청 상태 변경
  $pdo->prepare("UPDATE partner_applications SET status='approved', reviewer_id=:rid, reviewed_at=NOW(), updated_at=NOW() WHERE id=:id")
      ->execute([':rid'=>$adminId, ':id'=>$aid]);

  // 2) 사용자 승격 (이메일 대소문자/공백 무시, admin 은 승격 제외)
  $email = trim((string)($app['email'] ?? ''));
  if ($email !== '') {
    $u = $pdo->prepare("SELECT id, role, is_active FROM users WHERE LOWER(email)=LOWER(:em) LIMIT 1");
    $u->execute([':em'=>$email]);
    if ($usr = $u->fetch()) {
      if ($usr['role'] !== 'admin') {
        $pdo->prepare("UPDATE users SET role='partner', is_active=1, updated_at=NOW() WHERE id=:uid")
            ->execute([':uid'=>(int)$usr['id']]);
      }
    }
    // (옵션) 회원이 없을 때 자동 생성하려면 아래 주석을 해제하세요.
    // $pdo->prepare("INSERT INTO users(name,email,password_hash,role,is_active,created_at,updated_at) VALUES(:n,:e,'', 'partner',1,NOW(),NOW())")
    //     ->execute([':n'=>$app['name'] ?: $app['business_name'] ?: 'Partner', ':e'=>$email]);
  }

} elseif ($action === 'reject') {
  $pdo->prepare("UPDATE partner_applications SET status='rejected', reviewer_id=:rid, reviewed_at=NOW(), updated_at=NOW() WHERE id=:id")
      ->execute([':rid'=>$adminId, ':id'=>$aid]);

} elseif ($action === 'reset') {
  $pdo->prepare("UPDATE partner_applications SET status='pending', reviewer_id=NULL, reviewed_at=NULL, updated_at=NOW() WHERE id=:id")
      ->execute([':id'=>$aid]);
}

header('Location: ' . $return);
exit;