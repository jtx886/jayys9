<?php
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

class SMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from;
    private $fromName;
    private $socket;

    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->user = SMTP_USER;
        $this->pass = SMTP_PASS;
        $this->from = SMTP_FROM;
        $this->fromName = SMTP_FROM_NAME;
    }

    private function connect() {
        $this->socket = fsockopen('ssl://' . $this->host, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            return false;
        }
        $this->getResponse();
        return true;
    }

    private function sendCommand($cmd, $expectedCode = null) {
        fputs($this->socket, $cmd . "\r\n");
        return $this->getResponse($expectedCode);
    }

    private function getResponse($expectedCode = null) {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        if ($expectedCode !== null) {
            return strpos($response, (string)$expectedCode) === 0;
        }
        return $response;
    }

    public function send($to, $subject, $body, $isHtml = true) {
        if (!$this->connect()) {
            return false;
        }

        $this->sendCommand('EHLO ' . $this->host, 250);
        $this->sendCommand('AUTH LOGIN', 334);
        $this->sendCommand(base64_encode($this->user), 334);
        $this->sendCommand(base64_encode($this->pass), 235);

        $this->sendCommand("MAIL FROM: <{$this->from}>", 250);
        $this->sendCommand("RCPT TO: <{$to}>", 250);
        $this->sendCommand('DATA', 354);

        $boundary = md5(uniqid(time()));
        $headers = "From: " . ($this->fromName ? "=?UTF-8?B?" . base64_encode($this->fromName) . "?= " : "") . "<{$this->from}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        if ($isHtml) {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
            $headers .= "--{$boundary}\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $headers .= chunk_split(base64_encode($body)) . "\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $headers .= chunk_split(base64_encode($body)) . "\r\n";
        }
        $headers .= "--{$boundary}--\r\n";
        $headers .= ".\r\n";

        fputs($this->socket, $headers);
        $result = $this->getResponse(250);

        $this->sendCommand('QUIT', 221);
        fclose($this->socket);

        return $result;
    }

    public static function getVerifyTemplate($code, $username) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #0a0a0f; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: linear-gradient(145deg, #1a1a2e, #16213e); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(139, 92, 246, 0.3); }
                .header { background: linear-gradient(135deg, #8b5cf6, #6366f1); padding: 30px; text-align: center; }
                .logo { font-size: 28px; font-weight: bold; color: white; display: flex; align-items: center; justify-content: center; gap: 10px; }
                .logo-icon { width: 40px; height: 40px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
                .logo-icon::after { content: ""; width: 0; height: 0; border-left: 15px solid #8b5cf6; border-top: 10px solid transparent; border-bottom: 10px solid transparent; margin-left: 3px; }
                .content { padding: 40px 30px; color: #e2e8f0; }
                .greeting { font-size: 20px; margin-bottom: 20px; color: #fff; }
                .message { font-size: 15px; line-height: 1.8; margin-bottom: 30px; color: #cbd5e1; }
                .code-box { background: rgba(139, 92, 246, 0.1); border: 2px dashed #8b5cf6; border-radius: 15px; padding: 25px; text-align: center; margin-bottom: 30px; }
                .code-label { font-size: 13px; color: #a78bfa; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 2px; }
                .code { font-size: 36px; font-weight: bold; color: #fff; letter-spacing: 8px; font-family: "Courier New", monospace; }
                .note { font-size: 13px; color: #64748b; text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
                .footer { background: rgba(0,0,0,0.2); padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">
                        <div class="logo-icon"></div>
                        Jay影视
                    </div>
                </div>
                <div class="content">
                    <div class="greeting">你好，' . htmlspecialchars($username) . '！</div>
                    <div class="message">感谢您注册 Jay影视！您的邮箱验证码已生成，请在注册页面输入以下验证码完成注册：</div>
                    <div class="code-box">
                        <div class="code-label">验证码</div>
                        <div class="code">' . $code . '</div>
                    </div>
                    <div class="note">验证码有效期为 10 分钟，请勿将验证码透露给他人。<br>如果这不是您的操作，请忽略此邮件。</div>
                </div>
                <div class="footer">© ' . date('Y') . ' Jay影视 · 精品影视观看平台</div>
            </div>
        </body>
        </html>';
    }

    public static function getBanTemplate($username, $reason, $startTime, $endTime) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0a0a0f; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: linear-gradient(145deg, #1a1a2e, #16213e); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(239, 68, 68, 0.2); }
                .header { background: linear-gradient(135deg, #ef4444, #dc2626); padding: 30px; text-align: center; }
                .logo { font-size: 28px; font-weight: bold; color: white; }
                .icon { font-size: 50px; margin-bottom: 10px; }
                .content { padding: 40px 30px; color: #e2e8f0; }
                .title { font-size: 22px; margin-bottom: 20px; color: #fca5a5; text-align: center; }
                .info-box { background: rgba(239, 68, 68, 0.1); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
                .info-row:last-child { border-bottom: none; }
                .info-label { color: #94a3b8; }
                .info-value { color: #fff; font-weight: 500; }
                .reason { background: rgba(239, 68, 68, 0.05); border-left: 4px solid #ef4444; padding: 15px; border-radius: 0 8px 8px 0; margin-top: 20px; }
                .reason-label { color: #fca5a5; font-size: 13px; margin-bottom: 5px; }
                .reason-text { color: #e2e8f0; line-height: 1.6; }
                .footer { background: rgba(0,0,0,0.2); padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="icon">⚠️</div>
                    <div class="logo">账号封禁通知</div>
                </div>
                <div class="content">
                    <div class="title">尊敬的 ' . htmlspecialchars($username) . '，您的账号已被封禁</div>
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">封禁时间</span>
                            <span class="info-value">' . $startTime . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">解除时间</span>
                            <span class="info-value">' . $endTime . '</span>
                        </div>
                    </div>
                    <div class="reason">
                        <div class="reason-label">封禁原因</div>
                        <div class="reason-text">' . htmlspecialchars($reason) . '</div>
                    </div>
                </div>
                <div class="footer">© ' . date('Y') . ' Jay影视 · 如有疑问请联系管理员</div>
            </div>
        </body>
        </html>';
    }

    public static function getCustomTemplate($title, $content) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0a0a0f; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: linear-gradient(145deg, #1a1a2e, #16213e); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(139, 92, 246, 0.2); }
                .header { background: linear-gradient(135deg, #8b5cf6, #6366f1); padding: 30px; text-align: center; }
                .logo { font-size: 28px; font-weight: bold; color: white; }
                .content { padding: 40px 30px; color: #e2e8f0; }
                .title { font-size: 24px; margin-bottom: 25px; color: #fff; }
                .body { font-size: 15px; line-height: 1.8; color: #cbd5e1; }
                .footer { background: rgba(0,0,0,0.2); padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">Jay影视</div>
                </div>
                <div class="content">
                    <div class="title">' . htmlspecialchars($title) . '</div>
                    <div class="body">' . nl2br(htmlspecialchars($content)) . '</div>
                </div>
                <div class="footer">© ' . date('Y') . ' Jay影视 · 精品影视观看平台</div>
            </div>
        </body>
        </html>';
    }
}
