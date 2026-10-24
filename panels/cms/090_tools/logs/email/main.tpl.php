<h1>Email log</h1>
<p class="tagsHint">What was sent, and what failed. The eye opens one message.</p>
<section class="widget is-list logList">
	<header>
		<h2>Messages</h2>
		<input class="listSearch" type="search" placeholder="Search" data-on_input="filterLog" autocomplete="off">
		<a target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'downloadLog') ?>" class="button" title="Download"><i class="fa-regular fa-file-arrow-down"></i><span>Download</span></a>
		<a data-on_click="userConfirm" data-action="?action=clearLog&value=email" data-confirm="Delete this log file?" class="button" title="Clear"><i class="fa-regular fa-broom"></i><span>Clear</span></a>
	</header>
    <?php
    // Define EMAILLOGFILE constant if not already defined
    if (!defined('EMAILLOGFILE')) {
	define('EMAILLOGFILE', fs::$root . 'var/log/email.log');
    }
    $logFile = EMAILLOGFILE;
    $output = @fs::file_get_contents($logFile);
    
    if (empty($output)) {
	?>
	<p>The log is empty.</p>
	<?php
    } else {
	// Split entries by separator line
	$entries = explode(str_repeat("-", 80) . "\n", $output);
	$entries = array_filter($entries, function($entry) {
	    return !empty(trim($entry));
	});
	?>
	<table>
	    <thead>
		<tr>
		    <th>Date</th>
		    <th>Status</th>
		    <th>To</th>
		    <th>Subject</th>
		    <th></th>
		</tr>
	    </thead>
	    <tbody>
		<?php
		$entryIndex = 0;
		$rows = [];
		foreach ($entries as $entry) {
		    $lines = explode("\n", trim($entry));
		    if (empty($lines)) continue;
		    
		    // Parse first line: [timestamp] Status: ... | From: ... | To: ... | Subject: ... | User: ...
		    $headerLine = $lines[0];
		    
		    // Try to extract JSON data
		    $jsonData = null;
		    foreach ($lines as $line) {
			if (strpos($line, 'JSON_DATA:') === 0) {
			    $jsonStr = substr($line, strlen('JSON_DATA: '));
			    $jsonData = json_decode($jsonStr, true);
			    break;
			}
		    }
		    
		    // Extract timestamp
		    preg_match('/\[([^\]]+)\]/', $headerLine, $timestampMatch);
		    $timestamp = $timestampMatch[1] ?? '';
		    $dateOrder = $timestamp ? date('Y-m-d H:i:s', strtotime($timestamp)) : '';
		    
		    // Extract status
		    preg_match('/Status:\s*([^|]+)/', $headerLine, $statusMatch);
		    $status = trim($statusMatch[1] ?? 'unknown');
		    
		    // Extract From
		    preg_match('/From:\s*([^|]+)/', $headerLine, $fromMatch);
		    $from = trim($fromMatch[1] ?? '');
		    
		    // Extract To
		    preg_match('/To:\s*([^|]+)/', $headerLine, $toMatch);
		    $to = trim($toMatch[1] ?? '');
		    
		    // Extract Subject
		    preg_match('/Subject:\s*([^|]+)/', $headerLine, $subjectMatch);
		    $subject = trim($subjectMatch[1] ?? '');
		    
		    // Extract User
		    preg_match('/User:\s*(.+?)(?:\s*\||$)/', $headerLine, $userMatch);
		    $user = trim($userMatch[1] ?? 'not logged in');
		    
		    // Extract Attachments
		    preg_match('/Attachments:\s*([^|]+)/', $headerLine, $attachmentsMatch);
		    $attachmentsStr = trim($attachmentsMatch[1] ?? '');
		    $attachments = $jsonData['attachments'] ?? (!empty($attachmentsStr) ? explode(', ', $attachmentsStr) : []);
		    
		    // Extract Error if present
		    preg_match('/Error:\s*(.+?)(?:\s*\||$)/', $headerLine, $errorMatch);
		    $error = trim($errorMatch[1] ?? '');
		    
		    // Status badge color
		    $statusClass = '';
		    if ($status === 'success') {
			$statusClass = 'green';
		    } elseif ($status === 'error' || $status === 'failed') {
			$statusClass = 'red';
		    }
		    
		    // Create unique ID for this entry
		    $entryId = md5($timestamp . $from . $to . $subject . $entryIndex);
		    $pill = ($status === 'error' || $status === 'failed') ? 'is-bad' : ($status === 'success' ? 'is-ok' : '');
		    $rows[] = compact('timestamp', 'status', 'to', 'subject', 'error', 'entryId', 'pill') + ['index' => $entryIndex];
		    $entryIndex++;
		}
		foreach (array_reverse($rows) as $row) {
		    ?>
		    <tr>
			<td><?= htmlspecialchars($row['timestamp']) ?></td>
			<td><span class="logPill <?= $row['pill'] ?>"><?= htmlspecialchars($row['status']) ?></span></td>
			<td><?= htmlspecialchars($row['to']) ?></td>
			<td><?= htmlspecialchars($row['subject']) ?></td>
			<td class="icons">
			    <ul>
				<li>
				    <a href="view/?entry_id=<?= $row['entryId'] ?>&index=<?= $row['index'] ?>"
				       class="ajax button icononly"
				       title="View">
					<i class="fa-regular fa-eye"></i>
				    </a>
				</li>
			    </ul>
			</td>
		    </tr>
		    <?php
		}
		?>
	    </tbody>
	</table>
	<?php
    }
    ?>
</section>
