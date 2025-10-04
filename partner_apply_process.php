<?php
// /partner_apply_process.php — 파트너 신청 처리 (주소/좌표/소개/대표이미지 포함)
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/session.php';

// 공통 리다이렉트 헬퍼
function back_to_apply(string $qs = ''): void {
  // 기본 이동 경로
  $ref = $_SERVER['HTTP_REFERER'] ?? '/partner_apply.php';

  // referrer를 파싱해서 기존 err/msg 파라미터 제거
  $parts = parse_url($ref);
  $scheme = $parts['scheme'] ?? '';
  $host   = $parts['host']   ?? '';
  $path   = $parts['path']   ?? '/partner_apply.php';

  // 기존 쿼리 파라미터 정리
  $queryArr = [];
  if (!empty($parts['query'])) {
    parse_str($parts['query'], $queryArr);
    unset($queryArr['err'], $queryArr['msg']);
  }

  // 새로 붙일 파라미터 병합
  $extra = [];
  if ($qs !== '') {
    parse_str($qs, $extra);
  }
  $queryArr = array_merge($queryArr, $extra);

  // 최종 URL 구성 (스킴/호스트가 있으면 보존)
  $to = '';
  if ($scheme !== '' && $host !== '') {
    $to = $scheme . '://' . $host . $path;
  } else {
    $to = $path; // 상대 경로
  }
  if (!empty($queryArr)) {
    $to .= '?' . http_build_query($queryArr);
  }

  header('Location: ' . $to);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  back_to_apply();
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
  back_to_apply('err=' . urlencode('유효하지 않은 요청입니다.'));
}

// 로그인 체크 (필요 시 주석 해제)
if (empty($_SESSION['user']['id'])) {
  back_to_apply('err=' . urlencode('로그인이 필요합니다.'));
}

// ----------------------------
// 입력값 수집
// ----------------------------
$business_name       = trim($_POST['business_name'] ?? '');
$business_reg_number = trim($_POST['business_reg_number'] ?? '');
$contact_name        = trim($_POST['contact_name'] ?? '');
$contact_phone       = trim($_POST['contact_phone'] ?? '');
$bank_name           = trim($_POST['bank_name'] ?? '');
$bank_account        = trim($_POST['bank_account'] ?? '');
$email               = trim($_POST['email'] ?? '');

// 주소/좌표(자동완성) — 새 필드
$store_address  = trim($_POST['store_address'] ?? '');
$store_lat      = trim($_POST['store_lat'] ?? '');
$store_lng      = trim($_POST['store_lng'] ?? '');
$store_place_id = trim($_POST['store_place_id'] ?? '');

// (선택) 매장 소개
$store_intro = trim($_POST['store_intro'] ?? '');

// ----------------------------
// 필수 검증: 현재 폼과 '정확히' 일치
// ----------------------------
$required = [
  'business_name'       => $business_name,
  'business_reg_number' => $business_reg_number,
  'contact_name'        => $contact_name,
  'contact_phone'       => $contact_phone,
  'bank_name'           => $bank_name,
  'bank_account'        => $bank_account,
  'email'               => $email,
  'store_address'       => $store_address,
  'store_lat'           => $store_lat,
  'store_lng'           => $store_lng,
];
foreach ($required as $k => $v) {
  if ($v === '' || $v === null) {
    back_to_apply('err=' . urlencode('모든 필드를 입력해 주세요.'));
  }
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back_to_apply('err=' . urlencode('이메일 형식이 올바르지 않습니다.'));
}

// ----------------------------
// 파일 업로드 — 사업자등록증(필수), 매장 대표 이미지(선택)
// ----------------------------
$business_cert_path = null;
if (!empty($_FILES['business_cert']['name'])) {
  $f = $_FILES['business_cert'];
  if ($f['error'] !== UPLOAD_ERR_OK) {
    back_to_apply('err=' . urlencode('파일 업로드에 실패했습니다.'));
  }
  if ($f['size'] > 5 * 1024 * 1024) {
    back_to_apply('err=' . urlencode('파일 용량이 5MB를 초과했습니다.'));
  }
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($f['tmp_name']);
  $allowed = ['application/pdf','image/jpeg','image/png','image/webp'];
  if (!in_array($mime, $allowed, true)) {
    back_to_apply('err=' . urlencode('허용되지 않은 파일 형식입니다.'));
  }
  $dir = __DIR__ . '/uploads/partners';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'bin');
  $filename = 'cert_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $dest = $dir . '/' . $filename;
  if (!move_uploaded_file($f['tmp_name'], $dest)) {
    back_to_apply('err=' . urlencode('파일 저장에 실패했습니다.'));
  }
  $business_cert_path = '/uploads/partners/' . $filename;
} else {
  back_to_apply('err=' . urlencode('사업자등록증 파일을 첨부해 주세요.'));
}

