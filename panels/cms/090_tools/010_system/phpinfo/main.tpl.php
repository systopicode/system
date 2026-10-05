<script>
	$(document).ready(function () {
		/****************************** PHP INFO HACK ***************************/
		$(".phpinfo a[href='http://www.php.net/']").remove();
		$(".phpinfo a[href='http://www.zend.com/']").remove();
		$(".phpinfo table").addClass("hover");
	});
</script>
<div style='overflow: hidden'>
	<?php
	ob_start();
	phpinfo();
	$pinfo = ob_get_contents();
	ob_end_clean();
	$pinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo);
	echo $pinfo;
	?>
</div>