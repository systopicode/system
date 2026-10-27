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
	 * loaded unscoped, in the same layer. @font-face, :root and @keyframes do
	 * not apply inside @scope (a :root rule looks for a :root *descendant* of
	 * the scope and matches nothing). With $liftDocument they are taken out
	 * and emitted in the same layer, ahead of the scope.
	 *
	 * @param string   $scope css selector of the scoping root
	 * @param string   $layer cascade layer to put it in, '' for none
	 * @param string[] $skip  file names to leave out
	 * @param string   $limit selector of what the rules stop at, '' for none
	 * @param bool     $liftDocument lift :root, @font-face and @keyframes out of the scope
	 */
	static function includeCSSFilesScoped($dirFilesOrFile, string $scope, string $layer = '', array $skip = [], $recursive = FALSE, string $limit = '', bool $liftDocument = false): string
	{
		$css = '';
		$imports = '';
		$document = '';
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
			if ($liftDocument) {
				[$wide, $content] = self::liftDocumentRules($content);
				if (trim($wide) !== '') {
					$document .= "\n/* $file->name */\n" . $wide;
				}
			}
			$css .= "\n/* $file->name */\n" . $content;
		}
		if (trim($css) === '' && trim($document) === '' && $imports === '') {
			return '';
		}
		$out = '';
		if (trim($document) !== '') {
			$out .= $document . "\n";
		}
		if (trim($css) !== '') {
			$out .= "@scope ($scope)" . ($limit !== '' ? " to ($limit)" : '') . " {\n$css\n}\n";
		}
		if ($layer !== '') {
			$out = "@layer $layer {\n$out}\n";
		}
		return "<style>\n$imports$out</style>\n";
	}

	/**
	 * Top-level :root, @font-face and @keyframes, split off so they are not
	 * buried in an @scope where they match nothing.
	 *
	 * @return array{0: string, 1: string} document-wide rules, then the rest
	 */
	private static function liftDocumentRules(string $css): array
	{
		$document = '';
		$rest = '';
		$len = strlen($css);
		$i = 0;
		$buf = '';
		while ($i < $len) {
			if ($css[$i] === '/' && ($css[$i + 1] ?? '') === '*') {
				$end = strpos($css, '*/', $i + 2);
				$end = $end === false ? $len : $end + 2;
				$buf .= substr($css, $i, $end - $i);
				$i = $end;
				continue;
			}
			if ($css[$i] === '"' || $css[$i] === "'") {
				$q = $css[$i];
				$start = $i;
				$i++;
				while ($i < $len) {
					if ($css[$i] === '\\') {
						$i += 2;
						continue;
					}
					if ($css[$i] === $q) {
						$i++;
						break;
					}
					$i++;
				}
				$buf .= substr($css, $start, $i - $start);
				continue;
			}
			if ($css[$i] === '{') {
				$end = self::matchingBrace($css, $i);
				$block = substr($css, $i, $end - $i);
				if (self::isDocumentWide($buf)) {
					$document .= $buf . $block;
				} else {
					$rest .= $buf . $block;
				}
				$buf = '';
				$i = $end;
				continue;
			}
			$buf .= $css[$i];
			$i++;
		}
		$rest .= $buf;
		return [$document, $rest];
	}

	private static function isDocumentWide(string $prelude): bool
	{
		$head = preg_replace('~/\*.*?\*/~s', '', $prelude) ?? $prelude;
		$head = trim($head);
		if ($head === '') {
			return false;
		}
		if (preg_match('/^@font-face\b/i', $head) === 1 || preg_match('/^@(-webkit-|-moz-)?keyframes\b/i', $head) === 1) {
			return true;
		}
		$head = preg_replace('/\s+/', '', $head) ?? $head;
		return preg_match('/^(?::root,)*:root$/i', $head) === 1;
	}

	/** Index just after the `}` that closes the `{` at `$open`. */
	private static function matchingBrace(string $css, int $open): int
	{
		$len = strlen($css);
		$depth = 0;
		$i = $open;
		while ($i < $len) {
			$c = $css[$i];
			if ($c === '/' && ($css[$i + 1] ?? '') === '*') {
				$end = strpos($css, '*/', $i + 2);
				$i = $end === false ? $len : $end + 2;
				continue;
			}
			if ($c === '"' || $c === "'") {
				$q = $c;
				$i++;
				while ($i < $len) {
					if ($css[$i] === '\\') {
						$i += 2;
						continue;
					}
					if ($css[$i] === $q) {
						$i++;
						break;
					}
					$i++;
				}
				continue;
			}
			if ($c === '{') {
				$depth++;
			} elseif ($c === '}') {
				$depth--;
				if ($depth === 0) {
					return $i + 1;
				}
			}
			$i++;
		}
		return $len;
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
