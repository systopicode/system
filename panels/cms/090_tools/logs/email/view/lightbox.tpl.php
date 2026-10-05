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
    <div class="cmslightbox cms" data-size="2">
	<section>
	    <header>
		<h1>Email Log Entry</h1>
		<ul class="barmenu">
		    <li>
			<a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
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
	<div class="cmslightbox cms" data-size="2">
	    <section>
		<header>
		    <h1>Email Log Entry</h1>
		    <ul class="barmenu">
			<li>
			    <a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
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
	<div class="cmslightbox cms" data-size="2">
	    <section>
		<header>
		    <h1>Email Details</h1>
		    <ul class="barmenu">
			<li>
			    <a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
			</li>
		    </ul>
		</header>
		<div class="layout">
		    <main>
			<div>
			    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
				<h2 style="margin-top: 0;"><?= htmlspecialchars($subject) ?></h2>
				<table style="width: 100%; margin-bottom: 20px;">
				    <tr>
					<td style="width: 100px; font-weight: bold;">From:</td>
					<td><?= htmlspecialchars($from) ?></td>
				    </tr>
				    <tr>
					<td style="font-weight: bold;">To:</td>
					<td><?= htmlspecialchars($to) ?></td>
				    </tr>
				    <tr>
					<td style="font-weight: bold;">Date:</td>
					<td><?= htmlspecialchars($timestamp) ?></td>
				    </tr>
				    <tr>
					<td style="font-weight: bold;">Status:</td>
					<td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
				    </tr>
				    <?php if ($error): ?>
				    <tr>
					<td style="font-weight: bold; color: red;">Error:</td>
					<td style="color: red;"><?= htmlspecialchars($error) ?></td>
				    </tr>
				    <?php endif; ?>
				    <?php if (!empty($attachments)): ?>
				    <tr>
					<td style="font-weight: bold;">Attachments:</td>
					<td>
					    <?php foreach ($attachments as $attachment): ?>
						<span class="badge"><?= htmlspecialchars($attachment) ?></span>
					    <?php endforeach; ?>
					</td>
				    </tr>
				    <?php endif; ?>
				</table>
				
				<div style="border-top: 1px solid #ddd; padding-top: 20px;">
				    <?php if (!empty($htmlContent)): ?>
					<div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 4px;">
						<h3 style="margin-top: 0;">HTML Content:</h3>
						<div style="border: 1px solid #ccc; background: white; max-height: 600px; overflow: auto;">
							<iframe id="emailContentFrame" style="width: 100%; height: 600px; border: none;"></iframe>
							<script>
							    (function() {
								var frame = document.getElementById('emailContentFrame');
								var content = <?= json_encode($htmlContent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
								frame.srcdoc = content;
							    })();
							</script>
						</div>
					</div>
				    <?php elseif (!empty($textContent)): ?>
					<div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 4px;">
						<h3 style="margin-top: 0;">Text Content:</h3>
						<pre style="white-space: pre-wrap; font-family: inherit; background: white; padding: 15px; border: 1px solid #ccc; max-height: 600px; overflow: auto;"><?= htmlspecialchars($textContent) ?></pre>
					</div>
				    <?php else: ?>
					<p>No content available.</p>
				    <?php endif; ?>
				</div>
			    </div>
			</div>
		    </main>
		    <aside>
			<div>
			    <h3>Details</h3>
			    <div>
				<p><strong>User:</strong><br><?= htmlspecialchars($user) ?></p>
				<p><strong>Recipients:</strong><br><?= htmlspecialchars(implode(', ', $recipients)) ?></p>
			    </div>
			</div>
		    </aside>
		</div>
	    </section>
	</div>
	<?php
    }
}
