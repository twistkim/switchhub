<?php
// /admin/category_save.php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/csrf.php';

require_role('admin');
if (function_exists('csrf_require')) csrf_require();

$pdo = db();
$id    = (int)($_POST['id'] ?? 0);
$name  = trim($_POST['name'] ?? '');
$slug  = trim($_POST['slug'] ?? '');
$pid   = $_POST['parent_id'] === '' ? null : (int)$_POST['parent_id'];
$sort  = (int)($_POST['sort_order'] ?? 0);
$act   = isset($_POST['is_active']) ? 1 : 0;

if ($name === '') { header('Location: /admin/categories.php?err=required'); exit; }
if ($pid && $id && $pid === $id) { header('Location: /admin/categories.php?id='.$id.'&err=self_parent'); exit; }

try {
  if ($id > 0) {
    $sql = "UPDATE categories
               SET name=:name, slug=:slug, parent_id=:pid, sort_order=:so, is_active=:ia, updated_at=NOW()
             WHERE id=:id LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->bindValue(':name',$name); $st->bindValue(':slug',$slug!==''?$slug:null, $slug!==''?PDO::PARAM_STR:PDO::PARAM_NULL);
    $st->bindValue(':pid',$pid, $pid===null?PDO::PARAM_NULL:PDO::PARAM_INT);
    $st->bindValue(':so',$sort, PDO::PARAM_INT);
    $st->bindValue(':ia',$act, PDO::PARAM_INT);
    $st->bindValue(':id',$id, PDO::PARAM_INT);
    $st->execute();
    header('Location: /admin/categories.php?id='.$id.'&msg=updated'); exit;
  } else {
    $sql = "INSERT INTO categories (name, slug, parent_id, sort_order, is_active, created_at, updated_at)
            VALUES (:name, :slug, :pid, :so, :ia, NOW(), NOW())";
    $st = $pdo->prepare($sql);
    $st->bindValue(':name',$name); $st->bindValue(':slug',$slug!==''?$slug:null, $slug!==''?PDO::PARAM_STR:PDO::PARAM_NULL);
    $st->bindValue(':pid',$pid, $pid===null?PDO::PARAM_NULL:PDO::PARAM_INT);
    $st->bindValue(':so',$sort, PDO::PARAM_INT);
    $st->bindValue(':ia',$act, PDO::PARAM_INT);
    $st->execute();
    header('Location: /admin/categories.php?msg=created'); exit;
  }
} catch (Throwable $e) {
  $reason = urlencode($e->getMessage());
  header('Location: /admin/categories.php?err=exception&reason='.$reason); exit;
}