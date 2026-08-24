<?php
/**
 * 管理后台 - 登录（默认账号：杰同学 / 101113）
 */
require_once __DIR__ . '/includes.php';

if (admin_user()) redirect('index.php');

$error = '';
if (is_post()) {
    csrf_guard();
    $account = post('account');
    $password = post('password');
    $u = DB::row("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_admin = 1 LIMIT 1", array($account, $account));
    if (!$u || !password_verify($password, $u['password'])) {
        $error = '管理员账号或密码错误';
    } else {
        $_SESSION['admin_id'] = (int)$u['id'];
        session_regenerate_id(true);
        redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf" content="<?php echo e(csrf_token()); ?>">
<title>管理后台登录 - <?php echo e(site_name()); ?></title>
<?php echo theme_style_tag(); ?>
<link rel="stylesheet" href="../assets/css/style.css?v=<?php echo APP_VERSION; ?>">
<link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo APP_VERSION; ?>">
</head>
<body class="admin-body">
<div class="auth-wrap">
    <div class="auth-card fade-up">
        <div class="auth-brand"><span class="brand-logo" style="width:36px;height:36px;border-radius:11px"></span>管理后台</div>
        <p class="auth-sub"><?php echo e(site_name()); ?> · Administrator Console</p>
        <?php if ($error): ?><div class="auth-alert err"><?php echo e($error); ?></div><?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="form-item">
                <label class="form-label">管理员账号</label>
                <input class="form-input" type="text" name="account" placeholder="管理员用户名" required>
            </div>
            <div class="form-item">
                <label class="form-label">密码</label>
                <input class="form-input" type="password" name="password" placeholder="管理员密码" required>
            </div>
            <button class="btn btn-accent btn-block btn-lg" type="submit"><span class="ic ic-shield"></span>进入后台</button>
        </form>
        <p class="auth-foot"><a href="../index.php">← 返回前台首页</a></p>
    </div>
</div>
</body>
</html>
