<?php
// /partials/product_payment_badges.php
if (!function_exists('render_payment_badges')) {
  function render_payment_badges(?array $product): void {
    if (!$product) return;
    $badges = [];
    if ((int)($product['payment_normal'] ?? 0) === 1) $badges[] = '일반판매';
    if ((int)($product['payment_cod'] ?? 0) === 1)    $badges[] = 'COD';
    if (!$badges) return;
    ?>
    <div class="mt-3 flex flex-wrap gap-2" id="payment-badges">
      <?php foreach ($badges as $b): ?>
        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-800 text-xs font-medium">
          <?= htmlspecialchars($b, ENT_QUOTES, 'UTF-8') ?>
        </span>
      <?php endforeach; ?>
    </div>
    <?php
  }
}