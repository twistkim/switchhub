<?php
$pageTitle = '회원가입';
$activeMenu = 'login';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../i18n/bootstrap.php';
require_once __DIR__ . '/csrf.php';
include __DIR__ . '/../partials/header.php';


$err = $_GET['err'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<div class="max-w-md mx-auto bg-white border rounded-xl shadow-sm p-6">
  <h1 class="text-2xl font-bold mb-4">회원가입</h1>
  <?php if ($err): ?>
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($msg): ?>
    <div class="mb-4 p-3 rounded bg-green-50 text-green-700 text-sm"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="/auth/register_process.php" class="space-y-4">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div>
      <label class="block text-sm font-medium mb-1">이름</label>
      <input type="text" name="name" required minlength="2"
             class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">이메일</label>
      <input type="email" name="email" required
             class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">비밀번호</label>
      <input type="password" name="password" required minlength="6"
             class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">비밀번호 확인</label>
      <input type="password" name="password_confirm" required minlength="6"
             class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
    </div>

    <button class="w-full py-2.5 bg-primary text-white rounded-lg font-semibold">회원가입</button>
  </form>

  <div class="mt-4 text-sm text-gray-600">
    이미 계정이 있으신가요? <a href="/auth/login.php" class="text-primary font-semibold">로그인</a>
  </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>