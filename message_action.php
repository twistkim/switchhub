<?php
// /message_action.php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/messages.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/guard.php';

require_login(); // 로그인 필수

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /index.php?err=method_not_allowed'); exit;
}
if (!csrf_verify($_POST['csrf'] ?? '')) {
  header('Location: /index.php?err=bad_csrf'); exit;
}

$pdo       = db();
$me        = $_SESSION['user'] ?? null;
$userId    = (int)($me['id'] ?? 0);
$role      = strtolower((string)($me['role'] ?? 'customer'));

$action    = trim($_POST['action'] ?? '');
$threadId  = (int)($_POST['thread_id'] ?? 0);
$messageId = (int)($_POST['message_id'] ?? 0); // delete_message 용
$returnUrl = trim($_POST['return'] ?? '');

// 안전한 기본 리디렉션 목적지
function msgs_default_return($role, $threadId) {
  if ($threadId > 0) {
    if ($role === 'partner') return '/partner/message_view.php?thread_id='.$threadId;
    if ($role === 'admin')   return '/admin/message_view.php?thread_id='.$threadId;
    return '/my_message_view.php?thread_id='.$threadId;
  } else {
    if ($role === 'partner') return '/partner/messages.php';
    if ($role === 'admin')   return '/admin/messages.php';
    return '/my_messages.php';
  }
}

if ($userId <= 0) {
  header('Location: /auth/login.php?next=' . urlencode('/my_messages.php')); exit;
}
if ($threadId <= 0 && $action !== 'delete_message') {
  header('Location: /my_messages.php?err=bad_request'); exit;
}

try {
  switch ($action) {
    case 'archive': {
      // 스레드 참여자만 가능
      msgs_require_participant($pdo, $threadId, $userId);
      $st = $pdo->prepare("UPDATE message_participants SET is_archived=1 WHERE thread_id=:t AND user_id=:u");
      $st->execute([':t'=>$threadId, ':u'=>$userId]);
      $to = $returnUrl ?: msgs_default_return($role, 0);
      header('Location: '.$to.'&msg=archived'); exit;
    }

    case 'unarchive': {
      msgs_require_participant($pdo, $threadId, $userId);
      $st = $pdo->prepare("UPDATE message_participants SET is_archived=0 WHERE thread_id=:t AND user_id=:u");
      $st->execute([':t'=>$threadId, ':u'=>$userId]);
      $to = $returnUrl ?: msgs_default_return($role, $threadId);
      header('Location: '.$to.'&msg=unarchived'); exit;
    }

    case 'mark_read': {
      msgs_mark_read($pdo, $threadId, $userId);
      $to = $returnUrl ?: msgs_default_return($role, $threadId);
      header('Location: '.$to.'&msg=read'); exit;
    }

    case 'delete_message': {
      // 개별 메시지 소프트 삭제: 보낸 사람 본인 또는 관리자만
      if ($messageId <= 0) { header('Location: /my_messages.php?err=bad_request'); exit; }

      // 메시지 + 스레드 id 조회
      $st = $pdo->prepare("SELECT m.thread_id, m.sender_id FROM messages m WHERE m.id=:id LIMIT 1");
      $st->execute([':id'=>$messageId]);
      $row = $st->fetch();
      if (!$row) { header('Location: /my_messages.php?err=message_not_found'); exit; }

      $msgThreadId = (int)$row['thread_id'];
      $senderId    = (int)$row['sender_id'];

      // 스레드 참여자만 접근 가능
      msgs_require_participant($pdo, $msgThreadId, $userId);

      // 본인 메시지거나 관리자만 삭제 허용
      if ($userId !== $senderId && $role !== 'admin') {
        header('Location: ' . msgs_default_return($role, $msgThreadId) . '&err=forbidden'); exit;
      }

      $pdo->prepare("UPDATE messages SET is_deleted=1 WHERE id=:id")
          ->execute([':id'=>$messageId]);

      // 스레드 최신시간 재계산은 과도하므로 생략(단, 필요하면 트리거/스케줄러로)
      $to = $returnUrl ?: msgs_default_return($role, $msgThreadId);
      header('Location: '.$to.'&msg=message_deleted'); exit;
    }

    default:
      header('Location: ' . msgs_default_return($role, $threadId) . '&err=unknown_action'); exit;
  }

} catch (Throwable $e) {
  error_log('message_action error: ' . $e->getMessage());
  $to = $returnUrl ?: msgs_default_return($role, $threadId);
  header('Location: ' . $to . '&err=' . urlencode($e->getMessage() ?: 'action_failed')); exit;
}