<?php
// /lib/product_payment.php
// "일반판매 / COD" 다중 체크박스 파싱 + 검증 유틸

if (!function_exists('payment_parse_post')) {
  /**
   * @return array ['normal'=>0|1, 'cod'=>0|1]
   */
  function payment_parse_post(array $post): array {
    $methods = $post['payment_methods'] ?? [];
    if (!is_array($methods)) $methods = [];
    $allow_normal = in_array('normal', $methods, true) ? 1 : 0;
    $allow_cod    = in_array('cod',    $methods, true) ? 1 : 0;

    // 신규 등록시 아무 것도 안보내면 기본값(일반판매 허용)으로 보정하고 싶다면 아래 주석 해제
    // if ($allow_normal === 0 && $allow_cod === 0) { $allow_normal = 1; }

    return ['normal' => $allow_normal, 'cod' => $allow_cod];
  }
}

if (!function_exists('payment_validate_or_redirect')) {
  /**
   * 최소 1개 선택 필수. 실패 시 리다이렉트(에러코드 포함) 후 종료.
   */
  function payment_validate_or_redirect(array $allow, string $returnUrl): void {
    $hasAny = ((int)$allow['normal'] === 1) || ((int)$allow['cod'] === 1);
    if (!$hasAny) {
      // returnUrl에 에러 쿼리 붙여서 돌려보내기
      $to = $returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'err=payment_required';
      header('Location: ' . $to);
      exit;
    }
  }
}