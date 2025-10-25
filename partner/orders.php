<?php
// /partner/orders.php
$pageTitle  = '파트너 대시보드 - 주문 관리';
$activeMenu = 'orders';

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../partials/payment_method_badge.php';

require_role('partner');

$pdo = db();
$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
  header('Location: /auth/login.php');
  exit;
}

/* ---------------------------
   1) 파라미터 수집/정규화
--------------------------- */
$q         = trim($_GET['q'] ?? '');                      // 키워드: 주문번호/상품명/구매자/이메일
$status    = $_GET['status'] ?? '';                       // awaiting_payment|payment_confirmed|shipping|delivered|''
$tracking  = $_GET['tracking'] ?? '';                     // yes|no|''
$dateFrom  = trim($_GET['date_from'] ?? '');              // YYYY-MM-DD
$dateTo    = trim($_GET['date_to'] ?? '');                // YYYY-MM-DD
$perPage   = max(5, min(100, (int)($_GET['per'] ?? 20))); // 5~100
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $perPage;

/* ---------------------------
   2) 동적 WHERE (항상 내 상품만)
--------------------------- */
$where  = ["p.seller_id = :seller_id"];
$params = [':seller_id' => $uid];

// 상태 필터
$validStatus = ['awaiting_payment','payment_confirmed','shipping','delivered'];
if ($status !== '' && in_array($status, $validStatus, true)) {
  $where[] = "o.status = :status";
  $params[':status'] = $status;
}

// 송장 유무
if ($tracking === 'yes') {
  $where[] = "o.tracking_number IS NOT NULL AND o.tracking_number <> ''";
} elseif ($tracking === 'no') {
  $where[] = "(o.tracking_number IS NULL OR o.tracking_number = '')";
}

// 기간 필터 (주문일)
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
  $where[] = "o.created_at >= :dateFrom";
  $params[':dateFrom'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
  $where[] = "o.created_at <= :dateTo";
  $params[':dateTo'] = $dateTo . ' 23:59:59';
}

// 키워드: 주문번호 / 상품명 / 구매자명/이메일
if ($q !== '') {
  if (ctype_digit($q)) {
    $where[] = "(o.id = :qid OR p.name LIKE :q OR bu.name LIKE :q OR bu.email LIKE :q)";
    $params[':qid'] = (int)$q;
    $params[':q']   = '%'.$q.'%';
  } else {
    $where[] = "(p.name LIKE :q OR bu.name LIKE :q OR bu.email LIKE :q)";
    $params[':q'] = '%'.$q.'%';
  }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ---------------------------
   3) 총 개수/페이지 계산
--------------------------- */
$countSql = "
  SELECT COUNT(*)
  FROM orders o
  JOIN products p ON p.id = o.product_id
  JOIN users bu   ON bu.id = o.user_id
  $whereSql
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

