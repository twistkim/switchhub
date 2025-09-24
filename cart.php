<?php
$pageTitle = '장바구니';
$activeMenu = 'cart';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/lib/cart.php';

$pdo = db();
$cart = cart_get();
$productIds = array_keys($cart);

$rows = [];
$total = 0.0;

if (!empty($productIds)) {
  $in  = implode(',', array_fill(0, count($productIds), '?'));
  $sql = "
    SELECT p.id, p.name, p.price, p.status, p.approval_status,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS img
    FROM products p
    WHERE p.id IN ($in)
  ";
  $st = $pdo->prepare($sql);
  $st->execute($productIds);
  $rows = $st->fetchAll();

  // 노출/결제 가능한 상품만 합산 (approved + on_sale)
  foreach ($rows as $r) {
    if ($r['approval_status']==='approved' && $r['status']==='on_sale') {
      $total += (float)$r['price'];
    }
  }
}

include __DIR__ . '/partials/header.php';
?>

<div class="bg-white border rounded-xl shadow-sm p-6">
  <h1 class="text-2xl font-bold">장바구니</h1>

  <?php if (isset($_GET['msg'])): ?>
    <div class="mt-4 p-3 rounded bg-green-50 text-green-700 text-sm"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php elseif (isset($_GET['err'])): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm"><?= htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if (empty($rows)): ?>
    <div class="mt-6 text-gray-500">장바구니가 비어있습니다.</div>
    <div class="mt-4">
      <a href="/index.php" class="px-5 py-2.5 border rounded-lg">계속 쇼핑하기</a>
    </div>
  <?php else: ?>
    <div class="mt-6 space-y-4">
      <?php foreach ($rows as $it): 
        $img = $it['img'] ?: 'https://placehold.co/600x400?text=No+Image';
        $disabled = !($it['approval_status']==='approved' && $it['status']==='on_sale');
      ?>
        <div class="flex gap-4 items-center bg-gray-50 rounded-xl p-3">
          <div class="w-20 h-20 overflow-hidden rounded-lg border bg-white">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover" />
          </div>
          <div class="flex-1">
            <div class="font-semibold"><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text-sm text-gray-600"><?= number_format((float)$it['price']) ?> THB</div>
            <?php if ($disabled): ?>
              <div class="mt-1 inline-flex text-xs px-2 py-1 rounded bg-red-100 text-red-700">결제 불가(승인/상태 확인 필요)</div>
            <?php endif; ?>
          </div>
          <form method="post" action="/cart_remove.php">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="product_id" value="<?= (int)$it['id'] ?>">
            <button class="px-3 py-1.5 rounded-md border text-sm">제거</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- 합계 & 결제 -->
    <div class="mt-6 border-t pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="text-lg">총 결제 금액: <span class="font-bold text-primary"><?= number_format($total) ?></span> THB</div>
      <div class="flex gap-2">
        <form method="post" action="/cart_clear.php" onsubmit="return confirm('장바구니를 비우시겠습니까?');">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button class="px-4 py-2 rounded-md border">장바구니 비우기</button>
        </form>

        <!-- 결제 진행: 모달 오픈 버튼 -->
        <button type="button" class="px-5 py-2.5 rounded-md bg-primary text-white font-semibold"
                onclick="openPayModal()" <?= $total<=0?'disabled class="opacity-50 cursor-not-allowed"':'' ?>>
          결제 진행하기
        </button>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- QR 결제 모달 -->
<div id="payModal" class="fixed inset-0 hidden items-center justify-center bg-black/40 z-50">
  <div class="bg-white rounded-xl shadow-lg w-[90%] max-w-md p-6">
    <h2 class="text-xl font-bold">QR 결제</h2>
    <p class="text-gray-600 mt-1">아래 QR을 스캔 후, 결제 완료 버튼을 눌러주세요.</p>
    <div class="mt-4 flex justify-center">
      <img src="https://placehold.co/240x240?text=QR" alt="QR" class="rounded border" />
    </div>
    <form class="mt-6" method="post" action="/checkout.php" onsubmit="return confirm('결제를 완료하셨나요? 주문을 생성합니다. (관리자 입금 확인 후 진행됩니다)');">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <button class="w-full px-4 py-2.5 rounded-md bg-green-600 text-white font-semibold">결제 완료</button>
    </form>
    <button class="w-full mt-2 px-4 py-2.5 rounded-md border" onclick="closePayModal()">닫기</button>
  </div>
</div>

<script>
  function openPayModal(){ document.getElementById('payModal').classList.remove('hidden'); document.getElementById('payModal').classList.add('flex'); }
  function closePayModal(){ document.getElementById('payModal').classList.add('hidden'); document.getElementById('payModal').classList.remove('flex'); }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>