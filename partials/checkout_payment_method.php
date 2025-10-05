<?php
// /partials/checkout_payment_method.php
// 장바구니 결제 진행 폼(#checkoutForm) 안에서 include
// ❗ 결제 방식 노출은 cart.php에서 계산된 $allowNormal / $allowCOD 값을 신뢰합니다.
//    (없으면 기본 true 처리)

// cart.php에서 내려준 교집합 결과 (없으면 기본 true)
$allowNormal = isset($allowNormal) ? (bool)$allowNormal : true;
$allowCOD    = isset($allowCOD)    ? (bool)$allowCOD    : true;

// 기본 선택값 결정: 쿼리 pm=cod|normal → 허용 여부 우선 보정
$reqPm     = $_GET['pm'] ?? '';
$preferred = ($reqPm === 'cod') ? 'cod' : 'normal';
if ($preferred === 'cod' && !$allowCOD)       $preferred = $allowNormal ? 'normal' : '';
if ($preferred === 'normal' && !$allowNormal) $preferred = $allowCOD ? 'cod' : '';
$__pm_default = $preferred ?: ($allowNormal ? 'normal' : ($allowCOD ? 'cod' : ''));
?>

<fieldset class="border rounded-lg p-4">
  <legend class="px-2 text-sm font-semibold text-gray-700">결제 방식 선택</legend>

  <div class="mt-2 space-y-2">
    <?php if ($allowNormal): ?>
      <label class="flex items-center gap-2">
        <input type="radio" name="payment_method" value="normal" <?= $__pm_default==='normal'?'checked':'' ?> required>
        <span>일반결제 (QR)</span>
      </label>
    <?php endif; ?>

    <?php if ($allowCOD): ?>
      <label class="flex items-center gap-2">
        <input type="radio" name="payment_method" value="cod" <?= $__pm_default==='cod'?'checked':'' ?> required>
        <span>현장 결제(COD)</span>
      </label>
    <?php endif; ?>

    <?php if (!$allowNormal && !$allowCOD): ?>
      <div class="text-sm text-red-600">선택 가능한 결제 방식이 없습니다. (판매자 설정 확인)</div>
    <?php endif; ?>
  </div>

  <p class="mt-1 text-xs text-gray-500">※ 하나를 반드시 선택하세요.</p>
</fieldset>

<script>
// 이 검증은 결제 모달 폼(#checkoutForm)에만 적용됩니다.
(function(){
  function ready(fn){ if(document.readyState!=="loading") return fn(); document.addEventListener('DOMContentLoaded', fn); }
  ready(function(){
    var form = document.getElementById('checkoutForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
      var radios = form.querySelectorAll('input[name="payment_method"]');
      if (!radios.length) return; // 라디오가 없으면 검증 패스
      var sel = form.querySelector('input[name="payment_method"]:checked');
      if (!sel) { e.preventDefault(); alert('결제 방식을 선택하세요 (일반결제 또는 COD).'); }
    });
  });
})();
</script>