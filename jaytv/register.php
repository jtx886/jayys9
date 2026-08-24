&lt;?php
$pageTitle = '注册';
require_once __DIR__ . '/includes/init.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password2 = $_POST['password2'];
    $code = trim($_POST['code']);
    
    if (empty($email) || empty($username) || empty($password) || empty($code)) {
        $error = '请填写所有必填项';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } elseif (strlen($username) &lt; 2 || strlen($username) &gt; 20) {
        $error = '用户名长度需在2-20字符之间';
    } elseif (strlen($password) &lt; 6) {
        $error = '密码长度不能少于6位';
    } elseif ($password !== $password2) {
        $error = '两次密码输入不一致';
    } else {
        $db = Database::getInstance();
        
        $codeRecord = $db-&gt;fetch("SELECT * FROM email_codes WHERE email = ? AND code = ? AND type = 'register' AND used = 0 AND expires_at &gt; NOW() ORDER BY id DESC LIMIT 1", [$email, $code]);
        if (!$codeRecord) {
            $error = '验证码错误或已过期';
        } else {
            $exists = $db-&gt;fetch("SELECT id FROM users WHERE email = ? OR username = ?", [$email, $username]);
            if ($exists) {
                $error = '该邮箱或用户名已被注册';
            } else {
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $db-&gt;insert('users', [
                    'username' =&gt; $username,
                    'email' =&gt; $email,
                    'password' =&gt; $hashedPass,
                    'created_at' =&gt; date('Y-m-d H:i:s')
                ]);
                
                $db-&gt;update('email_codes', ['used' =&gt; 1], 'id = ?', [$codeRecord['id']]);
                
                $success = '注册成功！请登录';
                header('refresh:2;url=/login.php');
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?&gt;

&lt;div class="container" style="max-width: 450px; padding-top: 40px;"&gt;
    &lt;div class="card" style="padding: 40px;"&gt;
        &lt;div style="text-align: center; margin-bottom: 30px;"&gt;
            &lt;div style="font-size: 40px; margin-bottom: 10px;"&gt;🎬&lt;/div&gt;
            &lt;h2 style="font-size: 28px; font-weight: 700;"&gt;创建账号&lt;/h2&gt;
            &lt;p style="color: var(--text-muted); margin-top: 8px;"&gt;加入Jay影视，开启观影之旅&lt;/p&gt;
        &lt;/div&gt;
        
        &lt;?php if ($error): ?&gt;
            &lt;div class="alert alert-error"&gt;&lt;?php echo $error; ?&gt;&lt;/div&gt;
        &lt;?php endif; ?&gt;
        
        &lt;?php if ($success): ?&gt;
            &lt;div class="alert alert-success"&gt;&lt;?php echo $success; ?&gt;&lt;/div&gt;
        &lt;?php endif; ?&gt;
        
        &lt;form method="post" id="registerForm"&gt;
            &lt;div class="form-group"&gt;
                &lt;label class="form-label"&gt;邮箱&lt;/label&gt;
                &lt;input type="email" name="email" id="regEmail" class="form-input" placeholder="请输入邮箱" required value="&lt;?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?&gt;"&gt;
            &lt;/div&gt;
            &lt;div class="form-group"&gt;
                &lt;label class="form-label"&gt;用户名&lt;/label&gt;
                &lt;input type="text" name="username" class="form-input" placeholder="请输入用户名" required value="&lt;?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?&gt;"&gt;
            &lt;/div&gt;
            &lt;div class="form-row"&gt;
                &lt;div class="form-group"&gt;
                    &lt;label class="form-label"&gt;密码&lt;/label&gt;
                    &lt;input type="password" name="password" class="form-input" placeholder="至少6位" required&gt;
                &lt;/div&gt;
                &lt;div class="form-group"&gt;
                    &lt;label class="form-label"&gt;确认密码&lt;/label&gt;
                    &lt;input type="password" name="password2" class="form-input" placeholder="再次输入密码" required&gt;
                &lt;/div&gt;
            &lt;/div&gt;
            &lt;div class="form-group"&gt;
                &lt;label class="form-label"&gt;邮箱验证码&lt;/label&gt;
                &lt;div class="code-input-group"&gt;
                    &lt;input type="text" name="code" class="form-input" placeholder="请输入验证码" required maxlength="6"&gt;
                    &lt;button type="button" class="code-btn" id="sendCodeBtn" onclick="sendCode()"&gt;获取验证码&lt;/button&gt;
                &lt;/div&gt;
            &lt;/div&gt;
            &lt;button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 10px;"&gt;注册&lt;/button&gt;
        &lt;/form&gt;
        
        &lt;div class="form-footer"&gt;
            已有账号？&lt;a href="/login.php"&gt;立即登录&lt;/a&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;

&lt;script&gt;
let countdown = 0;
let timer = null;

function sendCode() {
    if (countdown &gt; 0) return;
    
    const email = document.getElementById('regEmail').value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast('请输入有效的邮箱地址', 'error');
        return;
    }
    
    const btn = document.getElementById('sendCodeBtn');
    btn.disabled = true;
    btn.textContent = '发送中...';
    
    fetch('/api/send_code.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(email) + '&amp;type=register'
    })
    .then(r =&gt; r.json())
    .then(data =&gt; {
        if (data.success) {
            showToast('验证码已发送，请查收邮箱', 'success');
            countdown = 60;
            timer = setInterval(() =&gt; {
                countdown--;
                btn.textContent = countdown + '秒后重发';
                if (countdown &lt;= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.textContent = '获取验证码';
                }
            }, 1000);
        } else {
            showToast(data.message || '发送失败', 'error');
            btn.disabled = false;
            btn.textContent = '获取验证码';
        }
    })
    .catch(() =&gt; {
        showToast('网络错误', 'error');
        btn.disabled = false;
        btn.textContent = '获取验证码';
    });
}
&lt;/script&gt;

&lt;?php require_once __DIR__ . '/includes/footer.php'; ?&gt;
