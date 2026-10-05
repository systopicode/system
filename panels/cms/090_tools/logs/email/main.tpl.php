<script>
    $(function () {
	$('table.hover').dataTable({
	    "iDisplayLength": 25,
	    "aaSorting": [[0, "desc"]],
	    "aLengthMenu": [
		[10, 25, 50, 100, -1],
		[10, 25, 50, 100, "All"]
	    ],
	    "oLanguage": {
		"sSearch": ""
	    }
	});
    });
</script>
<div class="tabContainer">
    <?php
    // Define EMAILLOGFILE constant if not already defined
    if (!defined('EMAILLOGFILE')) {
	define('EMAILLOGFILE', fs::$root . 'var/log/email.log');
    }
    $logFile = EMAILLOGFILE;
    $output = @fs::file_get_contents($logFile);
    
    if (empty($output)) {
	?>
	<h2>LOGFILE IS EMPTY</h2>
	<?php
    } else {
	// Split entries by separator line
	$entries = explode(str_repeat("-", 80) . "\n", $output);
	$entries = array_filter($entries, function($entry) {
	    return !empty(trim($entry));
	});
	?>
	<table class="hover">
	    <thead>
		<tr>
		    <th>Date / Time</th>
		    <th>Status</th>
		    <th>From</th>
		    <th>To</th>
		    <th>Subject</th>
		    <th>User</th>
		    <th>Attachments</th>
		    <th>Actions</th>
		</tr>
	    </thead>
	    <tbody>
		<?php
		$entryIndex = 0;
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
		    $entryIndex++;
		    ?>
		    <tr>
			<td data-order="<?= $dateOrder ?>"><?= htmlspecialchars($timestamp) ?></td>
			<td>
			    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
			    <?php if ($error): ?>
				<br><small style="color: red;"><?= htmlspecialchars($error) ?></small>
			    <?php endif; ?>
			</td>
			<td><?= htmlspecialchars($from) ?></td>
			<td><?= htmlspecialchars($to) ?></td>
			<td><?= htmlspecialchars($subject) ?></td>
			<td><?= htmlspecialchars($user) ?></td>
			<td>
			    <?php if (!empty($attachments)): ?>
				<?php foreach ($attachments as $attachment): ?>
				    <span class="badge"><?= htmlspecialchars($attachment) ?></span>
				<?php endforeach; ?>
			    <?php else: ?>
				-
			    <?php endif; ?>
			</td>
			<td>
			    <a href="view/?entry_id=<?= $entryId ?>&index=<?= $entryIndex - 1 ?>" 
			       class="ajax button transparent" 
			       title="View Email">
				<i class="fa-light fa-eye"></i> View
			    </a>
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
</div>
