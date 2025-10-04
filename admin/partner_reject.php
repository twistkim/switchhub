<?php
// 거절 = partner_applications.status='rejected'
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /admin/partners.php?err=method'); exit; }
if (!csrf_verify($_POST['csrf'] ?? null))           { header('Location: /admin/partners.php?err=csrf');   exit; }

$appId = (int)($_POST['id'] ?? 0);
if ($appId <= 0) { header('Location: /admin/partners.php?err=bad_id'); exit; }

$pdo = db();
$st = $pdo->prepare("UPDATE partner_applications SET status='rejected', updated_at=NOW() WHERE id=:id");
$st->execute([':id'=>$appId]);

$back = $_POST['return'] ?? '/admin/partners.php';
header('Location: ' . $back . (str_contains($back,'?')?'&':'?') . 'msg=rejected');