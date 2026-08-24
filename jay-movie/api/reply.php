<?php
/**
 * 反馈回复提交（管理员回复自动置顶排序）
 */
require_once __DIR__ . '/../includes/init.php';

if (!is_post()) json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
csrf_guard();

$user = current_user();
if (!$user) json_out(array('ok' => 0, 'need_login' => 1, 'msg' => '请先登录'), 401);
if (user_banned($user)) json_out(array('ok' => 0, 'msg' => '账号封禁期间无法回复'), 403);

$fid = (int)post('fid', 0);
$content = trim(post('content'));

if ($fid <= 0 || !DB::one("SELECT id FROM feedback WHERE id = ?", array($fid))) {
    json_out(array('ok' => 0, 'msg' => '反馈不存在'));
}
if ($content === '') json_out(array('ok' => 0, 'msg' => '回复内容不能为空'));
if (mb_strlen($content, 'UTF-8') > 1000) json_out(array('ok' => 0, 'msg' => '回复内容过长（最多1000字）'));

DB::insert("INSERT INTO feedback_replies (feedback_id, user_id, content, is_admin, created_at) VALUES (?,?,?,?,?)",
    array($fid, (int)$user['id'], $content, (int)$user['is_admin'] === 1 ? 1 : 0, now_str()));

json_out(array('ok' => 1, 'msg' => '回复成功'));
