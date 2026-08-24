<?php
/**
 * 观看历史：保存播放秒记录 / 删除记录
 */
require_once __DIR__ . '/../includes/init.php';

if (!is_post()) json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
csrf_guard();

$user = current_user();
if (!$user) json_out(array('ok' => 0, 'need_login' => 1, 'msg' => '请先登录'), 401);

$act = post('act');

if ($act === 'del') {
    $id = (int)post('id', 0);
    DB::exec("DELETE FROM watch_history WHERE id = ? AND user_id = ?", array($id, (int)$user['id']));
    json_out(array('ok' => 1));
}

if ($act === 'clear') {
    DB::exec("DELETE FROM watch_history WHERE user_id = ?", array((int)$user['id']));
    json_out(array('ok' => 1));
}

/* 保存进度 */
$tmdb  = (int)post('tmdb', 0);
$type  = post('type', 'movie');
if (!in_array($type, array('movie', 'tv'), true)) $type = 'movie';
$title = post('title');
$poster = post('poster');
$episode = mb_cut(post('episode'), 60);
$position = max(0, (int)post('position', 0));
$duration = max(0, (int)post('duration', 0));

if ($tmdb <= 0 || $title === '') json_out(array('ok' => 0, 'msg' => '参数错误'));

$exists = DB::row("SELECT id, position FROM watch_history WHERE user_id = ? AND tmdb_id = ? AND episode = ?",
    array((int)$user['id'], $tmdb, $episode));

if ($exists) {
    /* 回退重看（重进同一集，从头计）则覆盖；否则取较大值防抖动 */
    $newPos = ($position < 15) ? $position : max((int)$exists['position'], $position);
    DB::exec("UPDATE watch_history SET position = ?, duration = ?, title = ?, poster = ?, media_type = ?, updated_at = ? WHERE id = ?",
        array($newPos, $duration, mb_cut($title, 200), mb_cut($poster, 480), $type, now_str(), (int)$exists['id']));
} else {
    DB::insert("INSERT INTO watch_history (user_id, tmdb_id, media_type, title, poster, episode, position, duration, updated_at) VALUES (?,?,?,?,?,?,?,?,?)",
        array((int)$user['id'], $tmdb, $type, mb_cut($title, 200), mb_cut($poster, 480), $episode, $position, $duration, now_str()));
}
json_out(array('ok' => 1));
