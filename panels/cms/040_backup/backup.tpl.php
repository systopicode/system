<tr>
	<td><?= $backup['date']; ?></td>
	<!--<td><?= $backup['timefinished']; ?></td> -->
	<!--<td><?= $backup['duration']; ?></td> -->
	<td><?= $backup['totalsize']; ?></td>
	<td><?= $backup['stage']; ?></td>
	<td><?= $backup['databasename']; ?></td>
	<td><?= $backup['user'] ?></td>
	<td><?= $backup['note'] ?></td>
	<td style="vertical-align: middle"><?= $backup['filesincluded'] ? '<i class="fa-regular fa-check"></i>' : '&mdash;'; ?></td>
	<td class="icons">
		<ul>
			<?php if (\Systopic\System\Auth\Session::hasOneRole('admin', 'restore', 'recovery')) { ?>
				<li><a href='restore/?name=<?= $backup['filenamebase'] ?>' class='button transparent ajax icononly' title='Restore'><i class="fa-regular fa-arrow-rotate-left"></i></a></li>
			<?php } ?>
			<?php if (\Systopic\System\Auth\Session::hasRole('admin')) { ?>
				<li><a  data-on_click='userConfirm' data-action='remove?name=<?= basename($backup["download"][0]) ?>' class='button transparent ajax icononly' data-confirm='Delete this backup?' title='Delete'><i class="fa-regular fa-trash"></i></a></li>
			<?php } ?>
			<?php if (\Systopic\System\Auth\Session::hasRole('admin')) { ?>
				<li class="seperator">
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
							->append('i.fa-regular.fa-arrow-down-to-line');
					}
					?>
				</li>

<?php } ?>
		</ul>
	</td>
</tr>
