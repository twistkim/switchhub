<?php
// 관리자 저장 처리 — 기본으로 partner_profiles 테이블 업데이트
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';
require_role('admin');

// ===== DEBUG UTIL (temporary) ==============================================
$DBG   = (isset($_REQUEST['dbg']) && $_REQUEST['dbg'] == '1');
$TRACE = 'PU-'.date('Ymd_His').'-'.bin2hex(random_bytes(3));
function _dbg($msg){
  global $DBG, $TRACE;
  if (session_status() === PHP_SESSION_ACTIVE) {
    if (!isset($_SESSION['__PU_DEBUG'])) $_SESSION['__PU_DEBUG'] = [];
    // keep last 200 lines max
    if (count($_SESSION['__PU_DEBUG']) > 200) array_shift($_SESSION['__PU_DEBUG']);
    $_SESSION['__PU_DEBUG'][] = "[partner_update][{$TRACE}] ".$msg;
  }
  $line = "[partner_update][{$TRACE}] ".$msg;
  error_log($line);
  if ($DBG) {
    echo '<pre style="white-space:pre-wrap;word-break:break-all;background:#fff;border:1px solid #ddd;padding:10px;margin:6px 0">'.htmlspecialchars($line, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</pre>\n";
  }
}
_dbg('BEGIN method='.( $_SERVER['REQUEST_METHOD'] ?? ''));
// ==========================================================================

// ---- helpers: 프로필 해석/생성 & 이미지 저장 ----
// column existence check (defensive against schema drift)
function _has_column(PDO $pdo, string $table, string $column): bool {
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $st->execute([':t'=>$table, ':c'=>$column]);
    return (int)$st->fetchColumn() > 0;
  } catch (Throwable $e) {
    return false;
  }
}
function _resolve_profile_id_from_app(PDO $pdo, int $profileId, int $appId): array {
  $app = null;
  if ($profileId > 0) {
    return ['id'=>$profileId, 'app'=>null];
  }
  if ($appId <= 0) return ['id'=>0,'app'=>null];

  // 신청서 로드
  $st = $pdo->prepare("SELECT * FROM partner_applications WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$appId]);
  $app = $st->fetch(PDO::FETCH_ASSOC);
  _dbg('loaded application id='.(int)$appId.' exists='.( $app? '1':'0' ));
  if (!$app) return ['id'=>0,'app'=>null];

  // 1) profile_id -> profiles.id
  if (!empty($app['profile_id'])) {
    $st = $pdo->prepare("SELECT id FROM partner_profiles WHERE id=:pid LIMIT 1");
    $st->execute([':pid'=>$app['profile_id']]);
    $pid = (int)($st->fetchColumn() ?: 0);
    if ($pid > 0) {
      _dbg('RESOLVE via profile_id -> pid='.$pid);
      return ['id'=>$pid, 'app'=>$app];
    }
  }

  // 2) partner_id -> profiles.id
  if (!empty($app['partner_id'])) {
    $st = $pdo->prepare("SELECT id FROM partner_profiles WHERE id=:pid LIMIT 1");
    $st->execute([':pid'=>$app['partner_id']]);
    $pid = (int)($st->fetchColumn() ?: 0);
    if ($pid > 0) {
      _dbg('RESOLVE via partner_id -> pid='.$pid);
      return ['id'=>$pid, 'app'=>$app];
    }
  }

  // 3) user_id -> profiles.user_id (only if column exists)
  if (!empty($app['user_id']) && _has_column($pdo, 'partner_profiles', 'user_id')) {
    $st = $pdo->prepare("SELECT id FROM partner_profiles WHERE user_id=:uid ORDER BY id DESC LIMIT 1");
    $st->execute([':uid'=>$app['user_id']]);
    $pid = (int)($st->fetchColumn() ?: 0);
    if ($pid > 0) {
      _dbg('RESOLVE via user_id -> pid='.$pid);
      return ['id'=>$pid, 'app'=>$app];
    }
  }

  // 못 찾음
  _dbg('RESOLVE failed (no linkable key)');
  return ['id'=>0,'app'=>$app];
}

function _create_profile_from_application(PDO $pdo, array $app): int {
  $hasUserIdCol = _has_column($pdo, 'partner_profiles', 'user_id');
  // 0) user_id 해석 (컬럼 존재시에만 강제)
  $resolvedUserId = null;
  if ($hasUserIdCol) {
    // 0-a) 신청서에 user_id가 있으면 우선 사용
    if (!empty($app['user_id'])) {
      $resolvedUserId = (int)$app['user_id'];
    }
    // 0-b) 없으면 이메일로 users 매핑 시도
    if ($resolvedUserId === null && !empty($app['email'])) {
      try {
        $stU = $pdo->prepare("SELECT id FROM users WHERE email=:e LIMIT 1");
        $stU->execute([':e' => $app['email']]);
        $uid = (int)($stU->fetchColumn() ?: 0);
        if ($uid > 0) $resolvedUserId = $uid;
      } catch (Throwable $e) {
        // users 테이블이 없거나 다른 스키마인 경우 조용히 패스
      }
    }
    // 0-c) 그래도 없으면 현재 로그인 사용자(관리자) id로 임시 연결
    if ($resolvedUserId === null) {
      $resolvedUserId = (int)($_SESSION['user']['id'] ?? 0);
    }
    // 0-d) 여전히 0이면 DB 제약으로 실패하므로 방어적으로 예외 처리
    if ($resolvedUserId === 0) {
      throw new RuntimeException('프로필 생성에 필요한 user_id를 해석할 수 없습니다. (신청서에 user_id/email 없음, 세션 사용자도 없음)');
    }
    _dbg('CREATE profile resolving user_id='.$resolvedUserId.' (app.user_id='.(int)($app['user_id'] ?? 0).', app.email='.(string)($app['email'] ?? '').')');
  } else {
    _dbg('CREATE profile: partner_profiles.user_id column not found — proceeding without user binding');
  }

  // 기존에 해당 user_id의 프로필이 있으면 재사용 (중복 INSERT 방지)
  if ($hasUserIdCol && $resolvedUserId !== null) {
    try {
      $st = $pdo->prepare("SELECT id FROM partner_profiles WHERE user_id=:uid LIMIT 1");
      $st->execute([':uid' => $resolvedUserId]);
      $existingId = (int)($st->fetchColumn() ?: 0);
      if ($existingId > 0) {
        _dbg('REUSE existing profile for user_id='.$resolvedUserId.' id='.$existingId);
        return $existingId;
      }
    } catch (Throwable $e) {
      _dbg('REUSE check failed: '. $e->getMessage());
    }
  }

  // 1) 신청서 -> 프로필 값 매핑
  $store_name     = $app['business_name'] ?? $app['name'] ?? '신규 파트너';
  $phone          = $app['contact_phone'] ?? $app['phone'] ?? '';
  $address_line1  = $app['store_address'] ?? $app['business_address'] ?? $app['address'] ?? '';
  $province       = $app['province'] ?? '';
  $postal_code    = $app['postal_code'] ?? '';
  $lat            = $app['store_lat'] ?? $app['lat'] ?? null;
  $lng            = $app['store_lng'] ?? $app['lng'] ?? null;
  $place_id       = $app['store_place_id'] ?? $app['place_id'] ?? '';
  $logo_image_url = $app['logo_image_url'] ?? '';
  $hero_image_url = $app['hero_image_url'] ?? '';
  $intro          = $app['intro'] ?? $app['description'] ?? '';

  // 2) INSERT — user_id 컬럼이 있을 때만 포함
  if ($hasUserIdCol) {
    $sql = "INSERT INTO partner_profiles
            (user_id, store_name, phone, address_line1, province, postal_code, lat, lng, place_id, logo_image_url, hero_image_url, intro, is_published, created_at, updated_at)
            VALUES
            (:user_id, :store_name, :phone, :address_line1, :province, :postal_code, :lat, :lng, :place_id, :logo_image_url, :hero_image_url, :intro, 0, NOW(), NOW())";
  } else {
    $sql = "INSERT INTO partner_profiles
            (store_name, phone, address_line1, province, postal_code, lat, lng, place_id, logo_image_url, hero_image_url, intro, is_published, created_at, updated_at)
            VALUES
            (:store_name, :phone, :address_line1, :province, :postal_code, :lat, :lng, :place_id, :logo_image_url, :hero_image_url, :intro, 0, NOW(), NOW())";
  }

  $stmt = $pdo->prepare($sql);
  if ($hasUserIdCol) {
    $stmt->bindValue(':user_id', (int)$resolvedUserId, PDO::PARAM_INT);
  }
  $stmt->bindValue(':store_name', $store_name);
  $stmt->bindValue(':phone', $phone);
  $stmt->bindValue(':address_line1', $address_line1);
  $stmt->bindValue(':province', $province);
  $stmt->bindValue(':postal_code', $postal_code);
  if ($lat === null || $lat === '') { $stmt->bindValue(':lat', null, PDO::PARAM_NULL); } else { $stmt->bindValue(':lat', (float)$lat); }
  if ($lng === null || $lng === '') { $stmt->bindValue(':lng', null, PDO::PARAM_NULL); } else { $stmt->bindValue(':lng', (float)$lng); }
  $stmt->bindValue(':place_id', $place_id);
  $stmt->bindValue(':logo_image_url', $logo_image_url);
  $stmt->bindValue(':hero_image_url', $hero_image_url);
  $stmt->bindValue(':intro', $intro);
  _dbg('CREATE profile from app: store_name='.($store_name?:'').', phone='.($phone?:'').', addr='.($address_line1?:''));
  $stmt->execute();
  $newId = (int)$pdo->lastInsertId();
  _dbg('CREATE profile OK new_id='.$newId);

  return $newId;
}

// 업로드 저장 (경로 보정: /admin 상위 = 웹루트 기준)
function _save_uploaded_image(string $field, int $profileId): ?string {
  if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
  if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

  // Enforce server-side size limit (5 MB)
  $size = (int)($_FILES[$field]['size'] ?? 0);
  if ($size <= 0 || $size > 5 * 1024 * 1024) return null;

  $tmp  = $_FILES[$field]['tmp_name'] ?? null;
  if (!$tmp || !is_uploaded_file($tmp)) return null;

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = $finfo ? finfo_file($finfo, $tmp) : null;
  if ($finfo) finfo_close($finfo);

  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
  ];
  if (!$mime || !isset($allowed[$mime])) return null;

  $ext = $allowed[$mime];
  $baseDir = dirname(__DIR__); // /admin 의 상위 (웹루트)
  $relDir  = '/uploads/partners/' . $profileId;
  $absDir  = $baseDir . $relDir;
  if (!is_dir($absDir)) {
    if (!@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
      return null; // cannot create directory
    }
  }

  $file = sprintf('%s_%s.%s', $field, date('Ymd_His'), $ext);
  $abs  = $absDir . '/' . $file;
  $rel  = $relDir . '/' . $file;

  _dbg('UPLOAD attempt field='.$field.' mime='.($mime?:'').', size='.(int)$size.' -> '.$rel);
  if (!move_uploaded_file($tmp, $abs)) return null;
  _dbg('UPLOAD success -> '.$rel);
  return $rel; // DB에는 /uploads/... 저장
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: /admin/partners.php?err=method'); exit;
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
  header('Location: /admin/partners.php?err=csrf'); exit;
}

