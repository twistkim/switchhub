<?php
require_once __DIR__ . '/session.php';

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function csrf_verify(?string $token): bool {
  return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

// 세션 보장
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

/**
 * (없다면) 기본 토큰 생성기 보강
 * - 이미 프로젝트에 csrf_token()이 있으면 이 블록은 무시됨.
 */
if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
  }
}

/**
 * (신규) 통일된 검사 함수: 폼 name=csrf 를 기본으로 검사
 * - 기존에 다른 verify 함수가 있어도, 이건 호환용 래퍼라 충돌 없음.
 * - $token=null 이면 $_POST['csrf'] → $_GET['csrf'] 순으로 자동 취득
 * - $debug=true 이면 화면에 비교값을 같이 출력
 */
if (!function_exists('csrf_check_or_die')) {
  function csrf_check_or_die(?string $token = null, string $field = 'csrf', bool $debug = false): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

    $posted = $token ?? ($_POST[$field] ?? $_GET[$field] ?? '');
    $sess   = $_SESSION['csrf_token'] ?? '';

    $ok = (is_string($posted) && is_string($sess) && $posted !== '' && $sess !== '' && hash_equals($sess, $posted));
    if ($ok) return;

    http_response_code(400);
    echo "CSRF validation failed";
    if ($debug) {
      echo "<hr><pre>posted=" . htmlspecialchars((string)$posted, ENT_QUOTES, 'UTF-8') .
           "\nsession=" . htmlspecialchars((string)$sess,   ENT_QUOTES, 'UTF-8') . "</pre>";
    }
    exit;
  }
}

/**
 * (편의) 숨은필드 생성기
 */
if (!function_exists('csrf_field')) {
  function csrf_field(string $name = 'csrf'): string {
    return '<input type="hidden" name="'.$name.'" value="' .
           htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
  }
}