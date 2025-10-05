<?php
// /partials/payment_method_badge.php
require_once __DIR__ . '/../lib/order_payment.php';
if (!function_exists('render_payment_method_badge')) {
  function render_payment_method_badge(?string $m): void {
    echo op_badge_html($m);
  }
}