$store_hero_url = null;
if (!empty($_FILES['store_hero']['tmp_name'])) {
  $sf = $_FILES['store_hero'];
  if ($sf['error'] === UPLOAD_ERR_OK) {
    if (!empty($sf['size']) && (int)$sf['size'] > 5 * 1024 * 1024) {
      back_to_apply('err=' . urlencode('대표 이미지는 최대 5MB까지 업로드 가능합니다.'));
    }
    $okExt = ['jpg','jpeg','png','webp'];
    $ext   = strtolower(pathinfo($sf['name'] ?? 'file', PATHINFO_EXTENSION));
    if (!in_array($ext, $okExt, true)) {
      back_to_apply('err=' . urlencode('대표 이미지는 JPG/PNG/WEBP만 허용됩니다.'));
    }
    $dir2 = __DIR__ . '/uploads/partner_store_hero';
    if (!is_dir($dir2)) { @mkdir($dir2, 0775, true); }
    $safe = preg_replace('/[^a-zA-Z0-9_.-]/','_', $sf['name'] ?? 'store_hero');
    $destRel = '/uploads/partner_store_hero/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe;
    $destAbs = __DIR__ . $destRel;
    if (!@move_uploaded_file($sf['tmp_name'], $destAbs)) {
      back_to_apply('err=' . urlencode('대표 이미지를 저장하지 못했습니다.'));
    }
    $store_hero_url = $destRel;
  }
}

// ----------------------------
// DB 저장 — 존재 컬럼만 동적 저장 (환경 차이 대응)
// ----------------------------
$pdo = db();
$colsStmt = $pdo->prepare('SHOW COLUMNS FROM partner_applications');
$colsStmt->execute();
$colNames = array_map(static function($r){ return $r['Field']; }, $colsStmt->fetchAll());

$now = date('Y-m-d H:i:s');

$base = [
  // 표준 컬럼들(존재 여부와 무관하게 후보로 준비)
  'name'                  => $contact_name,        // 신청자 이름 = 담당자 이름
  'email'                 => $email,
  'business_name'         => $business_name,
  'businessRegNumber'     => $business_reg_number, // 카멜 표기 환경용
  'business_reg_number'   => $business_reg_number, // 스네이크 표기 환경용
  'business_address'      => $store_address,       // 구형 스키마 호환
  'store_address'         => $store_address,       // 신형 스키마
  'store_lat'             => $store_lat,
  'store_lng'             => $store_lng,
  'store_place_id'        => $store_place_id,
  'contact_name'          => $contact_name,
  'contact_phone'         => $contact_phone,
  'bank_name'             => $bank_name,
  'bank_account'          => $bank_account,
  'business_cert_path'    => $business_cert_path,
  'intro'                 => $store_intro,
  'hero_image_url'        => $store_hero_url,
  'status'                => 'pending',
  'created_at'            => $now,
  'updated_at'            => $now,
];

// 실제 존재하는 컬럼만 추려서 저장 배열 구성
$save = [];
foreach ($base as $k => $v) {
  if (in_array($k, $colNames, true)) {
    $save[$k] = $v;
  }
}

// INSERT 실행
$cols = array_keys($save);
$phs  = array_map(static fn($c) => ':' . $c, $cols);
$sql  = 'INSERT INTO partner_applications (' . implode(',', $cols) . ') VALUES (' . implode(',', $phs) . ')';
$stmt = $pdo->prepare($sql);
foreach ($save as $k => $v) {
  $stmt->bindValue(':' . $k, $v);
}
$stmt->execute();

back_to_apply('msg=' . urlencode('신청이 접수되었습니다. 검토 후 연락드리겠습니다.'));