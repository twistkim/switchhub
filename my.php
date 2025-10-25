<?php
// my.php - 마이페이지 (주문 내역/상태 업데이트)
$pageTitle  = '마이페이지';
$activeMenu = 'my';

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/auth/csrf.php';

// 로그인 체크
$me = $_SESSION['user'] ?? null;
if (!$me) {
  header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/my.php'));
  exit;
}

$pdo = db();
$err = '';
$msg = '';

// 주문 상태 한글 라벨
function order_status_label($s) {
  return match ($s) {
    'awaiting_payment'   => '입금 대기중',
    'payment_confirmed'  => '입금 확인',
    'shipping'           => '배송중',
    'delivered'          => '배송 완료',
    default              => $s
  };
}

// POST 액션 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['csrf'] ?? null)) {
    $err = '유효하지 않은 요청입니다.';
  } else {
    $action   = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    // 주문 소유/상태 확인
    if ($order_id > 0) {
      $stmt = $pdo->prepare("
        SELECT o.id, o.user_id, o.status, o.product_id
        FROM orders o
        WHERE o.id = :oid AND o.user_id = :uid
        LIMIT 1
      ");
      $stmt->execute([':oid' => $order_id, ':uid' => (int)$me['id']]);
      $own = $stmt->fetch();
      if (!$own) {
        $err = '해당 주문을 찾을 수 없거나 권한이 없습니다.';
      } else {
        if ($action === 'mark_delivered') {
          if ($own['status'] !== 'shipping') {
            $err = '배송중 상태에서만 배송 완료 처리할 수 있습니다.';
          } else {
            try {
              $pdo->beginTransaction();
              // 1) 주문 상태 delivered로
              $upd = $pdo->prepare("UPDATE orders SET status = 'delivered', updated_at = CURRENT_TIMESTAMP WHERE id = :oid");
              $upd->execute([':oid' => $order_id]);

              // 2) 해당 상품도 판매완료로
              if (!empty($own['product_id'])) {
                $pupd = $pdo->prepare("UPDATE products SET status='sold', updated_at = CURRENT_TIMESTAMP WHERE id = :pid");
                $pupd->execute([':pid' => (int)$own['product_id']]);
              }

              $pdo->commit();
              $msg = '주문이 배송 완료로 업데이트되었습니다.';
            } catch (Throwable $e) {
              if ($pdo->inTransaction()) $pdo->rollBack();
              $err = '처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
            }
          }
        } elseif ($action === 'submit_issue') {
          // 간단한 하자신청 저장 (테이블이 없다면 생성)
          $issue_type = trim($_POST['issue_type'] ?? 'defect');
          $detail     = trim($_POST['detail'] ?? '');

          if ($detail === '') {
            $err = '하자 내용(상세 사유)을 입력해주세요.';
          } else {
            // 테이블 보장
            $pdo->exec("
              CREATE TABLE IF NOT EXISTS order_issues (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                issue_type VARCHAR(50) NOT NULL,
                detail TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_issue_order (order_id),
                INDEX idx_issue_user (user_id)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");
            $ins = $pdo->prepare("
              INSERT INTO order_issues (order_id, user_id, issue_type, detail)
              VALUES (:oid, :uid, :type, :detail)
            ");
            $ins->execute([
              ':oid'   => $order_id,
              ':uid'   => (int)$me['id'],
              ':type'  => $issue_type,
              ':detail'=> $detail,
            ]);
            $msg = '하자 신청이 접수되었습니다. 관리자/파트너가 확인 후 연락드립니다.';
          }
        }
      }
    } else {
      $err = '잘못된 주문 ID 입니다.';
    }
  }
}

// 내 주문 목록 조회
$stmt = $pdo->prepare("
  SELECT
    o.id, o.product_id, o.status, o.tracking_number, o.created_at, o.updated_at,
    p.name AS product_name,
    (
      SELECT pi.image_url
      FROM product_images pi
      WHERE pi.product_id = p.id
      ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
      LIMIT 1
    ) AS primary_image_url
  FROM orders o
  JOIN products p ON p.id = o.product_id
  WHERE o.user_id = :uid
  ORDER BY o.created_at DESC
");
$stmt->execute([':uid' => (int)$me['id']]);
$orders = $stmt->fetchAll();

include __DIR__ . '/partials/header.php';
?>

<!-- 사용자 기본 정보 -->
<section class="mb-8">
  <div class="bg-white border rounded-xl shadow-sm p-6">
    <h1 class="text-2xl font-bold"><?= __('my.1') ?: '마이페이지' ?></h1>
    <p class="mt-2 text-gray-600"><?= __('my.2') ?: '안녕하세요,' ?> <span class="font-semibold"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span> <?= __('my.3') ?: '님,' ?></p>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
      <div class="p-4 bg-gray-50 rounded-lg">
        <div class="text-gray-500"><?= __('my.4') ?: '이름' ?></div>
        <div class="font-semibold"><?= htmlspecialchars($me['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="p-4 bg-gray-50 rounded-lg">
        <div class="text-gray-500"><?= __('my.5') ?: '이메일' ?></div>
        <div class="font-semibold"><?= htmlspecialchars($me['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="p-4 bg-gray-50 rounded-lg">
        <div class="text-gray-500"><?= __('my.6') ?: '역할' ?></div>
        <div class="font-semibold">
          <?php
            $r = $me['role'] ?? 'customer';
            echo $r === 'admin' ? '관리자' : ($r === 'partner' ? '파트너' : '고객');
          ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 알림 -->
<?php if ($err || $msg): ?>
  <div class="mb-6">
    <?php if ($err): ?>
      <div class="p-3 rounded bg-red-50 text-red-700"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
      <div class="p-3 rounded bg-green-50 text-green-700"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- 주문 목록 -->
<section>
  <h2 class="text-xl font-bold mb-4"><?= __('my.7') ?: '주문 내역' ?></h2>

  <?php if (empty($orders)): ?>
    <div class="text-gray-500 bg-white border rounded-xl shadow-sm p-8 text-center">
      <?= __('my.8') ?: '아직 주문 내역이 없습니다.' ?>
    </div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($orders as $o): 
        $img  = $o['primary_image_url'] ?: 'https://placehold.co/600x400?text=No+Image';
        $name = htmlspecialchars($o['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $status_kr = order_status_label($o['status']);
        $isShipping = ($o['status'] === 'shipping');
      ?>
      <div class="bg-white border rounded-xl shadow-sm p-4">
        <div class="flex gap-4">
          <div class="w-28 h-28 flex-shrink-0 overflow-hidden rounded-lg border bg-gray-50">
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $name ?>" class="w-full h-full object-cover">
          </div>
          <div class="flex-1">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <h3 class="text-lg font-semibold"><?= $name ?></h3>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                  <?= $o['status']==='delivered' ? 'bg-green-100 text-green-700' :
                      ($o['status']==='shipping' ? 'bg-blue-100 text-blue-700' :
                      ($o['status']==='payment_confirmed' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700')) ?>">
                  <?= $status_kr ?>
                </span>
              </div>
            </div>

            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
              <div>
                <div class="text-gray-500">
                  <?= __('my.9') ?: '주문번호' ?>
                </div>
                <div class="font-medium">#<?= (int)$o['id'] ?>
              </div>
              </div>
              <div>
                <div class="text-gray-500">
                  <?= __('my.10') ?: '주문일' ?>
                </div>
                <div class="font-medium"><?= htmlspecialchars($o['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div>
                <div class="text-gray-500">
                  <?= __('my.11') ?: '송장번호' ?>
                </div>
                <div class="font-medium"><?= htmlspecialchars($o['tracking_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>

            <?php if ($isShipping): ?>
            <div class="mt-4 flex flex-wrap items-center gap-3">
              <!-- 배송 완료 버튼 -->
              <form method="post" action="/my.php" onsubmit="return confirm('해당 주문을 배송 완료 처리하시겠습니까?');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="mark_delivered">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="px-4 py-2 rounded-md bg-primary text-white text-sm font-semibold">
                  <?= __('my.12') ?: '배송 완료 확인' ?>
                </button>
              </form>

              

              <!-- 하자 신청 토글 버튼 -->
              <button type="button" class="px-4 py-2 rounded-md bg-red-50 text-red-700 text-sm font-semibold"
                      onclick="toggleClaim('claim-<?= (int)$o['id'] ?>')">
                      <?= __('my.13') ?: '상품 하자 신청' ?>
              </button>
            </div>

            <!-- 하자 신청 폼 (토글) -->
            <div id="claim-<?= (int)$o['id'] ?>" class="mt-4 hidden">
              <form method="post" action="/my.php" class="border rounded-lg p-4 bg-red-50/30">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="submit_issue">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">
                      <?= __('my.14') ?: '이슈 유형' ?>
                    </label>
                    <select name="issue_type" class="w-full border rounded-md px-3 py-2">
                      <option value="defect"><?= __('my.15') ?: '불량/하자' ?></option>
                      <option value="missing"><?= __('my.16') ?: '구성품 누락' ?></option>
                      <option value="wrong_item"><?= __('my.17') ?: '오배송' ?></option>
                      <option value="other"><?= __('my.18') ?: '기타' ?></option>
                    </select>
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1"><?= __('my.19') ?: '상세 내용' ?></label>
                    <textarea name="detail" rows="3" class="w-full border rounded-md px-3 py-2" placeholder="문제 증상/상세 내용을 입력하세요."></textarea>
                  </div>
                </div>

                <div class="mt-3 flex justify-end">
                  <button class="px-4 py-2 rounded-md bg-red-600 text-white text-sm font-semibold"><?= __('my.20') ?: '하자 신청 제출' ?></button>
                </div>
              </form>
            </div>
            <?php endif; ?>

            <div class="mt-3">
              <a href="/message_start.php?order_id=<?= (int)$o['id'] ?>"
                 class="inline-flex items-center px-4 py-2 rounded-md border border-primary text-primary hover:bg-primary/5 text-sm font-semibold">
                <?= htmlspecialchars(__('messages.contact_about_order') ?: '주문 관련 문의', ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>

            <!-- 리뷰 쓰기 -->
            <?php if ($o['status'] === 'delivered' || str_replace(' ', '', strtolower($o['status'])) === '배송완료'): ?>
              <a href="<?= htmlspecialchars(
                    lang_url('/product.php', APP_LANG, ['id'=>(int)$o['product_id']]).'#reviews',
                    ENT_QUOTES, 'UTF-8'
                  ) ?>"
                class="px-3 py-1.5 rounded bg-amber-600 hover:bg-amber-700 text-white text-sm">
                <?= htmlspecialchars(__('product.write_review') ?: '리뷰 쓰기', ENT_QUOTES, 'UTF-8') ?>
              </a>
            <?php endif; ?>

          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<script>
  function toggleClaim(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
  }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>