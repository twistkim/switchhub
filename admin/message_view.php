<?php
// /admin/message_view.php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../i18n/bootstrap.php';

require_once __DIR__ . '/../lib/messages.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin'); // 관리자만 접근

$pdo    = db();
$me     = $_SESSION['user'] ?? null;
$userId = (int)($me['id'] ?? 0);

$threadId = (int)($_GET['thread_id'] ?? 0);
if ($threadId <= 0) {
  header('Location: ' . lang_url('/admin/messages.php', APP_LANG, ['err'=>'bad_request'])); exit;
}

try {
  // 관리자: 모든 스레드 접근 가능. messages.php에서 쓰던 공용 헬퍼 재사용
  $data   = msgs_get_thread_with_messages($pdo, $threadId, $userId, /*allowAdminOverride*/true);
  $thread = $data['thread'];
  $msgs   = $data['messages'];

  // 참여자 목록(상단 표시용)
  $participants = msgs_get_participants($pdo, $threadId);

  // 읽음 처리 (관리자도 읽음 타임스탬프 갱신)
  msgs_mark_read($pdo, $threadId, $userId);

} catch (Throwable $e) {
  error_log('admin_message_view error: ' . $e->getMessage());
  header('Location: ' . lang_url('/admin/messages.php', APP_LANG, ['err'=>'view_failed'])); exit;
}

$pageTitle  = __('messages.thread') ?: 'Conversation';
$activeMenu = 'admin';
include __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb / Header -->
<div class="flex items-center justify-between mb-6">
  <div class="min-w-0">
    <div class="text-sm text-gray-500 mb-1">
      <a class="hover:underline" href="<?= htmlspecialchars(lang_url('/admin/messages.php'), ENT_QUOTES, 'UTF-8') ?>">
        <?= __('messages.inbox') ?: 'Inbox' ?>
      </a>
      <span class="mx-1">/</span>
      <span class="text-gray-700">#<?= (int)$thread['id'] ?></span>
    </div>
    <h1 class="text-2xl font-bold truncate">
      <?= htmlspecialchars($thread['subject'] ?: __('messages.no_subject') ?: '(No subject)', ENT_QUOTES, 'UTF-8') ?>
    </h1>

    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
      <span><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$thread['updated_at'])), ENT_QUOTES, 'UTF-8') ?></span>
      <?php if (!empty($thread['product_id'])): ?>
        <span class="mx-1">·</span>
        <a class="hover:underline" href="<?= htmlspecialchars(lang_url('/product.php', APP_LANG, ['id'=>(int)$thread['product_id']])) ?>">#P<?= (int)$thread['product_id'] ?></a>
      <?php endif; ?>
      <?php if (!empty($thread['order_id'])): ?>
        <span class="mx-1">·</span>
        <span>#O<?= (int)$thread['order_id'] ?></span>
      <?php endif; ?>

      <?php if (!empty($participants)): ?>
        <span class="mx-1">·</span>
        <span>
          <?= __('messages.last_message_at') ?: 'Last message at' ?>:
          <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$thread['updated_at'])), ENT_QUOTES, 'UTF-8') ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Participants (admin only, small) -->
<?php if (!empty($participants)): ?>
  <div class="mb-4 text-sm text-gray-700">
    <span class="font-medium mr-2"><?= __('common.participants') ?: 'Participants' ?>:</span>
    <?php foreach ($participants as $idx => $u): ?>
      <span class="inline-flex items-center gap-1 mr-2">
        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800">
          <?= htmlspecialchars($u['name'] . ' ('.$u['role'].')', ENT_QUOTES, 'UTF-8') ?>
        </span>
      </span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Timeline -->
<div class="bg-white border rounded-lg p-4">
  <?php if (empty($msgs)): ?>
    <div class="text-gray-600 text-sm"><?= __('messages.empty_thread') ?: 'No messages yet.' ?></div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($msgs as $m): ?>
        <?php
          $mine = ((int)$m['sender_id'] === $userId);
          $senderName = $m['sender_name'] ?? 'User#' . (int)$m['sender_id'];
        ?>
        <div class="flex <?= $mine ? 'justify-end' : 'justify-start' ?>">
          <div class="max-w-[80%]">
            <div class="<?= $mine ? 'bg-primary text-white' : 'bg-gray-100 text-gray-800' ?> rounded-2xl px-4 py-2 shadow">
              <div class="text-xs mb-1 opacity-80">
                <?= htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div class="whitespace-pre-wrap break-words leading-relaxed">
                <?= msgs_render_body((string)$m['body']) ?>
              </div>
            </div>
            <div class="text-[11px] text-gray-500 mt-1 <?= $mine ? 'text-right' : '' ?>">
              <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$m['created_at'])), ENT_QUOTES, 'UTF-8') ?>
            </div>

            <!-- 관리자/본인: 메시지 소프트 삭제 -->
            <form method="post" action="<?= htmlspecialchars(lang_url('/message_action.php'), ENT_QUOTES, 'UTF-8') ?>"
                  class="mt-1 <?= $mine ? 'text-right' : '' ?>"
                  onsubmit="return confirm('이 메시지를 삭제하시겠어요?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="delete_message">
              <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
              <button class="text-xs text-red-600 hover:underline"><?= __('messages.delete_message') ?: 'Delete message' ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Reply form -->
<div class="mt-4 bg-white border rounded-lg p-4">
  <form method="post" action="<?= htmlspecialchars(lang_url('/message_send.php'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-2">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="thread_id" value="<?= (int)$thread['id'] ?>">
    <input type="hidden" name="return" value="<?= htmlspecialchars(lang_url('/admin/message_view.php', APP_LANG, ['thread_id' => (int)$thread['id']]), ENT_QUOTES, 'UTF-8') ?>">

    <label class="block text-sm font-medium mb-1"><?= __('messages.reply') ?: 'Reply' ?></label>
    <textarea name="body" required rows="3" class="w-full border rounded px-3 py-2"
              placeholder="<?= htmlspecialchars(__('messages.message_placeholder') ?: 'Type your message...', ENT_QUOTES, 'UTF-8') ?>"></textarea>

    <div class="flex items-center justify-between mt-2">
      <a href="<?= htmlspecialchars(lang_url('/admin/messages.php'), ENT_QUOTES, 'UTF-8') ?>"
         class="text-sm text-gray-600 hover:underline"><?= __('common.back') ?: 'Back' ?></a>
      <button type="submit" class="px-4 py-2 rounded bg-primary text-white hover:bg-primary-dark">
        <?= __('messages.send') ?: 'Send' ?>
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>