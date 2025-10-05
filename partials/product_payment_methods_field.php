<?php
// /partials/product_payment_methods_field.php
// 재사용 가능한 판매방식 필드 렌더러
// 사용법: require_once __DIR__.'/../partials/product_payment_methods_field.php';
//        render_payment_methods_field($product ?? null);

if (!function_exists('render_payment_methods_field')) {
  function render_payment_methods_field(?array $product = null): void {
    // 수정폼이면 기존값 프리체크, 신규는 일반판매만 기본 ON
    $payment_normal_checked = $product ? ((int)($product['payment_normal'] ?? 1) === 1) : true;
    $payment_cod_checked    = $product ? ((int)($product['payment_cod'] ?? 0) === 1) : false;
    ?>
    <fieldset class="mt-4 border rounded-lg p-4">
      <legend class="px-2 text-sm font-semibold text-gray-700">판매 방식 (최소 1개 선택)</legend>
      <div class="mt-2 flex items-center gap-6">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="payment_methods[]" value="normal" <?= $payment_normal_checked ? 'checked' : '' ?>>
          <span>일반판매</span>
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="payment_methods[]" value="cod" <?= $payment_cod_checked ? 'checked' : '' ?>>
          <span>COD</span>
        </label>
      </div>
      <p class="mt-1 text-xs text-gray-500">※ 최소 1개는 반드시 선택해야 합니다.</p>
    </fieldset>

    <script>
      // 제출 시 최소 1개 체크 보조검증(서버에서도 검증합니다)
      document.addEventListener('DOMContentLoaded', function () {
        // 이 파일이 여러 폼에서 쓰여도 안전하게: 가장 가까운 form 기준
        document.querySelectorAll('form').forEach(function (form) {
          form.addEventListener('submit', function (e) {
            const boxes = form.querySelectorAll('input[name="payment_methods[]"]');
            if (!boxes.length) return; // 다른 폼엔 영향 X
            const any = Array.from(boxes).some(b => b.checked);
            if (!any) {
              e.preventDefault();
              alert('판매 방식을 최소 1개 이상 선택하세요 (일반판매, COD).');
            }
          });
        });
      });
    </script>
    <?php
  }
}