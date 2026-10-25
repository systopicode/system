<?php

#[AllowDynamicProperties]
class fsLoader {

	static $filesIncluded = [];

	static function includeCSSFiles($dirFilesOrFile, $recursive = FALSE) {
		$files = fsFiles::create()->import($dirFilesOrFile, $recursive)->filter('~\.css$~');
		$files->sort(function ($a, $b) {
			return strcasecmp($a->name, $b->name);
		});

		return $files->reduce(function ($return, $file) {
				if (!$file->exists || $file->ext !== 'css' || in_array($file, self::$filesIncluded, true)) {
					return $return; // Akkumulator zurückgeben!
				}
				self::$filesIncluded[] = $file; // skip duplicates
				$return->link()
					->href(\Systopic\System\Http\Assets::href($file->href, $file->filepath))
					->rel("stylesheet")
					->type("text/css");
				return $return; // immer zurückgeben
			}, html::nodelist());
	}

	/**
	 * The same stylesheets, but declared into a cascade layer.
	 *
	 * A <link> cannot name a layer - only @import can - so this emits one
	 * <style> with an @import per file instead of a list of <link>s. The
	 * imports sit side by side in that one sheet, so the browser still
	 * fetches them in parallel, and url() inside each file still resolves
	 * against that file rather than against the document.
	 *
	 * Why bother: a layer decides who wins WITHOUT touching specificity.
	 * Everything the cms ui brings goes into the weaker layer, so the site
	 * css can style the page preview without out-specifying `.cms .layout`
	 * and without a single !important.
	 *
	 * Layer order is declared once, by the caller, before any of this:
	 *   <style>@layer cms, preview;</style>
	 */
	static function includeCSSFilesInLayer($dirFilesOrFile, string $layer, $recursive = FALSE): string
	{
		$imports = '';
		foreach (self::cssFilesOf($dirFilesOrFile, $recursive) as $file) {
			$imports .= "@import url('" . \Systopic\System\Http\Assets::href($file->href, $file->filepath)
				. "') layer($layer);\n";
		}
		return $imports === '' ? '' : "<style>\n$imports</style>\n";
	}

	/**
	 * Stylesheets inlined into an @scope block, so they only reach inside
	 * one element - the page preview in the cms.
	 *
	 * @scope limits WHERE rules match without rewriting a single selector.
	 * That is the whole point: the legacy did it with a regex that prefixed
	 * every selector with `article `, which broke on the first @media (the
	 * pattern cannot do nested braces), turned `:root` into something that
	 * never matches, and stripped the letters "body" out of class names.
	 *
	 * Inlined rather than linked, because a stylesheet cannot be scoped from
	 * the outside - the rules have to sit inside the block. An inlined url()
	 * resolves against the DOCUMENT, so relative ones are rewritten to the
	 * file's address. @import cannot sit inside @scope: it is lifted out and
	 * loaded unscoped, in the same layer. Files that need @font-face, :root or
	 * @keyframes belong in $skip (or behind such an @import) - none of those
	 * three work inside @scope.
	 *
	 * @param string   $scope css selector of the scoping root
	 * @param string   $layer cascade layer to put it in, '' for none
	 * @param string[] $skip  file names to leave out
	 * @param string   $limit selector of what the rules stop at, '' for none
	 */
	static function includeCSSFilesScoped($dirFilesOrFile, string $scope, string $layer = '', array $skip = [], $recursive = FALSE, string $limit = ''): string
	{
		$css = '';
		$imports = '';
		foreach (self::cssFilesOf($dirFilesOrFile, $recursive) as $file) {
			if (in_array($file->name, $skip, TRUE)) {
				continue;
			}
			$content = (string) $file->contents;
			if (trim($content) === '') {
				continue;
			}
			$base = dirname((string) $file->href) . '/';
			$absolute = static fn(string $url): string => preg_match('~^([a-z]+:|/|#)~i', $url) ? $url : $base . $url;
			$content = preg_replace_callback('~@import\s+(?:url\(\s*)?([\'"]?)([^\'")\s;]+)\1\s*\)?([^;]*);~i', function ($m) use (&$imports, $absolute, $layer) {
				$imports .= "@import url('" . $absolute($m[2]) . "')" . ($layer !== '' ? " layer($layer)" : '') . "$m[3];\n";
				return '';
			}, $content);
			$content = preg_replace_callback('~url\(\s*([\'"]?)([^\'")]+)\1\s*\)~i', fn($m) => "url('" . $absolute(trim($m[2])) . "')", $content);
			$css .= "\n/* $file->name */\n" . $content;
		}
		if (trim($css) === '' && $imports === '') {
			return '';
		}
		$out = "@scope ($scope)" . ($limit !== '' ? " to ($limit)" : '') . " {\n$css\n}\n";
		if ($layer !== '') {
			$out = "@layer $layer {\n$out}\n";
		}
		return "<style>\n$imports$out</style>\n";
	}

	/**
	 * Shared discovery for the two methods above: the .css files of a folder
	 * (or one file), sorted by name, duplicates skipped -
	 * the same selection includeCSSFiles() makes, without the markup.
	 *
	 * @return object[] fsFile
	 */
	private static function cssFilesOf($dirFilesOrFile, $recursive = FALSE): array
	{
		$files = fsFiles::create()->import($dirFilesOrFile, $recursive)->filter('~\.css$~');
		$files->sort(function ($a, $b) {
			return strcasecmp($a->name, $b->name);
		});
		$out = [];
		foreach ($files as $file) {
			if (!$file->exists || $file->ext !== 'css' || in_array($file, self::$filesIncluded, TRUE)) {
				continue;
			}
			self::$filesIncluded[] = $file;
			$out[] = $file;
		}
		return $out;
	}

	static function includeScriptFiles($dirFilesOrFile, $recursive = TRUE) {
		if (defined('JSOFF')) {
			return '';
		}
		$files = fsFiles::create()->import($dirFilesOrFile, $recursive)->filter('~\.js$~');
		$files->sort('fsLoader::scriptFilesSorting');
		return $files->reduce(function ($return, $file) {
				if (!$file->exists || $file->ext !== 'js' || in_array($file, self::$filesIncluded, TRUE)) {
					return;
				}
				self::$filesIncluded[] = $file; // skip duplicates
				$return->script()
					->src(\Systopic\System\Http\Assets::href($file->href, $file->filepath))
					->type("text/javascript");
			}, html::nodelist());
	}

	static function scriptFilesSorting($a, $b) {
		if (empty($a->filebase)) {
			// p("$a->name");
			// p($a->pathinfo);
			// p($a);
			return -1;
		}
		if (empty($b->filebase)) {
			// p("$a->name $b->name");
			return 1;
		}
		// sorgt dafür, dass server.js vor server.command.js geladen wird
		if (strpos($a->filebase, $b->filebase) === 0) {
			return 1;
		}
		if (strpos($b->filebase, $a->filebase) === 0) {
			return -1;
		}
		// notwendig, da files mit grossbuchstaben sonst vor jquery geladen werden
	}
}
