<?php
// /lib/media.php
function render_product_img(string $src, string $alt = '', string $class = 'w-full h-56 object-cover') {
  // webp면 jpg 폴백 경로 추정 (같은 파일명에 확장자만 .jpg/.jpeg)
  $fallback = $src;
  if (preg_match('/\.webp$/i', $src)) {
    $fallback = preg_replace('/\.webp$/i', '.jpg', $src);
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $fallback)) {
      $fallback = preg_replace('/\.webp$/i', '.jpeg', $src);
    }
  }

  // 출력
  $altEsc = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
  $srcEsc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
  $fbEsc  = htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
  echo '<picture>';
  echo '  <source srcset="'.$srcEsc.'" type="image/webp">';
  echo '  <img src="'.$fbEsc.'" alt="'.$altEsc.'" class="'.$class.'" loading="lazy">';
  echo '</picture>';
}