/* ---------------------------
   4) 목록 조회 (페이지네이션)
--------------------------- */
$listSql = "
  SELECT
    o.id, o.status, o.payment_method, o.tracking_number, o.created_at, o.updated_at,
    bu.name AS buyer_name, bu.email AS buyer_email,
    p.name AS product_name, p.price,
    (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=p.id
      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image_url
  FROM orders o
  JOIN products p ON p.id = o.product_id
  JOIN users bu   ON bu.id = o.user_id
  $whereSql
  ORDER BY o.created_at DESC
  LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

include __DIR__ . '/../partials/header_partner.php';
?>

<section class="mb-6">
  <h1 class="text-2xl font-bold">
    <?= __('partner_orders.1') ?: '주문 관리' ?>
  </h1>
  <p class="text-gray-600 mt-2">
    <?= __('partner_orders.2') ?: '내가 판매한 상품의 주문을 조회하고 송장/배송 상태를 관리합니다.' ?>
  </p>
</section>

<!-- 검색/필터 폼 -->
<form method="get" action="/partner/orders.php" class="bg-white border rounded-xl shadow-sm p-4 mb-6">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        <?= __('partner_orders.3') ?: '키워드' ?>
      </label>
      <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
             placeholder="주문번호/상품/구매자/이메일" class="w-full border rounded-md px-3 py-2">
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        <?= __('partner_orders.4') ?: '상태' ?>
      </label>
      <select name="status" class="w-full border rounded-md px-3 py-2">
        <option value="">
          <?= __('partner_orders.5') ?: '전체' ?>
        </option>
        <?php
          $opts = [
            'awaiting_payment'=>'입금 대기중',
            'payment_confirmed'=>'입금 확인',
            'shipping'=>'배송중',
            'delivered'=>'배송 완료'
          ];
          foreach ($opts as $k=>$v):
        ?>
          <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        <?= __('partner_orders.6') ?: '송장' ?>
      </label>
      <select name="tracking" class="w-full border rounded-md px-3 py-2">
        <option value=""><?= __('partner_orders.7') ?: '전체' ?></option>
        <option value="yes" <?= $tracking==='yes'?'selected':'' ?>><?= __('partner_orders.8') ?: '있음' ?></option>
        <option value="no"  <?= $tracking==='no'?'selected':''  ?>><?= __('partner_orders.9') ?: '없음' ?></option>
      </select>
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        <?= __('partner_orders.10') ?: '페이지당' ?>
      </label>
      <select name="per" class="w-full border rounded-md px-3 py-2">
        <?php foreach ([10,20,30,50,100] as $n): ?>
          <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?><?= __('partner_orders.11') ?: '개' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        <?= __('partner_orders.12') ?: '기간(시작)' ?>
      </label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" class="w-full border rounded-md px-3 py-2">
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">
        <?= __('partner_orders.13') ?: '기간(끝)' ?>
      </label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" class="w-full border rounded-md px-3 py-2">
    </div>
    <div class="md:col-span-2 flex items-end gap-2">
      <button class="px-4 py-2 rounded-md bg-primary text-white font-semibold">
        <?= __('partner_orders.14') ?: '검색' ?>
      </button>
      <a href="/partner/orders.php" class="px-4 py-2 rounded-md border">
        <?= __('partner_orders.15') ?: '초기화' ?>
      </a>
    </div>
  </div>
</form>

<!-- 결과/카드 리스트 -->
<section class="space-y-4">
  <div class="text-sm text-gray-600">
    총 <span class="font-semibold"><?= number_format($total) ?></span>건 · <?= $page ?>/<?= $totalPages ?> <?= __('partner_orders.17') ?: '페이지' ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="bg-white border rounded-xl shadow-sm p-8 text-gray-500 text-center">
    <?= __('partner_orders.16') ?: '검색 조건에 해당하는 주문이 없습니다.' ?>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $o):
      $img = $o['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
      $statusLabel = [
        'awaiting_payment'=>'입금 대기중',
        'payment_confirmed'=>'입금 확인',
        'shipping'=>'배송중',
        'delivered'=>'배송 완료'
      ][$o['status']] ?? $o['status'];
    ?>
      <div class="bg-white border rounded-xl shadow-sm p-4">
        <div class="flex gap-4">
          <div class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden border bg-gray-50">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover" alt="">
          </div>
          <div class="flex-1">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <h3 class="text-lg font-semibold"><?= htmlspecialchars($o['product_name'], ENT_QUOTES, 'UTF-8') ?></h3>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                  <?= $o['status']==='delivered'?'bg-green-100 text-green-700':($o['status']==='shipping'?'bg-blue-100 text-blue-700':($o['status']==='payment_confirmed'?'bg-yellow-100 text-yellow-700':'bg-gray-100 text-gray-700')) ?>">
                  <?= $statusLabel ?>
                </span>
                <?php if (function_exists('render_payment_method_badge')): ?>
                  <?php render_payment_method_badge($o['payment_method'] ?? null); ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="mt-2 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
              <div><span class="text-gray-500"><?= __('partner_orders.18') ?: '주문번호' ?></span> <span class="font-medium">#<?= (int)$o['id'] ?></span></div>
              <div><span class="text-gray-500"><?= __('partner_orders.19') ?: '구매자' ?></span> <span class="font-medium"><?= htmlspecialchars($o['buyer_name'].' ('.$o['buyer_email'].')', ENT_QUOTES, 'UTF-8') ?></span></div>
              <div><span class="text-gray-500"><?= __('partner_orders.20') ?: '가격' ?></span> <span class="font-medium"><?= number_format((float)$o['price']) ?> THB</span></div>
              <div><span class="text-gray-500"><?= __('partner_orders.21') ?: '주문일' ?></span> <span class="font-medium"><?= htmlspecialchars($o['created_at'], ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>

            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <div><span class="text-gray-500"><?= __('partner_orders.22') ?: '송장' ?></span> <span class="font-medium"><?= htmlspecialchars($o['tracking_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></div>
              <div class="text-gray-500"><?= __('partner_orders.23') ?: '배송완료는 고객이 직접 확인합니다.' ?></div>
            </div>

            <!-- 파트너 액션: 송장 저장/배송중 처리만 허용 -->
            <div class="mt-3 flex flex-wrap gap-2">
              <?php if (in_array($o['status'], ['payment_confirmed','shipping'])): ?>
                <form method="post" action="/partner/order_action.php" class="flex items-center gap-2">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="action" value="set_tracking">
                  <input type="text" name="tracking_number" value="<?= htmlspecialchars($o['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                         placeholder="송장번호" class="px-3 py-1.5 border rounded-md text-xs">
                  <button class="px-3 py-1.5 rounded-md bg-blue-600 text-white text-xs"
                          onclick="return confirm('송장을 저장하고 배송중으로 전환할까요?');">
                          <?= __('partner_orders.24') ?: '송장 저장/배송중' ?>
                  </button>
                </form>
              <?php endif; ?>

              <?php if ($o['status'] === 'awaiting_payment'): ?>
                <span class="text-xs text-gray-500">
                <?= __('partner_orders.25') ?: '입금 확인 후 배송 처리가 가능합니다.' ?></span>
              <?php endif; ?>

              <?php if ($o['status'] === 'delivered'): ?>
                <span class="text-xs text-green-700"><?= __('partner_orders.26') ?: '배송 완료됨 (고객 확인)' ?></span>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<!-- 페이지네이션 -->
<?php
  $window = 2;
  $start = max(1, $page - $window);
  $end   = min($totalPages, $page + $window);
  if ($end - $start < $window*2) {
    $start = max(1, min($start, $totalPages - $window*2));
    $end   = min($totalPages, max($end, 1 + $window*2));
  }
  $qsBase = $_GET; unset($qsBase['page']);
  $mk = function($p) use($qsBase) { return '/partner/orders.php?' . http_build_query(array_merge($qsBase, ['page'=>$p])); };
?>
<nav class="mt-6 flex justify-center">
  <ul class="inline-flex items-center gap-1">
    <li><a class="px-3 py-1.5 border rounded <?= $page==1?'pointer-events-none opacity-50':'' ?>" href="<?= $mk(1) ?>">« 처음</a></li>
    <li><a class="px-3 py-1.5 border rounded <?= $page==1?'pointer-events-none opacity-50':'' ?>" href="<?= $mk(max(1, $page-1)) ?>">‹ 이전</a></li>
    <?php for ($p=$start; $p<=$end; $p++): ?>
      <li><a class="px-3 py-1.5 border rounded <?= $p==$page?'bg-primary text-white border-primary':'' ?>" href="<?= $mk($p) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
    <li><a class="px-3 py-1.5 border rounded <?= $page==$totalPages?'pointer-events-none opacity-50':'' ?>" href="<?= $mk(min($totalPages, $page+1)) ?>">다음 ›</a></li>
    <li><a class="px-3 py-1.5 border rounded <?= $page==$totalPages?'pointer-events-none opacity-50':'' ?>" href="<?= $mk($totalPages) ?>">끝 »</a></li>
  </ul>
</nav>

<?php include __DIR__ . '/../partials/footer.php'; ?>