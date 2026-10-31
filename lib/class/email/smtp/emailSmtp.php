<?php

if (!class_exists('SMTPMailer', false)) {
	fs::includeFile(fs::$sysRoot . 'src/SMTPMailer.php');
}

class emailSmtp extends SMTPMailer {

    static function create() {
        return new self;
    }

    /* function Send() { // DEBUG
      p("send mail skipped subject:",$this->subject);
      return TRUE;
      }
     */

    /** set when this instance has no "mail" block and is not DEV: Send() refuses */
    private bool $unconfigured = false;

    /** set by sensitive(): the log gets the envelope, not the content */
    private bool $sensitive = false;

    /** The mail carries a secret (a login code): var/log/email.log keeps no content. */
    public function sensitive(): static {
        $this->sensitive = true;
        return $this;
    }

    /**
     * The account comes from the "mail" block of the instance's config
     * (<Instance>.config.json or config.local.json - never git):
     *
     *   "mail": {"host": "smtp.example.net", "port": 587, "secure": "tls",
     *            "user": "…", "password": "…", "from": "…", "fromName": "…"}
     *
     * Without one, DEV hands the mail to Mailpit (127.0.0.1:1025, no account)
     * and every other stage refuses to send. An explicit $server (the legacy
     * callers) brings no account: the caller authenticates.
     */
    public function __construct($server = false, $port = false, $secure = 'tls') {
        $mail = $server === false ? self::config() : null;
        if ($server !== false) {
            parent::__construct($server, $port, $secure);
        } elseif ($mail !== null) {
            parent::__construct((string) $mail['host'], (int) ($mail['port'] ?? 587), (string) ($mail['secure'] ?? 'tls'));
            if ((string) ($mail['user'] ?? '') !== '') {
                $this->Auth((string) $mail['user'], (string) ($mail['password'] ?? ''));
            }
        } elseif (defined('STAGE') && str_starts_with((string) STAGE, 'DEV')) {
            parent::__construct('127.0.0.1', 1025, 'none');
        } else {
            parent::__construct('localhost', 25, 'none');
            $this->unconfigured = true;
        }
        unset($this->subject);
        $address = (string) ($mail['from'] ?? '') ?: (defined('MAIL_SENDER_ADDRESS') ? (string) MAIL_SENDER_ADDRESS : '') ?: (string) ($mail['user'] ?? '') ?: 'noreply@systopic.media';
        $name = (string) ($mail['fromName'] ?? '') ?: (defined('MAIL_SENDER_NAME') ? (string) MAIL_SENDER_NAME : '') ?: 'fluxo';
        $this->From($address, $name);
    }

    /** @return array<string, mixed>|null the "mail" block, when it names a host */
    public static function config(): ?array {
        $mail = \Systopic\System\Mail\Delivery::settings();
        return (string) ($mail['host'] ?? '') !== '' ? $mail : null;
    }


    protected function generateMessageID() {
        $cleanNumber = preg_replace('/[^0-9]/', '', microtime(false));
        $local = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? $_SERVER['SERVER_ADDR'] ?? '';
        return sprintf(
            "<%s.%s@%s>",
            base_convert($cleanNumber, 10, 36),
            base_convert(bin2hex(openssl_random_pseudo_bytes(8)), 16, 36),
            $local
        );
    }

    // interface allows shorthands and chaining calls

    function __call($name, $args) {
        $method = "add" . ucfirst($name);
        if (method_exists($this, $method)) {
            call_user_func_array([$this, $method], $args);
        }
        return $this;
    }

    // Authentication Login
    public function Auth($user, $pass) {
        parent::Auth($user, $pass);
        return $this;
    }

    // Set from email address
    public function From($address, $name = '') {
        parent::From($address, $name);
        return $this;
    }

    // Add email reply to address
    public function addReplyTo($address, $name = '') {
        parent::addReplyTo($address, $name);
        return $this;
    }

    // Add recipient email address
    public function addTo($address, $name = '') {
        parent::addTo($address, $name);
        return $this;
    }

    // Add carbon copy email address
    public function addCc($address, $name = '') {
        parent::addCc($address, $name);
        return $this;
    }

    // Add blind carbon copy email address
    public function addBcc($address, $name = '') {
        parent::addBcc($address, $name);
        return $this;
    }

    // Set email subject
    public function Subject($subject) {
        parent::Subject($subject);
        return $this;
    }

    // Set email html body
    public function Body($html) {
        parent::Body($html);
        return $this;
    }

    // Set email plain text
    public function Text($text) {
        parent::Text($text);
        return $this;
    }

    // Add attachment file
    public function File($path) {
        parent::File($path);
        return $this;
    }

    // Set charset. Default 'UTF-8'
    public function Charset($charset) {
        parent::Charset($charset);
        return $this;
    }

    // Set Content Transfer Encoding. Default '8bit'
    public function TransferEncoding($encode) {
        parent::TransferEncoding($encode);
        return $this;
    }

