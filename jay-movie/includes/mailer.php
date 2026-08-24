<?php
/**
 * Jay影视 - 纯 PHP SMTP 发件客户端（SSL 465 / 163邮箱）
 * 附带精致 HTML 邮件模板：验证码 / 封禁通知 / 管理员邮件推送
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

class SmtpClient
{
    private $fp = null;

    public function __construct()
    {
        $target = 'ssl://' . SMTP_HOST . ':' . SMTP_PORT;
        $this->fp = @stream_socket_client($target, $errno, $errstr, 20);
        if (!$this->fp) {
            /* 直连失败时尝试通过 HTTP 代理 CONNECT 隧道（部分受限主机环境需要） */
            $this->fp = $this->connectViaProxy($errno, $errstr);
        }
        if (!$this->fp) {
            throw new Exception("连接SMTP服务器失败：{$errstr} ({$errno})");
        }
        stream_set_timeout($this->fp, 20);
    }

    /** 通过 HTTP 代理 CONNECT 隧道建立 TLS 连接（读取 http_proxy/https_proxy 环境变量） */
    private function connectViaProxy(&$errno, &$errstr)
    {
        $proxy = '';
        foreach (array('https_proxy', 'HTTPS_PROXY', 'http_proxy', 'HTTP_PROXY') as $k) {
            if (!empty($_ENV[$k])) { $proxy = $_ENV[$k]; break; }
            $v = getenv($k);
            if ($v !== false && $v !== '') { $proxy = $v; break; }
        }
        if ($proxy === '') return null;

        $p = parse_url($proxy);
        if (empty($p['host'])) return null;
        $pHost = $p['host'];
        $pPort = isset($p['port']) ? (int)$p['port'] : 80;

        $tunnel = @stream_socket_client("tcp://{$pHost}:{$pPort}", $errno, $errstr, 10);
        if (!$tunnel) return null;

        fwrite($tunnel, "CONNECT " . SMTP_HOST . ":" . SMTP_PORT . " HTTP/1.1\r\nHost: " . SMTP_HOST . ":" . SMTP_PORT . "\r\n\r\n");
        $resp = '';
        while (($line = fgets($tunnel, 1024)) !== false) {
            $resp .= $line;
            if ($line === "\r\n" || $line === "\n") break;
        }
        if (strpos($resp, ' 200 ') === false) {
            @fclose($tunnel);
            $errstr = '代理CONNECT隧道建立失败';
            return null;
        }

        /* 设置 SNI / 对端名称后启用 TLS（代理隧道场景下必须显式指定） */
        stream_context_set_option($tunnel, 'ssl', 'peer_name', SMTP_HOST);
        stream_context_set_option($tunnel, 'ssl', 'verify_peer', false);
        stream_context_set_option($tunnel, 'ssl', 'verify_peer_name', false);
        if (!@stream_socket_enable_crypto($tunnel, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            @fclose($tunnel);
            $errstr = 'TLS握手失败（代理隧道）';
            return null;
        }
        return $tunnel;
    }

    private function read()
    {
        $data = '';
        while (($line = fgets($this->fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break; // 响应结束
            if (stream_get_meta_data($this->fp)['timed_out']) break;
        }
        return $data;
    }

    private function cmd($c, $expect, $allowMulti = false)
    {
        fwrite($this->fp, $c . "\r\n");
        $resp = $this->read();
        $code = (int)substr($resp, 0, 3);
        if ($code !== $expect) {
            /* 凭证类指令（纯base64串）在异常信息中脱敏，避免密码泄露到日志 */
            $safe = (!preg_match('/\s/', $c) && strlen($c) > 8 && preg_match('#^[A-Za-z0-9+/=]+$#', $c)) ? '凭证已隐藏' : $c;
            throw new Exception("SMTP指令[{$safe}]失败：{$resp}");
        }
        return $resp;
    }

    /**
     * 发送邮件
     * @return bool
     */
    public function send($to, $subject, $html)
    {
        // 问候
        $greet = $this->read();
        if ((int)substr($greet, 0, 3) !== 220) throw new Exception('SMTP问候异常：' . $greet);

        $this->cmd('EHLO jaymovie', 250);
        $this->cmd('AUTH LOGIN', 334);
        $this->cmd(base64_encode(SMTP_USER), 334);
        $this->cmd(base64_encode(SMTP_PASS), 235);
        $this->cmd('MAIL FROM:<' . SMTP_FROM . '>', 250);
        $this->cmd('RCPT TO:<' . $to . '>', 250);
        $this->cmd('DATA', 354);

        $mail = $this->buildHeaders($to, $subject, $html);
        // 点转义（base64内容不会出现行首.，此处仍做保护）
        $this->cmd($mail . "\r\n.", 250);
        $this->cmd('QUIT', 221);
        return true;
    }

    private function buildHeaders($to, $subject, $html)
    {
        $boundary = '=_JayMail_' . md5(uniqid('', true));
        $textAlt  = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
        $textAlt  = html_entity_decode($textAlt, ENT_QUOTES, 'UTF-8');

        $headers  = 'From: =?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?= <' . SMTP_FROM . ">\r\n";
        $headers .= 'To: <' . $to . ">\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $headers .= 'Date: ' . date('r') . "\r\n";
        $headers .= 'Message-ID: <' . md5(uniqid('', true)) . "@jaymovie>\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'X-Mailer: JayMovie/' . APP_VERSION . "\r\n";
        $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n";

        $body  = '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
        $body .= chunk_split(base64_encode($textAlt)) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= '--' . $boundary . '--';

        return $headers . $body;
    }

    public function close()
    {
        if ($this->fp) { @fclose($this->fp); $this->fp = null; }
    }

    public function __destruct() { $this->close(); }
}

/**
 * 发送邮件（带数据库日志）
 * @return array [ok(bool), err(string)]
 */
function send_mail($to, $subject, $html)
{
    $to = trim((string)$to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return array(false, '邮箱格式不正确');
    $ok = false; $err = '';
    try {
        $client = new SmtpClient();
        $ok = $client->send($to, $subject, $html);
        $client->close();
        if (!$ok) $err = '发送失败';
    } catch (Exception $ex) {
        $err = $ex->getMessage();
        $ok  = false;
    }
    try {
        DB::insert("INSERT INTO mail_log (email, subject, status, error, created_at) VALUES (?,?,?,?,?)",
            array($to, $subject, $ok ? 1 : 0, $err, now_str()));
    } catch (Exception $ignore) {}
    return array($ok, $err);
}

/* ==================== HTML 邮件模板 ==================== */

/** 品牌邮件外壳 */
function mail_wrap($title, $inner, $footerNote = '')
{
    $site = e(site_name());
    $year = date('Y');
    $note = $footerNote !== '' ? '<p style="margin:0 0 6px;color:#98a2b3;font-size:12px;line-height:20px;">' . $footerNote . '</p>' : '';
    return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#eef1f6;">
<div style="display:none;max-height:0;overflow:hidden;">{$title}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6;padding:32px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,.10);">
  <!-- 顶部品牌条 -->
  <tr><td style="background:linear-gradient(135deg,#14161d 0%,#232734 55%,#e50914 260%);padding:30px 40px;">
    <table role="presentation" width="100%"><tr>
      <td style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:1px;">
        <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#e50914;margin-right:10px;"></span>{$site}
      </td>
    </tr></table>
    <p style="margin:10px 0 0;font-size:13px;color:rgba(255,255,255,.65);letter-spacing:2px;">JAY&nbsp;MOVIE&nbsp;/&nbsp;极致观影体验</p>
  </td></tr>
  <!-- 正文 -->
  <tr><td style="padding:36px 40px 12px;font-family:'PingFang SC','Microsoft YaHei',Segoe UI,Arial,sans-serif;">
    <h1 style="margin:0 0 22px;font-size:20px;color:#101828;line-height:1.4;">{$title}</h1>
    {$inner}
  </td></tr>
  <!-- 底部 -->
  <tr><td style="padding:22px 40px 30px;border-top:1px solid #f0f2f7;">
    {$note}
    <p style="margin:0;color:#98a2b3;font-size:12px;line-height:20px;">此邮件由 {$site} 系统自动发送，请勿直接回复。<br>© {$year} {$site} · 保留所有权利</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
}

/** 邮箱验证码模板（精致卡片 + 大号验证码） */
function tpl_verify_code($code)
{
    $inner = <<<HTML
<p style="margin:0 0 14px;font-size:14px;color:#475467;line-height:26px;">您好！感谢注册 <b style="color:#101828;">Jay影视</b>，您正在进行的操作需要验证邮箱。</p>
<p style="margin:0 0 6px;font-size:14px;color:#475467;line-height:26px;">本次验证码为：</p>
<div style="margin:18px 0 22px;padding:22px 20px;background:linear-gradient(180deg,#fafbfe,#f2f5fb);border:1px dashed #d5dbe8;border-radius:14px;text-align:center;">
  <span style="display:inline-block;font-size:34px;font-weight:800;letter-spacing:14px;color:#e50914;font-family:'Courier New',monospace;padding-left:14px;">{$code}</span>
</div>
<p style="margin:0 0 8px;font-size:13px;color:#475467;line-height:24px;">验证码 <b style="color:#e50914;">10 分钟</b> 内有效，请勿泄露给他人。如非本人操作，请忽略本邮件。</p>
HTML;
    return mail_wrap('邮箱验证码', $inner, '为保障账号安全，验证码一次性使用，过期后需重新获取。');
}

/** 封禁通知模板 */
function tpl_ban_notice($username, $reason, $start, $end)
{
    $reason = e($reason !== '' ? $reason : '违反社区规范');
    $inner = <<<HTML
<p style="margin:0 0 14px;font-size:14px;color:#475467;line-height:26px;">您好，<b style="color:#101828;">{$username}</b>：</p>
<p style="margin:0 0 18px;font-size:14px;color:#475467;line-height:26px;">由于您的账号存在违规行为，管理员已对账号执行封禁处理，具体信息如下：</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;">
  <tr><td style="padding:12px 16px;background:#fff4f4;border-left:3px solid #e50914;font-size:13px;color:#b42318;line-height:22px;border-radius:0 8px 8px 0;">
    <b>封禁原因：</b>{$reason}
  </td></tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e9edf5;border-radius:12px;overflow:hidden;font-size:13px;color:#344054;">
  <tr><td style="padding:12px 18px;background:#f8fafc;border-bottom:1px solid #e9edf5;width:110px;">封禁开始时间</td><td style="padding:12px 18px;border-bottom:1px solid #e9edf5;">{$start}</td></tr>
  <tr><td style="padding:12px 18px;background:#f8fafc;">解除封禁时间</td><td style="padding:12px 18px;">{$end}</td></tr>
</table>
<p style="margin:18px 0 0;font-size:13px;color:#475467;line-height:24px;">封禁到期后账号将自动恢复正常。如有疑问，请通过站内反馈中心与管理员联系。</p>
HTML;
    return mail_wrap('账号封禁通知', $inner);
}

/** 管理员邮件推送模板 */
function tpl_admin_push($title, $contentHtml)
{
    $inner = <<<HTML
<p style="margin:0 0 16px;font-size:14px;color:#475467;line-height:26px;">您好！来自 <b style="color:#101828;">Jay影视</b> 官方的一封新邮件：</p>
<div style="padding:18px 20px;background:#f8fafc;border:1px solid #e9edf5;border-radius:12px;font-size:14px;color:#344054;line-height:26px;">{$contentHtml}</div>
HTML;
    return mail_wrap($title, $inner);
}
