<h1>PHP info</h1>
<p class="tagsHint">The configuration this PHP is running with.</p>
<section class="widget">
	<header><h2>phpinfo</h2></header>
	<main class="phpinfo">
		<?php
		ob_start();
		phpinfo();
		$pinfo = ob_get_clean();
		$pinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', (string) $pinfo);
		echo $pinfo;
		?>
	</main>
</section>
<script>
	$(function () {
		$(".phpinfo a[href='http://www.php.net/']").remove();
		$(".phpinfo a[href='http://www.zend.com/']").remove();
	});
</script>
