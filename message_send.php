<?php
// /message_send.php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/messages.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/guard.php';

require_login(); // 로그인 필수

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php?err=method_not_allowed');
    exit;
}

if (!csrf_verify($_POST['csrf'] ?? '')) {
    header('Location: /index.php?err=bad_csrf');
    exit;
}

$pdo       = db();
$me        = $_SESSION['user'] ?? null;
$userId    = (int)($me['id'] ?? 0);
$threadId  = (int)($_POST['thread_id'] ?? 0);
$body      = trim($_POST['body'] ?? '');
$returnUrl = trim($_POST['return'] ?? ''); // 선택: 호출측에서 보낸 돌아갈 주소

if ($userId <= 0) {
    header('Location: /auth/login.php?next=' . urlencode('/my_messages.php'));
    exit;
}

if ($threadId <= 0 || $body === '') {
    // thread_id나 body가 비정상이면 안전한 기본 위치로
    header('Location: /my_messages.php?err=bad_request');
    exit;
}

try {
    // 참여자 권한 확인 + 메시지 전송 (내부에서 last_read_at 처리)
    msgs_send_message($pdo, $threadId, $userId, $body);

    // 리다이렉트 경로 결정
    if ($returnUrl !== '') {
        // 호출하는 폼에서 명시적으로 return을 준 경우 우선
        $to = $returnUrl;
    } else {
        // 사용자 역할에 따라 기본 상세 화면으로
        $role = strtolower((string)($me['role'] ?? 'customer'));
        if ($role === 'partner') {
            $to = '/partner/message_view.php?thread_id=' . $threadId . '&msg=sent';
        } elseif ($role === 'admin') {
            // 관리자 뷰가 따로 있다면 여기에 맞춰 수정
            $to = '/admin/message_view.php?thread_id=' . $threadId . '&msg=sent';
        } else {
            $to = '/my_message_view.php?thread_id=' . $threadId . '&msg=sent';
        }
    }

    header('Location: ' . $to);
    exit;

} catch (Throwable $e) {
    // not_participant, empty_body 등 에러를 쿼리로 표시
    $code = $e->getMessage() ?: 'send_failed';
    // 가능한 안전한 기본 페이지로
    header('Location: /my_messages.php?err=' . urlencode($code));
    exit;
}