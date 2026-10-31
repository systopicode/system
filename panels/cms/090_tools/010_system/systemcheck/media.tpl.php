<?php include __DIR__ . '/checks.inc.php'; ?>
    <section class="widget"><header><h2>Media support</h2><button type="button" class="button" data-action-feedback data-on_click="probeMediaTools"><i class="cmsIcon cmsIcon-flask"></i>Test media tools</button></header><main>
    <p class="tagsHint">Check whether this host can start the optional video and image tools. No media files are changed.</p>
    <ul class="toolCheck">
        <?php foreach ($var_binaries as $binary): ?>
        <li class="<?= htmlspecialchars((string) $binary['status'], ENT_QUOTES) ?>"><?= $binary['label'] ?></li>
        <?php endforeach; ?>
        <li class="<?= class_exists('Imagick') ? 'confirm' : 'warning' ?>">Image editing: <?= class_exists('Imagick') ? 'Imagick is available' : 'Imagick PHP extension is missing' ?></li>
        <li class="<?= class_exists('ZipArchive') ? 'confirm' : 'warning' ?>">Backup archives: <?= class_exists('ZipArchive') ? 'ZIP support is available' : 'ZipArchive PHP extension is missing' ?></li>
    </ul>
    <p class="tagsHint">The test checks startup only. Supported formats, PDF permissions and large conversions still depend on the host.</p>
    <details class="mediaCheckInfo"><summary>About this check<i class="cmsIcon cmsIcon-chevron-down"></i></summary><main><p>Project-local tools in <code>var/bin/</code>, <code>bin/</code> and platform subfolders are supported. The version test has a short timeout and never downloads tools or changes permissions.</p><p>Missing optional tools limit the related media feature. Cron and Centrifugo are not required for ordinary editing. Backups also need sufficient disk space and runtime.</p></main></details>
    </main></section>
