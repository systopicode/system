<?php
// Define EMAILLOGFILE constant if not already defined
if (!defined('EMAILLOGFILE')) {
    define('EMAILLOGFILE', fs::$root . 'var/log/email.log');
}

$logFile = EMAILLOGFILE;
$output = @fs::file_get_contents($logFile);
$entryIndex = (int)http::get('index', 0);

if (empty($output)) {
    ?>
    <div class="cmslightbox cms" data-size="1">
	<section>
	    <header>
		<h1>Email Log Entry</h1>
		<ul class="barmenu">
		    <li>
			<a class="ajax" href="<?= htmlspecialchars($this->parent->getHref(\http::$root)) ?>"><i class="cmsIcon cmsIcon-xmark"></i></a>
		    </li>
		</ul>
	    </header>
	    <main>
		<p>Log file is empty or entry not found.</p>
	    </main>
	</section>
    </div>
    <?php
} else {
    // Split entries by separator line
    $entries = explode(str_repeat("-", 80) . "\n", $output);
    $entries = array_filter($entries, function($entry) {
	return !empty(trim($entry));
    });
    $entries = array_values($entries); // Re-index array
    
    if (!isset($entries[$entryIndex])) {
	?>
	<div class="cmslightbox cms" data-size="1">
	    <section>
		<header>
		    <h1>Email Log Entry</h1>
		    <ul class="barmenu">
			<li>
			    <a class="ajax" href="<?= htmlspecialchars($this->parent->getHref(\http::$root)) ?>"><i class="cmsIcon cmsIcon-xmark"></i></a>
			</li>
		    </ul>
		</header>
		<main>
		    <p>Entry not found.</p>
		</main>
	    </section>
	</div>
	<?php
    } else {
	$entry = $entries[$entryIndex];
	$lines = explode("\n", trim($entry));
	
	// Parse header line
	$headerLine = $lines[0];
	
	// Extract JSON data
	$jsonData = null;
	foreach ($lines as $line) {
	    if (strpos($line, 'JSON_DATA:') === 0) {
		$jsonStr = substr($line, strlen('JSON_DATA: '));
		$jsonData = json_decode($jsonStr, true);
		break;
	    }
	}
	
	// Extract data from header or JSON
	preg_match('/\[([^\]]+)\]/', $headerLine, $timestampMatch);
	$timestamp = $timestampMatch[1] ?? '';
	
	preg_match('/Status:\s*([^|]+)/', $headerLine, $statusMatch);
	$status = trim($statusMatch[1] ?? 'unknown');
	
	preg_match('/From:\s*([^|]+)/', $headerLine, $fromMatch);
	$from = trim($fromMatch[1] ?? '');
	
	preg_match('/To:\s*([^|]+)/', $headerLine, $toMatch);
	$to = trim($toMatch[1] ?? '');
	
	preg_match('/Subject:\s*([^|]+)/', $headerLine, $subjectMatch);
	$subject = trim($subjectMatch[1] ?? '');
	
	preg_match('/User:\s*(.+?)(?:\s*\||$)/', $headerLine, $userMatch);
	$user = trim($userMatch[1] ?? 'not logged in');
	if ($user === 'not logged in' && is_array($jsonData) && !empty($jsonData['username'])) {
	    $user = trim((string) $jsonData['username']);
	}

	preg_match('/UserId:\s*([^|]+)/', $headerLine, $userIdMatch);
	$userId = trim($userIdMatch[1] ?? '');
	if (($userId === '' || $userId === '-') && is_array($jsonData) && isset($jsonData['userId']) && $jsonData['userId'] !== '' && $jsonData['userId'] !== null) {
	    $userId = (string) $jsonData['userId'];
	}
	if ($userId === '-') {
	    $userId = '';
	}

	preg_match('/UserEmail:\s*([^|]+)/', $headerLine, $userEmailMatch);
	$userEmail = trim($userEmailMatch[1] ?? '');
	if (($userEmail === '' || $userEmail === '-') && is_array($jsonData) && !empty($jsonData['userEmail'])) {
	    $userEmail = trim((string) $jsonData['userEmail']);
	}
	if ($userEmail === '-') {
	    $userEmail = '';
	}
	
	preg_match('/Error:\s*(.+?)(?:\s*\||$)/', $headerLine, $errorMatch);
	$error = trim($errorMatch[1] ?? '');
	
	// Get data from JSON if available
	$htmlContent = $jsonData['htmlContent'] ?? '';
	$textContent = $jsonData['textContent'] ?? '';
	$attachments = $jsonData['attachments'] ?? [];
	$recipients = $jsonData['recipients'] ?? [];
	
	// Status badge color
	$statusClass = '';
	if ($status === 'success') {
	    $statusClass = 'green';
	} elseif ($status === 'error' || $status === 'failed') {
	    $statusClass = 'red';
	}
	?>
	<?php
	$pill = ($status === 'error' || $status === 'failed') ? 'is-bad' : ($status === 'success' ? 'is-ok' : '');
	$who = $recipients !== [] ? implode(', ', $recipients) : $to;
	?>
	<div class="cmslightbox cms" data-size="1">
	    <section>
		<header>
		    <h1><?= htmlspecialchars($subject !== '' ? $subject : 'Email') ?></h1>
		    <ul class="barmenu">
			<li>
			    <a class="ajax" href="<?= htmlspecialchars($this->parent->getHref(\http::$root)) ?>"><i class="cmsIcon cmsIcon-xmark"></i></a>
			</li>
		    </ul>
		</header>
		<main class="mailLog">
		    <dl>
			<div><dt>From</dt><dd><?= htmlspecialchars($from) ?></dd></div>
			<div><dt>To</dt><dd><?= htmlspecialchars($who) ?></dd></div>
			<div><dt>Date</dt><dd><?= htmlspecialchars($timestamp) ?></dd></div>
			<div><dt>Status</dt><dd><span class="logPill <?= $pill ?>"><?= htmlspecialchars($status) ?></span></dd></div>
			<?php if ($user !== '' && $user !== 'not logged in') { ?>
			    <div><dt>Sent by</dt><dd><?= htmlspecialchars($user) ?><?php if ($userId !== '') { ?> <span class="logActorId">#<?= htmlspecialchars($userId) ?></span><?php } ?></dd></div>
			<?php } ?>
			<?php if ($userEmail !== '') { ?>
			    <div><dt>Email</dt><dd><?= htmlspecialchars($userEmail) ?></dd></div>
			<?php } ?>
			<?php if ($attachments !== []) { ?>
			    <div><dt>Attachments</dt><dd><?= htmlspecialchars(implode(', ', $attachments)) ?></dd></div>
			<?php } ?>
		    </dl>
		    <?php if ($error !== '') { ?>
			<p class="logError"><?= htmlspecialchars($error) ?></p>
		    <?php } ?>
		    <?php if ($htmlContent !== '') { ?>
			<?php
                // Logged HTML is untrusted. Keep formatting, block scripts and remote resources.
                $previewHtml = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'; form-action \'none\'">' . $htmlContent;
            ?>
            <iframe class="mailPreview" title="Message" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= htmlspecialchars($previewHtml, ENT_QUOTES) ?>"></iframe>
		    <?php } elseif ($textContent !== '') { ?>
			<pre class="mailPreview"><?= htmlspecialchars($textContent) ?></pre>
		    <?php } else { ?>
			<p>No content was stored.</p>
		    <?php } ?>
		</main>
	    </section>
	</div>
	<?php
    }
}
