<h1>PHP info</h1>
<p class="tagsHint">The configuration this PHP is running with.</p>
<div class="phpinfo">
		<?php
		ob_start();
		phpinfo();
		$pinfo = ob_get_clean();
		$pinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', (string) $pinfo);
        // The PHP brand and version share the standard, vertically centred widget header.
        $pinfo = preg_replace_callback('~<table\b[^>]*>(.*?)</table>~si', static function (array $table): string {
            if (!preg_match('~<h1\b[^>]*>(PHP Version.*?)</h1>~si', $table[1], $title)
                || !preg_match('~<img\b[^>]*>~si', $table[1], $logo)) return $table[0];
            return '<section class="widget phpInfoBrand"><header>' . $logo[0] . '<h2>' . $title[1] . '</h2></header></section>';
        }, $pinfo);
		// Each PHP table uses the same complete widget shell as other data tables.
        $pinfo = preg_replace('~<table\b[^>]*>~i', '<section class="widget is-list"><div class="tableScroll"><table>', $pinfo);
        $pinfo = str_ireplace('</table>', '</table></div></section>', $pinfo);
        echo $pinfo;
		?>
</div>
<script>
	$(function () {
		$(".phpinfo a[href='http://www.php.net/']").remove();
		$(".phpinfo a[href='http://www.zend.com/']").remove();
	});
</script>
