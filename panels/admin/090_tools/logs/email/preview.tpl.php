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
