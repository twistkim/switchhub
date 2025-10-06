<?php
// /partials/product_form_core.php
// $product: 편집 대상 배열
// $categories: [ [id,name], ... ]
// $mode: 'partner'|'admin'
// 필수: csrf_token() 사용 가능해야 함

$name         = $product['name']          ?? '';
$description  = $product['description']   ?? '';
$price        = $product['price']         ?? '';
$condition    = $product['condition']     ?? '';
$release_year = $product['release_year']  ?? '';
$category_id  = (int)($product['category_id'] ?? 0);
$payment_normal = (int)($product['payment_normal'] ?? 0);
$payment_cod    = (int)($product['payment_cod'] ?? 0);
$status       = $product['status']        ?? 'on_sale';        // on_sale|pending|sold 등
$approval     = $product['approval_status'] ?? 'pending';      // admin 전용
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <div>
    <label class="block text-sm font-medium">상품명</label>
    <input name="name" type="text" class="mt-1 w-full border rounded px-3 py-2"
           value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
  </div>

  <div>
    <label class="block text-sm font-medium">가격(THB)</label>
    <input name="price" type="number" min="0" step="1" class="mt-1 w-full border rounded px-3 py-2"
           value="<?= htmlspecialchars((string)$price, ENT_QUOTES, 'UTF-8') ?>" required>
  </div>

  <div class="md:col-span-2">
    <label class="block text-sm font-medium">상세 설명</label>
    <textarea name="description" rows="5" class="mt-1 w-full border rounded px-3 py-2"
              required><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
  </div>

  <div>
    <label class="block text-sm font-medium">상품 상태(Condition)</label>
    <select name="condition" class="mt-1 w-full border rounded px-3 py-2" required>
      <?php
        $conds = ['new'=>'새상품','like_new'=>'미사용급','good'=>'상','fair'=>'중','as_is'=>'하'];
        foreach ($conds as $k=>$label):
      ?>
        <option value="<?= $k ?>" <?= $condition===$k?'selected':'' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div>
    <label class="block text-sm font-medium">출시년도</label>
    <input name="release_year" type="number" min="2000" max="2100"
           class="mt-1 w-full border rounded px-3 py-2"
           value="<?= htmlspecialchars((string)$release_year, ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <div>
    <label class="block text-sm font-medium">카테고리</label>
    <select name="category_id" class="mt-1 w-full border rounded px-3 py-2" required>
      <option value="">선택</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= $category_id===(int)$cat['id']?'selected':'' ?>>
          <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="md:col-span-2">
    <label class="block text-sm font-medium mb-1">결제 허용</label>
    <div class="flex gap-4 items-center">
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="payment_normal" value="1" <?= $payment_normal? 'checked':''; ?>>
        <span>일반결제(QR)</span>
      </label>
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="payment_cod" value="1" <?= $payment_cod? 'checked':''; ?>>
        <span>현장 결제(COD)</span>
      </label>
    </div>
    <p class="text-xs text-gray-500 mt-1">※ 적어도 1개는 선택해야 합니다.</p>
  </div>

  <?php if (($mode ?? '') === 'admin'): ?>
    <div>
      <label class="block text-sm font-medium">판매 상태</label>
      <select name="status" class="mt-1 w-full border rounded px-3 py-2">
        <?php foreach (['on_sale'=>'판매중','pending'=>'구매 진행중','sold'=>'판매 완료'] as $k=>$lbl): ?>
          <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium">승인 상태</label>
      <select name="approval_status" class="mt-1 w-full border rounded px-3 py-2">
        <?php foreach (['pending'=>'대기','approved'=>'승인','rejected'=>'거절'] as $k=>$lbl): ?>
          <option value="<?= $k ?>" <?= $approval===$k?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
</div>

<div class="mt-6 flex gap-2">
  <button class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">저장</button>
  <a href="javascript:history.back()" class="px-4 py-2 rounded border">취소</a>
</div>