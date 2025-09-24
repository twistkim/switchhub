<?php
$pageTitle = '관리자 대시보드';
// 탭에 따라 헤더 활성 메뉴 설정 (orders 탭은 /admin/orders.php에서 처리)
$tab = $_GET['tab'] ?? 'products';
$activeMenu = ($tab === 'issues') ? 'issues' : (($tab === 'settlements') ? 'settlements' : 'admin');

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

$pdo = db();
include __DIR__ . '/../partials/header_admin.php';
// Flash messages (msg/err)
if (isset($_GET['msg']) || isset($_GET['err'])): ?>
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
  <h1 class="text-2xl font-bold">관리자 대시보드</h1>
  <div class="mt-4 flex flex-wrap gap-2">
    <a href="/admin/orders.php" class="px-4 py-2 rounded-md border bg-white hover:bg-gray-50">주문 관리</a>
    <a href="/admin/index.php?tab=products" class="px-4 py-2 rounded-md border <?= $tab==='products'?'bg-primary text-white border-primary':'bg-white hover:bg-gray-50'?>">상품 목록</a>
    <a href="/admin/index.php?tab=issues" class="px-4 py-2 rounded-md border <?= $tab==='issues'?'bg-primary text-white border-primary':'bg-white hover:bg-gray-50'?>">하자 신청</a>
    <a href="/admin/index.php?tab=settlements" class="px-4 py-2 rounded-md border <?= $tab==='settlements'?'bg-primary text-white border-primary':'bg-white hover:bg-gray-50'?>">정산 요청</a>
    <a href="/admin/partners.php" class="px-4 py-2 rounded-md border bg-white hover:bg-gray-50">파트너 관리</a> 
    <a href="/admin/products_pending.php" class="px-4 py-2 rounded-md border bg-white hover:bg-gray-50">상품 승인</a>
  </div>
</section>

