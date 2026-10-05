<tr>
	<td><?= $backup['date']; ?></td>
	<!--<td><?= $backup['timefinished']; ?></td> -->
	<!--<td><?= $backup['duration']; ?></td> -->
	<td><?= $backup['totalsize']; ?></td>
	<td><?= $backup['stage']; ?></td>
	<td><?= $backup['databasename']; ?></td>
	<td><?= $backup['user'] ?></td>
	<td><?= $backup['note'] ?></td>
	<td style="vertical-align: middle"><?= $backup['filesincluded'] ? '<i class="fa-light fa-check"></i>' : '&mdash;'; ?></td>
	<td class="icons">
		<ul>
			<?php if (\Systopic\System\Auth\Session::hasOneRole('admin', 'restore', 'recovery')) { ?>
				<li><a href='restore/?name=<?= $backup['filenamebase'] ?>' class='button transparent ajax icononly' title='Restore'><i class="fa-light fa-arrow-rotate-left"></i></a></li>
			<?php } ?>
			<?php if (\Systopic\System\Auth\Session::hasRole('admin')) { ?>
				<li><a  data-on_click='userConfirm' data-action='remove?name=<?= basename($backup["download"][0]) ?>' class='button transparent ajax icononly' data-confirm='Do you really want to delete this Backup?' title='Delete'><i class="fa-light fa-trash"></i></a></li>
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
						echo html::create('a.button.transparent')->target('_self')
							->download(basename($singlefile))
							->href(http::$siteUrl . 'var/backups/' . basename($singlefile))
							->title(basename($singlefile))
							->append('i.fa-light.fa-arrow-down-to-line')
							('a')->text(($i === 1 ? 'Path ' : '') . ($i))
						;
					}
					?>
				</li>

<?php } ?>
		</ul>
	</td>
</tr>
