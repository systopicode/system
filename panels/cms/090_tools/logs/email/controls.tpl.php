<div class="rightAlign">
    <a id='downloadLog' href='<?= http::$root . 'var/' ?>log/email.log' download title='Download Logfile' class='button'><i class="fa-light fa-file-arrow-down"></i></a>
    <a  data-on_click='userConfirm' data-action='?action=clearLog&value=email' title='Clear Logfile' data-confirm='Do you really want to delete this Log file?' class='ajax button' ><i class="fa-light fa-trash"></i></a>
</div>