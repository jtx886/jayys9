&lt;?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) &amp;&amp; !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) &amp;&amp; $_SESSION['is_admin'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php?msg=需要登录才可以观看哦，如没有账号请注册！');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /login.php');
        exit;
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function formatTime($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now-&gt;diff($ago);

    if ($diff-&gt;d &gt; 365) {
        return floor($diff-&gt;d / 365) . '年前';
    }
    if ($diff-&gt;d &gt; 30) {
        return floor($diff-&gt;d / 30) . '个月前';
    }
    if ($diff-&gt;d &gt; 0) {
        return $diff-&gt;d . '天前';
    }
    if ($diff-&gt;h &gt; 0) {
        return $diff-&gt;h . '小时前';
    }
    if ($diff-&gt;i &gt; 0) {
        return $diff-&gt;i . '分钟前';
    }
    return '刚刚';
}

function getSetting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        $db = Database::getInstance();
        $rows = $db-&gt;fetchAll("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return isset($settings[$key]) ? $settings[$key] : $default;
}

function setSetting($key, $value) {
    $db = Database::getInstance();
    $existing = $db-&gt;fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
    if ($existing) {
        $db-&gt;update('settings', ['setting_value' =&gt; $value], 'setting_key = ?', [$key]);
    } else {
        $db-&gt;insert('settings', ['setting_key' =&gt; $key, 'setting_value' =&gt; $value]);
    }
}

function sendMail($to, $subject, $htmlBody) {
    require_once __DIR__ . '/smtp.php';
    
    $smtp = new SMTP();
    $smtp-&gt;host = SMTP_HOST;
    $smtp-&gt;port = SMTP_PORT;
    $smtp-&gt;user = SMTP_USER;
    $smtp-&gt;pass = SMTP_PASS;
    $smtp-&gt;from = SMTP_FROM;
    $smtp-&gt;fromName = SMTP_FROM_NAME;
    
    return $smtp-&gt;send($to, $subject, $htmlBody);
}

function tmdbRequest($endpoint, $params = []) {
    $apiKey = getSetting('tmdb_api_key', '');
    if (empty($apiKey)) {
        return null;
    }
    
    $baseUrl = 'https://api.themoviedb.org/3';
    $params['api_key'] = $apiKey;
    $params['language'] = 'zh-CN';
    
    $url = $baseUrl . $endpoint . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function getVideoSources() {
    $db = Database::getInstance();
    return $db-&gt;fetchAll("SELECT * FROM video_sources ORDER BY is_default DESC, id ASC");
}

function getDefaultSource() {
    $db = Database::getInstance();
    return $db-&gt;fetch("SELECT * FROM video_sources WHERE is_default = 1 LIMIT 1");
}

function getPlayUrl($sourceUrl, $vid, $type = 'movie') {
    $url = $sourceUrl;
    if (strpos($url, '?') !== false) {
        $url .= '&amp;';
    } else {
        $url .= '?';
    }
    
    if ($type === 'tv') {
        $url .= 'wd=' . urlencode($vid);
    } else {
        $url .= 'wd=' . urlencode($vid);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data &amp;&amp; isset($data['list']) &amp;&amp; !empty($data['list'])) {
        $first = $data['list'][0];
        if (isset($first['vod_play_url'])) {
            $parts = explode('#', $first['vod_play_url']);
            if (!empty($parts)) {
                $firstPart = explode('$', $parts[0]);
                if (count($firstPart) &gt;= 2) {
                    return trim($firstPart[1]);
                }
            }
        }
    }
    
    return null;
}

function getThemeColor() {
    return getSetting('theme_color', '#7c3aed');
}
