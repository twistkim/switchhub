<?php
declare(strict_types=1);

// ---- config ----
$root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$cacheDir = $root . '/uploads/_cache';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }

// ---- input & validation ----
$src = $_GET['src'] ?? '';
if ($src === '' || strpos($src, '..') !== false || $src[0] !== '/') {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Bad 'src' param");
}
$path = $root . $src;
if (!is_file($path)) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Not found");
}

// ---- negotiation ----
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$supportsWebp = (stripos($accept, 'image/webp') !== false);

// 미디어 타입 추정
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
  'webp' => 'image/webp',
  'png'  => 'image/png',
  'jpg','jpeg' => 'image/jpeg',
  default => 'application/octet-stream',
};

header('Vary: Accept');

// 1) 브라우저가 webp 지원하거나, 원본이 webp가 아닐 때: 그냥 원본 서빙
if ($supportsWebp || $ext !== 'webp') {
  header('Content-Type: ' . $mime);
  header('Cache-Control: public, max-age=31536000, immutable');
  readfile($path);
  exit;
}

// 2) 브라우저가 webp 미지원 + 원본이 webp → jpg 변환
if (!function_exists('imagecreatefromwebp')) {
  http_response_code(415);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Server can't decode WEBP (no GD/Imagick support).");
}

$hash = md5($src) . '.jpg';
$cached = $cacheDir . '/' . $hash;

if (!is_file($cached) || filemtime($cached) < filemtime($path)) {
  $im = @imagecreatefromwebp($path);
  if (!$im) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("WEBP decode failed");
  }
  // 퀄리티 85로 JPEG 저장 (원하면 75~90 사이로 조절)
  imagejpeg($im, $cached, 85);
  imagedestroy($im);
}

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=31536000, immutable');
readfile($cached);