<?php
// /partner/index.php — 파트너 대시보드 (복구)
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/guard.php';
require_role('partner');
require_once __DIR__ . '/../auth/csrf.php';

$pdo = db();
$me  = $_SESSION['user'];

// --- DEBUG: ?debug=1 일 때 로그인/카운트 표시 ---
$__DEBUG = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($__DEBUG) {
  try {
    $u = $pdo->prepare("SELECT id,name,email,role FROM users WHERE id = ? LIMIT 1");
    $u->execute([ (int)$me['id'] ]);
    $urow = $u->fetch();

    $cntUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $cntProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $cntOrders   = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    echo '<div style="background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:8px 12px;margin:8px 0;border-radius:8px;">'
       . 'DEBUG · user_id=' . (int)$me['id'] . ' role=' . htmlspecialchars($me['role'] ?? '-', ENT_QUOTES, 'UTF-8')
       . ' · users=' . $cntUsers . ' · products=' . $cntProducts . ' · orders=' . $cntOrders
       . ( $urow ? (' · login_email=' . htmlspecialchars($urow['email'], ENT_QUOTES, 'UTF-8')) : '' )
       . '</div>';
  } catch (Throwable $e) {
    echo '<div style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:8px 12px;margin:8px 0;border-radius:8px;">DEBUG error: '
       . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
  }
}

// 진단 기본값 (빈 목록일 때도 표시되도록)
$diag = [
  'products_by_me' => 0,
  'orders_on_my_products' => 0,
  'orders_by_orders_seller_id' => 0,
  'path' => 'init',
  'partner_id' => (int)($me['id'] ?? 0),
];


$pageTitle = '파트너 대시보드';
include __DIR__ . '/../partials/header_partner.php';
?>
<?php if (isset($_GET['msg']) || isset($_GET['err'])): ?>
  <div class="mb-4">
    <?php if (isset($_GET['msg'])): ?>
      <div class="p-3 rounded bg-green-50 text-green-700 text-sm"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
      <div class="p-3 rounded bg-red-50 text-red-700 text-sm mt-2"><?= htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<section class="mb-8">
  <h1 class="text-2xl font-bold">파트너 대시보드</h1>
  <p class="text-sm text-gray-500 mt-1">내 상품과 판매 내역을 한 곳에서 관리합니다.</p>
  <div class="mt-3 flex flex-wrap gap-2">
    <a href="#products" class="px-3 py-1.5 rounded border bg-white hover:bg-gray-50">내 상품</a>
    <a href="#orders" class="px-3 py-1.5 rounded border bg-white hover:bg-gray-50">판매 내역</a>
    <a href="#settlements" class="px-3 py-1.5 rounded border bg-white hover:bg-gray-50">정산 현황</a>
    <a href="/partner/product_new.php" class="px-3 py-1.5 rounded bg-primary text-white">새 상품 등록</a>
  </div>
</section>