<?php if ($tab === 'orders'): ?>
  <?php
  // 전체 주문 목록 + 핵심 필드
  $sql = "
    SELECT
      o.id, o.user_id, o.product_id, o.status, o.tracking_number, o.created_at, o.updated_at,
      u.name AS buyer_name, u.email AS buyer_email,
      p.name AS product_name, p.price, p.seller_id,
      s.name AS seller_name, s.email AS seller_email,
      (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image_url
    FROM orders o
    JOIN users u ON u.id = o.user_id
    JOIN products p ON p.id = o.product_id
    JOIN users s ON s.id = p.seller_id
    ORDER BY o.created_at DESC
    LIMIT 200
  ";
  $orders = $pdo->query($sql)->fetchAll();
  ?>
  <section class="space-y-4">
    <?php foreach ($orders as $o): 
      $img = $o['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
      $status = $o['status'];
      $status_kr = [
        'awaiting_payment'=>'입금 대기중',
        'payment_confirmed'=>'입금 확인',
        'shipping'=>'배송중',
        'delivered'=>'배송 완료'
      ][$status] ?? $status;
    ?>
    <div class="bg-white border rounded-xl shadow-sm p-4">
      <div class="flex gap-4">
        <div class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden border bg-gray-50">
          <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover" />
        </div>
        <div class="flex-1">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h3 class="text-lg font-semibold"><?= htmlspecialchars($o['product_name'], ENT_QUOTES, 'UTF-8') ?></h3>
            <span class="text-sm inline-flex items-center px-2.5 py-1 rounded-full
              <?= $status==='delivered'?'bg-green-100 text-green-700':($status==='shipping'?'bg-blue-100 text-blue-700':($status==='payment_confirmed'?'bg-yellow-100 text-yellow-700':'bg-gray-100 text-gray-700')) ?>">
              <?= $status_kr ?>
            </span>
          </div>
          <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div><span class="text-gray-500">주문번호</span> <span class="font-medium">#<?= (int)$o['id'] ?></span></div>
            <div><span class="text-gray-500">구매자</span> <span class="font-medium"><?= htmlspecialchars($o['buyer_name'].' ('.$o['buyer_email'].')', ENT_QUOTES, 'UTF-8') ?></span></div>
            <div><span class="text-gray-500">판매자</span> <span class="font-medium"><?= htmlspecialchars($o['seller_name'].' ('.$o['seller_email'].')', ENT_QUOTES, 'UTF-8') ?></span></div>
          </div>

          <!-- 액션: 입금확인, 송장 입력(배송중), 배송 완료 -->
          <div class="mt-3 flex flex-wrap gap-2">
            <?php if ($status === 'awaiting_payment'): ?>
              <form method="post" action="/admin/order_action.php">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <input type="hidden" name="action" value="confirm_payment">
                <button class="px-3 py-1.5 rounded-md bg-yellow-600 text-white text-sm">입금 확인</button>
              </form>
            <?php endif; ?>

            <?php if (in_array($status, ['payment_confirmed','shipping'])): ?>
              <form method="post" action="/admin/order_action.php" class="flex items-center gap-2">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <input type="hidden" name="action" value="set_tracking">
                <input type="text" name="tracking_number" value="<?= htmlspecialchars($o['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="송장번호" class="px-3 py-1.5 border rounded-md text-sm">
                <button class="px-3 py-1.5 rounded-md bg-blue-600 text-white text-sm">배송중 처리</button>
              </form>
            <?php endif; ?>

            <?php if (in_array($status, ['shipping'])): ?>
              <form method="post" action="/admin/order_action.php" onsubmit="return confirm('배송 완료 처리하시겠습니까?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <input type="hidden" name="action" value="mark_delivered">
                <button class="px-3 py-1.5 rounded-md bg-green-600 text-white text-sm">배송 완료</button>
              </form>
            <?php endif; ?>

            <!-- 상품 삭제 (관리자) -->
            <form method="post" action="/admin/product_delete.php"
                  onsubmit="return confirm('이 상품을 삭제할까요? 이미지도 함께 삭제됩니다.\n※ 이미 주문이 있는 상품은 삭제할 수 없습니다.');">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="product_id" value="<?= (int)$o['product_id'] ?>">
              <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
              <button class="px-3 py-1.5 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-sm">상품 삭제</button>
            </form>
          </div>

          <!-- 하자신청 바로가기 -->
          <div class="mt-2 text-sm">
            <a class="text-primary hover:underline" href="/admin/index.php?tab=issues#order-<?= (int)$o['id'] ?>">하자 신청 보기</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </section>
<?php elseif ($tab === 'products'): ?>
  <?php
  // 전체 상품 목록 (최대 300, is_deleted=0만)
  $products = $pdo->query("
    SELECT p.id, p.name, p.price, p.status, p.approval_status, p.created_at, p.seller_id,
           u.name AS seller_name, u.email AS seller_email,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image_url
    FROM products p
    JOIN users u ON u.id = p.seller_id
    WHERE p.is_deleted = 0
    ORDER BY p.created_at DESC
    LIMIT 300
  ")->fetchAll();
  ?>
  <section class="space-y-4">
    <?php if (empty($products)): ?>
      <div class="bg-white border rounded-xl shadow-sm p-8 text-gray-500 text-center">등록된 상품이 없습니다.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($products as $p):
        $img = $p['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
        $status = $p['status'];
        $badge = ['label' => '판매중', 'cls' => 'bg-emerald-600'];
        if ($status==='negotiating') $badge = ['label'=>'구매 진행중','cls'=>'bg-yellow-500'];
        if ($status==='sold')        $badge = ['label'=>'판매 완료','cls'=>'bg-red-600'];
        $approval = $p['approval_status'] ?? 'pending';
        $approvalBadge = ['label'=>'승인 대기','cls'=>'bg-gray-400'];
        if ($approval==='approved') $approvalBadge = ['label'=>'승인 완료','cls'=>'bg-sky-600'];
        if ($approval==='rejected') $approvalBadge = ['label'=>'거절','cls'=>'bg-rose-600'];
      ?>
        <div class="bg-white border rounded-xl overflow-hidden shadow-sm">
          <div class="relative">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-44 object-cover">
            <span class="absolute top-2 left-2 text-xs text-white font-semibold px-2.5 py-1 rounded-full <?= $badge['cls'] ?>"><?= $badge['label'] ?></span>
            <span class="absolute top-2 right-2 text-xs text-white font-semibold px-2.5 py-1 rounded-full <?= $approvalBadge['cls'] ?>"><?= $approvalBadge['label'] ?></span>
          </div>
          <div class="p-4">
            <div class="font-semibold truncate">#<?= (int)$p['id'] ?> · <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text-sm text-gray-500 mt-1"><?= number_format((float)$p['price']) ?> THB</div>
            <div class="text-xs text-gray-500 mt-1">판매자: <?= htmlspecialchars($p['seller_name'].' ('.$p['seller_email'].')', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="mt-3 flex items-center gap-2">
              <a href="/product.php?id=<?= (int)$p['id'] ?>" class="px-3 py-1.5 rounded-md border">상세</a>
              <form method="post" action="/admin/product_delete.php" onsubmit="return confirm('이 상품을 숨김 처리(is_deleted=1) 하시겠습니까? 주문/이미지는 보존됩니다.');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                <button class="px-3 py-1.5 rounded-md bg-rose-600 hover:bg-rose-700 text-white">삭제(숨김)</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php elseif ($tab === 'issues'): ?>
  <?php
  // 주문별 하자 목록
  $issues = $pdo->query("
    SELECT i.id, i.order_id, i.user_id, i.issue_type, i.detail, i.created_at,
           o.status AS order_status,
           u.name AS reporter_name, u.email AS reporter_email,
           p.name AS product_name
    FROM order_issues i
    JOIN orders o ON o.id = i.order_id
    JOIN products p ON p.id = o.product_id
    JOIN users u ON u.id = i.user_id
    ORDER BY i.created_at DESC
    LIMIT 200
  ")->fetchAll();
  ?>
  <section class="space-y-4">
    <?php if (empty($issues)): ?>
      <div class="bg-white border rounded-xl shadow-sm p-8 text-gray-500 text-center">접수된 하자 신청이 없습니다.</div>
    <?php else: ?>
      <?php foreach ($issues as $i): ?>
        <div id="order-<?= (int)$i['order_id'] ?>" class="bg-white border rounded-xl shadow-sm p-4">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-sm text-gray-500">주문번호 #<?= (int)$i['order_id'] ?> · <?= htmlspecialchars($i['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="mt-1 font-semibold"><?= htmlspecialchars($i['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="mt-1 text-sm">
                신청자: <?= htmlspecialchars($i['reporter_name'].' ('.$i['reporter_email'].')', ENT_QUOTES, 'UTF-8') ?>
                · 상태: <span class="inline-block px-2 py-0.5 rounded bg-gray-100"><?= htmlspecialchars($i['order_status'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
            <div><span class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs font-semibold"><?= htmlspecialchars($i['issue_type'], ENT_QUOTES, 'UTF-8') ?></span></div>
          </div>
          <div class="mt-2 text-sm whitespace-pre-wrap bg-red-50/50 p-3 rounded"><?= htmlspecialchars($i['detail'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>

          <!-- 처리 액션(메모 기록 등은 추후 확장) -->
          <form method="post" action="/admin/issue_action.php" class="mt-3 flex flex-wrap items-center gap-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="issue_id" value="<?= (int)$i['id'] ?>">
            <select name="action" class="border rounded px-3 py-1.5 text-sm">
              <option value="note">메모 저장</option>
              <option value="resolve">처리 완료(행정종결)</option>
            </select>
            <input type="text" name="note" class="border rounded px-3 py-1.5 text-sm" placeholder="메모(선택)">
            <button class="px-3 py-1.5 rounded-md bg-gray-900 text-white text-sm">저장</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
<?php elseif ($tab === 'settlements'): ?>
  <?php
  // 정산 요청 목록 + robust 필터링 및 오류 처리
  $st = $_GET['status'] ?? 'all';
  $valid = ['requested','approved','paid','rejected','all'];
  if (!in_array($st, $valid, true)) $st = 'all';
  $where = "WHERE 1=1";
  if ($st !== 'all') {
    // 호환성: 기존 'pending' 값을 'requested'로 간주하여 함께 매칭
    if ($st === 'requested') {
      $where .= " AND (ps.status = 'requested' OR ps.status = 'pending')";
    } else {
      $where .= " AND ps.status = " . $pdo->quote($st);
    }
  }

  $rows = [];
  $sqlSettle = "
    SELECT
      ps.id, ps.order_id, ps.amount, ps.status, ps.note,
      o.created_at AS created_at,  /* 요청일: ps.created_at 없을 때 주문 생성일로 대체 */
      NULL AS paid_at,
      o.status AS order_status, o.tracking_number, o.created_at AS order_created_at,
      p.name AS product_name, p.price AS product_price,
      (SELECT pi.image_url FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS primary_image_url,
      u.id AS partner_id, u.name AS partner_name, u.email AS partner_email,
      pa.bank_name, pa.bank_account, pa.contact_phone
    FROM partner_settlements ps
    JOIN orders   o ON o.id = ps.order_id
    JOIN products p ON p.id = o.product_id
    LEFT JOIN users u ON u.id = p.seller_id
    LEFT JOIN partner_applications pa ON pa.email = u.email
    $where
    ORDER BY o.created_at DESC
    LIMIT 300";

  try {
    $stmt = $pdo->query($sqlSettle);
    $rows = $stmt->fetchAll() ?: [];
  } catch (Throwable $e) {
    echo '<div class="p-3 bg-red-50 text-red-700 rounded text-sm">정산 요청 목록을 불러오는 중 오류가 발생했습니다.<br><b>'
       . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
       . '</b></div>';
    $rows = [];
  }
  ?>

  <section class="mb-6">
    <h2 class="text-xl font-semibold">정산 요청</h2>
    <div class="mt-2 flex gap-2">
      <?php
        $opts = ['requested'=>'요청','approved'=>'승인','paid'=>'지급완료','rejected'=>'거절','all'=>'전체'];
        foreach ($opts as $k=>$label):
          $q = http_build_query(['tab'=>'settlements','status'=>$k]);
      ?>
        <a href="/admin/index.php?<?= $q ?>" class="px-3 py-1.5 rounded border <?= $st===$k?'bg-primary text-white border-primary':'bg-white hover:bg-gray-50' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (empty($rows)): ?>
    <div class="bg-white border rounded-xl shadow-sm p-8 text-center text-gray-500">
      정산 요청이 없습니다.
      <?php if ($st !== 'all'): ?>
        <div class="mt-2 text-xs">
          다른 상태도 보기: <a href="/admin/index.php?tab=settlements&status=all" class="text-primary hover:underline">전체</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($rows as $r): ?>
        <div class="bg-white border rounded-xl shadow-sm p-4">
          <div class="flex gap-4">
            <div class="w-24 h-24 rounded-lg overflow-hidden border bg-gray-50">
              <img src="<?= htmlspecialchars($r['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image', ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover">
            </div>
            <div class="flex-1">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="text-lg font-semibold">#<?= (int)$r['order_id'] ?> · <?= htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-xl font-bold text-primary"><?= number_format((float)($r['amount'] ?? 0), 2) ?> THB</div>
              </div>
              <div class="mt-1 text-sm text-gray-600">
                파트너: <?= htmlspecialchars(($r['partner_name'] ?? '').' ('.($r['partner_email'] ?? '').')', ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($r['bank_name']) || !empty($r['bank_account'])): ?>
                  · 계좌: <?= htmlspecialchars(($r['bank_name'] ?? '').' '.($r['bank_account'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
                <?php if (!empty($r['contact_phone'])): ?>
                  · 연락처: <?= htmlspecialchars($r['contact_phone'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </div>
              <div class="mt-1 text-xs text-gray-500">
                주문상태: <?= htmlspecialchars($r['order_status'] ?? '-', ENT_QUOTES, 'UTF-8') ?> · 송장: <?= htmlspecialchars($r['tracking_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?> · 요청일: <?= htmlspecialchars($r['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
              </div>

              <div class="mt-3 flex flex-wrap gap-2">
                <?php
                  $state = $r['status'] ?? '';
                  if ($state === 'pending') $state = 'requested';
                ?>
                <?php if ($state === 'requested'): ?>
                  <form method="post" action="/admin/settlement_action.php" onsubmit="return confirm('이 정산을 승인할까요?');">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="px-3 py-1.5 rounded bg-emerald-600 text-white text-sm">승인</button>
                  </form>
                  <form method="post" action="/admin/settlement_action.php" onsubmit="return confirm('이 정산을 거절할까요?');">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="text" name="note" placeholder="거절 사유(선택)" class="border rounded px-2 py-1 text-sm">
                    <button class="px-3 py-1.5 rounded bg-rose-600 text-white text-sm">거절</button>
                  </form>
                <?php elseif ($state === 'approved'): ?>
                  <form method="post" action="/admin/settlement_action.php" onsubmit="return confirm('정산 금액을 지급 완료로 표시할까요?');">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="mark_paid">
                    <button class="px-3 py-1.5 rounded bg-indigo-600 text-white text-sm">지급 완료</button>
                  </form>
                <?php else: ?>
                  <span class="inline-flex items-center px-2.5 py-1 rounded text-xs bg-gray-100">
                    상태: <?= htmlspecialchars(strtoupper($state), ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($r['note'])): ?> · 메모: <?= htmlspecialchars($r['note'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                    <?php if (!empty($r['paid_at'])): ?> · 지급일: <?= htmlspecialchars($r['paid_at'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>