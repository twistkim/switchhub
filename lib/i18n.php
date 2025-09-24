<?php
// /lib/i18n.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function i18n_detect_lang(): string {
  // 1) URL ?lang=th|ko 우선
  if (!empty($_GET['lang'])) {
    $lang = $_GET['lang'];
    if (in_array($lang, ['ko','th'], true)) {
      $_SESSION['lang'] = $lang;
      return $lang;
    }
  }
  // 2) 세션
  if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['ko','th'], true)) {
    return $_SESSION['lang'];
  }
  // 3) 브라우저 언어 (기본 ko)
  $al = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
  if (str_starts_with($al, 'th')) return 'th';
  return 'ko';
}

function i18n_load(string $lang): array {
  $file = __DIR__ . "/../lang/{$lang}.php";
  if (!is_file($file)) $file = __DIR__ . '/../lang/ko.php';
  /** @var array $L */
  $L = require $file;
  return $L;
}

function __t(string $key, array $rep = []): string {
  static $L = null;
  if ($L === null) {
    $lang = i18n_detect_lang();
    $L = i18n_load($lang);
  }
  $s = $L[$key] ?? $key;
  // 간단 치환: ['{name}' => '홍길동']
  if ($rep) $s = strtr($s, $rep);
  return $s;
}

// 필요 시 현재 언어 코드가 필요하면:
function i18n_lang(): string {
  return i18n_detect_lang();
}