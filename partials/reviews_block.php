<?php
if (isset($_GET['debug']) && $_GET['debug']=='1') { echo "<div style='background:#fee'>RB LOADED</div>"; }
if (isset($_GET['debug']) && $_GET['debug']=='1') { echo "<pre>".htmlspecialchars(print_r($_GET,true),ENT_QUOTES,'UTF-8')."</pre>"; }

// /partials/reviews_block.php  — safe header
if (!function_exists('lang_url')) { require_once __DIR__ . '/../i18n/bootstrap.php'; }
require_once __DIR__ . '/../auth/session.php';  // ✅ 세션 가장 먼저
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/reviews.php';

$pdo = db();

// 입력 파라미터/디버그 플래그
$pid         = isset($productId) ? (int)$productId : (int)($_GET['id'] ?? 0);
 
// 세션 사용자
$me  = $_SESSION['user'] ?? null;
$uid = $me && isset($me['id']) ? (int)$me['id'] : 0;

// 기본 값
$why   = '';
$stats = ['avg'=>null,'count'=>0];
$items = [];

// 데이터 로드 (pid가 있어야만)
if ($pid > 0) {
  $stats = reviews_get_stats($pdo, $pid);
  $items = reviews_get_list($pdo, $pid, 20, 0);
  if (isset($_GET['debug'])) {
    error_log("DBG reviews_block · stats=" . json_encode($stats) . " · items_count=" . count($items));
  }
} else {
  $why = 'no_product_id';
}

// 권한 판정
$can = ['allowed'=>false, 'order_id'=>0];
if ($uid > 0 && $pid > 0) {
  $can = reviews_can_review($pdo, $uid, $pid);
  if (isset($_GET['debug'])) {
    error_log("DBG reviews_block · can=" . json_encode($can));
  }
  if (!$can['allowed'] && !$why) $why = 'no_delivered_order';
} elseif ($uid <= 0 && !$why) {
  $why = 'not_logged_in';
}
// 현재 사용자가 이미 남긴 리뷰(수정 모드 확인)
$mine = ($uid > 0 && $pid > 0) ? reviews_get_user_review($pdo, $uid, $pid) : null;

// 폼 표시 조건: 로그인 + 배송완료 주문 보유 (디버그 강제 표시 지원)
$canRenderForm = ($uid > 0 && !empty($can['allowed']));
if (isset($_GET['debug']) && $_GET['debug']=='1' && isset($_GET['force_review']) && $_GET['force_review']=='1') {
  $canRenderForm = true;
}

// 디버그 로그 (uid/pid/can)
if (isset($_GET['debug']) && $_GET['debug']=='1') {
  error_log("DBG reviews_block · uid={$uid} · pid={$pid} · can=" . json_encode($can));
}

?>
<?php if (isset($_GET['debug']) && $_GET['debug']=='1'): ?>
  <div style="background:#fee;padding:4px;font-size:12px">DEBUG: uid=<?= (int)$uid ?> · pid=<?= (int)$pid ?> · why=<?= htmlspecialchars($why, ENT_QUOTES, 'UTF-8') ?> · can=<?= !empty($can['allowed']) ? '1' : '0' ?></div>
<?php endif; ?>

<section id="reviews" class="mt-10">
  <h2 class="text-xl font-bold mb-3"><?= htmlspecialchars(__('product.reviews') ?: '리뷰', ENT_QUOTES, 'UTF-8') ?></h2>

 
 
  <!-- 평점 요약 -->
  <div class="flex items-center gap-3 mb-4">
    <div class="text-2xl font-extrabold"><?= $stats['avg'] !== null ? number_format($stats['avg'], 1) : '–' ?></div>
    <div class="text-sm text-gray-600">
      <?= (int)$stats['count'] ?> <?= htmlspecialchars(__('product.reviews_count') ?: 'reviews', ENT_QUOTES, 'UTF-8') ?>
    </div>
  </div>

 

  <?php if ($canRenderForm): ?>
    <div class="bg-white border rounded-lg p-4 mb-6">
      <form method="post" action="<?= htmlspecialchars(lang_url('/reviews/save.php'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="product_id" value="<?= (int)$pid ?>">
        <input type="hidden" name="order_id" value="<?= (int)($can['order_id'] ?? 0) ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars(lang_url('/product.php', APP_LANG, ['id'=>$pid]), ENT_QUOTES, 'UTF-8') ?>">

        <div class="flex items-center gap-3 mb-3">
          <label class="text-sm text-gray-700"><?= htmlspecialchars(__('product.your_rating') ?: '내 별점', ENT_QUOTES, 'UTF-8') ?></label>
          <select name="rating" class="px-3 py-2 border rounded">
            <?php
              $cur = $mine ? (int)$mine['rating'] : 5;
              for ($i=5; $i>=1; $i--) {
                $sel = $cur===$i ? 'selected' : '';
                echo "<option value=\"$i\" $sel>$i ★</option>";
              }
            ?>
          </select>
        </div>

        <textarea name="body" rows="3" class="w-full border rounded p-3"
          placeholder="<?= htmlspecialchars(__('product.review_placeholder') ?: '구매 후기를 남겨주세요.', ENT_QUOTES, 'UTF-8') ?>"><?= $mine ? htmlspecialchars($mine['body'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>

        <div class="mt-3 flex justify-end">
            <?php
                $label = $mine
                ? (__('product.review_update') ?: '리뷰 수정')
                : (__('product.review_submit') ?: '리뷰 등록');
            ?>
            <button class="px-4 py-2 rounded bg-primary text-white hover:opacity-90">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <!-- 안내 문구 (권한/상태에 따라 다름) -->
    <?php if ($uid <= 0): ?>
      <div class="text-sm text-gray-600 mb-6"><?= htmlspecialchars(__('product.login_to_review') ?: '리뷰를 쓰려면 로그인하세요.', ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
      <div class="text-sm text-gray-600 mb-6">
        <?= htmlspecialchars(__('product.only_after_delivery') ?: '배송 완료된 주문만 리뷰를 작성할 수 있습니다.', ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- 리뷰 리스트 -->
  <div class="space-y-3">
    <?php if (empty($items)): ?>
      <div class="text-gray-500"><?= htmlspecialchars(__('product.no_reviews') ?: '아직 리뷰가 없습니다.', ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: foreach ($items as $r): ?>
      <div class="bg-white border rounded p-4">
        <div class="flex items-center justify-between">
          <div class="font-semibold"><?= htmlspecialchars($r['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></div>
          <div class="text-sm text-yellow-600"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']) ?></div>
        </div>
        <?php if (!empty($r['body'])): ?>
          <div class="mt-2 text-sm text-gray-800" style="white-space:pre-line"><?= htmlspecialchars($r['body'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="mt-2 text-xs text-gray-500"><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</section>