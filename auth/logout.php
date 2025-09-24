<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: /?msg=' . urlencode('로그아웃 되었습니다.'));
exit;