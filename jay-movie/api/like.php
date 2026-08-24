<?php
/**
 * 反馈点赞（每用户每条反馈一次）
 */
require_once __DIR__ . '/../includes/init.php';

if (!is_post()) json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
csrf_guard();

$user = current_user();
if (!$user) json_out(array('ok' => 0, 'need_login' => 1, 'msg' => '请先登录'), 401);
if (user_banned($user)) json_out(array('ok' => 0, 'msg' => '账号封禁期间无法操作'), 403);

$fid = (int)post('fid', 0);
if ($fid <= 0 || !DB::one("SELECT id FROM feedback WHERE id = ?", array($fid))) {
    json_out(array('ok' => 0, 'msg' => '反馈不存在'));
}

$exists = DB::one("SELECT id FROM feedback_likes WHERE feedback_id = ? AND user_id = ?", array($fid, (int)$user['id']));
if ($exists) {
    DB::exec("DELETE FROM feedback_likes WHERE id = ?", array((int)$exists));
    $liked = false;
} else {
    DB::insert("INSERT INTO feedback_likes (feedback_id, user_id, created_at) VALUES (?,?,?)", array($fid, (int)$user['id'], now_str()));
    $liked = true;
}
$count = (int)DB::one("SELECT COUNT(*) FROM feedback_likes WHERE feedback_id = ?", array($fid));
json_out(array('ok' => 1, 'liked' => $liked, 'count' => $count));
