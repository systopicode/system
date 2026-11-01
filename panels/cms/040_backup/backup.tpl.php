<tr>
	<td><?= htmlspecialchars((new \DateTimeImmutable($backup['date']))->format(\Systopic\System\Sys\LocalTime::DATETIME_SECONDS), ENT_QUOTES) ?></td>
	<!--<td><?= $backup['timefinished']; ?></td> -->
	<!--<td><?= $backup['duration']; ?></td> -->
	<td><?= $backup['totalsize']; ?></td>
	<td><?= $backup['stage']; ?></td>
	<td><?= $backup['databasename']; ?></td>
	<td><?= $backup['user'] ?></td>
	<td><?= $backup['note'] ?></td>
	<td><?= ($backup['contentsLabel'] ?? '') !== '' ? htmlspecialchars((string) $backup['contentsLabel'], ENT_QUOTES) : '&mdash;' ?></td>
	<td class="icons">
		<ul>
			<?php if (\Systopic\System\Auth\Session::hasOneRole('admin', 'restore', 'recovery')) { ?>
				<li><a href='restore/?name=<?= $backup['filenamebase'] ?>' class='button transparent ajax icononly' title='Restore'><i class="cmsIcon cmsIcon-arrow-rotate-left"></i></a></li>
			<?php } ?>
			<?php if (\Systopic\System\Auth\Session::hasRole('admin')) { ?>
				<li><button type="button" data-cms-action  data-on_click='userConfirm' data-action='remove?name=<?= basename($backup["download"][0]) ?>' class='button transparent ajax icononly' data-confirm='Delete this backup?' title='Delete'><i class="cmsIcon cmsIcon-trash"></i></button></li>
			<?php } ?>
			<?php if (\Systopic\System\Auth\Session::hasRole('admin')) { ?>
				<li>
				<?php } ?>
				<?php if (\Systopic\System\Auth\Session::hasRole('admin')) { ?>
				<li>
					<?php
					$i = 0;
					foreach ($backup["download"] as $key => $singlefile) {
						$i++;
						echo html::create('a.button.transparent.icononly')->target('_self')
							->download(basename($singlefile))
							// through the panel (actions.php: download) - var/backups/ is not served
							->href(http::$siteUrl . 'cms/backup/download?name=' . rawurlencode(basename($singlefile)))
							->title('Download ' . basename($singlefile))
							->append('i.cmsIcon.cmsIcon-arrow-down-to-line');
					}
					?>
				</li>

<?php } ?>
		</ul>
	</td>
</tr>
