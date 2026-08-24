<?php
/**
 * 发送注册邮箱验证码（163 SMTP · 精致HTML邮件）
 * 60秒发送间隔限制，验证码10分钟有效
 */
require_once __DIR__ . '/../includes/init.php';

if (!is_post()) json_out(array('ok' => 0, 'msg' => '请求方式错误'), 405);
csrf_guard();

header('Content-Type: application/json; charset=utf-8');
$email = post('email');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(array('ok' => 0, 'msg' => '邮箱格式不正确'));
if (DB::one("SELECT id FROM users WHERE email = ?", array($email))) json_out(array('ok' => 0, 'msg' => '该邮箱已注册，请直接登录'));

/* 60 秒发送间隔 */
$last = DB::row("SELECT sent_at FROM email_codes WHERE email = ? ORDER BY id DESC LIMIT 1", array($email));
if ($last && (time() - strtotime($last['sent_at'])) < 60) {
    $wait = 60 - (time() - strtotime($last['sent_at']));
    json_out(array('ok' => 0, 'msg' => '发送过于频繁，请 ' . $wait . ' 秒后重试'));
}

/* 一天最多 15 次 */
$todayCount = (int)DB::one("SELECT COUNT(*) FROM email_codes WHERE email = ? AND sent_at > ?", array($email, date('Y-m-d H:i:s', time() - 86400)));
if ($todayCount >= 15) json_out(array('ok' => 0, 'msg' => '今日发送次数已达上限，请明天再试'));

$code = sprintf('%06d', mt_rand(0, 999999));
DB::exec("UPDATE email_codes SET used = 1 WHERE email = ? AND used = 0", array($email));
DB::insert("INSERT INTO email_codes (email, code, expires_at, used, sent_at) VALUES (?,?,?,0,?)",
    array($email, $code, date('Y-m-d H:i:s', time() + 600), now_str()));

list($ok, $err) = send_mail($email, '【' . site_name() . '】邮箱验证码', tpl_verify_code($code));
if ($ok) {
    json_out(array('ok' => 1, 'msg' => '验证码已发送，请查收邮箱'));
}
json_out(array('ok' => 0, 'msg' => '邮件发送失败：' . $err . '（若持续失败请联系管理员检查SMTP端口是否被主机商限制）'));
