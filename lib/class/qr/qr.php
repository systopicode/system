<?php

#[AllowDynamicProperties]
class qr {

	static function svg(string $payload, int $moduleSize = 6, string $ecc = 'M'): string {
		$payload = trim($payload);
		if ($payload === '') {
			return '';
		}
		if (!self::ensureBarcodeLibLoaded()) {
			return '';
		}
		$ecc = strtoupper(trim($ecc));
		if (!in_array($ecc, ['L', 'M', 'Q', 'H'], true)) {
			$ecc = 'M';
		}
		try {
			$barcode = new TCPDF2DBarcode($payload, 'QRCODE,' . $ecc);
			$array = $barcode->getBarcodeArray();
		} catch (Throwable $e) {
			return '';
		}
		if (
			!is_array($array)
			|| empty($array['num_rows'])
			|| empty($array['num_cols'])
			|| empty($array['bcode'])
		) {
			return '';
		}
		$moduleSize = max(1, $moduleSize);
		return $barcode->getBarcodeSVGcode($moduleSize, $moduleSize, '#111111');
	}

	static function svgMarkup(string $payload, int $moduleSize = 6, string $ecc = 'M', string $className = 'qr-code'): string {
		$svg = self::svg($payload, $moduleSize, $ecc);
		if ($svg === '') {
			return '';
		}
		$className = trim($className);
		if ($className === '') {
			return $svg;
		}
		if (preg_match('~<svg\b[^>]*class=~i', $svg)) {
			return preg_replace_callback('~<svg\b([^>]*)class=(["\'])(.*?)\2([^>]*)>~i', function($m) use ($className) {
				$existing = trim((string) $m[3]);
				$merged = trim($existing . ' ' . $className);
				$svgOpen = '<svg' . $m[1] . 'class="' . htmlspecialchars($merged, ENT_QUOTES) . '"' . $m[4] . '>';
				return self::normalizeSvgOpenTag($svgOpen);
			}, $svg, 1) ?? $svg;
		}
		$svg = preg_replace('~<svg\b~i', '<svg class="' . htmlspecialchars($className, ENT_QUOTES) . '"', $svg, 1) ?? $svg;
		return self::normalizeSvgOpenTag($svg);
	}

	static function svgDataUri(string $payload, int $moduleSize = 6, string $ecc = 'M'): string {
		$svg = self::svg($payload, $moduleSize, $ecc);
		if ($svg === '') {
			return '';
		}
		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}

	static function payload(string $type, array $data = []): string {
		return match (strtolower(trim($type))) {
			'url' => self::payloadUrl((string) ($data['url'] ?? '')),
			'event' => self::payloadEvent($data),
			'vcard' => self::payloadVcard($data),
			'email' => self::payloadEmail((string) ($data['email'] ?? ''), (string) ($data['subject'] ?? ''), (string) ($data['body'] ?? '')),
			'sms' => self::payloadSms((string) ($data['phone'] ?? ''), (string) ($data['message'] ?? '')),
			'telefon', 'phone', 'tel' => self::payloadTelefon((string) ($data['phone'] ?? '')),
			default => '',
		};
	}

	static function payloadUrl(string $url): string {
		return trim($url);
	}

	static function payloadTelefon(string $phone): string {
		$normalized = self::normalizePhoneForUri($phone);
		if ($normalized === '') {
			return '';
		}
		return 'tel:' . $normalized;
	}

	static function payloadSms(string $phone, string $message = ''): string {
		$normalized = self::normalizePhoneForUri($phone);
		if ($normalized === '') {
			return '';
		}
		$message = trim($message);
		if ($message === '') {
			return 'SMSTO:' . $normalized;
		}
		return 'SMSTO:' . $normalized . ':' . $message;
	}

	static function payloadEmail(string $email, string $subject = '', string $body = ''): string {
		$email = trim($email);
		if ($email === '') {
			return '';
		}
		$query = [];
		if (trim($subject) !== '') {
			$query['subject'] = $subject;
		}
		if (trim($body) !== '') {
			$query['body'] = $body;
		}
		$queryString = $query ? ('?' . http_build_query($query)) : '';
		return 'mailto:' . $email . $queryString;
	}

