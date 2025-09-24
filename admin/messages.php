<?php
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../i18n/bootstrap.php';

require_once __DIR__ . '/../lib/messages.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');

$pdo = db();
$me  = $_SESSION['user'];

// 모든 스레드 조회 (관리자는 전체)
$st = $pdo->query("
  SELECT t.id, t.subject, t.created_at, MAX(m.created_at) AS last_msg_at,
         (SELECT body FROM messages WHERE thread_id=t.id ORDER BY id DESC LIMIT 1) AS last_body
  FROM message_threads t
  JOIN messages m ON m.thread_id = t.id
  GROUP BY t.id, t.subject, t.created_at
  ORDER BY last_msg_at DESC
");
$threads = $st->fetchAll();
?>
<?php include __DIR__ . '/../partials/header.php'; ?>

<h1 class="text-2xl font-bold mb-6"><?= __('messages.inbox') ?> (Admin)</h1>

<?php if (!$threads): ?>
  <p class="text-gray-500"><?= __('messages.empty') ?></p>
<?php else: ?>
  <div class="bg-white shadow rounded-lg divide-y">
    <?php foreach ($threads as $t): ?>
      <a href="<?= htmlspecialchars(lang_url('/admin/message_view.php', APP_LANG, ['thread_id' => $t['id']])) ?>"
         class="block p-4 hover:bg-gray-50">
        <div class="flex justify-between items-center">
          <span class="font-semibold"><?= htmlspecialchars($t['subject'] ?: 'No Subject') ?></span>
          <span class="text-sm text-gray-500"><?= htmlspecialchars($t['last_msg_at']) ?></span>
        </div>
        <p class="text-sm text-gray-600 mt-1 truncate">
          <?= htmlspecialchars($t['last_body']) ?>
        </p>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>