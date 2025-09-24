<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /admin/index.php?tab=issues');
  exit;
}
$pdo = db();
$issue_id = (int)($_POST['issue_id'] ?? 0);
$action = $_POST['action'] ?? 'note';
$note = trim($_POST['note'] ?? '');

$exists = $pdo->prepare("SELECT id FROM order_issues WHERE id=:id LIMIT 1");
$exists->execute([':id'=>$issue_id]);
if (!$exists->fetch()) {
  header('Location: /admin/index.php?tab=issues');
  exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS order_issue_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issue_id BIGINT UNSIGNED NOT NULL,
  admin_id BIGINT UNSIGNED NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_issue (issue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

if ($action === 'note' && $note !== '') {
  $ins = $pdo->prepare("INSERT INTO order_issue_notes(issue_id, admin_id, note) VALUES (:iid, :aid, :note)");
  $ins->execute([':iid'=>$issue_id, ':aid'=>$_SESSION['user']['id'], ':note'=>$note]);
} elseif ($action === 'resolve') {
  // 종결 메모 남기기
  $ins = $pdo->prepare("INSERT INTO order_issue_notes(issue_id, admin_id, note) VALUES (:iid, :aid, :note)");
  $ins->execute([':iid'=>$issue_id, ':aid'=>$_SESSION['user']['id'], ':note'=>$note!==''?$note:'처리 완료']);
}
header('Location: /admin/index.php?tab=issues');
exit;