<main class="backupConfirm">
    <p>Restore this backup? Current data and media files will be replaced.</p>
    <p class="backupConfirmName"><?= htmlspecialchars((string) http::get('name')) ?></p>
</main>
<footer>
    <a href="createTaskList?name=<?= urlencode((string) http::get('name')) ?>" class="button submit ajax put"><i class="fa-regular fa-arrow-rotate-left"></i><span>Restore</span></a>
</footer>		
