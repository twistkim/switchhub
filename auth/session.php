<?php
// /auth/session.php
if (session_status() !== PHP_SESSION_ACTIVE) {
  // (선택) 고정된 세션 이름 사용
  if (ini_get('session.name') !== 'PHPSESSID') {
    session_name('PHPSESSID');
  }

  // 쿠키 파라미터: 반드시 path='/'
  $params = session_get_cookie_params();
  $cookieParams = [
    'lifetime' => 0,
    'path'     => '/', // ✅ 언어 프리픽스 경로에서도 동일 쿠키
    'domain'   => $params['domain'] ?: $_SERVER['HTTP_HOST'],
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
  ];
  session_set_cookie_params($cookieParams);

  session_start();
}

// 편의: 사용자 배열이 아니면 null로
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
  // 로그인 페이지에서 세팅해야 함
  // $_SESSION['user'] = ['id'=>..., 'name'=>..., 'role'=>...];
}