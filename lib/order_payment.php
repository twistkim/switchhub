<?php
// /lib/order_payment.php
if (!function_exists('op_parse_method')) {
  /**
   * POST/GET에서 결제 방식 파싱: normal | cod
   * 기본값 normal, 잘못된 값이면 normal
   */
  function op_parse_method(array $src): string {
    $m = strtolower(trim($src['payment_method'] ?? 'normal'));
    return in_array($m, ['normal','cod'], true) ? $m : 'normal';
  }
}
if (!function_exists('op_label')) {
  function op_label(?string $m): string {
    return $m === 'cod' ? 'COD' : '일반결제';
  }
}
if (!function_exists('op_badge_html')) {
  function op_badge_html(?string $m): string {
    $isCod = ($m === 'cod');
    $txt = $isCod ? 'COD' : '일반결제';
    $cls = $isCod ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800';
    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs '.$cls.'">'.$txt.'</span>';
  }
}