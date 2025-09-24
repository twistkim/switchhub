<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /partner_apply.php');
  exit;
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /partner_apply.php?err=' . urlencode('유효하지 않은 요청입니다.'));
  exit;
}

$name        = trim($_POST['business_name'] ?? '');
$regNo       = trim($_POST['business_reg_number'] ?? '');
$contactName = trim($_POST['contact_name'] ?? '');
$contactTel  = trim($_POST['contact_phone'] ?? '');
$bankName    = trim($_POST['bank_name'] ?? '');
$bankAcc     = trim($_POST['bank_account'] ?? '');
$email       = trim($_POST['email'] ?? '');
$address     = trim($_POST['business_address'] ?? '');

if ($name==='' || $regNo==='' || $contactName==='' || $contactTel==='' || $bankName==='' || $bankAcc==='' || $email==='' || $address==='') {
  header('Location: /partner_apply.php?err=' . urlencode('모든 필드를 입력해 주세요.'));
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: /partner_apply.php?err=' . urlencode('이메일 형식이 올바르지 않습니다.'));
  exit;
}

// 파일 업로드 처리
$certPath = null;
if (!empty($_FILES['business_cert']['name'])) {
  $f = $_FILES['business_cert'];
  if ($f['error'] !== UPLOAD_ERR_OK) {
    header('Location: /partner_apply.php?err=' . urlencode('파일 업로드에 실패했습니다.'));
    exit;
  }
  // 용량 5MB 제한
  if ($f['size'] > 5 * 1024 * 1024) {
    header('Location: /partner_apply.php?err=' . urlencode('파일 용량이 5MB를 초과했습니다.'));
    exit;
  }
  // MIME 검사(간단)
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($f['tmp_name']);
  $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
  if (!in_array($mime, $allowed, true)) {
    header('Location: /partner_apply.php?err=' . urlencode('허용되지 않은 파일 형식입니다.'));
    exit;
  }
  // 저장 디렉터리
  $dir = __DIR__ . '/uploads/partners';
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  $ext = pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'bin';
  $filename = 'cert_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
  $dest = $dir . '/' . $filename;
  if (!move_uploaded_file($f['tmp_name'], $dest)) {
    header('Location: /partner_apply.php?err=' . urlencode('파일 저장에 실패했습니다.'));
    exit;
  }
  // 웹 경로
  $certPath = '/uploads/partners/' . $filename;
} else {
  header('Location: /partner_apply.php?err=' . urlencode('사업자등록증 파일을 첨부해 주세요.'));
  exit;
}

// 저장
$pdo = db();
$ins = $pdo->prepare("
  INSERT INTO partner_applications
  (name, email, business_name, business_reg_number, business_address, status,
   contact_name, contact_phone, bank_name, bank_account, business_cert_path, created_at, updated_at)
  VALUES
  (:name, :email, :bname, :breg, :addr, 'pending',
   :cname, :cphone, :bank, :account, :cert, NOW(), NOW())
");
$ins->execute([
  ':name'   => $contactName,  // 신청자 이름 = 담당자 이름
  ':email'  => $email,
  ':bname'  => $name,
  ':breg'   => $regNo,
  ':addr'   => $address,
  ':cname'  => $contactName,
  ':cphone' => $contactTel,
  ':bank'   => $bankName,
  ':account'=> $bankAcc,
  ':cert'   => $certPath,
]);

header('Location: /partner_apply.php?msg=' . urlencode('신청이 접수되었습니다. 검토 후 연락드리겠습니다.'));
exit;