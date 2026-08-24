<?php
/**
 * 收藏开关 / 删除收藏
 */
require_once __DIR__ . '/../includes/init.php';

if (!is_post()) json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
csrf_guard();

$user = current_user();
if (!$user) json_out(array('ok' => 0, 'need_login' => 1, 'msg' => '请先登录'), 401);
if (user_banned($user)) json_out(array('ok' => 0, 'msg' => '账号封禁期间无法操作'), 403);

$tmdb  = (int)post('tmdb', 0);
$type  = post('type', 'movie');
if (!in_array($type, array('movie', 'tv'), true)) $type = 'movie';
$act   = post('act');

if ($act === 'del') {
    DB::exec("DELETE FROM favorites WHERE user_id = ? AND tmdb_id = ? AND media_type = ?", array((int)$user['id'], $tmdb, $type));
    $count = (int)DB::one("SELECT COUNT(*) FROM favorites WHERE tmdb_id = ? AND media_type = ?", array($tmdb, $type));
    json_out(array('ok' => 1, 'favorited' => false, 'count' => $count));
}

$title  = post('title');
$poster = post('poster');
if ($tmdb <= 0 || $title === '') json_out(array('ok' => 0, 'msg' => '参数错误'));

$exists = DB::one("SELECT id FROM favorites WHERE user_id = ? AND tmdb_id = ? AND media_type = ?", array((int)$user['id'], $tmdb, $type));
if ($exists) {
    DB::exec("DELETE FROM favorites WHERE id = ?", array((int)$exists));
    $favorited = false;
} else {
    DB::insert("INSERT INTO favorites (user_id, tmdb_id, media_type, title, poster, created_at) VALUES (?,?,?,?,?,?)",
        array((int)$user['id'], $tmdb, $type, mb_cut($title, 200), mb_cut($poster, 480), now_str()));
    $favorited = true;
}
$count = (int)DB::one("SELECT COUNT(*) FROM favorites WHERE tmdb_id = ? AND media_type = ?", array($tmdb, $type));
json_out(array('ok' => 1, 'favorited' => $favorited, 'count' => $count));
