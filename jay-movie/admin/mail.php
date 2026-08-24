<?php
/**
 * 管理后台 - 邮件推送
 * 自定义邮件内容，通过 163 SMTP 向用户邮箱发送通知
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

set_time_limit(0);
$msg = ''; $msgType = 'ok';
$mailResults = null;

if (is_post() && post('act') === 'send') {
    csrf_guard();
    $target = post('target');
    $toUser = (int)post('to_user', 0);
    $toEmail = post('to_email');
    $subject = mb_cut(post('subject'), 120);
    $content = trim(post('content'));

    if ($subject === '' || $content === '') {
        $msg = '请填写邮件标题和内容'; $msgType = 'err';
    } else {
        /* 收件人列表 */
        $recipients = array();
        if ($target === 'all') {
            foreach (DB::all("SELECT username, email FROM users WHERE is_admin = 0 AND email LIKE '%@%'") as $u) {
                $recipients[] = $u;
            }
        } elseif ($target === 'one') {
            $u = DB::row("SELECT username, email FROM users WHERE id = ?", array($toUser));
            if ($u) $recipients[] = $u;
        } else {
            if (filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = array('username' => '', 'email' => $toEmail);
            }
        }

        if (!$recipients) {
            $msg = '未找到有效收件人'; $msgType = 'err';
        } else {
            /* 内容包装：含 HTML 标签则原样，纯文本自动换行 */
            $contentHtml = preg_match('/<[a-z][\s\S]*>/i', $content) ? $content : nl2br(e($content));
            $tpl = tpl_admin_push($subject, $contentHtml);
            $mailResults = array();
            $okCount = 0;
            foreach ($recipients as $rcpt) {
                list($ok, $err) = send_mail($rcpt['email'], $subject, $tpl);
                $mailResults[] = array('email' => $rcpt['email'], 'ok' => $ok, 'err' => $err);
                if ($ok) $okCount++;
            }
            $msg = '邮件推送完成：成功 ' . $okCount . ' / ' . count($recipients) . ' 封';
            $msgType = $okCount === count($recipients) ? 'ok' : 'err';
        }
    }
}

$users = DB::all("SELECT id, username, email FROM users ORDER BY id DESC LIMIT 500");
$mailLog = DB::all("SELECT * FROM mail_log ORDER BY id DESC LIMIT 50");

admin_header(array('title' => '邮件推送', 'active' => 'mail'));
?>
<?php if ($msg): ?><div class="auth-alert <?php echo $msgType; ?>" style="margin-bottom:18px"><?php echo e($msg); ?></div><?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-mail"></span>发送通知邮件（163 SMTP）</h2></div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="act" value="send">
            <div class="form-grid">
                <div class="form-item span2"><label class="form-label">收件对象</label>
                    <select class="form-select" name="target" id="mailTarget">
                        <option value="all">全部用户</option>
                        <option value="one">指定用户</option>
                        <option value="custom">自定义邮箱</option>
                    </select>
                </div>
                <div class="form-item span2" id="rowUser" style="display:none"><label class="form-label">选择用户</label>
                    <select class="form-select" name="to_user">
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>"><?php echo e($u['username']); ?>（<?php echo e($u['email']); ?>）</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-item span2" id="rowEmail" style="display:none"><label class="form-label">收件邮箱</label>
                    <input class="form-input" type="email" name="to_email" placeholder="多个邮箱请逐个发送">
                </div>
                <div class="form-item span2"><label class="form-label">邮件标题</label>
                    <input class="form-input" type="text" name="subject" placeholder="例如：站点维护通知" required>
                </div>
                <div class="form-item span2"><label class="form-label">邮件内容（支持简单 HTML，纯文本自动换行；将套用品牌邮件模板）</label>
                    <textarea class="form-textarea" name="content" style="min-height:160px" placeholder="亲爱的用户：&#10;    ……&#10;Jay影视 官方团队" required></textarea>
                </div>
                <div class="span2">
                    <button class="btn btn-accent btn-lg" type="submit"><span class="ic ic-send"></span>立即发送</button>
                    <span class="form-hint" style="display:inline;margin-left:10px">发件人：<?php echo e(SMTP_FROM_NAME); ?> &lt;<?php echo e(SMTP_USER); ?>&gt;</span>
                </div>
            </div>
        </form>
        <?php if (is_array($mailResults)): ?>
        <div class="mail-result" style="margin-top:18px;border-top:1px solid var(--border);padding-top:14px">
            <?php foreach ($mailResults as $r): ?>
            <div class="<?php echo $r['ok'] ? 'ok' : 'fail'; ?>">
                <span class="ic ic-<?php echo $r['ok'] ? 'check' : 'close'; ?>"></span>
                <?php echo e($r['email']); ?> —— <?php echo $r['ok'] ? '发送成功' : '失败：' . $r['err']; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-list"></span>发送日志（最近50条）</h2></div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>收件邮箱</th><th>主题</th><th>状态</th><th>时间</th></tr></thead>
            <tbody>
            <?php if ($mailLog): foreach ($mailLog as $l): ?>
            <tr>
                <td><?php echo e($l['email']); ?></td>
                <td style="color:var(--sub)"><?php echo e(mb_cut($l['subject'], 30)); ?></td>
                <td><?php echo (int)$l['status'] === 1 ? '<span class="badge badge-green">成功</span>' : '<span class="badge badge-red">失败</span>' . ($l['error'] ? '<div style="font-size:11.5px;color:var(--sub);margin-top:4px">' . e(mb_cut($l['error'], 60)) . '</div>' : ''); ?></td>
                <td style="color:var(--sub)"><?php echo dt($l['created_at']); ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;color:var(--sub);padding:30px">暂无发送记录</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('mailTarget');
    if (!sel) return;
    function refresh() {
        document.getElementById('rowUser').style.display = sel.value === 'one' ? '' : 'none';
        document.getElementById('rowEmail').style.display = sel.value === 'custom' ? '' : 'none';
    }
    sel.addEventListener('change', refresh);
    refresh();
});
</script>
<?php admin_footer(); ?>