    // Override Send() to add logging
    public function Send() {
        \Systopic\System\Mail\Delivery::prepare($this);
        // Collect email data before sending
        $recipients = [];
        foreach ($this->to as $address) {
            $recipients[] = $address[0];
        }
        foreach ($this->cc as $address) {
            $recipients[] = $address[0] . ' (CC)';
        }
        foreach ($this->bcc as $address) {
            $recipients[] = $address[0] . ' (BCC)';
        }
        
        $sender = !empty($this->from) ? $this->from[0] : 'unknown';
        $subject = $this->subject ?? 'No subject';
        $htmlContent = $this->sensitive ? '(not logged: contains a login code)' : ($this->body ?? '');
        $textContent = $this->sensitive ? '' : ($this->text ?? '');
        $content = !empty($htmlContent) ? $htmlContent : $textContent;
        // Truncate content for logging (first 500 chars)
        $contentPreview = mb_substr($content, 0, 500);
        if (mb_strlen($content) > 500) {
            $contentPreview .= '...';
        }
        
        // Collect attachment filenames
        $attachments = [];
        if (!empty($this->file)) {
            foreach ($this->file as $file) {
                if (is_array($file)) {
                    $attachments[] = $file[0]; // filename from array
                } else if (is_string($file) && fs::fileExists($file)) {
                    $attachments[] = basename($file);
                }
            }
        }
        
        // Who sent it, as it stood then. The id and the address stay in the log
        // after the name or the address changes.
        $actor = \Systopic\System\Auth\Session::known() ? \Systopic\System\Auth\Session::current() : null;
        $username = $actor?->name;
        $userId = $actor?->user?->id;
        $userEmail = trim((string) ($actor?->user?->email ?? ''));
        
        // Try to send email
        $status = 'failed';
        $error = null;
        try {
            if ($this->unconfigured) {
                error_log('[mail] not sent: no "mail" block in the config of stage ' . (defined('STAGE') ? STAGE : '?'));
                throw new \RuntimeException('mail: no "mail" block in the config of this instance');
            }
            $result = parent::Send();
            $status = $result ? 'success' : 'failed';
        } catch (Exception $e) {
            $status = 'error';
            $error = \Systopic\System\Mail\Failure::detail($e->getMessage(), \Systopic\System\Mail\Delivery::settings());
        }
        
        // Log email
        $this->logEmail([
            'recipients' => $recipients,
            'sender' => $sender,
            'subject' => $subject,
            'content' => $contentPreview,
            'htmlContent' => $htmlContent,
            'textContent' => $textContent,
            'attachments' => $attachments,
            'status' => $status,
            'error' => $error,
            'username' => $username,
            'userId' => $userId,
            'userEmail' => $userEmail,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Re-throw exception if sending failed
        if ($status === 'error' && $error) {
            throw new Exception($error);
        }
        
        return $result ?? false;
    }

    // Log email to file
    private function logEmail($data) {
        // Use fs::$root if available, otherwise fallback to relative path
       
        $logDir = (string) fs::$root . 'var/log';
        $logFile = $logDir . '/email.log';
        
        // Create directory if it doesn't exist
        if (!fs::isDir($logDir)) {
            @fs::fsMkdir($logDir, 0755, true);
        }
        
        // Format log entry with JSON for structured data
        $logData = [
            'timestamp' => $data['timestamp'],
            'status' => $data['status'],
            'sender' => $data['sender'],
            'recipients' => $data['recipients'],
            'subject' => $data['subject'],
            'username' => $data['username'] ?? 'not logged in',
            'userId' => $data['userId'] ?? null,
            'userEmail' => $data['userEmail'] ?? '',
            'error' => $data['error'] ?? null,
            'attachments' => $data['attachments'] ?? [],
            'htmlContent' => $data['htmlContent'] ?? '',
            'textContent' => $data['textContent'] ?? '',
            'contentPreview' => $data['content']
        ];
        
        // Format log entry (human readable header + JSON data)
        $loggedId = $data['userId'] ?? null;
        $loggedMail = trim((string) ($data['userEmail'] ?? ''));
        $logEntry = sprintf(
            "[%s] Status: %s | From: %s | To: %s | Subject: %s | User: %s | UserId: %s | UserEmail: %s",
            $data['timestamp'],
            $data['status'],
            $data['sender'],
            implode(', ', $data['recipients']),
            $data['subject'],
            $data['username'] ?? 'not logged in',
            $loggedId === null || $loggedId === '' ? '-' : (string) $loggedId,
            $loggedMail !== '' ? $loggedMail : '-'
        );
        
        if (!empty($data['attachments'])) {
            $logEntry .= " | Attachments: " . implode(', ', $data['attachments']);
        }
        
        if ($data['error']) {
            $logEntry .= " | Error: " . $data['error'];
        }
        
        $logEntry .= "\n";
        $logEntry .= "Content: " . str_replace(["\r\n", "\n", "\r"], " ", $data['content']) . "\n";
        $logEntry .= "JSON_DATA: " . json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $logEntry .= str_repeat("-", 80) . "\n";
        
        // Append to log file
        @fs::file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
