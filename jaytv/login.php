&lt;?php
$pageTitle = '登录';
require_once __DIR__ . '/includes/init.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = '请填写邮箱和密码';
    } else {
        $db = Database::getInstance();
        $user = $db-&gt;fetch("SELECT * FROM users WHERE email = ? OR username = ?", [$email, $email]);
        
        if ($user &amp;&amp; password_verify($password, $user['password'])) {
            if ($user['is_banned']) {
                $banEnd = strtotime($user['ban_end']);
                if ($banEnd &gt; time()) {
                    $error = '您的账号已被封禁，解封时间：' . $user['ban_end'] . '，原因：' . $user['ban_reason'];
                } else {
                    $db-&gt;update('users', ['is_banned' =&gt; 0, 'ban_reason' =&gt; null, 'ban_start' =&gt; null, 'ban_end' =&gt; null], 'id = ?', [$user['id']]);
                    $user['is_banned'] = 0;
                }
            }
            
            if (empty($error)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_avatar'] = $user['avatar'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                
                $redirect = '/index.php';
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                }
                header('Location: ' . $redirect);
                exit;
            }
        } else {
            $error = '邮箱/用户名或密码错误';
        }
    }
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
require_once __DIR__ . '/includes/header.php';
?&gt;

&lt;div class="container" style="max-width: 450px; padding-top: 40px;"&gt;
    &lt;div class="card" style="padding: 40px;"&gt;
        &lt;div style="text-align: center; margin-bottom: 30px;"&gt;
            &lt;div style="font-size: 40px; margin-bottom: 10px;"&gt;🎬&lt;/div&gt;
            &lt;h2 style="font-size: 28px; font-weight: 700;"&gt;欢迎回来&lt;/h2&gt;
            &lt;p style="color: var(--text-muted); margin-top: 8px;"&gt;登录您的Jay影视账号&lt;/p&gt;
        &lt;/div&gt;
        
        &lt;?php if ($msg): ?&gt;
            &lt;div class="alert alert-error"&gt;&lt;?php echo htmlspecialchars($msg); ?&gt;&lt;/div&gt;
        &lt;?php endif; ?&gt;
        
        &lt;?php if ($error): ?&gt;
            &lt;div class="alert alert-error"&gt;&lt;?php echo $error; ?&gt;&lt;/div&gt;
        &lt;?php endif; ?&gt;
        
        &lt;form method="post"&gt;
            &lt;div class="form-group"&gt;
                &lt;label class="form-label"&gt;邮箱/用户名&lt;/label&gt;
                &lt;input type="text" name="email" class="form-input" placeholder="请输入邮箱或用户名" required value="&lt;?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?&gt;"&gt;
            &lt;/div&gt;
            &lt;div class="form-group"&gt;
                &lt;label class="form-label"&gt;密码&lt;/label&gt;
                &lt;input type="password" name="password" class="form-input" placeholder="请输入密码" required&gt;
            &lt;/div&gt;
            &lt;button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 10px;"&gt;登录&lt;/button&gt;
        &lt;/form&gt;
        
        &lt;div class="form-footer"&gt;
            还没有账号？&lt;a href="/register.php"&gt;立即注册&lt;/a&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;

&lt;?php require_once __DIR__ . '/includes/footer.php'; ?&gt;
