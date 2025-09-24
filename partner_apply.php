<?php
$pageTitle = '파트너 신청';
$activeMenu = 'partner';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/i18n/bootstrap.php';
require_once __DIR__ . '/auth/csrf.php';
include __DIR__ . '/partials/header.php';

$err = $_GET['err'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<div class="max-w-2xl mx-auto bg-white border rounded-xl shadow-sm p-6">
  <h1 class="text-2xl font-bold">파트너 신청</h1>
  <p class="text-gray-600 mt-1">사업자 정보를 입력해 주세요. 관리자가 검토 후 승인합니다.</p>

  <?php if ($err): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($msg): ?>
    <div class="mt-4 p-3 rounded bg-green-50 text-green-700 text-sm"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form class="mt-6 space-y-5" method="post" action="/partner_apply_process.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <!-- 1) 사업자등록증 업로드 -->
    <div>
      <label class="block text-sm font-medium">사업자등록증 (이미지 또는 PDF, 최대 5MB)</label>
      <input type="file" name="business_cert" accept=".pdf,image/*" required
             class="mt-1 w-full border rounded-lg px-3 py-2">
      <p class="text-xs text-gray-500 mt-1">허용: JPG/PNG/PDF</p>
    </div>

    <!-- 2) 상호 / 사업자등록번호 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">상호</label>
        <input type="text" name="business_name" required class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">사업자 등록번호</label>
        <input type="text" name="business_reg_number" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="예: TH-0123456789">
      </div>
    </div>

    <!-- 3) 담당자 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">담당자 이름</label>
        <input type="text" name="contact_name" required class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium">담당자 연락처</label>
        <input type="text" name="contact_phone" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="+66-xxx-xxxx">
      </div>
    </div>

    <!-- 4) 정산 계좌 -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">은행명</label>
        <input type="text" name="bank_name" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="예: Kasikornbank">
      </div>
      <div>
        <label class="block text-sm font-medium">계좌번호</label>
        <input type="text" name="bank_account" required class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>
    </div>

    <!-- 5) 이메일 -->
    <div>
      <label class="block text-sm font-medium">이메일</label>
      <input type="email" name="email" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="partner@example.co.th">
    </div>

    <!-- 6) 사업장 주소 (태국 주소 자동완성 훅: 추후 확장) -->
    <div>
      <label class="block text-sm font-medium">사업장 주소</label>
      <textarea name="business_address" rows="3" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="지번/도로명, 구·군·도, 우편번호 등"></textarea>
      <p class="text-xs text-gray-500 mt-1">※ 추후 태국 주소 자동완성을 연동할 수 있습니다.</p>
    </div>

    <div class="pt-4 flex gap-3">
      <button class="px-5 py-2.5 bg-primary text-white rounded-lg font-semibold">신청 제출</button>
      <a href="/" class="px-5 py-2.5 border rounded-lg">취소</a>
    </div>
  </form>
</div>

<!-- (옵션) 태국 주소 자동완성 훅: 여기서 JS로 postcodes/province 데이터셋 연결 가능 -->
<script>
// TODO: 태국 주소 자동완성 연동시 여기에 스크립트 추가
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>