<?php
// 내 상품 목록 (is_deleted=0, debug시 숨김 미적용)
$myProducts = [];
try {
  $whereDeleted = $__DEBUG ? '' : 'AND p.is_deleted = 0';
  $sqlProducts = "SELECT p.id, p.name, p.price, p.status, p.approval_status, p.created_at,
                         (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS primary_image_url
                    FROM products p
                   WHERE p.seller_id = :sid $whereDeleted
                   ORDER BY p.created_at DESC
                   LIMIT 200";
  $ps = $pdo->prepare($sqlProducts);
  $ps->execute([':sid' => (int)$me['id']]);
  $myProducts = $ps->fetchAll() ?: [];

  if ($__DEBUG) {
    // 판매자 기준 카운트 (삭제 포함/제외)
    $c1 = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
    $c1->execute([ (int)$me['id'] ]);
    $c_all = (int)$c1->fetchColumn();

    $c2 = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND is_deleted = 0");
    $c2->execute([ (int)$me['id'] ]);
    $c_visible = (int)$c2->fetchColumn();

    echo '<div style="background:#e2f0ff;border:1px solid #b6daff;color:#004085;padding:8px 12px;margin:8px 0;border-radius:8px;">'
       . 'DEBUG · myProducts count=' . count($myProducts) . ' (all=' . $c_all . ', visible=' . $c_visible . ')</div>';
  }
} catch (Throwable $e) {
  error_log('[partner/index] products query failed: ' . $e->getMessage());
}

// 정산 테이블 보장 (없어도 대시보드가 열리도록)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS partner_settlements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    partner_id INT UNSIGNED NOT NULL,
    amount     DECIMAL(12,2) NOT NULL,
    status     ENUM('requested','approved','paid','rejected') NOT NULL DEFAULT 'requested',
    note       VARCHAR(255) NULL,
    reviewed_by INT UNSIGNED NULL,
    paid_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_partner_order (order_id, partner_id),
    KEY idx_partner (partner_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {
  // 테이블 생성 실패해도 페이지는 계속 렌더 (정산 상태만 비표시)
}

$orders = [];
$pathUsed = 'unified_or';

// 주문 목록 (내 상품 기준) — 통합 OR 조건 (삭제/스키마 차이/권한 이슈 회피)
if ($__DEBUG) {
  try {
    $cOrdByJoin = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN products p ON p.id=o.product_id WHERE p.seller_id=?");
    $cOrdByJoin->execute([ (int)$me['id'] ]);
    $n1 = (int)$cOrdByJoin->fetchColumn();

    $n2 = 0;
    try {
      $cOrdByOrd = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id=?");
      $cOrdByOrd->execute([ (int)$me['id'] ]);
      $n2 = (int)$cOrdByOrd->fetchColumn();
    } catch (Throwable $e) { /* seller_id 없을 수 있음 */ }

    echo '<div style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:8px 12px;margin:8px 0;border-radius:8px;">'
       . 'DEBUG · orders by products.seller_id = ' . $n1 . ' · orders by orders.seller_id = ' . $n2 . '</div>';
  } catch (Throwable $e) {}
}
$orders = [];
$pathUsed = 'unified_or';

try {
  // 내 상품 ID 목록 구성 (빈 배열이어도 동작)
  $myProductIds = array_map(function($row){ return (int)$row['id']; }, $myProducts);
  $myProductIds = array_values(array_filter($myProductIds, function($v){ return $v > 0; }));
  $in = '';
  if (!empty($myProductIds)) {
    $in = implode(',', array_fill(0, count($myProductIds), '?'));
  }

  // LEFT JOIN: 상품이 하드/소프트 삭제여도 주문은 보이게
  $sql = "
    SELECT
      o.`id`             AS order_id,
      o.`status`         AS order_status,
      o.`tracking_number`,
      o.`created_at`     AS order_created_at,
      o.`product_id`,
      COALESCE(p.`name`, CONCAT('삭제된 상품 #', o.`product_id`)) AS product_name,
      COALESCE(p.`price`, 0)  AS product_price,
      ps.`status`        AS settlement_status,
      ps.`amount`        AS settlement_amount,
      (SELECT pi.`image_url` FROM `product_images` pi
        WHERE pi.`product_id` = o.`product_id`
        ORDER BY pi.`is_primary` DESC, pi.`sort_order` ASC, pi.`id` ASC LIMIT 1) AS primary_image_url
    FROM `orders` o
    LEFT JOIN `products` p ON p.`id` = o.`product_id`
    LEFT JOIN `partner_settlements` ps ON ps.`order_id` = o.`id` AND ps.`partner_id` = ?
    WHERE o.`product_id` IN (
      SELECT p2.`id` FROM `products` p2 WHERE p2.`seller_id` = ?
    )
    ORDER BY o.`created_at` DESC
    LIMIT 200";

  // 파라미터: ps.partner_id, seller_id
  $params = [ (int)$me['id'], (int)$me['id'] ];

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $orders = $stmt->fetchAll() ?: [];
  $pathUsed = 'subquery_by_seller_only';
  $diag['path'] = $pathUsed;

  // Fallback 1: 내 상품 ID 기반 OR 조건이 비어 있으면, 서브쿼리로 내 상품(삭제 여부 무관) 전체를 다시 매칭
  if (empty($orders)) {
    $pathUsed = 'fallback_subquery_products_by_seller';
    $sql2 = "
      SELECT
        o.`id`             AS order_id,
        o.`status`         AS order_status,
        o.`tracking_number`,
        o.`created_at`     AS order_created_at,
        o.`product_id`,
        COALESCE(p.`name`, CONCAT('삭제된 상품 #', o.`product_id`)) AS product_name,
        COALESCE(p.`price`, 0)  AS product_price,
        ps.`status`        AS settlement_status,
        ps.`amount`        AS settlement_amount,
        (SELECT pi.`image_url` FROM `product_images` pi WHERE pi.`product_id` = o.`product_id` ORDER BY pi.`is_primary` DESC, pi.`sort_order` ASC, pi.`id` ASC LIMIT 1) AS primary_image_url
      FROM `orders` o
      LEFT JOIN `products` p ON p.`id` = o.`product_id`
      LEFT JOIN `partner_settlements` ps ON ps.`order_id` = o.`id` AND ps.`partner_id` = ?
      WHERE (
        o.`product_id` IN (SELECT p2.`id` FROM `products` p2 WHERE p2.`seller_id` = ?)
        OR (o.`seller_id` IS NOT NULL AND o.`seller_id` = ?)
      )
      ORDER BY o.`created_at` DESC
      LIMIT 200";
    $params2 = [ (int)$me['id'], (int)$me['id'], (int)$me['id'] ];
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($params2);
    $orders = $stmt2->fetchAll() ?: [];
    $diag['path'] = $pathUsed;
  }

  // --- 진단 보조: 판매 내역 카운트 ---
  try {
    $diag['products_by_me'] = count($myProducts); // 상단 섹션과 동일 기준

    // 내 상품에 대한 주문 수 (products ⟂ orders)
    $q2 = $pdo->prepare("SELECT COUNT(*) FROM `orders` o JOIN `products` p ON p.`id` = o.`product_id` WHERE p.`seller_id` = ?");
    $q2->execute([ (int)$me['id'] ]);
    $diag['orders_on_my_products'] = (int)$q2->fetchColumn();

    // orders.seller_id 직접 보유 스키마 카운트 (있을 수도 없음)
    try {
      $q3 = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE `seller_id` = ?");
      $q3->execute([ (int)$me['id'] ]);
      $diag['orders_by_orders_seller_id'] = (int)$q3->fetchColumn();
    } catch (Throwable $e3) {
      $diag['orders_by_orders_seller_id'] = 0;
    }

    $diag['path'] = $pathUsed;
  } catch (Throwable $e) {
    error_log('[partner/index] diag(unified_or) failed: ' . $e->getMessage());
  }

} catch (Throwable $e) {
  error_log('[partner/index] orders(unified_or) failed: ' . $e->getMessage());
  $orders = [];
}

// --- DEBUG: 주문 2차 원본 체크 ---
if ($__DEBUG && empty($orders) && !empty($myProductIds)) {
  try {
    $in3 = implode(',', array_fill(0, count($myProductIds), '?'));
    $q = $pdo->prepare("SELECT id, product_id, status, tracking_number, created_at FROM orders WHERE product_id IN ($in3) ORDER BY created_at DESC LIMIT 5");
    $q->execute($myProductIds);
    $raw = $q->fetchAll();
    echo '<pre style="background:#f7f7f9;border:1px solid #e1e1e8;padding:8px;overflow:auto;">DEBUG raw orders by myProductIds:\n' . htmlspecialchars(print_r($raw, true), ENT_QUOTES, 'UTF-8') . '</pre>';
  } catch (Throwable $e) {
    echo '<div style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:8px 12px;margin:8px 0;border-radius:8px;">DEBUG raw orders error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
  }
}
?>

<a id="products"></a>
<section class="mb-8">
  <h2 class="text-xl font-semibold">내 상품</h2>
  <?php if (empty($myProducts)): ?>
    <div class="bg-white border rounded-xl shadow-sm p-8 text-center text-gray-500 mt-3">등록한 상품이 없습니다. 우측 상단의 <b>새 상품 등록</b>을 눌러 추가하세요.</div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-3">
      <?php foreach ($myProducts as $p):
        $img = $p['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
        $s   = (string)$p['status'];
        $ap  = (string)($p['approval_status'] ?? 'pending');
        $badge = ['label'=>'판매중','cls'=>'bg-emerald-600'];
        if ($s==='negotiating') $badge=['label'=>'구매 진행중','cls'=>'bg-yellow-500'];
        if ($s==='sold')        $badge=['label'=>'판매 완료','cls'=>'bg-red-600'];
        $apBadge = ['label'=>'승인 대기','cls'=>'bg-gray-500'];
        if ($ap==='approved') $apBadge=['label'=>'승인 완료','cls'=>'bg-sky-600'];
        if ($ap==='rejected') $apBadge=['label'=>'거절','cls'=>'bg-rose-600'];
      ?>
        <div class="bg-white border rounded-xl overflow-hidden shadow-sm">
          <div class="relative">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-40 object-cover" alt="thumb">
            <span class="absolute top-2 left-2 text-xs text-white font-semibold px-2.5 py-1 rounded-full <?= $badge['cls'] ?>"><?= $badge['label'] ?></span>
            <span class="absolute top-2 right-2 text-xs text-white font-semibold px-2.5 py-1 rounded-full <?= $apBadge['cls'] ?>"><?= $apBadge['label'] ?></span>
          </div>
          <div class="p-4">
            <div class="font-semibold truncate">#<?= (int)$p['id'] ?> · <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text-sm text-gray-500 mt-1"><?= number_format((float)$p['price']) ?> THB</div>
            <div class="mt-3 flex items-center gap-2">
              <a href="/product.php?id=<?= (int)$p['id'] ?>" class="px-3 py-1.5 rounded border">상세</a>
              <a href="/partner/product_edit.php?id=<?= (int)$p['id'] ?>" class="px-3 py-1.5 rounded border">수정</a>
              <form method="post" action="/partner/product_delete.php" onsubmit="return confirm('이 상품을 숨김 처리(is_deleted=1) 하시겠습니까? 주문/이미지는 보존됩니다.');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                <button class="px-3 py-1.5 rounded bg-rose-600 hover:bg-rose-700 text-white">삭제(숨김)</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<a id="orders"></a>
<h2 class="text-xl font-semibold mb-3">판매 내역</h2>

<?php if (empty($orders)): ?>
  <div class="bg-white border rounded-xl shadow-sm p-10 text-center text-gray-500">
    아직 판매 내역이 없습니다.
    <div class="mt-3 text-xs text-gray-400">
      (진단) 내 상품 수: <?= isset($diag['products_by_me']) ? (int)$diag['products_by_me'] : 0 ?> · 내 상품에 대한 주문 수: <?= isset($diag['orders_on_my_products']) ? (int)$diag['orders_on_my_products'] : 0 ?> · orders.seller_id 주문 수: <?= isset($diag['orders_by_orders_seller_id']) ? (int)$diag['orders_by_orders_seller_id'] : 0 ?> · path: <?= htmlspecialchars($diag['path'] ?? 'n/a', ENT_QUOTES, 'UTF-8') ?>
    </div>
  </div>
<?php else: ?>
  <div class="space-y-4">
    <?php foreach ($orders as $r): 
      $img = $r['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
      $status = (string)$r['order_status'];
      $settle_status = (string)($r['settlement_status'] ?? '');
      $is_requested = ($settle_status !== '' && strtolower($settle_status) !== 'null' && $settle_status !== '0');

      // 상태 정규화
      $norm = strtolower(trim($status));
      $deliveredAliases = ['delivered','배송완료','배송 완료','delivery_complete','completed','complete'];
      $paidAliases      = ['paid','payment_confirmed','입금확인','입금 확인'];

      // A) 배송중 전환 가능? (입금확인 상태에서만)
      $can_ship = in_array($norm, array_map('strtolower', $paidAliases), true);
      // B) 정산 요청 가능? (배송완료 상태이고 아직 정산 미요청)
      $can_request = (in_array($norm, $deliveredAliases, true)) && !$is_requested;

      $productName = (string)$r['product_name'];
      $productPrice = (float)$r['product_price'];
    ?>
      <div class="bg-white border rounded-xl shadow-sm p-4">
        <div class="flex gap-4">
          <div class="w-24 h-24 rounded-lg overflow-hidden border bg-gray-50">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover" alt="thumbnail">
          </div>
          <div class="flex-1">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div class="font-semibold">#<?= (int)$r['order_id'] ?> · <?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="text-primary font-bold"><?= number_format($productPrice) ?> THB</div>
            </div>
            <div class="text-sm text-gray-600 mt-1">상태: <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?> · 송장: <?= htmlspecialchars($r['tracking_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?> · 주문일: <?= htmlspecialchars($r['order_created_at'], ENT_QUOTES, 'UTF-8') ?></div>

            <?php if ($can_ship): ?>
              <form method="post" action="/partner/order_action.php" class="mt-3 flex flex-wrap items-center gap-2" onsubmit="return confirm('송장을 저장하고 상태를 배송중으로 변경할까요?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_shipping">
                <input type="hidden" name="order_id" value="<?= (int)$r['order_id'] ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="text" name="tracking_number" required class="border rounded px-3 py-1.5 text-sm" placeholder="송장번호 입력">
                <button class="px-3 py-1.5 rounded-md bg-gray-900 text-white text-sm">송장 저장/배송중</button>
              </form>
            <?php endif; ?>

            <?php if ($can_request): ?>
              <form method="post" action="/partner/settlement_request.php" class="mt-3 flex flex-wrap items-center gap-2" onsubmit="return confirm('이 주문에 대해 정산을 요청할까요?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_id" value="<?= (int)$r['order_id'] ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="number" step="0.01" name="amount" class="border rounded px-3 py-1.5 text-sm" placeholder="정산 요청 금액(기본: 판매가)" value="<?= htmlspecialchars((string)$productPrice, ENT_QUOTES, 'UTF-8') ?>">
                <button class="px-3 py-1.5 rounded-md bg-gray-900 text-white text-sm">정산 요청</button>
              </form>
            <?php else: ?>
              <div class="mt-3 text-sm">
                정산 상태: <span class="font-semibold"><?= $is_requested ? htmlspecialchars(strtoupper($settle_status), ENT_QUOTES, 'UTF-8') : '미요청' ?></span>
                <?php if ($is_requested && isset($r['settlement_amount']) && $r['settlement_amount'] !== null): ?>
                  · 금액: <span class="font-semibold"><?= number_format((float)$r['settlement_amount']) ?> THB</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>


<hr class="my-10" />
<a id="settlements"></a>
<?php
// 정산 현황: 내 상품(판매자=me)과 연결된 정산 요청 목록
$mySettlements = [];
try {
  $qs = $pdo->prepare("SELECT 
      ps.id, ps.order_id, ps.amount, ps.status, ps.note,
      o.created_at AS requested_at,
      NULL AS paid_at,
      COALESCE(p.name, CONCAT('삭제된 상품 #', o.product_id)) AS product_name,
      COALESCE(p.price, 0) AS product_price,
      (SELECT pi.image_url FROM product_images pi 
         WHERE pi.product_id = o.product_id 
         ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image_url
    FROM partner_settlements ps
    JOIN orders   o ON o.id = ps.order_id
    LEFT JOIN products p ON p.id = o.product_id
    WHERE p.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 200");
  $qs->execute([ (int)$me['id'] ]);
  $mySettlements = $qs->fetchAll() ?: [];
} catch (Throwable $e) {
  error_log('[partner/index] settlements query failed: ' . $e->getMessage());
}
?>

<section class="mb-10">
  <h2 class="text-xl font-semibold">정산 현황</h2>
  <?php if (empty($mySettlements)): ?>
    <div class="bg-white border rounded-xl shadow-sm p-8 text-center text-gray-500 mt-3">아직 정산 요청 내역이 없습니다.</div>
  <?php else: ?>
    <div class="space-y-4 mt-3">
      <?php foreach ($mySettlements as $s):
        $img = $s['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
        $status = strtolower((string)($s['status'] ?? 'requested'));
        $badge = ['label'=>'요청','cls'=>'bg-amber-500'];
        if ($status === 'approved') $badge = ['label'=>'승인','cls'=>'bg-sky-600'];
        if ($status === 'paid')     $badge = ['label'=>'지급완료','cls'=>'bg-emerald-600'];
        if ($status === 'rejected') $badge = ['label'=>'거절','cls'=>'bg-rose-600'];
        $amt = (float)($s['amount'] ?? 0);
      ?>
      <div class="bg-white border rounded-xl shadow-sm p-4">
        <div class="flex gap-4">
          <div class="w-20 h-20 rounded-lg overflow-hidden border bg-gray-50">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover" alt="thumbnail">
          </div>
          <div class="flex-1">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div class="font-semibold">정산 #<?= (int)$s['id'] ?> · 주문 #<?= (int)$s['order_id'] ?> · <?= htmlspecialchars($s['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="text-primary font-bold"><?= number_format($amt, 2) ?> THB</div>
            </div>
            <div class="mt-1 text-sm text-gray-600">
              정산상태: <span class="inline-block align-middle text-white text-xs font-semibold px-2.5 py-0.5 rounded <?= $badge['cls'] ?>"><?= $badge['label'] ?></span>
              · 요청일: <?= htmlspecialchars($s['requested_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
              <?php if (!empty($s['paid_at'])): ?> · 지급일: <?= htmlspecialchars($s['paid_at'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
            </div>
            <?php if (!empty($s['note'])): ?>
              <div class="mt-1 text-xs text-gray-500">메모: <?= htmlspecialchars($s['note'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../partials/footer.php'; ?>