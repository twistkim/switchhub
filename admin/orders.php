<?php
// /admin/orders.php
$pageTitle = '관리자 대시보드 - 주문 관리';
$activeMenu = 'orders';

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../i18n/bootstrap.php';

require_role('admin');

$pdo = db();

/* ---------------------------
   1) 파라미터 수집/정규화
--------------------------- */
$q         = trim($_GET['q'] ?? '');                            // 키워드: 주문번호/상품명/구매자/판매자/이메일
$status    = $_GET['status'] ?? '';                             // awaiting_payment|payment_confirmed|shipping|delivered|''
$tracking  = $_GET['tracking'] ?? '';                           // yes|no|''
$dateFrom  = trim($_GET['date_from'] ?? '');                    // YYYY-MM-DD
$dateTo    = trim($_GET['date_to'] ?? '');                      // YYYY-MM-DD
$perPage   = max(5, min(100, (int)($_GET['per'] ?? 20)));       // 5~100
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $perPage;

/* ---------------------------
   2) 동적 WHERE 구성
--------------------------- */
$where = [];
$params = [];

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

// 키워드: 주문번호 / 상품명 / 구매자명/이메일 / 판매자명/이메일
if ($q !== '') {
  if (ctype_digit($q)) {
    // 숫자면 주문번호 우선 매칭
    $where[] = "(o.id = :qid OR p.name LIKE :q OR bu.name LIKE :q OR bu.email LIKE :q OR su.name LIKE :q OR su.email LIKE :q)";
    $params[':qid'] = (int)$q;
    $params[':q']   = '%'.$q.'%';
  } else {
    $where[] = "(p.name LIKE :q OR bu.name LIKE :q OR bu.email LIKE :q OR su.name LIKE :q OR su.email LIKE :q)";
    $params[':q']   = '%'.$q.'%';
  }
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* ---------------------------
   3) 총 개수/페이지 계산