	static function payloadEvent(array $data): string {
		$title = self::escapeIcs((string) ($data['title'] ?? ''));
		$description = self::escapeIcs((string) ($data['description'] ?? ''));
		$location = self::escapeIcs((string) ($data['location'] ?? ''));
		$url = trim((string) ($data['url'] ?? ''));
		$dtStart = self::toIcsDate((string) ($data['date_from'] ?? ''));
		$dtEnd = self::toIcsDate((string) ($data['date_to'] ?? ''));

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//fluxo//QR Event//DE',
			'BEGIN:VEVENT',
		];
		if ($title !== '') {
			$lines[] = 'SUMMARY:' . $title;
		}
		if ($dtStart !== '') {
			$lines[] = 'DTSTART:' . $dtStart;
		}
		if ($dtEnd !== '') {
			$lines[] = 'DTEND:' . $dtEnd;
		}
		if ($location !== '') {
			$lines[] = 'LOCATION:' . $location;
		}
		if ($description !== '') {
			$lines[] = 'DESCRIPTION:' . $description;
		}
		if ($url !== '') {
			$lines[] = 'URL:' . $url;
		}
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';
		return implode("\r\n", $lines);
	}

	static function payloadVcard(array $data): string {
		$firstName = trim((string) ($data['first_name'] ?? ''));
		$lastName = trim((string) ($data['last_name'] ?? ''));
		$fullName = trim((string) ($data['full_name'] ?? ($firstName . ' ' . $lastName)));
		if ($fullName === '') {
			$fullName = trim((string) ($data['company'] ?? ''));
		}

		$company = trim((string) ($data['company'] ?? ''));
		$title = trim((string) ($data['title'] ?? ''));
		$email = trim((string) ($data['email'] ?? ''));
		$phone = trim((string) ($data['phone'] ?? ''));
		$mobile = trim((string) ($data['mobile'] ?? ''));
		$street = trim((string) ($data['street'] ?? ''));
		$city = trim((string) ($data['city'] ?? ''));
		$plz = trim((string) ($data['plz'] ?? ''));
		$country = trim((string) ($data['country'] ?? ''));
		$url = trim((string) ($data['url'] ?? ''));
		$note = trim((string) ($data['note'] ?? ''));
		$photo = trim((string) ($data['photo_url'] ?? ''));
		$photoBase64 = trim((string) ($data['photo_base64'] ?? ''));
		$photoMime = strtolower(trim((string) ($data['photo_mime'] ?? '')));

		$lines = [
			'BEGIN:VCARD',
			'VERSION:3.0',
			'N:' . self::escapeVcard($lastName) . ';' . self::escapeVcard($firstName) . ';;;',
			'FN:' . self::escapeVcard($fullName),
		];
		if ($company !== '') {
			$lines[] = 'ORG:' . self::escapeVcard($company);
		}
		if ($title !== '') {
			$lines[] = 'TITLE:' . self::escapeVcard($title);
		}
		if ($email !== '') {
			$lines[] = 'EMAIL;TYPE=INTERNET:' . self::escapeVcard($email);
		}
		if ($phone !== '') {
			$lines[] = 'TEL;TYPE=WORK,VOICE:' . self::escapeVcard($phone);
		}
		if ($mobile !== '') {
			$lines[] = 'TEL;TYPE=CELL,VOICE:' . self::escapeVcard($mobile);
		}
		if ($street !== '' || $city !== '' || $plz !== '' || $country !== '') {
			$lines[] = 'ADR;TYPE=WORK:;;' . self::escapeVcard($street) . ';' . self::escapeVcard($city) . ';;' . self::escapeVcard($plz) . ';' . self::escapeVcard($country);
		}
		if ($url !== '') {
			$lines[] = 'URL:' . self::escapeVcard($url);
		}
		if ($photoBase64 !== '') {
			$type = match ($photoMime) {
				'image/png' => 'PNG',
				'image/gif' => 'GIF',
				default => 'JPEG',
			};
			$lines[] = 'PHOTO;ENCODING=BASE64;TYPE=' . $type . ':' . self::foldVcardBase64($photoBase64);
		} elseif ($photo !== '') {
			$lines[] = 'PHOTO;VALUE=URI:' . self::escapeVcard($photo);
		}
		if ($photo !== '') {
			$lines[] = 'X-PHOTO-URL:' . self::escapeVcard($photo);
		}
		if ($note !== '') {
			$lines[] = 'NOTE:' . self::escapeVcard($note);
		}
		$lines[] = 'END:VCARD';
		return implode("\r\n", $lines);
	}

	static function normalizePhoneForUri(string $phone): string {
		$phone = trim($phone);
		if ($phone === '') {
			return '';
		}
		$clean = preg_replace('/[^0-9+]/', '', $phone);
		if (str_starts_with($clean, '00')) {
			$clean = '+' . substr($clean, 2);
		}
		if ($clean !== '' && $clean[0] !== '+' && str_starts_with($clean, '0')) {
			$clean = '+49' . substr($clean, 1);
		}
		return $clean;
	}

	private static function escapeIcs(string $value): string {
		$value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
		$value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $value);
		return $value;
	}

	private static function toIcsDate(string $value): string {
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		$ts = strtotime($value);
		if (!$ts) {
			return '';
		}
		return date('Ymd\THis', $ts);
	}

	private static function escapeVcard(string $value): string {
		$value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
		$value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $value);
		return $value;
	}

	private static function foldVcardBase64(string $base64): string {
		$base64 = preg_replace('/\s+/', '', $base64 ?? '');
		if ($base64 === '') {
			return '';
		}
		$chunks = str_split($base64, 72);
		return implode("\r\n ", $chunks);
	}

	private static function normalizeSvgOpenTag(string $svg): string {
		return preg_replace_callback('~<svg\b([^>]*)>~i', function($m) {
			$attrs = (string) $m[1];
			$width = null;
			$height = null;
			$hasViewBox = (bool) preg_match('~\bviewBox\s*=\s*(["\']).*?\1~i', $attrs);

			if (preg_match('~\bwidth\s*=\s*(["\'])(.*?)\1~i', $attrs, $wMatch)) {
				$width = (float) preg_replace('/[^0-9.]/', '', (string) $wMatch[2]);
			}
			if (preg_match('~\bheight\s*=\s*(["\'])(.*?)\1~i', $attrs, $hMatch)) {
				$height = (float) preg_replace('/[^0-9.]/', '', (string) $hMatch[2]);
			}

			$attrs = preg_replace('~\s+\bwidth\s*=\s*(["\']).*?\1~i', '', $attrs) ?? $attrs;
			$attrs = preg_replace('~\s+\bheight\s*=\s*(["\']).*?\1~i', '', $attrs) ?? $attrs;
			$attrs = trim($attrs);

			$append = ' width="100%" height="100%"';
			if (!$hasViewBox && $width > 0 && $height > 0) {
				$append .= ' viewBox="0 0 ' . $width . ' ' . $height . '"';
			}

			return '<svg' . ($attrs !== '' ? ' ' . $attrs : '') . $append . '>';
		}, $svg, 1) ?? $svg;
	}

	private static function ensureBarcodeLibLoaded(): bool {
		if (class_exists('TCPDF2DBarcode', false)) {
			return true;
		}

		$candidates = [];
		if (fs::$sysRoot !== null && fs::$sysRoot->exists) {
			$candidates[] = fs::toNative(rtrim((string) fs::$sysRoot, '/') . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php');
		}
		if (fs::$projectRoot !== null && fs::$projectRoot->exists) {
			$candidates[] = fs::toNative(rtrim((string) fs::$projectRoot, '/') . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php');
		}
		$candidates[] = dirname(__DIR__, 2) . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';

		foreach ($candidates as $file) {
			if (fs::isFile($file)) {
				require_once $file;
				if (class_exists('TCPDF2DBarcode', false)) {
					return true;
				}
			}
		}
		return false;
	}
}
