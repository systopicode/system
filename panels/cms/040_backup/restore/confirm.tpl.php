<main>
    <p>Do you really want to restore the backup</p>
    <h3><?= htmlspecialchars((string) http::get('name')) ?></h3>
    <p>all present data and media-files will be overwritten.</p>
</main>
<footer>
    <a href="createTaskList?name=<?= urlencode((string) http::get('name')) ?>" class="button submit ajax put"><i class="fa-light fa-arrow-rotate-left"></i> Restore</span></a>
</footer>		
