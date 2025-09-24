<?php
require_once __DIR__ . '/session.php';

function current_user() {
  return $_SESSION['user'] ?? null; // ['id','name','email','role']
}
function is_logged_in(): bool {
  return isset($_SESSION['user']);
}
function require_login() {
  if (!is_logged_in()) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
  }
}
function require_role(string $role) {
  require_login();
  if (($_SESSION['user']['role'] ?? '') !== $role) {
    http_response_code(403);
    echo '권한이 없습니다.';
    exit;
  }
}