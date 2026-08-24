&lt;?php
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' =&gt; false, 'message' =&gt; '请求方法错误']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$type = $_POST['type'] ?? 'register';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' =&gt; false, 'message' =&gt; '邮箱格式不正确']);
    exit;
}

$db = Database::getInstance();

if ($type === 'register') {
    $exists = $db-&gt;fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($exists) {
        echo json_encode(['success' =&gt; false, 'message' =&gt; '该邮箱已被注册']);
        exit;
    }
}

$recentCode = $db-&gt;fetch("SELECT * FROM email_codes WHERE email = ? AND type = ? AND created_at &gt; DATE_SUB(NOW(), INTERVAL 60 SECOND)", [$email, $type]);
if ($recentCode) {
    echo json_encode(['success' =&gt; false, 'message' =&gt; '发送太频繁，请稍后再试']);
    exit;
}

$code = strval(rand(100000, 999999));
$expires = date('Y-m-d H:i:s', time() + 300);

$db-&gt;insert('email_codes', [
    'email' =&gt; $email,
    'code' =&gt; $code,
    'type' =&gt; $type,
    'expires_at' =&gt; $expires,
    'created_at' =&gt; date('Y-m-d H:i:s')
]);

$mailBody = &lt;&lt;&lt;HTML
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
&lt;meta charset="UTF-8"&gt;
&lt;style&gt;
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif; background: #f0f0f5; padding: 20px; }
.container { max-width: 500px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
.header { background: linear-gradient(135deg, #7c3aed, #ec4899); padding: 40px 30px; text-align: center; }
.logo { font-size: 32px; font-weight: 800; color: white; }
.content { padding: 40px 30px; }
.title { font-size: 24px; font-weight: 700; color: #1a1a2e; margin-bottom: 16px; text-align: center; }
.desc { color: #666; line-height: 1.7; margin-bottom: 30px; text-align: center; font-size: 15px; }
.code-box { background: linear-gradient(135deg, #7c3aed, #ec4899); border-radius: 16px; padding: 30px; text-align: center; margin-bottom: 30px; }
.code-text { font-size: 42px; font-weight: 800; color: white; letter-spacing: 12px; font-family: 'Courier New', monospace; }
.code-tip { color: rgba(255,255,255,0.8); font-size: 13px; margin-top: 10px; }
.notice { color: #999; font-size: 13px; line-height: 1.6; text-align: center; padding: 20px; background: #f8f8fc; border-radius: 12px; }
.footer { padding: 24px 30px; text-align: center; color: #aaa; font-size: 12px; border-top: 1px solid #f0f0f5; }
&lt;/style&gt;
&lt;/head&gt;
&lt;body&gt;
&lt;div class="container"&gt;
    &lt;div class="header"&gt;
        &lt;div class="logo"&gt;🎬 Jay影视&lt;/div&gt;
    &lt;/div&gt;
    &lt;div class="content"&gt;
        &lt;h2 class="title"&gt;您的验证码&lt;/h2&gt;
        &lt;p class="desc"&gt;您正在进行邮箱验证，请勿将验证码泄露给他人&lt;/p&gt;
        &lt;div class="code-box"&gt;
            &lt;div class="code-text"&gt;$code&lt;/div&gt;
            &lt;div class="code-tip"&gt;验证码5分钟内有效&lt;/div&gt;
        &lt;/div&gt;
        &lt;div class="notice"&gt;
            如果这不是您的操作，请忽略此邮件。&lt;br&gt;
            此邮件由系统自动发送，请勿回复。
        &lt;/div&gt;
    &lt;/div&gt;
    &lt;div class="footer"&gt;
        © 2024 Jay影视. All Rights Reserved.
    &lt;/div&gt;
&lt;/div&gt;
&lt;/body&gt;
&lt;/html&gt;
HTML;

$result = sendMail($email, 'Jay影视 - 邮箱验证码', $mailBody);

if ($result) {
    echo json_encode(['success' =&gt; true, 'message' =&gt; '验证码已发送']);
} else {
    echo json_encode(['success' =&gt; false, 'message' =&gt; '邮件发送失败，请稍后重试']);
}
