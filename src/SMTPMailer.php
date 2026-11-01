<?php

/* * ***********************************************************
  Description: PHP Class for sending SMTP Mail
  Author     : halojoy  https://github.com/halojoy
  Copyright  : 2018 halojoy
  License    : MIT License  https://opensource.org/licenses/MIT
 * *********************************************************** */

if (!class_exists(\Systopic\System\Mail\Failure::class)) require_once __DIR__ . '/Mail/Failure.php';
if (!class_exists(\Systopic\System\Mail\Delivery::class)) require_once __DIR__ . '/Mail/Delivery.php';

Class SMTPMailer {

	private $server = 'smtp.gmail.com';
	private $port = 587;
	private $secure = 'tls';
	private $username = '';
	private $password = '';
	public $to = array();
	public $from = array();
	public $cc = array();
	public $bcc = array();
	public $reply_to = array();
	public $subject = 'No subject';
	public $body = '';
	public $text = '';
	public $file = array();
	public $charset = 'UTF-8';
	public $transferEncoding = 'quoted-printable';
	private $headers;
	private $ahead;
	private $sock;
	private $hostname;
	private $local;
	private $log = array();

	public function __construct($server = false, $port = false, $secure = false) {
		// Setup basic configuration
		if (fs::fileExists('conf/config_smtp.php')) {
			include 'conf/config_smtp.php';
			$this->server = $cfg_server;
			$this->port = $cfg_port;
			$this->secure = $cfg_secure;
			$this->username = $cfg_username;
			$this->password = $cfg_password;
		}
		if ($server !== false) {
			$this->server = $server;
			$this->username = '';
			$this->password = '';
		}
		if ($port !== false)
			$this->port = $port;
		if ($secure !== false)
			$this->secure = $secure;

		// Define connection hostname and localhost
		$this->hostname = $this->server;
		$this->secure = strtolower($this->secure);
		if ($this->secure == 'tls')
			$this->hostname = 'tcp://' . $this->server;
		if ($this->secure == 'ssl')
			$this->hostname = 'ssl://' . $this->server;
		if (!empty($_SERVER['HTTP_HOST']))
			$this->local = $_SERVER['HTTP_HOST'];
		elseif (!empty($_SERVER['SERVER_NAME']))
			$this->local = $_SERVER['SERVER_NAME'];
		else
			$this->local = $_SERVER['SERVER_ADDR'] ?? 'localhost'; // CLI has none
		$host = parse_url('http://' . $this->local, PHP_URL_HOST) ?: 'localhost';
        $this->local = filter_var($host, FILTER_VALIDATE_IP) ? '[' . (str_contains($host, ':') ? 'IPv6:' : '') . $host . ']' : (preg_match('/^[a-zA-Z0-9.-]+$/', $host) ? $host : 'localhost');
		if ($this->username)
			$this->from = array($this->username, '');
		if (!defined("NL")) {
			define("NL", "\r\n");
		}
	}

	// Authentication Login
	public function Auth($user, $pass) {
		$this->username = $user;
		$this->password = $pass;
	}

	// Set from email address
	public function From($address, $name = '') {
		$this->from = array($address, $name);
	}

	// Add email reply to address
	public function addReplyTo($address, $name = '') {
		$this->reply_to[] = array($address, $name);
	}

	// Add recipient email address
	public function addTo($address, $name = '') {
		$this->to[] = array($address, $name);
	}

	// Add carbon copy email address
	public function addCc($address, $name = '') {
		$this->cc[] = array($address, $name);
	}

	// Add blind carbon copy email address
	public function addBcc($address, $name = '') {
		$this->bcc[] = array($address, $name);
	}

	// Set email subject
	public function Subject($subject) {
		$this->subject = $subject;
	}

	// Set email html body
	public function Body($html) {
		$this->body = $html;
	}

	// Set email plain text
	public function Text($text) {
		$this->text = $text;
	}

	// Add attachment file
	public function File($path) {
		$this->file[] = $path;
	}

	// Set charset. Default 'UTF-8'
	public function Charset($charset) {
		$this->charset = $charset;
	}

	// Set Content Transfer Encoding. Default 'quoted-printable'
	public function TransferEncoding($encode) {
		$this->transferEncoding = $encode;
	}

	// Display current log file
	public function ShowLog() {
		echo '<pre>';
		echo '<b>SMTP Mail Transaction Log</b><br>';
		print_r($this->log);
	}

	// Display current headers
	public function ShowHeaders() {
		echo '<pre>';
		echo '<b>SMTP Mail Headers</b><br>';
		echo htmlspecialchars($this->doHeaders(false));
	}

	// Send the SMTP Mail
	public function Send() {
        \Systopic\System\Mail\Delivery::prepare($this);
		// Prepare data for sending
		$this->headers = $this->doHeaders();
		$user64 = base64_encode($this->username);
		$pass64 = base64_encode($this->password);
		$mailfrom = '<' . $this->from[0] . '>';
		foreach (array_merge($this->to, $this->cc, $this->bcc) as $address) {
            if (!filter_var($address[0], FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('SMTP: invalid recipient address');
            $mailto[] = '<' . $address[0] . '>';
        }

		// Open server connection and run transfers
		$this->sock = @fsockopen($this->hostname, $this->port, $enum, $estr, 30);
		if (!$this->sock)
			throw new \RuntimeException("SMTP: no connection to $this->hostname:$this->port ($estr)");
		$this->log[] = 'CONNECTION: fsockopen(' . $this->hostname . ')';
		$this->response('220');
		$this->logreq('EHLO ' . $this->local, '250');

		if ($this->secure == 'tls') {
			$this->logreq('STARTTLS', '220');
            if (@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                fclose($this->sock);
                throw new \RuntimeException('SMTP: TLS handshake failed; check encryption, port and certificate');
            }
			$this->logreq('EHLO ' . $this->local, '250');
		}

		// without an account no AUTH - a local catcher (Mailpit) takes it so
		if ($this->username !== '') {
			$this->logreq('AUTH LOGIN', '334');
			$this->logreq($user64, '334');
			$this->logreq($pass64, '235');
		}

		$this->logreq('MAIL FROM: ' . $mailfrom, '250');
		foreach ($mailto as $address)
			$this->logreq('RCPT TO: ' . $address, '250');

		$this->logreq('DATA', '354');
		$this->log[] = htmlspecialchars($this->doHeaders(false));
		$wire = preg_replace('/(?m)^\./', '..', $this->headers);
		$this->request(rtrim($wire, "\r\n") . NL . '.', '250');
		$this->logreq('QUIT', '221');
		fclose($this->sock);

		return true;
	}

	// Log command and do request
	private function logreq($cmd, $code) {
		$this->log[] = htmlspecialchars($cmd);
		$this->request($cmd, $code);
		return;
	}

	// Send one command and test response
	private function request($cmd, $code) {
		fwrite($this->sock, $cmd . NL);
		$this->response($code);
		return;
	}

	// Read and verify response code
	private function response($code) {
		stream_set_timeout($this->sock, 8);
		$result = fread($this->sock, 768);
		$meta = stream_get_meta_data($this->sock);
		// errors throw, they never end the request; the log stays out of the
		// message - it holds the account in base64
		if ($meta['timed_out'] === true) {
			fclose($this->sock);
			throw new \RuntimeException("SMTP: $this->hostname did not answer (expected $code)");
		}
		$this->log[] = $result;
		if (substr($result, 0, 3) == $code)
			return;
		fclose($this->sock);
		throw new \RuntimeException("SMTP: expected $code from $this->hostname, got: " . \Systopic\System\Mail\Failure::detail(trim((string) $result), ['user'=>$this->username,'password'=>$this->password]));
	}

	// Do create headers after precheck
	private function doHeaders($filedata = true) {
		// Precheck. Test if we have necessary data
		if (empty($this->from) && $this->username !== '')
			$this->from = array($this->username, '');
		if (empty($this->from) || !filter_var($this->from[0], FILTER_VALIDATE_EMAIL))
			throw new \RuntimeException('SMTP: no valid sender address');
		if (empty($this->to) || !filter_var($this->to[0][0], FILTER_VALIDATE_EMAIL))
			throw new \RuntimeException('SMTP: no valid recipient address');
		if (strlen(trim($this->body)) < 3 && strlen(trim($this->text)) < 3)
			throw new \RuntimeException('SMTP: no message to send');

		// Create Headers
		$headerstring = '';
		$this->createHeaders($filedata);
		foreach ($this->ahead as $val) {
			$headerstring .= $val . NL;
		}

		return rtrim($headerstring);
	}

	private function createHeaders($filedata) {
        $this->ahead = [
            'Date: ' . date('r'),
            'To: ' . $this->formatAddressList($this->to),
            'From: ' . $this->formatAddress($this->from),
        ];
        if ($this->cc) $this->ahead[] = 'Cc: ' . $this->formatAddressList($this->cc);
        // Bcc recipients belong to the SMTP envelope, never the delivered header.
        if ($this->reply_to) $this->ahead[] = 'Reply-To: ' . $this->formatAddressList($this->reply_to);
        $this->ahead[] = 'Subject: ' . $this->encodeHeader((string) $this->subject);
        $this->ahead[] = 'Message-ID: ' . $this->generateMessageID();
        $this->ahead[] = 'MIME-Version: 1.0';
        if ($this->body !== '' && $this->text === '') $this->text = $this->plainText((string) $this->body);
        $content = $this->messageContent();
        if (!$this->file) {
            $this->ahead[] = $content;
            return;
        }
        $boundary = 'fluxo-mixed-' . bin2hex(random_bytes(16));
        $this->ahead[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $this->ahead[] = '';
        $this->ahead[] = '--' . $boundary;
        $this->ahead[] = $content;
        foreach ($this->file as $attachment) {
            $name = is_array($attachment) ? (string) $attachment[0] : basename($attachment);
            if (!is_array($attachment) && !is_file($attachment)) throw new \RuntimeException('SMTP: attachment not found');
            $this->safeHeader($name);
            $fallback = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $this->ahead[] = '--' . $boundary;
            $this->ahead[] = 'Content-Type: application/octet-stream';
            $this->ahead[] = 'Content-Disposition: attachment;' . NL . (preg_match('/^[a-zA-Z0-9._-]+$/', $name) ? "\t" . 'filename="' . $fallback . '"' : "\tfilename*=UTF-8''" . rawurlencode($name));
            $this->ahead[] = 'Content-Transfer-Encoding: base64';
            $this->ahead[] = '';
            $bytes = $filedata ? (is_array($attachment) ? $attachment[1] : file_get_contents($attachment)) : '';
            if ($bytes === false) throw new \RuntimeException('SMTP: attachment could not be read');
            $this->ahead[] = rtrim(chunk_split(base64_encode($bytes), 76, NL), "\r\n");
        }
        $this->ahead[] = '--' . $boundary . '--';
    }

    private function messageContent(): string {
        if ($this->text !== '' && $this->body !== '') {
            $boundary = 'fluxo-alt-' . bin2hex(random_bytes(16));
            return 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . NL . NL
                . '--' . $boundary . NL . $this->contentPart('plain', $this->text) . NL
                . '--' . $boundary . NL . $this->contentPart('html', $this->body) . NL
                . '--' . $boundary . '--';
        }
        return $this->text !== '' ? $this->contentPart('plain', $this->text) : $this->contentPart('html', $this->body);
    }

    private function contentPart(string $type, string $content): string {
        $this->safeHeader((string) $this->charset);
        $content = preg_replace('/\r\n|\r|\n/', NL, $content);
        $encoding = strtolower((string) $this->transferEncoding);
        $encoded = match ($encoding) {
            'quoted-printable' => quoted_printable_encode($content),
            'base64' => rtrim(chunk_split(base64_encode($content), 76, NL), "\r\n"),
            '7bit', '8bit' => $content,
            default => throw new \RuntimeException('SMTP: unsupported transfer encoding'),
        };
        return 'Content-Type: text/' . $type . '; charset="' . $this->charset . '"' . NL
            . 'Content-Transfer-Encoding: ' . $encoding . NL . NL . $encoded;
    }

    private function plainText(string $html): string {
        $html = preg_replace('~<(style|script)\b[^>]*>.*?</\1>~is', '', $html);
        $html = preg_replace_callback('~<a\b[^>]*href=["\x27]([^"\x27]*)["\x27][^>]*>(.*?)</a>~is',
            static fn($m) => strip_tags($m[2]) . ' (' . $m[1] . ')', $html);
        $html = preg_replace('~<br\s*/?>|</(?:p|div|h[1-6]|tr|li)>~i', "\n", $html);
        return trim(preg_replace('/\n[\t ]*\n(?:[\t ]*\n)+/', "\n\n", html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function safeHeader(string $value): void {
        if (preg_match('/[\r\n\x00]/', $value)) throw new \RuntimeException('SMTP: invalid header value');
    }

    private function encodeHeader(string $value): string {
        $this->safeHeader($value);
        // RFC 2047 encoded words, kept below 75 characters without splitting UTF-8.
        $words = []; $chunk = '';
        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (strlen($chunk . $char) > 42) { $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?='; $chunk = ''; }
            $chunk .= $char;
        }
        if ($chunk !== '') $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
        return implode(NL . "\t", $words);
    }

    private function formatAddress($address) {
        if (!filter_var($address[0], FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('SMTP: invalid email address');
        $name = (string) ($address[1] ?? '');
        return $name === '' ? $address[0] : $this->encodeHeader($name) . NL . "\t<" . $address[0] . '>';
    }

    private function formatAddressList($addresses) {
        return implode(',' . NL . "\t", array_map(fn($address) => $this->formatAddress($address), $addresses));
    }

    protected function generateMessageID() {
        $domain = substr((string) $this->from[0], strrpos((string) $this->from[0], '@') + 1);
        return '<' . bin2hex(random_bytes(20)) . '@' . $domain . '>';
    }
}
