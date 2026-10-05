<?php
include_once('actions.php');
?>
<div class='layout'>
    <div class='halfColumn'>
        <h1>PHP Settings</h1>
        <ul class="checkSystemEnvironment">
            <li class="<?= $php_version['status'] ?>"><?= $php_version['label'] ?></li><br>
            <li class="<?= $post_max_size['status'] ?>"><?= $post_max_size['label'] ?></li><br>
            <li class="<?= $upload_max_size['status'] ?>"><?= $upload_max_size['label'] ?></li><br>
            <li class="<?= $max_execution_time['status'] ?>"><?= $max_execution_time['label'] ?></li><br>
            <li class="<?= $memory_limit['status'] ?>"><?= $memory_limit['label'] ?></li><br>
            <li class="<?= $display_errors['status'] ?>"><?= $display_errors['label'] ?></li><br>
            <li class="<?= $log_errors['status'] ?>"><?= $log_errors['label'] ?></li>
        </ul>
    </div>
    <div class='halfColumn'>
        <h1>Folder Settings</h1>
        <ul class="checkSystemEnvironment">
            <?php foreach ($var_writeableFolders as $folder => $status) { ?>
                <li class="<?= $status['status'] ?>"><?= $status['label'] ?></li><br>
            <?php } ?>
        </ul>
    </div>
</div>
<div class='layout'>
    <div class='halfColumn'>
        <br><br><br>
        <h1>Extensions</h1>
        <ul class="checkSystemEnvironment">
            <?php foreach ($var_extensions as $extension => $status) { ?>
                <li class="<?= $status['status'] ?>"><?= $status['label'] ?></li><br>
            <?php } ?>
        </ul>
    </div>

</div>
<style>
    ul.checkSystemEnvironment{
        margin-top:-10px;
    }

    ul.checkSystemEnvironment li{
        margin-bottom: 10px;
        padding: 3px 10px;
        display:inline-block;
    }

    ul.checkSystemEnvironment li:last-child{
        margin-bottom: 0;
    }

    ul.checkSystemEnvironment li.confirm{
        background:rgb(124,178,73);
        color:#fff;
    }
    ul.checkSystemEnvironment li.warning{
        background:rgb(246,191,59);
    }
    ul.checkSystemEnvironment li.error{
        background:rgb(213,20,25);
        color:#fff;
    }
</style>