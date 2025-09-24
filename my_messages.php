<?php
// /my_messages.php
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/messages.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/guard.php';

require_login();

$pdo   = db();
$me    = $_SESSION['user'];
$userId = (int)$me['id'];

// 페이징 & 보기 옵션
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$show   = ($_GET['show'] ?? 'all'); // all|archived|unread

// 목록 조회 쿼리 (참여자만, 최신순) — HY093 방지: 중복 플레이스홀더 금지
$sql = "
  SELECT
    mt.id,
    mt.subject,
    mt.product_id,
    mt.order_id,
    mt.updated_at,
    mp.is_archived AS archived,
    -- 상대방 이름
    (SELECT u.name
       FROM message_participants mp2
       JOIN users u ON u.id = mp2.user_id
      WHERE mp2.thread_id = mt.id
        AND mp2.user_id <> :uid_other
      ORDER BY mp2.id ASC
      LIMIT 1) AS other_name,
    -- 미읽음 여부
    EXISTS(
      SELECT 1
        FROM messages m
        LEFT JOIN message_participants mp3
               ON mp3.thread_id = m.thread_id AND mp3.user_id = :uid_unread
       WHERE m.thread_id = mt.id
         AND m.is_deleted = 0
         AND (mp3.last_read_at IS NULL OR m.created_at > mp3.last_read_at)
    ) AS has_unread,
    -- 최근 메시지 스니펫
    (SELECT m2.body
       FROM messages m2
      WHERE m2.thread_id = mt.id AND m2.is_deleted = 0
      ORDER BY m2.id DESC
      LIMIT 1) AS last_body
  FROM message_threads mt
  JOIN message_participants mp
    ON mp.thread_id = mt.id AND mp.user_id = :uid_main
  WHERE 1
";

$params = [
  ':uid_main'   => $userId,
  ':uid_other'  => $userId,
  ':uid_unread' => $userId,
];

// 보기 필터
if ($show === 'archived') {
  $sql .= " AND mp.is_archived = 1 ";
} elseif ($show === 'unread') {
  $sql .= " AND EXISTS(
              SELECT 1
                FROM messages m
                LEFT JOIN message_participants mp3
                       ON mp3.thread_id = m.thread_id AND mp3.user_id = :uid_unread2
               WHERE m.thread_id = mt.id
                 AND m.is_deleted = 0
                 AND (mp3.last_read_at IS NULL OR m.created_at > mp3.last_read_at)
            ) ";
  $params[':uid_unread2'] = $userId;
} else {
  // all(default)
  // $sql .= " AND mp.is_archived = 0 ";
}

$sql .= " ORDER BY mt.updated_at DESC, mt.id DESC
          LIMIT :lim OFFSET :off";

$st = $pdo->prepare($sql);
$st->bindValue(':uid_main',   $userId, PDO::PARAM_INT);
$st->bindValue(':uid_other',  $userId, PDO::PARAM_INT);
$st->bindValue(':uid_unread', $userId, PDO::PARAM_INT);
if (isset($params[':uid_unread2'])) {
  $st->bindValue(':uid_unread2', $userId, PDO::PARAM_INT);
}
$st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
$st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$st->execute();
$threads = $st->fetchAll() ?: [];


// 스니펫 길이 제한
foreach ($threads as &$t) {
  $body = (string)($t['last_body'] ?? '');
  if (function_exists('mb_strimwidth')) {
    $t['last_body'] = mb_strimwidth($body, 0, 120, '...', 'UTF-8');
  } else {
    $t['last_body'] = substr($body, 0, 120) . (strlen($body) > 120 ? '...' : '');
  }
}

$pageTitle  = __('messages.inbox') ?: '쪽지함';
$activeMenu = 'my';
include __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-between mb-6 gap-3 flex-wrap">
  <h1 class="text-2xl font-bold"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>

  <div class="flex items-center gap-2 ml-auto">
    <div class="flex gap-2">
      <a href="<?= htmlspecialchars(lang_url('/my_messages.php', APP_LANG, ['show'=>'all']), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 rounded border <?= $show==='all'?'bg-gray-900 text-white':'bg-white' ?>">All</a>
      <a href="<?= htmlspecialchars(lang_url('/my_messages.php', APP_LANG, ['show'=>'unread']), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 rounded border <?= $show==='unread'?'bg-gray-900 text-white':'bg-white' ?>"><?= __('messages.unread') ?: '미읽음' ?></a>
      <a href="<?= htmlspecialchars(lang_url('/my_messages.php', APP_LANG, ['show'=>'archived']), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 rounded border <?= $show==='archived'?'bg-gray-900 text-white':'bg-white' ?>"><?= __('messages.archived') ?: '보관됨' ?></a>
    </div>
    <a href="<?= htmlspecialchars(lang_url('/message_start.php'), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 rounded bg-primary text-white hover:bg-primary-dark whitespace-nowrap"><?= __('messages.new') ?: '새 메시지' ?></a>
  </div>
