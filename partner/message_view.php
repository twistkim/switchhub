<?php
// /partner/message_view.php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/messages.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('partner'); // 파트너만 접근

$pdo    = db();
$me     = $_SESSION['user'] ?? null;
$userId = (int)($me['id'] ?? 0);
$role   = strtolower((string)($me['role'] ?? 'partner'));

$threadId = (int)($_GET['thread_id'] ?? 0);
if ($threadId <= 0) {
  header('Location: /partner/messages.php?err=bad_request'); exit;
}

try {
  // 참여자 권한 + 스레드/메시지 로드
  $data   = msgs_get_thread_with_messages($pdo, $threadId, $userId);
  $thread = $data['thread'];
  $msgs   = $data['messages'];

  // 상대방 정보
  $other  = msgs_get_other_user($pdo, $threadId, $userId);

  // 읽음 처리
  msgs_mark_read($pdo, $threadId, $userId);

} catch (Throwable $e) {
  error_log('partner_message_view error: ' . $e->getMessage());
  header('Location: /partner/messages.php?err=' . urlencode($e->getMessage() ?: 'view_failed')); exit;
}

$pageTitle  = __('messages.thread') ?: '대화';
$activeMenu = 'partner';

// 파트너 전용 헤더(없으면 공통 헤더 사용)
if (is_file(__DIR__ . '/../partials/header_partner.php')) {
  include __DIR__ . '/../partials/header_partner.php';
} else {
  include __DIR__ . '/../partials/header.php';
}
?>

<!-- 상단 헤더 -->
<div class="flex items-center justify-between mb-6">
  <div class="min-w-0">
    <div class="text-sm text-gray-500 mb-1">
      <a class="hover:underline" href="/partner/messages.php"><?= __('messages.inbox') ?: '쪽지함' ?></a>
      <span class="mx-1">/</span>
      <span class="text-gray-700"><?= htmlspecialchars($other['name'] ?? __('messages.unknown_user') ?: '상대방', ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <h1 class="text-2xl font-bold truncate">
      <?= htmlspecialchars($thread['subject'] ?: __('messages.no_subject') ?: '(제목 없음)', ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
      <span><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$thread['updated_at'])), ENT_QUOTES, 'UTF-8') ?></span>
      <?php if (!empty($thread['product_id'])): ?>
        <span class="mx-1">·</span>
        <a class="hover:underline" href="/product.php?id=<?= (int)$thread['product_id'] ?>">#P<?= (int)$thread['product_id'] ?></a>
      <?php endif; ?>
      <?php if (!empty($thread['order_id'])): ?>
        <span class="mx-1">·</span>
        <span>#O<?= (int)$thread['order_id'] ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 타임라인 -->
<div class="bg-white border rounded-lg p-4">
  <?php if (empty($msgs)): ?>
    <div class="text-gray-600 text-sm"><?= __('messages.empty_thread') ?: '메시지가 없습니다.' ?></div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($msgs as $m): ?>
        <?php
          $mine = ((int)$m['sender_id'] === $userId);
          $senderName = $mine ? __('messages.you') ?: '나' : ($m['sender_name'] ?? __('messages.unknown_user') ?: '상대방');
        ?>
        <div class="flex <?= $mine ? 'justify-end' : 'justify-start' ?>">
          <div class="max-w-[80%]">
            <div class="<?= $mine ? 'bg-primary text-white' : 'bg-gray-100 text-gray-800' ?> rounded-2xl px-4 py-2 shadow">
              <div class="text-xs mb-1 opacity-80"><?= htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="whitespace-pre-wrap break-words leading-relaxed">
                <?= nl2br(htmlspecialchars((string)$m['body'], ENT_QUOTES, 'UTF-8')) ?>
              </div>
            </div>
            <div class="text-[11px] text-gray-500 mt-1 <?= $mine ? 'text-right' : '' ?>">
              <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$m['created_at'])), ENT_QUOTES, 'UTF-8') ?>
            </div>

            <?php if ($mine || $role === 'admin'): ?>
              <!-- 내 메시지 소프트 삭제 (선택) -->
              <form method="post" action="/message_action.php" class="mt-1 <?= $mine ? 'text-right' : '' ?>" onsubmit="return confirm('이 메시지를 삭제하시겠어요?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_message">
                <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                <button class="text-xs text-red-600 hover:underline"><?= __('messages.delete_message') ?: '메시지 삭제' ?></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 답장 폼 -->
<div class="mt-4 bg-white border rounded-lg p-4">
  <form method="post" action="/message_send.php" class="space-y-2">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="thread_id" value="<?= (int)$thread['id'] ?>">
    <!-- 현재 화면으로 돌아오기 -->
    <input type="hidden" name="return" value="/partner/message_view.php?thread_id=<?= (int)$thread['id'] ?>">

    <label class="block text-sm font-medium mb-1"><?= __('messages.reply') ?: '답장' ?></label>
    <textarea name="body" required rows="3" class="w-full border rounded px-3 py-2" placeholder="<?= htmlspecialchars(__('messages.message_placeholder') ?: '메시지를 입력하세요', ENT_QUOTES, 'UTF-8') ?>"></textarea>

    <div class="flex items-center justify-between mt-2">
      <a href="/partner/messages.php" class="text-sm text-gray-600 hover:underline"><?= __('common.back') ?: '목록으로' ?></a>
      <button type="submit" class="px-4 py-2 rounded bg-primary text-white hover:bg-primary-dark"><?= __('messages.send') ?: '보내기' ?></button>
    </div>
  </form>
</div>

<?php
// 파트너 전용 푸터(없으면 공통 푸터)
if (is_file(__DIR__ . '/../partials/footer_partner.php')) {
  include __DIR__ . '/../partials/footer_partner.php';
} else {
  include __DIR__ . '/../partials/footer.php';
}
?>