$back = $_POST['return'] ?? '/admin/partners.php';
$pdo = db();
_dbg('INPUT id='.(int)($_POST['id'] ?? 0).', app_id='.(int)($_POST['app_id'] ?? 0).', return='.($_POST['return'] ?? '')); 

// 어떤 기준으로 업데이트할지 결정
$profileId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$appId     = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;

try {
  $pdo->beginTransaction();
  _dbg('TX begin');

  // app_id 또는 id 기반으로 프로필 확정 (필요 시 생성)
  $resolved = _resolve_profile_id_from_app($pdo, $profileId, $appId);
  $profileId = (int)($resolved['id'] ?? 0);
  $app = $resolved['app'] ?? null;

  if ($profileId <= 0 && $app) {
    // 연결 프로필이 없으면 신청서로 새 프로필 생성
    $profileId = _create_profile_from_application($pdo, $app);
  }
  _dbg('RESOLVED profileId='. (int)$profileId);

  if ($profileId <= 0) {
    throw new RuntimeException('연결된 파트너 프로필을 찾을 수 없습니다. (app_id='.(int)$appId.', profileId='.(int)$profileId.')');
  }

  // 업로드 파일 저장은 프로필 id 확정 이후에 수행
  $newHero = _save_uploaded_image('hero_image_file', $profileId);
  $newLogo = _save_uploaded_image('logo_image_file', $profileId);

  // 기존 값 로드
  $st = $pdo->prepare("SELECT * FROM partner_profiles WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$profileId]);
  $before = $st->fetch(PDO::FETCH_ASSOC);
  if (!$before) throw new RuntimeException('파트너 프로필을 찾을 수 없습니다.');
  _dbg('LOAD before OK id='.(int)$profileId);

  // 입력값 정리 (텍스트 기반)
  // 공개 여부는 체크박스가 전송되지 않으면 기존 값 유지
  $isp = isset($_POST['is_published']) ? 1 : (isset($before['is_published']) ? (int)$before['is_published'] : 0);
  $payload = [
    'store_name'     => trim($_POST['store_name'] ?? ''),
    'phone'          => trim($_POST['phone'] ?? ''),
    'address_line1'  => trim($_POST['address_line1'] ?? ''),
    'address_line2'  => trim($_POST['address_line2'] ?? ''),
    'province'       => trim($_POST['province'] ?? ''),
    'postal_code'    => trim($_POST['postal_code'] ?? ''),
    'lat'            => ($_POST['lat'] ?? '') === '' ? null : (float)$_POST['lat'],
    'lng'            => ($_POST['lng'] ?? '') === '' ? null : (float)$_POST['lng'],
    'place_id'       => trim($_POST['place_id'] ?? ''),
    'intro'          => trim($_POST['intro'] ?? ''),
    'is_published'   => $isp,
  ];
  _dbg('PAYLOAD keys='.implode(',', array_keys($payload)));

  // 이미지 필드 적용 우선순위: 새 업로드 > 기존 유지(hidden) > 빈 값
  $payload['hero_image_url'] = $newHero ?? trim($_POST['hero_image_url_existing'] ?? '');
  $payload['logo_image_url'] = $newLogo ?? trim($_POST['logo_image_url_existing'] ?? '');

  // 동적 SET 쿼리
  $sets = [];
  foreach ($payload as $k => $v) $sets[] = "{$k} = :{$k}";
  $sql = "UPDATE partner_profiles SET ".implode(', ', $sets).", updated_at = NOW() WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  foreach ($payload as $k => $v) {
    if (in_array($k, ['lat','lng'], true) && $v === null) {
      $stmt->bindValue(':'.$k, null, PDO::PARAM_NULL);
    } elseif ($k === 'is_published') {
      $stmt->bindValue(':'.$k, (int)$v, PDO::PARAM_INT);
    } else {
      $stmt->bindValue(':'.$k, $v, PDO::PARAM_STR);
    }
  }
  $stmt->bindValue(':id', $profileId, PDO::PARAM_INT);
  $stmt->execute();
  _dbg('UPDATE OK id='.(int)$profileId);

  // app_id로 접근한 경우 신청서에 profile_id 연결 (컬럼이 있을 때만)
  if ($appId > 0 && _has_column($pdo, 'partner_applications', 'profile_id')) {
    $st = $pdo->prepare("UPDATE partner_applications SET profile_id = :pid WHERE id = :aid");
    $st->execute([':pid' => $profileId, ':aid' => $appId]);
    _dbg('APPLICATION linked: app_id='.(int)$appId.' -> profile_id='.(int)$profileId);
  }

  $pdo->commit();
  _dbg('TX commit');

  if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['__PU_DEBUG_READY'] = 1;
  $dbgFlag = (isset($_REQUEST['dbg']) && $_REQUEST['dbg'] == '1') ? '&dbg=1' : '';
  header('Location: /admin/partner_edit.php?id='.$profileId.'&saved=1'.$dbgFlag);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  _dbg('ERROR: '.$e->getMessage());
  if ($DBG) {
    http_response_code(500);
    echo '<pre style="white-space:pre-wrap;word-break:break-all;background:#fff3f3;border:1px solid #fca5a5;color:#991b1b;padding:12px;border-radius:6px">';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\n\n";
    echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    echo '</pre>';
    exit;
  }
  $sep = (strpos($back, '?') !== false ? '&' : '?');
  if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['__PU_DEBUG_READY'] = 1;
  $dbgFlag = (isset($_REQUEST['dbg']) && $_REQUEST['dbg'] == '1') ? '&dbg=1' : '';
  header('Location: '.$back.$sep.'err='.rawurlencode($e->getMessage()).$dbgFlag);
  exit;
}