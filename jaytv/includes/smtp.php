&lt;?php
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

class SMTP {
    public $host;
    public $port;
    public $user;
    public $pass;
    public $from;
    public $fromName;
    
    private $socket;
    
    public function send($to, $subject, $body) {
        $this-&gt;connect();
        if (!$this-&gt;socket) return false;
        
        $this-&gt;recv();
        $this-&gt;send("EHLO " . $this-&gt;host . "\r\n");
        $this-&gt;recv();
        
        $this-&gt;send("AUTH LOGIN\r\n");
        $this-&gt;recv();
        
        $this-&gt;send(base64_encode($this-&gt;user) . "\r\n");
        $this-&gt;recv();
        
        $this-&gt;send(base64_encode($this-&gt;pass) . "\r\n");
        $resp = $this-&gt;recv();
        if (strpos($resp, '235') === false) return false;
        
        $this-&gt;send("MAIL FROM:&lt;" . $this-&gt;from . "&gt;\r\n");
        $this-&gt;recv();
        
        $this-&gt;send("RCPT TO:&lt;$to&gt;\r\n");
        $this-&gt;recv();
        
        $this-&gt;send("DATA\r\n");
        $this-&gt;recv();
        
        $fromName = '=?UTF-8?B?' . base64_encode($this-&gt;fromName) . '?=';
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        
        $headers = "From: $fromName &lt;" . $this-&gt;from . "&gt;\r\n";
        $headers .= "To: &lt;$to&gt;\r\n";
        $headers .= "Subject: $subjectEnc\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        
        $this-&gt;send($headers . $body . "\r\n.\r\n");
        $this-&gt;recv();
        
        $this-&gt;send("QUIT\r\n");
        fclose($this-&gt;socket);
        return true;
    }
    
    private function connect() {
        $this-&gt;socket = @fsockopen('ssl://' . $this-&gt;host, $this-&gt;port, $errno, $errstr, 10);
    }
    
    private function send($data) {
        fputs($this-&gt;socket, $data);
    }
    
    private function recv() {
        $response = '';
        while ($line = fgets($this-&gt;socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }
}
