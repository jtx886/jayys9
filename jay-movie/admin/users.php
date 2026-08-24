<?php
/**
 * 管理后台 - 用户管理
 * 封禁用户（自定义封禁开始/解除时间，自动 SMTP 邮件通知：原因/封禁时间/解除时间）、解封
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

$msg = ''; $msgType = 'ok';

if (is_post()) {
    csrf_guard();
    $act = post('act');

    /* ---- 封禁 ---- */
    if ($act === 'ban') {
        $uid = (int)post('uid');
        $reason = mb_cut(post('reason'), 400);
        $start = post('ban_start');
        $end = post('ban_end');
        $target = DB::row("SELECT * FROM users WHERE id = ? AND is_admin = 0", array($uid));
        if (!$target) {
            $msg = '用户不存在或不可封禁'; $msgType = 'err';
        } elseif ($start === '' || $end === '' || strtotime($end) <= strtotime($start)) {
            $msg = '请正确设置封禁时间（解除时间需晚于开始时间）'; $msgType = 'err';
        } else {
            $startD = date('Y-m-d H:i:s', strtotime($start));
            $endD   = date('Y-m-d H:i:s', strtotime($end));
            DB::exec("UPDATE users SET status = 0, ban_reason = ?, ban_start = ?, ban_end = ? WHERE id = ?",
                array($reason, $startD, $endD, $uid));
            /* SMTP 封禁通知邮件 */
            list($ok, $err) = send_mail(
                $target['email'],
                '【' . site_name() . '】账号封禁通知',
                tpl_ban_notice($target['username'], $reason, $startD, $endD)
            );
            $msg = '已封禁用户「' . $target['username'] . '」' . ($ok ? '，通知邮件已发送' : '，但邮件发送失败：' . $err);
            $msgType = $ok ? 'ok' : 'err';
        }
    }

    /* ---- 解封 ---- */
    if ($act === 'unban') {
        $uid = (int)post('uid');
        $target = DB::row("SELECT * FROM users WHERE id = ?", array($uid));
        if ($target) {
            DB::exec("UPDATE users SET status = 1, ban_reason = NULL, ban_start = NULL, ban_end = NULL WHERE id = ?", array($uid));
            $msg = '已解除用户「' . $target['username'] . '」的封禁';
        }
    }
}

/* ---------- 列表与搜索 ---------- */
$kw = get_param('kw');
$where = '1=1'; $params = array();
if ($kw !== '') {
    $where = "(username LIKE ? OR email LIKE ?)";
    $like = '%' . $kw . '%';
    $params = array($like, $like);
}
$users = DB::all("SELECT * FROM users WHERE {$where} ORDER BY id DESC LIMIT 200", $params);

admin_header(array('title' => '用户管理', 'active' => 'users'));
?>
<?php if ($msg): ?><div class="auth-alert <?php echo $msgType; ?>" style="margin-bottom:18px"><?php echo e($msg); ?></div><?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head">
        <h2><span class="ic ic-user"></span>用户列表（<?php echo count($users); ?>）</h2>
        <form method="get" class="toolbar">
            <input class="form-input" type="text" name="kw" placeholder="搜索用户名 / 邮箱" value="<?php echo e($kw); ?>">
            <button class="btn btn-accent btn-sm" type="submit"><span class="ic ic-search"></span>搜索</button>
        </form>
    </div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>用户</th><th>邮箱</th><th>注册时间</th><th>状态 / 封禁信息</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $banned = user_banned($u); ?>
            <tr>
                <td><div class="u-cell"><?php echo user_avatar_html($u, 32); ?><span><?php echo admin_name_badged($u['username'], $u['is_admin']); ?></span></div></td>
                <td style="color:var(--sub)"><?php echo e($u['email']); ?></td>
                <td style="color:var(--sub)"><?php echo dt($u['created_at']); ?></td>
                <td>
                    <?php if ((int)$u['is_admin'] === 1): ?>
                        <span class="badge badge-gold">管理员</span>
                    <?php elseif ($banned): ?>
                        <span class="badge badge-red">已封禁</span>
                        <div style="font-size:12px;color:var(--sub);margin-top:5px;line-height:1.8">
                            原因：<?php echo e($u['ban_reason'] ?: '未填写'); ?><br>
                            <?php echo dt($u['ban_start']); ?> ~ <?php echo $u['ban_end'] ? dt($u['ban_end']) : '无限期'; ?>
                        </div>
                    <?php elseif ((int)$u['status'] === 0 && $u['ban_start'] && strtotime($u['ban_start']) > time()): ?>
                        <span class="badge badge-gray">定时封禁（未生效）</span>
                        <div style="font-size:12px;color:var(--sub);margin-top:5px"><?php echo dt($u['ban_start']); ?> ~ <?php echo dt($u['ban_end']); ?></div>
                    <?php else: ?>
                        <span class="badge badge-green">正常</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="atable-actions">
                        <?php if ((int)$u['is_admin'] !== 1): ?>
                            <?php if ((int)$u['status'] === 1): ?>
                            <button class="btn btn-danger btn-sm" type="button" data-open="banModal<?php echo (int)$u['id']; ?>"><span class="ic ic-ban"></span>封禁</button>
                            <?php else: ?>
                            <form method="post" style="display:inline" data-confirm="确定解除该用户的封禁吗？">
                                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="act" value="unban">
                                <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                                <button class="btn btn-ghost btn-sm" type="submit"><span class="ic ic-check"></span>解封</button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ((int)$u['is_admin'] !== 1): ?>
                    <div class="modal-mask" id="banModal<?php echo (int)$u['id']; ?>">
                        <div class="modal">
                            <div class="modal-head">
                                <h3><span class="ic ic-ban"></span>封禁用户：<?php echo e($u['username']); ?></h3>
                                <button class="modal-close" data-close="banModal<?php echo (int)$u['id']; ?>" type="button"><span class="ic ic-close"></span></button>
                            </div>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="act" value="ban">
                                <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                                <div class="modal-body">
                                    <div class="form-item">
                                        <label class="form-label">封禁原因（将写入通知邮件）</label>
                                        <textarea class="form-textarea" name="reason" style="min-height:80px" placeholder="例如：发布违规内容、恶意刷屏等" required></textarea>
                                    </div>
                                    <div class="form-item">
                                        <label class="form-label">封禁开始时间</label>
                                        <input class="form-input" type="datetime-local" name="ban_start" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                    </div>
                                    <div class="form-item">
                                        <label class="form-label">解除封禁时间</label>
                                        <input class="form-input" type="datetime-local" name="ban_end" value="<?php echo date('Y-m-d\TH:i', time() + 7 * 86400); ?>" required>
                                        <p class="form-hint">到期后系统自动解除封禁；封禁后立即通过 SMTP（163邮箱）向用户发送通知邮件</p>
                                    </div>
                                </div>
                                <div class="modal-foot">
                                    <button class="btn btn-ghost" type="button" data-close="banModal<?php echo (int)$u['id']; ?>">取消</button>
                                    <button class="btn btn-accent" type="submit"><span class="ic ic-ban"></span>确认封禁并发送邮件</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php admin_footer(); ?>
