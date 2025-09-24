<?php
/**
 * /lib/messages.php
 * 쪽지(스레드/메시지) 공용 헬퍼
 *
 * 의존:
 *   - PDO 연결 함수 db()  (lib/db.php)
 *   - 로그인 세션: $_SESSION['user']['id']
 *   - (선택) /auth/guard.php 의 current_user(), require_login()
 */

if (!function_exists('db')) {
  require_once __DIR__ . '/db.php';
}

/** 내부 공통: 지금 로그인한 사용자 ID */
function msgs_current_user_id(): ?int {
  if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
  return null;
}

/** 참여자 여부 체크 */
function msgs_is_participant(PDO $pdo, int $threadId, int $userId): bool {
  $st = $pdo->prepare("SELECT 1 FROM message_participants WHERE thread_id=:t AND user_id=:u LIMIT 1");
  $st->execute([':t'=>$threadId, ':u'=>$userId]);
  return (bool)$st->fetchColumn();
}

/** 참여자 강제 요구(아니면 예외) */
function msgs_require_participant(PDO $pdo, int $threadId, int $userId): void {
  if (!msgs_is_participant($pdo, $threadId, $userId)) {
    throw new RuntimeException('not_participant');
  }
}

/** 스레드 생성 (product/order 연동 가능), 첫 메시지 같이 작성 */
function msgs_create_thread(
  PDO $pdo,
  int $creatorUserId,
  int $recipientUserId,
  ?string $subject,
  string $body,
  ?int $productId = null,
  ?int $orderId   = null
): int {
  if (trim($body) === '') throw new InvalidArgumentException('empty_body');

  $pdo->beginTransaction();
  try {
    // 1) thread
    $st = $pdo->prepare("
      INSERT INTO message_threads (subject, product_id, order_id, created_by)
      VALUES (:subj, :pid, :oid, :uid)
    ");
    $st->execute([
      ':subj' => $subject ?: null,
      ':pid'  => $productId ?: null,
      ':oid'  => $orderId ?: null,
      ':uid'  => $creatorUserId,
    ]);
    $threadId = (int)$pdo->lastInsertId();

    // 2) participants (중복 방지: UNIQUE(thread_id,user_id))
    $insPart = $pdo->prepare("
      INSERT IGNORE INTO message_participants (thread_id, user_id, role, last_read_at)
      VALUES (:t,:u,:r, NULL)
    ");
    // 역할은 최소값으로 채워두고, 실제 역할을 엄격히 쓰려면 users.role 조회해서 대입
    $insPart->execute([':t'=>$threadId, ':u'=>$creatorUserId,  ':r'=>'customer']);
    $insPart->execute([':t'=>$threadId, ':u'=>$recipientUserId, ':r'=>'partner']);

    // 3) first message
    $st2 = $pdo->prepare("
      INSERT INTO messages (thread_id, sender_id, body)
      VALUES (:t, :s, :b)
    ");
    $st2->execute([':t'=>$threadId, ':s'=>$creatorUserId, ':b'=>$body]);

    // 4) updated_at 갱신은 트리거처럼 자동(ON UPDATE) 되지만 안전하게 한 번 더
    $pdo->prepare("UPDATE message_threads SET updated_at=CURRENT_TIMESTAMP WHERE id=:t")
        ->execute([':t'=>$threadId]);

    $pdo->commit();
    return $threadId;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/** 스레드에 메시지 전송 (참여자만 가능) */
function msgs_send_message(PDO $pdo, int $threadId, int $senderUserId, string $body): int {
  if (trim($body) === '') throw new InvalidArgumentException('empty_body');
  msgs_require_participant($pdo, $threadId, $senderUserId);

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("INSERT INTO messages (thread_id, sender_id, body) VALUES (:t,:s,:b)");
    $st->execute([':t'=>$threadId, ':s'=>$senderUserId, ':b'=>$body]);
    $msgId = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE message_threads SET updated_at=CURRENT_TIMESTAMP WHERE id=:t")
        ->execute([':t'=>$threadId]);

    // 보낸 사람은 즉시 읽음 처리(선택)
    $pdo->prepare("
      UPDATE message_participants
         SET last_read_at = CURRENT_TIMESTAMP
       WHERE thread_id = :t AND user_id = :u
    ")->execute([':t'=>$threadId, ':u'=>$senderUserId]);

    $pdo->commit();
    return $msgId;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

/** 스레드 읽음 처리 */
function msgs_mark_read(PDO $pdo, int $threadId, int $userId): void {
  if (!msgs_is_participant($pdo, $threadId, $userId)) return;
  $pdo->prepare("
    UPDATE message_participants
       SET last_read_at = CURRENT_TIMESTAMP
     WHERE thread_id = :t AND user_id = :u
  ")->execute([':t'=>$threadId, ':u'=>$userId]);
}

/** 내 스레드 목록(최근 메시지 시간순) + 미리보기 */
function msgs_list_threads_for_user(PDO $pdo, int $userId, int $limit = 20, int $offset = 0): array {
  $limit = max(1, min(100, $limit));
  $offset = max(0, $offset);

  // 최신 메시지 스니펫 1개
  $sql = "
    SELECT
      mt.id,
      mt.subject,
      mt.product_id,
      mt.order_id,
      mt.updated_at,
      -- 상대방 이름 (간단: participants 중 내 id가 아닌 한 명)
      (SELECT u.name
         FROM message_participants mp2
         JOIN users u ON u.id = mp2.user_id
        WHERE mp2.thread_id = mt.id
          AND mp2.user_id <> :uid
        ORDER BY mp2.id ASC
        LIMIT 1) AS other_name,
      -- 미읽음 여부
      EXISTS(
        SELECT 1
          FROM messages m
          LEFT JOIN message_participants mp3
                 ON mp3.thread_id = m.thread_id AND mp3.user_id = :uid
         WHERE m.thread_id = mt.id
           AND (mp3.last_read_at IS NULL OR m.created_at > mp3.last_read_at)
      ) AS has_unread,
      -- 최근 메시지 스니펫
      (SELECT m2.body
         FROM messages m2
        WHERE m2.thread_id = mt.id AND m2.is_deleted = 0
        ORDER BY m2.id DESC
        LIMIT 1) AS last_body
    FROM message_threads mt
    JOIN message_participants mp ON mp.thread_id = mt.id AND mp.user_id = :uid
    ORDER BY mt.updated_at DESC, mt.id DESC
    LIMIT :limit OFFSET :offset
  ";
  $st = $pdo->prepare($sql);
  $st->bindValue(':uid', $userId, PDO::PARAM_INT);
  $st->bindValue(':limit', $limit, PDO::PARAM_INT);
  $st->bindValue(':offset', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll() ?: [];

  // 스니펫 길이 가볍게 제한
  foreach ($rows as &$r) {
    $body = (string)($r['last_body'] ?? '');
    if (function_exists('mb_strimwidth')) {
      $r['last_body'] = mb_strimwidth($body, 0, 120, '...', 'UTF-8');
    } else {
      $r['last_body'] = substr($body, 0, 120) . (strlen($body) > 120 ? '...' : '');
    }
  }
  return $rows;
}

/** 스레드 + 메시지 전체 조회 (권한 검증 포함; 관리자 오버라이드 지원) */
function msgs_get_thread_with_messages(
  PDO $pdo,
  int $threadId,
  int $viewerUserId,
  bool $allowAdminOverride = false
): array {
  // 1) 권한 체크
  $canBypass = false;
  if ($allowAdminOverride) {
    $stRole = $pdo->prepare("SELECT role FROM users WHERE id=:id LIMIT 1");
    $stRole->execute([':id'=>$viewerUserId]);
    $role = (string)$stRole->fetchColumn();
    if ($role && strtolower($role) === 'admin') {
      $canBypass = true; // 관리자는 참여자가 아니어도 열람 가능
    }
  }
  if (!$canBypass) {
    // 관리자 오버라이드가 아니면 참여자만 허용
    msgs_require_participant($pdo, $threadId, $viewerUserId);
  }

  // 2) 스레드 조회
  $st = $pdo->prepare("SELECT * FROM message_threads WHERE id=:t LIMIT 1");
  $st->execute([':t'=>$threadId]);
  $thread = $st->fetch();
  if (!$thread) {
    throw new RuntimeException('thread_not_found');
  }

  // 3) 메시지 전체 (소프트 삭제 제외)
  $st2 = $pdo->prepare("
    SELECT m.id, m.sender_id, u.name AS sender_name, m.body, m.created_at
      FROM messages m
      JOIN users u ON u.id = m.sender_id
     WHERE m.thread_id = :t AND m.is_deleted = 0
     ORDER BY m.id ASC
  ");
  $st2->execute([':t'=>$threadId]);
  $msgs = $st2->fetchAll() ?: [];

  return ['thread' => $thread, 'messages' => $msgs];
}

/** 스레드 참여자 전체 조회 (관리자/표시용) */
function msgs_get_participants(PDO $pdo, int $threadId): array {
  $st = $pdo->prepare(
    "SELECT 
        mp.thread_id,
        mp.user_id,
        mp.role       AS participant_role,
        mp.is_archived,
        mp.last_read_at,
        mp.created_at AS joined_at,
        u.name,
        u.email,
        u.role        AS user_role
      FROM message_participants mp
      JOIN users u ON u.id = mp.user_id
     WHERE mp.thread_id = :t
     ORDER BY mp.id ASC"
  );
  $st->execute([':t' => $threadId]);
  return $st->fetchAll() ?: [];
}

/** 스레드의 상대방(최초 1명) 조회 (UI 표시용) */
function msgs_get_other_user(PDO $pdo, int $threadId, int $myUserId): ?array {
  $st = $pdo->prepare("
    SELECT u.*
      FROM message_participants mp
      JOIN users u ON u.id = mp.user_id
     WHERE mp.thread_id = :t AND mp.user_id <> :u
     ORDER BY mp.id ASC
     LIMIT 1
  ");
  $st->execute([':t'=>$threadId, ':u'=>$myUserId]);
  return $st->fetch() ?: null;
}

/** 안전 렌더링 + URL 자동 링크 + 줄바꿈 변환 */
if (!function_exists('msgs_render_body')) {
  function msgs_render_body(string $text): string {
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // URL 자동 링크 (http/https)
    $linked = preg_replace(
      '~(https?://[\\w\\-\\.\\?\\,\\./\\:\\#\\%\\&\\=\\+\\~\\;\\@\\[\\]\\(\\)]+)~u',
      '<a href="$1" target="_blank" rel="noopener noreferrer" class="underline break-all">$1</a>',
      $escaped
    );
    return nl2br($linked);
  }
}