<?php
// 세션 공통 초기화 (모든 인증 관련 스크립트 최상단에서 include)
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) {
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
  ]);
}