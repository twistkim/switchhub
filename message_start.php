<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/messages.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/guard.php';

require_login(); // 반드시 로그인 필요
$pdo = db();
$me  = $_SESSION['user'];

// === 1) POST 요청 처리 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        header('Location: /index.php?err=bad_csrf');
        exit;
    }

    $recipientId = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
    $productId   = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $orderId     = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $subject     = trim($_POST['subject'] ?? '');
    $body        = trim($_POST['body'] ?? '');

    if (!$recipientId && !$productId && !$orderId) {
        header('Location: /index.php?err=no_target');
        exit;
    }

    // 2) product_id / order_id 기반으로 recipient 자동 결정
    if (!$recipientId && $productId) {
        $st = $pdo->prepare("SELECT seller_id FROM products WHERE id=:pid LIMIT 1");
        $st->execute([':pid' => $productId]);
        $row = $st->fetch();
        if (!$row) { header('Location: /index.php?err=product_not_found'); exit; }
        $recipientId = (int)$row['seller_id'];
    }
    if (!$recipientId && $orderId) {
        $st = $pdo->prepare("SELECT p.seller_id
                               FROM orders o
                               JOIN products p ON p.id=o.product_id
                              WHERE o.id=:oid LIMIT 1");
        $st->execute([':oid' => $orderId]);
        $row = $st->fetch();
        if (!$row) { header('Location: /index.php?err=order_not_found'); exit; }
        $recipientId = (int)$row['seller_id'];
    }

    if ($recipientId === (int)$me['id']) {
        header('Location: /index.php?err=cannot_message_self');
        exit;
    }

    try {
        $threadId = msgs_create_thread(
            $pdo,
            (int)$me['id'],
            $recipientId,
            $subject,
            $body,
            $productId ?: null,
            $orderId ?: null
        );
        header('Location: /my_message_view.php?thread_id=' . $threadId);
        exit;
    } catch (Throwable $e) {
        error_log('message_start error: ' . $e->getMessage());
        header('Location: /index.php?err=message_start_failed');
        exit;
    }
}

// === 2) GET 요청 시: 새 쪽지 작성 폼 보여주기 ===
$recipientId = isset($_GET['recipient_id']) ? (int)$_GET['recipient_id'] : null;
$productId   = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$orderId     = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

$pageTitle = '새 쪽지 보내기';
include __DIR__ . '/partials/header.php';
?>

<h1 class="text-2xl font-bold mb-4">새 쪽지 보내기</h1>
<form method="post" class="space-y-4">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($recipientId): ?>
        <input type="hidden" name="recipient_id" value="<?= $recipientId ?>">
    <?php endif; ?>
    <?php if ($productId): ?>
        <input type="hidden" name="product_id" value="<?= $productId ?>">
    <?php endif; ?>
    <?php if ($orderId): ?>
        <input type="hidden" name="order_id" value="<?= $orderId ?>">
    <?php endif; ?>

    <div>
        <label class="block font-medium">제목</label>
        <input type="text" name="subject" class="w-full border rounded px-3 py-2" placeholder="제목을 입력하세요">
    </div>
    <div>
        <label class="block font-medium">메시지</label>
        <textarea name="body" class="w-full border rounded px-3 py-2 h-32" required></textarea>
    </div>
    <div>
        <button type="submit"
            class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">보내기</button>
    </div>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>