<?php
// /lib/reviews.php
require_once __DIR__ . '/db.php';

if (!function_exists('reviews_can_review')) {
  // normalize order status (lowercase, remove spaces/underscores/hyphens and non-alnum)
  if (!function_exists('_norm_status')) {
    function _norm_status(string $s): string {
      $s = mb_strtolower($s, 'UTF-8');
      $s = preg_replace('/[\s_\-]+/u', '', $s);
      $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
      return $s;
    }
  }

  function reviews_can_review(PDO $pdo, int $userId, int $productId): array {
    // 최근 주문들을 확인하여 '배송완료' 계열 상태가 있는지 판정
    $st = $pdo->prepare(
      "SELECT o.id AS order_id, o.status\n     FROM orders o\n     WHERE o.user_id = :uid AND o.product_id = :pid\n     ORDER BY o.id DESC\n     LIMIT 20"
    );
    $st->execute([':uid' => $userId, ':pid' => $productId]);
    $rows = $st->fetchAll() ?: [];

    // 허용 토큰 (정규화된 상태와 비교)
    $allow = [
      // 영어 계열
      'delivered','deliverycomplete','deliveredconfirmed','deliveredcomplete',
      'completed','complete','done','finished','received',
      // 한국어 계열(공백 제거 기준)
      '배송완료','수령완료','구매확정','구매완료','거래완료',
    ];

    $okOrderId = null;
    foreach ($rows as $r) {
      $raw  = trim((string)$r['status']);        // 공백 제거 보강
      $norm = _norm_status($raw);               // 소문자화 + 공백/밑줄/하이픈/기타 제거

      // 1) 정확 토큰 매칭
      if (in_array($norm, $allow, true)) {
        $okOrderId = (int)$r['order_id'];
        break;
      }

      // 2) 포함 매칭 (PHP 7 호환: mb_strpos 사용)
      if (
        mb_strpos($norm, '배송완료') !== false ||
        mb_strpos($norm, '구매확정') !== false ||
        mb_strpos($norm, '수령완료') !== false ||
        mb_strpos($norm, 'delivered') !== false ||
        mb_strpos($norm, 'deliverycomplete') !== false ||
        mb_strpos($norm, 'completed') !== false
      ) {
        $okOrderId = (int)$r['order_id'];
        break;
      }
    }

    // (옵션) 디버그 로그 — URL에 ?debug=1 일 때만 기록
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
      @error_log('DBG reviews_can_review rows=' . json_encode($rows, JSON_UNESCAPED_UNICODE));
      @error_log('DBG reviews_can_review okOrderId=' . ($okOrderId === null ? 'null' : $okOrderId));
    }

    return [
      'allowed'  => $okOrderId !== null,
      'order_id' => $okOrderId ?: 0,
    ];
  }

  function reviews_get_stats(PDO $pdo, int $productId): array {
    $st = $pdo->prepare("
      SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating
      FROM product_reviews
      WHERE product_id = :pid AND is_deleted=0 AND is_approved=1
    ");
    $st->execute([':pid'=>$productId]);
    $row = $st->fetch() ?: ['cnt'=>0,'avg_rating'=>null];
    return [
      'count' => (int)$row['cnt'],
      'avg'   => $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 2) : null,
    ];
  }

  function reviews_get_list(PDO $pdo, int $productId, int $limit = 20, int $offset = 0): array {
    $st = $pdo->prepare("
      SELECT r.id, r.user_id, r.rating, r.body, r.created_at,
             u.name AS user_name
      FROM product_reviews r
      JOIN users u ON u.id = r.user_id
      WHERE r.product_id = :pid AND r.is_deleted=0 AND r.is_approved=1
      ORDER BY r.id DESC
      LIMIT :lim OFFSET :off
    ");
    $st->bindValue(':pid', $productId, PDO::PARAM_INT);
    $st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll() ?: [];
  }

  function reviews_get_user_review(PDO $pdo, int $userId, int $productId): ?array {
    $st = $pdo->prepare("
      SELECT id, rating, body, created_at, updated_at
      FROM product_reviews
      WHERE user_id = :uid AND product_id = :pid AND is_deleted=0
      LIMIT 1
    ");
    $st->execute([':uid'=>$userId, ':pid'=>$productId]);
    $row = $st->fetch();
    return $row ?: null;
  }

  // insert or update (upsert by uq_pr_user_product)
  function reviews_save(PDO $pdo, int $userId, int $productId, int $orderId, int $rating, string $body): int {
    if ($rating < 1 || $rating > 5) throw new InvalidArgumentException('rating_out_of_range');
    $pdo->beginTransaction();
    try {
      $exists = reviews_get_user_review($pdo, $userId, $productId);
      if ($exists) {
        $st = $pdo->prepare("
          UPDATE product_reviews
             SET rating = :rating,
                 body = :body,
                 is_deleted = 0,
                 is_approved = 1
           WHERE id = :id
        ");
        $st->execute([
          ':rating'=>$rating,
          ':body'=>$body,
          ':id'=>(int)$exists['id'],
        ]);
        $id = (int)$exists['id'];
      } else {
        $st = $pdo->prepare("
          INSERT INTO product_reviews (product_id, order_id, user_id, rating, body, is_approved, is_deleted)
          VALUES (:pid, :oid, :uid, :rating, :body, 1, 0)
        ");
        $st->execute([
          ':pid'=>$productId,
          ':oid'=>$orderId ?: null,
          ':uid'=>$userId,
          ':rating'=>$rating,
          ':body'=>$body,
        ]);
        $id = (int)$pdo->lastInsertId();
      }
      $pdo->commit();
      return $id;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
  }
}