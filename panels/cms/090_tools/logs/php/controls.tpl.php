<div class="rightAlign">
    <a id='downloadLog' href='<?= http::$root . 'var/' ?>log/PHP_errors.log' download class='button'><i>Download</i><span>Download Logfile</span></a>
    <a  data-on_click='userConfirm' data-action='?action=clearLog&value=php' data-confirm='Do you really want to delete this Log file?' class='ajax button' title=''><i>Bin</i><span>Clear Logfile</span></a>
</div>