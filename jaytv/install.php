&lt;?php
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $dbHost = trim($_POST['db_host']);
        $dbName = trim($_POST['db_name']);
        $dbUser = trim($_POST['db_user']);
        $dbPass = $_POST['db_pass'];
        $tmdbKey = trim($_POST['tmdb_key']);
        
        try {
            $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE =&gt; PDO::ERRMODE_EXCEPTION
            ]);
            
            $pdo-&gt;exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo-&gt;exec("USE `$dbName`");
            
            $sql = file_get_contents(__DIR__ . '/includes/schema.sql');
            $pdo-&gt;exec($sql);
            
            $adminPass = password_hash('101113', PASSWORD_DEFAULT);
            $stmt = $pdo-&gt;prepare("INSERT INTO users (username, email, password, is_admin, created_at) VALUES (?, ?, ?, 1, NOW())");
            $stmt-&gt;execute(['杰同学', 'admin@jaytv.com', $adminPass]);
            
            $stmt = $pdo-&gt;prepare("INSERT INTO settings (setting_key, setting_value) VALUES 
                ('theme_color', '#7c3aed'),
                ('tmdb_api_key', ?),
                ('site_name', 'Jay影视')
            ");
            $stmt-&gt;execute([$tmdbKey]);
            
            $stmt = $pdo-&gt;prepare("INSERT INTO video_sources (name, url, is_default) VALUES ('默认源', 'https://api.yyzy-tv.vip/inc/apijson.php', 1)");
            $stmt-&gt;execute();
            
            $configContent = &lt;&lt;&lt;PHP
&lt;?php
return [
    'db_host' =&gt; '$dbHost',
    'db_name' =&gt; '$dbName',
    'db_user' =&gt; '$dbUser',
    'db_pass' =&gt; '$dbPass',
];
PHP;
            file_put_contents(__DIR__ . '/config.php', $configContent);
            
            header('Location: /install.php?step=3');
            exit;
        } catch (Exception $e) {
            $error = $e-&gt;getMessage();
        }
    }
}
?&gt;
&lt;!DOCTYPE html&gt;
&lt;html lang="zh-CN"&gt;
&lt;head&gt;
&lt;meta charset="UTF-8"&gt;
&lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
&lt;title&gt;Jay影视 - 安装向导&lt;/title&gt;
&lt;style&gt;
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    padding: 20px;
}
.install-box {
    background: rgba(20, 20, 35, 0.95);
    border-radius: 20px;
    padding: 40px;
    width: 100%;
    max-width: 500px;
    border: 1px solid rgba(124, 58, 237, 0.3);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.logo {
    text-align: center;
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 10px;
    background: linear-gradient(135deg, #7c3aed, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.subtitle {
    text-align: center;
    color: #888;
    margin-bottom: 30px;
}
.steps {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-bottom: 30px;
}
.step {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #2a2a3e;
    color: #666;
    font-weight: 600;
    transition: all 0.3s;
}
.step.active { background: #7c3aed; color: #fff; }
.step.done { background: #10b981; color: #fff; }
.form-group { margin-bottom: 20px; }
label { display: block; margin-bottom: 8px; color: #ccc; font-size: 14px; }
input {
    width: 100%;
    padding: 12px 16px;
    background: #1a1a2e;
    border: 1px solid #2a2a3e;
    border-radius: 10px;
    color: #fff;
    font-size: 15px;
    transition: all 0.3s;
}
input:focus {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
}
.btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3); }
.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.success-box {
    text-align: center;
    padding: 30px 0;
}
.success-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 40px;
}
.info-list {
    background: #1a1a2e;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
}
.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #2a2a3e;
}
.info-item:last-child { border-bottom: none; }
&lt;/style&gt;
&lt;/head&gt;
&lt;body&gt;
&lt;div class="install-box"&gt;
    &lt;div class="logo"&gt;🎬 Jay影视&lt;/div&gt;
    &lt;div class="subtitle"&gt;
        &lt;?php if ($step === 1): ?&gt;欢迎使用安装向导&lt;?php endif; ?&gt;
        &lt;?php if ($step === 2): ?&gt;配置数据库信息&lt;?php endif; ?&gt;
        &lt;?php if ($step === 3): ?&gt;安装完成&lt;?php endif; ?&gt;
    &lt;/div&gt;
    
    &lt;div class="steps"&gt;
        &lt;div class="step &lt;?php echo $step &gt;= 1 ? 'done' : ''; ?&gt; &lt;?php echo $step === 1 ? 'active' : ''; ?&gt;"&gt;1&lt;/div&gt;
        &lt;div class="step &lt;?php echo $step &gt;= 2 ? 'done' : ''; ?&gt; &lt;?php echo $step === 2 ? 'active' : ''; ?&gt;"&gt;2&lt;/div&gt;
        &lt;div class="step &lt;?php echo $step === 3 ? 'active' : ''; ?&gt;"&gt;3&lt;/div&gt;
    &lt;/div&gt;
    
    &lt;?php if ($error): ?&gt;&lt;div class="error"&gt;&lt;?php echo $error; ?&gt;&lt;/div&gt;&lt;?php endif; ?&gt;
    
    &lt;?php if ($step === 1): ?&gt;
    &lt;div class="info-list"&gt;
        &lt;div class="info-item"&gt;&lt;span&gt;PHP版本&lt;/span&gt;&lt;span style="color:#10b981"&gt;&lt;?php echo PHP_VERSION; ?&gt; ✓&lt;/span&gt;&lt;/div&gt;
        &lt;div class="info-item"&gt;&lt;span&gt;PDO扩展&lt;/span&gt;&lt;span style="color:&lt;?php echo extension_loaded('pdo_mysql') ? '#10b981' : '#ef4444'; ?&gt;"&gt;&lt;?php echo extension_loaded('pdo_mysql') ? '已安装 ✓' : '未安装 ✗'; ?&gt;&lt;/span&gt;&lt;/div&gt;
        &lt;div class="info-item"&gt;&lt;span&gt;cURL扩展&lt;/span&gt;&lt;span style="color:&lt;?php echo extension_loaded('curl') ? '#10b981' : '#ef4444'; ?&gt;"&gt;&lt;?php echo extension_loaded('curl') ? '已安装 ✓' : '未安装 ✗'; ?&gt;&lt;/span&gt;&lt;/div&gt;
        &lt;div class="info-item"&gt;&lt;span&gt;OpenSSL扩展&lt;/span&gt;&lt;span style="color:&lt;?php echo extension_loaded('openssl') ? '#10b981' : '#ef4444'; ?&gt;"&gt;&lt;?php echo extension_loaded('openssl') ? '已安装 ✓' : '未安装 ✗'; ?&gt;&lt;/span&gt;&lt;/div&gt;
    &lt;/div&gt;
    &lt;button class="btn" onclick="window.location.href='?step=2'"&gt;开始安装&lt;/button&gt;
    &lt;?php endif; ?&gt;
    
    &lt;?php if ($step === 2): ?&gt;
    &lt;form method="post"&gt;
        &lt;div class="form-group"&gt;
            &lt;label&gt;数据库主机&lt;/label&gt;
            &lt;input type="text" name="db_host" value="localhost" required&gt;
        &lt;/div&gt;
        &lt;div class="form-group"&gt;
            &lt;label&gt;数据库名&lt;/label&gt;
            &lt;input type="text" name="db_name" placeholder="jaytv" required&gt;
        &lt;/div&gt;
        &lt;div class="form-group"&gt;
            &lt;label&gt;数据库用户名&lt;/label&gt;
            &lt;input type="text" name="db_user" placeholder="root" required&gt;
        &lt;/div&gt;
        &lt;div class="form-group"&gt;
            &lt;label&gt;数据库密码&lt;/label&gt;
            &lt;input type="password" name="db_pass"&gt;
        &lt;/div&gt;
        &lt;div class="form-group"&gt;
            &lt;label&gt;TMDB API Key&lt;/label&gt;
            &lt;input type="text" name="tmdb_key" placeholder="请输入您的TMDB API密钥"&gt;
        &lt;/div&gt;
        &lt;button type="submit" class="btn"&gt;开始安装&lt;/button&gt;
    &lt;/form&gt;
    &lt;?php endif; ?&gt;
    
    &lt;?php if ($step === 3): ?&gt;
    &lt;div class="success-box"&gt;
        &lt;div class="success-icon"&gt;✓&lt;/div&gt;
        &lt;h2&gt;安装成功！&lt;/h2&gt;
        &lt;p style="color:#888;margin:10px 0 20px"&gt;Jay影视已成功安装到您的服务器&lt;/p&gt;
        &lt;div class="info-list"&gt;
            &lt;div class="info-item"&gt;&lt;span&gt;默认管理员&lt;/span&gt;&lt;span&gt;杰同学&lt;/span&gt;&lt;/div&gt;
            &lt;div class="info-item"&gt;&lt;span&gt;默认密码&lt;/span&gt;&lt;span&gt;101113&lt;/span&gt;&lt;/div&gt;
        &lt;/div&gt;
        &lt;button class="btn" onclick="window.location.href='/index.php'"&gt;进入网站首页&lt;/button&gt;
    &lt;/div&gt;
    &lt;?php endif; ?&gt;
&lt;/div&gt;
&lt;/body&gt;
&lt;/html&gt;
