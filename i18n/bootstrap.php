<?php
// Session safe-start
if (session_status() === PHP_SESSION_NONE) {
  @ini_set('session.use_strict_mode', '1');
  @ini_set('session.cookie_httponly', '1');
  @ini_set('session.cookie_samesite', 'Lax');
  session_start();
}

// /i18n/bootstrap.php
// - 언어 프리픽스(/ko,/en,/th,/my) 기반 감지
// - ?lang= 사용 금지, 리다이렉트 금지
// - URL 생성기 lang_url()
// - 간단 i18n 로더 및 __() 함수

// -------------------- Helpers (declare first!) --------------------
if (!function_exists('i18n_supported_langs')) {
  function i18n_supported_langs(): array {
    // 사용 언어 목록
    return ['ko','en','th','my'];
  }
}

if (!function_exists('i18n_detect_prefix')) {
  function i18n_detect_prefix(string $uriPath): ?string {
    return preg_match('#^/(ko|en|th|my)(/|$)#', $uriPath, $m) ? $m[1] : null;
  }
}

if (!function_exists('i18n_current_path_clean')) {
  // 현재 요청에서 언어 프리픽스와 중복 lang 파라미터 제거
  function i18n_current_path_clean(): string {
    $uri   = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path  = $parts['path'] ?? '/';

    if ($pref = i18n_detect_prefix($path)) {
      $path = substr($path, strlen('/'.$pref));
      if ($path === '') $path = '/';
    }

    // ?lang 제거
    $qs = [];
    if (!empty($parts['query'])) {
      parse_str($parts['query'], $qs);
      unset($qs['lang']);
    }
    return $path . (empty($qs) ? '' : ('?' . http_build_query($qs)));
  }
}

if (!function_exists('lang_url')) {
  /**
   * lang_url('/path', $lang = null, $params = [])
   * - 프리픽스(/ko,/en,...)만 사용. ?lang= 미사용
   * - path에 기존 프리픽스가 있으면 제거 후 지정 언어 프리픽스를 1회만 부여
   * - params 병합 시 lang 키는 강제 제거
   */
  function lang_url(string $path, ?string $lang = null, array $params = []): string {
    $supported = i18n_supported_langs();
    $lang = $lang ?: (defined('APP_LANG') ? APP_LANG : 'ko');
    $lang = strtolower((string)$lang);
    if (!in_array($lang, $supported, true)) $lang = 'ko';

    if ($path === '' || $path === null) $path = '/';
    if ($path[0] !== '/') $path = '/' . $path;

    $parts = parse_url($path);
    $clean = $parts['path'] ?? '/';

    // 기존 프리픽스 제거
    if ($old = i18n_detect_prefix($clean)) {
      $clean = substr($clean, strlen('/'.$old));
      if ($clean === '') $clean = '/';
    }

    // 쿼리: path의 쿼리 + params 병합, lang 제거
    $qs = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $qs);
    unset($qs['lang'], $params['lang']);
    $qs = array_filter(
      array_merge($qs, $params),
      function ($v) { return $v !== null && $v !== ''; }
    );

    $url = '/' . $lang . ($clean === '/' ? '' : rtrim($clean, '/'));
    if (!empty($qs)) $url .= '?' . http_build_query($qs);
    return $url;
  }
}

// -------------------- Decide APP_LANG (no redirects) --------------------
$supported = i18n_supported_langs();
$default   = 'ko';

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$lang    = i18n_detect_prefix($uriPath) ?: ($_SESSION['lang'] ?? $default);
$lang    = strtolower((string)$lang);
if (!in_array($lang, $supported, true)) $lang = $default;
$_SESSION['lang'] = $lang;

if (!defined('APP_LANG')) define('APP_LANG', $lang);

// -------------------- Simple i18n loader & __() --------------------
if (!function_exists('__')) {
  $GLOBALS['_I18N'] = $GLOBALS['_I18N'] ?? [];

  // 메시지 파일 로드 순서 (덮어쓰기 우선순위 보장):
  // 1) en (전역 기본)
  // 2) ko (사이트 기본)
  // 3) APP_LANG (현재 선택 언어)  ← 마지막이 최종 우선권
  $baseDir = __DIR__;
  $files = [
    $baseDir . '/messages.en.php',
    $baseDir . '/messages.ko.php',
    $baseDir . '/messages.' . APP_LANG . '.php',
  ];

  foreach ($files as $file) {
    if (is_file($file)) {
      $arr = include $file;
      if (is_array($arr)) {
        // array_replace: 오른쪽(새값)이 왼쪽(기존)을 덮어씀
        $GLOBALS['_I18N'] = array_replace($GLOBALS['_I18N'], $arr);
      }
    }
  }

  function __(string $key) {
    $dict = $GLOBALS['_I18N'] ?? [];
    return $dict[$key] ?? $key;
  }
}