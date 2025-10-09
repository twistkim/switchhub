<?php
// /lib/category_tree.php
declare(strict_types=1);

/**
 * 카테고리 트리 로드: parent_id 기준으로 자식 맵 생성
 * return: [ 'childrenOf' => array<int,array<int>>, 'rowsById' => array<int,array> ]
 */
function cat_load_tree(PDO $pdo): array {
  $rows = $pdo->query("SELECT id, name, parent_id FROM categories")->fetchAll(PDO::FETCH_ASSOC);
  $rowsById = [];
  $childrenOf = [];
  foreach ($rows as $r) {
    $id  = (int)$r['id'];
    $pid = (int)($r['parent_id'] ?? 0);
    $rowsById[$id] = $r;
    if (!isset($childrenOf[$pid])) $childrenOf[$pid] = [];
    $childrenOf[$pid][] = $id;
  }
  return ['childrenOf' => $childrenOf, 'rowsById' => $rowsById];
}

/** 주어진 루트 id 포함, 모든 하위 id 수집 */
function cat_collect_descendants(array $childrenOf, int $rootId): array {
  if ($rootId <= 0) return [];
  $out = [];
  $st = [$rootId];
  $guard = 0;
  while (!empty($st) && $guard < 10000) {
    $cur = array_pop($st);
    if ($cur <= 0 || isset($out[$cur])) { $guard++; continue; }
    $out[$cur] = true;
    if (!empty($childrenOf[$cur])) {
      foreach ($childrenOf[$cur] as $ch) $st[] = (int)$ch;
    }
    $guard++;
  }
  return array_keys($out);
}

/**
 * 요청 파라미터로부터 카테고리 필터 id 배열을 생성
 * - GET: cat, cat_level, cat_mode (tree)
 * - tree 모드일 때는 선택 노드 + 모든 하위 포함
 */
function cat_ids_for_query_from_request(array $childrenOf): array {
  $cat      = (int)($_GET['cat'] ?? 0);
  $catMode  = (string)($_GET['cat_mode'] ?? '');   // 'tree'면 하위 포함
  if ($cat <= 0) return [];
  if ($catMode === 'tree') return cat_collect_descendants($childrenOf, $cat);
  return [$cat];
}