--------------------------- */
$countSql = "
  SELECT COUNT(*)
  FROM orders o
  JOIN users bu ON bu.id = o.user_id
  JOIN products p ON p.id = o.product_id
  JOIN users su ON su.id = p.seller_id
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
    o.id, o.status, o.tracking_number, o.created_at, o.updated_at,
    bu.name AS buyer_name, bu.email AS buyer_email,
    su.name AS seller_name, su.email AS seller_email,
    p.name AS product_name, p.price,
    (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image_url
  FROM orders o
  JOIN users bu ON bu.id = o.user_id
  JOIN products p ON p.id = o.product_id
  JOIN users su ON su.id = p.seller_id
  $whereSql
  ORDER BY o.created_at DESC
  LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($listSql);
// 바인딩 (LIMIT/OFFSET는 정수 바인딩)
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

include __DIR__ . '/../partials/header_admin.php';
?>

<section class="mb-6">
  <h1 class="text-2xl font-bold">주문 관리</h1>
  <p class="text-gray-600 mt-2">검색/필터로 주문을 조회하고 상태를 관리합니다.</p>
</section>

<!-- 검색/필터 폼 -->
<form method="get" action="/admin/orders.php" class="bg-white border rounded-xl shadow-sm p-4 mb-6">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div>
      <label class="block text-xs text-gray-600 mb-1">키워드</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="주문번호/상품/구매자/판매자/이메일"
             class="w-full border rounded-md px-3 py-2">
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">상태</label>
      <select name="status" class="w-full border rounded-md px-3 py-2">
        <option value="">전체</option>
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
      <label class="block text-xs text-gray-600 mb-1">송장</label>
      <select name="tracking" class="w-full border rounded-md px-3 py-2">
        <option value="">전체</option>
        <option value="yes" <?= $tracking==='yes'?'selected':'' ?>>있음</option>
        <option value="no"  <?= $tracking==='no'?'selected':''  ?>>없음</option>
      </select>
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">페이지당</label>
      <select name="per" class="w-full border rounded-md px-3 py-2">
        <?php foreach ([10,20,30,50,100] as $n): ?>
          <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?>개</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">기간(시작)</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" class="w-full border rounded-md px-3 py-2">
    </div>
    <div>
      <label class="block text-xs text-gray-600 mb-1">기간(끝)</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" class="w-full border rounded-md px-3 py-2">
    </div>
    <div class="md:col-span-2 flex items-end gap-2">
      <button class="px-4 py-2 rounded-md bg-primary text-white font-semibold">검색</button>
      <a href="/admin/orders.php" class="px-4 py-2 rounded-md border">초기화</a>
      <!-- (옵션) CSV 다운로드 -->
      <a href="/admin/orders_export.php?<?= http_build_query($_GET) ?>" class="px-4 py-2 rounded-md border">CSV 내보내기</a>
    </div>
  </div>
</form>

<!-- 결과/카드 리스트 -->
<section class="space-y-4">
  <div class="text-sm text-gray-600">총 <span class="font-semibold"><?= number_format($total) ?></span>건 · <?= $page ?>/<?= $totalPages ?> 페이지</div>

  <?php if (empty($rows)): ?>
    <div class="bg-white border rounded-xl shadow-sm p-8 text-gray-500 text-center">검색 조건에 해당하는 주문이 없습니다.</div>
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
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover">
          </div>
          <div class="flex-1">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <h3 class="text-lg font-semibold"><?= htmlspecialchars($o['product_name'], ENT_QUOTES, 'UTF-8') ?></h3>
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                <?= $o['status']==='delivered'?'bg-green-100 text-green-700':($o['status']==='shipping'?'bg-blue-100 text-blue-700':($o['status']==='payment_confirmed'?'bg-yellow-100 text-yellow-700':'bg-gray-100 text-gray-700')) ?>">
                <?= $statusLabel ?>
              </span>
            </div>

            <div class="mt-2 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
              <div><span class="text-gray-500">주문번호</span> <span class="font-medium">#<?= (int)$o['id'] ?></span></div>
              <div><span class="text-gray-500">구매자</span> <span class="font-medium"><?= htmlspecialchars($o['buyer_name'].' ('.$o['buyer_email'].')', ENT_QUOTES, 'UTF-8') ?></span></div>
              <div><span class="text-gray-500">판매자</span> <span class="font-medium"><?= htmlspecialchars($o['seller_name'].' ('.$o['seller_email'].')', ENT_QUOTES, 'UTF-8') ?></span></div>
              <div><span class="text-gray-500">주문일</span> <span class="font-medium"><?= htmlspecialchars($o['created_at'], ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>

            <div class="mt-2 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
              <div><span class="text-gray-500">가격</span> <span class="font-medium"><?= number_format((float)$o['price']) ?> THB</span></div>
              <div class="md:col-span-2"><span class="text-gray-500">송장</span> <span class="font-medium"><?= htmlspecialchars($o['tracking_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></div>
              <div><a class="text-primary hover:underline" href="/admin/index.php?tab=issues#order-<?= (int)$o['id'] ?>">하자신청 보기</a></div>
            </div>

            <!-- 빠른 상태 변경 액션 (옵션) -->
            <div class="mt-3 flex flex-wrap gap-2">
              <?php if ($o['status'] === 'awaiting_payment'): ?>
                <form method="post" action="/admin/order_action.php">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="action" value="confirm_payment">
                  <button class="px-3 py-1.5 rounded-md bg-yellow-600 text-white text-xs">입금 확인</button>
                </form>
              <?php endif; ?>

              <?php if (in_array($o['status'], ['payment_confirmed','shipping'])): ?>
                <form method="post" action="/admin/order_action.php" class="flex items-center gap-2">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="action" value="set_tracking">
                  <input type="text" name="tracking_number" value="<?= htmlspecialchars($o['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                         placeholder="송장번호" class="px-3 py-1.5 border rounded-md text-xs">
                  <button class="px-3 py-1.5 rounded-md bg-blue-600 text-white text-xs">배송중 처리</button>
                </form>
              <?php endif; ?>

              <?php if ($o['status'] === 'shipping'): ?>
                <form method="post" action="/admin/order_action.php" onsubmit="return confirm('배송 완료 처리하시겠습니까?');">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="action" value="mark_delivered">
                  <button class="px-3 py-1.5 rounded-md bg-green-600 text-white text-xs">배송 완료</button>
                </form>
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
  // 페이지 버튼 구성 (현재 페이지 중심 5~7개)
  $window = 2; // 좌우 2페이지
  $start = max(1, $page - $window);
  $end   = min($totalPages, $page + $window);
  if ($end - $start < $window*2) {
    // 좌우 균형 맞추기
    $start = max(1, min($start, $totalPages - $window*2));
    $end   = min($totalPages, max($end, 1 + $window*2));
  }

  // 공통 쿼리스트링 (page만 바꿔 끼움)
  $qsBase = $_GET;
  unset($qsBase['page']);
?>
<nav class="mt-6 flex justify-center">
  <ul class="inline-flex items-center gap-1">
    <?php
      $first = 1;
      $prev  = max(1, $page - 1);
      $next  = min($totalPages, $page + 1);
      $last  = $totalPages;
      $mk = function($p) use($qsBase) {
        return '/admin/orders.php?' . http_build_query(array_merge($qsBase, ['page'=>$p]));
      };
    ?>
    <li><a class="px-3 py-1.5 border rounded <?= $page==1?'pointer-events-none opacity-50':'' ?>" href="<?= $mk($first) ?>">« 처음</a></li>
    <li><a class="px-3 py-1.5 border rounded <?= $page==1?'pointer-events-none opacity-50':'' ?>" href="<?= $mk($prev) ?>">‹ 이전</a></li>

    <?php for ($p=$start; $p<=$end; $p++): ?>
      <li>
        <a class="px-3 py-1.5 border rounded <?= $p==$page?'bg-primary text-white border-primary':'' ?>" href="<?= $mk($p) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>

    <li><a class="px-3 py-1.5 border rounded <?= $page==$totalPages?'pointer-events-none opacity-50':'' ?>" href="<?= $mk($next) ?>">다음 ›</a></li>
    <li><a class="px-3 py-1.5 border rounded <?= $page==$totalPages?'pointer-events-none opacity-50':'' ?>" href="<?= $mk($last) ?>">끝 »</a></li>
  </ul>
</nav>

<?php include __DIR__ . '/../partials/footer.php'; ?>