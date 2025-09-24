<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /auth/login.php');
  exit;
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /auth/login.php?err=' . urlencode('유효하지 않은 요청입니다.'));
  exit;
}

$email = trim($_POST['email'] ?? '');
$pw    = (string)($_POST['password'] ?? '');
$redirect = $_POST['redirect'] ?? '/';

if ($email === '' || $pw === '') {
  header('Location: /auth/login.php?err=' . urlencode('이메일/비밀번호를 입력하세요.'));
  exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || !(int)$user['is_active']) {
  header('Location: /auth/login.php?err=' . urlencode('계정을 찾을 수 없거나 비활성화 상태입니다.'));
  exit;
}
if (empty($user['password_hash']) || !password_verify($pw, $user['password_hash'])) {
  header('Location: /auth/login.php?err=' . urlencode('이메일 또는 비밀번호가 올바르지 않습니다.'));
  exit;
}

// 로그인 성공: 세션 저장(민감정보 제외)
$_SESSION['user'] = [
  'id'    => (int)$user['id'],
  'name'  => $user['name'],
  'email' => $user['email'],
  'role'  => $user['role'],
];

header('Location: ' . ($redirect ?: '/'));
exit;