</div>

<?php if (empty($threads)): ?>
  <div class="bg-white border rounded p-6 text-center text-gray-600">
    <?= htmlspecialchars(__('messages.empty') ?: '표시할 대화가 없습니다.', ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($threads as $t): ?>
      <?php
        $threadUrl = lang_url('/my_message_view.php', APP_LANG, ['thread_id' => (int)$t['id']]);
      ?>
      <div class="bg-white border rounded p-4 hover:shadow transition">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <a href="<?= htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8') ?>" class="font-semibold truncate hover:underline">
                <?= htmlspecialchars($t['other_name'] ?: __('messages.unknown_user') ?: '상대방', ENT_QUOTES, 'UTF-8') ?>
              </a>
              <?php if (!empty($t['subject'])): ?>
                <span class="text-gray-400">·</span>
                <span class="text-sm text-gray-700 truncate"><?= htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
              <?php if (!empty($t['product_id'])): ?>
                <span class="ml-2 inline-flex items-center text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700">#P<?= (int)$t['product_id'] ?></span>
              <?php endif; ?>
              <?php if (!empty($t['order_id'])): ?>
                <span class="inline-flex items-center text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700">#O<?= (int)$t['order_id'] ?></span>
              <?php endif; ?>
              <?php if ((int)$t['archived'] === 1): ?>
                <span class="ml-2 inline-flex items-center text-xxs px-2 py-0.5 rounded bg-gray-200 text-gray-500"><?= __('messages.archived') ?: '보관됨' ?></span>
              <?php endif; ?>
              <?php if (!empty($t['has_unread'])): ?>
                <span class="ml-2 inline-flex items-center text-xxs px-2 py-0.5 rounded bg-amber-500 text-white"><?= __('messages.unread') ?: '미읽음' ?></span>
              <?php endif; ?>
            </div>

            <a href="<?= htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8') ?>" class="block text-sm text-gray-600 mt-1 line-clamp-2">
              <?= nl2br(htmlspecialchars((string)$t['last_body'], ENT_QUOTES, 'UTF-8')) ?>
            </a>
          </div>

          <div class="text-right shrink-0">
            <div class="text-xs text-gray-500">
              <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$t['updated_at'])), ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="flex gap-1 justify-end mt-2">
              <!-- 읽음 처리 -->
              <form method="post" action="<?= htmlspecialchars(lang_url('/message_action.php'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                <button class="px-2 py-1 text-xs rounded bg-emerald-600 hover:bg-emerald-700 text-white"><?= __('messages.mark_read') ?: '읽음' ?></button>
              </form>

              <?php if ((int)$t['archived'] === 1): ?>
                <!-- 보관 해제 -->
                <form method="post" action="<?= htmlspecialchars(lang_url('/message_action.php'), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="unarchive">
                  <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                  <button class="px-2 py-1 text-xs rounded bg-gray-200 hover:bg-gray-300 text-gray-800"><?= __('messages.unarchive') ?: '보관 해제' ?></button>
                </form>
              <?php else: ?>
                <!-- 보관 -->
                <form method="post" action="<?= htmlspecialchars(lang_url('/message_action.php'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('이 대화를 보관하시겠어요?');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="archive">
                  <input type="hidden" name="thread_id" value="<?= (int)$t['id'] ?>">
                  <button class="px-2 py-1 text-xs rounded bg-gray-200 hover:bg-gray-300 text-gray-800"><?= __('messages.archive') ?: '보관' ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- (간단) 다음 페이지만 있는 페이징 -->
  <div class="mt-6 flex justify-center gap-2">
    <?php if ($page > 1): ?>
      <a class="px-3 py-1.5 rounded border hover:bg-gray-50" href="<?= htmlspecialchars(lang_url('/my_messages.php', APP_LANG, ['show'=>$show, 'page'=>$page-1]), ENT_QUOTES, 'UTF-8') ?>">‹ <?= __('common.prev') ?: '이전' ?></a>
    <?php endif; ?>
    <?php if (count($threads) === $limit): ?>
      <a class="px-3 py-1.5 rounded border hover:bg-gray-50" href="<?= htmlspecialchars(lang_url('/my_messages.php', APP_LANG, ['show'=>$show, 'page'=>$page+1]), ENT_QUOTES, 'UTF-8') ?>"><?= __('common.next') ?: '다음' ?> ›</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>