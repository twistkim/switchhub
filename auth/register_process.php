<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /auth/register.php');
  exit;
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /auth/register.php?err=' . urlencode('유효하지 않은 요청입니다.'));
  exit;
}

$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$pw    = (string)($_POST['password'] ?? '');
$pw2   = (string)($_POST['password_confirm'] ?? '');

if ($name === '' || $email === '' || $pw === '' || $pw2 === '') {
  header('Location: /auth/register.php?err=' . urlencode('모든 필드를 입력하세요.'));
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: /auth/register.php?err=' . urlencode('이메일 형식이 올바르지 않습니다.'));
  exit;
}
if ($pw !== $pw2) {
  header('Location: /auth/register.php?err=' . urlencode('비밀번호가 일치하지 않습니다.'));
  exit;
}
if (strlen($pw) < 6) {
  header('Location: /auth/register.php?err=' . urlencode('비밀번호는 6자 이상이어야 합니다.'));
  exit;
}

$pdo = db();

// 이메일 중복 확인
$exists = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$exists->execute([':email' => $email]);
if ($exists->fetch()) {
  header('Location: /auth/register.php?err=' . urlencode('이미 사용 중인 이메일입니다.'));
  exit;
}

// 비밀번호 해시
$hash = password_hash($pw, PASSWORD_DEFAULT);

// 고객 계정 생성
$ins = $pdo->prepare("
  INSERT INTO users (name, email, password_hash, role, is_active)
  VALUES (:name, :email, :hash, 'customer', 1)
");
$ins->execute([
  ':name' => $name,
  ':email' => $email,
  ':hash' => $hash,
]);

// 자동 로그인
$userId = (int)$pdo->lastInsertId();
$_SESSION['user'] = [
  'id'    => $userId,
  'name'  => $name,
  'email' => $email,
  'role'  => 'customer',
];

header('Location: /?msg=' . urlencode('회원가입이 완료되었습니다.'));
exit;