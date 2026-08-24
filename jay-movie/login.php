<?php
/**
 * Jay影视 - 登录页（未登录点播放跳转至此并弹窗提示）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) redirect('index.php');

$notice = get_param('notice');
$noticeText = '';
if ($notice === 'play') {
    $noticeText = '需要登录才可以观看哦，如没有账号请注册！';
} elseif ($notice === 'fav') {
    $noticeText = '登录后即可收藏、点赞与参与讨论哦！';
}

$error = '';
if (is_post()) {
    csrf_guard();
    $account = post('account');
    $password = post('password');
    if ($account === '' || $password === '') {
        $error = '请输入账号和密码';
    } else {
        $u = DB::row("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1", array($account, $account));
        if (!$u || !password_verify($password, $u['password'])) {
            $error = '账号或密码错误';
        } elseif (user_banned($u)) {
            $error = '账号已被封禁（' . ($u['ban_reason'] !== '' ? '原因：' . $u['ban_reason'] . '；' : '')
                . '封禁时间：' . dt($u['ban_start']) . ' ~ ' . ($u['ban_end'] ? dt($u['ban_end']) : '无限期') . '）';
        } else {
            login_user((int)$u['id']);
            $back = isset($_SESSION['login_back']) ? $_SESSION['login_back'] : '';
            unset($_SESSION['login_back']);
            if ($back !== '' && strpos($back, '/') !== 0 && strpos($back, '://') === false) redirect($back);
            redirect('index.php');
        }
    }
}

render_header(array('title' => '登录'));
?>
<div class="auth-wrap">
    <div class="auth-card fade-up">
        <div class="auth-brand"><span class="brand-logo" style="width:36px;height:36px;border-radius:11px"></span><?php echo e(site_name()); ?></div>
        <p class="auth-sub">登录后畅享全网影视 · 高清播放 · 极致体验</p>

        <?php if ($error): ?><div class="auth-alert err"><?php echo e($error); ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="form-item">
                <label class="form-label">用户名 / 邮箱</label>
                <input class="form-input" type="text" name="account" placeholder="请输入用户名或邮箱" value="<?php echo e(post('account')); ?>" required>
            </div>
            <div class="form-item">
                <label class="form-label">密码</label>
                <input class="form-input" type="password" name="password" placeholder="请输入密码" required>
            </div>
            <button class="btn btn-accent btn-block btn-lg" type="submit">
                <span class="ic ic-user"></span>登 录
            </button>
        </form>

        <p class="auth-foot">还没有账号？<a href="register.php">立即注册</a></p>
    </div>
</div>

<?php if ($noticeText): ?>
<div class="modal-mask" id="loginNoticeModal" data-auto="1">
    <div class="modal">
        <div class="modal-head">
            <h3><span class="ic ic-megaphone"></span>温馨提示</h3>
            <button class="modal-close" data-close="loginNoticeModal" type="button"><span class="ic ic-close"></span></button>
        </div>
        <div class="modal-body" style="padding:10px 22px 24px">
            <p style="font-size:15px;line-height:2;color:#c3cad8"><?php echo e($noticeText); ?></p>
        </div>
        <div class="modal-foot">
            <a class="btn btn-ghost" href="register.php">去注册</a>
            <button class="btn btn-accent" data-close="loginNoticeModal" type="button">去登录</button>
        </div>
    </div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
