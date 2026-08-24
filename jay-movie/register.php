<?php
/**
 * Jay影视 - 注册页（邮箱验证码 · SMTP 邮件）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) redirect('index.php');

$error = '';
$old = array('email' => '', 'username' => '');

if (is_post()) {
    csrf_guard();
    $old['email']    = post('email');
    $old['username'] = post('username');
    $password  = post('password');
    $password2 = post('password2');
    $code      = post('code');

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = '请输入正确的邮箱地址';
    } elseif (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]{2,20}$/u', $old['username'])) {
        $error = '用户名需为 2-20 位中文、字母、数字或下划线';
    } elseif (DB::one("SELECT id FROM users WHERE email = ?", array($old['email']))) {
        $error = '该邮箱已被注册';
    } elseif (DB::one("SELECT id FROM users WHERE username = ?", array($old['username']))) {
        $error = '该用户名已被使用';
    } elseif (strlen($password) < 6 || strlen($password) > 32) {
        $error = '密码长度需为 6-32 位';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = '请输入 6 位邮箱验证码';
    } else {
        $row = DB::row(
            "SELECT * FROM email_codes WHERE email = ? AND code = ? AND used = 0 AND expires_at > ? ORDER BY id DESC LIMIT 1",
            array($old['email'], $code, now_str())
        );
        if (!$row) {
            $error = '验证码错误或已过期，请重新获取';
        } else {
            DB::exec("UPDATE email_codes SET used = 1 WHERE id = ?", array((int)$row['id']));
            $uid = DB::insert(
                "INSERT INTO users (username, email, password, avatar, is_admin, status, created_at) VALUES (?,?,?,'',0,1,?)",
                array($old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT), now_str())
            );
            if ($uid) {
                login_user((int)$uid);
                redirect('index.php');
            }
            $error = '注册失败，请稍后重试';
        }
    }
}

render_header(array('title' => '注册'));
?>
<div class="auth-wrap">
    <div class="auth-card fade-up">
        <div class="auth-brand"><span class="brand-logo" style="width:36px;height:36px;border-radius:11px"></span>注册 <?php echo e(site_name()); ?></div>
        <p class="auth-sub">邮箱验证注册 · 全部功能免费畅用</p>

        <?php if ($error): ?><div class="auth-alert err"><?php echo e($error); ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="form-item">
                <label class="form-label">邮箱地址</label>
                <input class="form-input" type="email" id="regEmail" name="email" placeholder="用于接收验证码和通知" value="<?php echo e($old['email']); ?>" required>
            </div>
            <div class="form-item">
                <label class="form-label">用户名</label>
                <input class="form-input" type="text" name="username" placeholder="2-20位中文、字母、数字" value="<?php echo e($old['username']); ?>" required>
            </div>
            <div class="form-item">
                <label class="form-label">密码</label>
                <input class="form-input" type="password" name="password" placeholder="6-32位密码" required>
            </div>
            <div class="form-item">
                <label class="form-label">确认密码</label>
                <input class="form-input" type="password" name="password2" placeholder="再次输入密码" required>
            </div>
            <div class="form-item">
                <label class="form-label">邮箱验证码</label>
                <div class="input-group">
                    <input class="form-input" type="text" name="code" placeholder="6位数字验证码" maxlength="6" required>
                    <button class="btn btn-ghost" type="button" id="sendCodeBtn">获取验证码</button>
                </div>
                <p class="form-hint">验证码将通过 <?php echo e(SMTP_FROM_NAME); ?>（<?php echo e(SMTP_USER); ?>）发送至您的邮箱，10分钟内有效</p>
            </div>
            <button class="btn btn-accent btn-block btn-lg" type="submit"><span class="ic ic-mail"></span>注 册</button>
        </form>

        <p class="auth-foot">已有账号？<a href="login.php">直接登录</a></p>
    </div>
</div>
<?php render_footer(); ?>
