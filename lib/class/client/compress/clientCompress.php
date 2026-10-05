<?php

class clientCompress {

	public static function compressCSS($dirPath) {
		$compressedPath = rtrim($dirPath, '/') . '/compressed.css';

		// Finde alle .css-Dateien im Verzeichnis (aber nicht compressed.css selbst)
		$cssFiles = fs::glob($dirPath . '/*.css');
		$cssFiles = array_filter($cssFiles, function ($file) use ($compressedPath) {
			return fs::realpath($file) !== fs::realpath($compressedPath);
		});

		if (empty($cssFiles)) {
			return;
		}

		$needRebuild = false;

		if (!fs::fileExists($compressedPath)) {
			$needRebuild = true;
		} else {
			$compressedTime = fs::filemtime($compressedPath);
			foreach ($cssFiles as $file) {
				// Prüfe ob es eine gleichnamige .php Datei gibt
				$phpFile = preg_replace('/\.css$/', '.php', $file);
				$checkFile = fs::fileExists($phpFile) ? $phpFile : $file;
				
				if (fs::filemtime($checkFile) > $compressedTime) {
					$needRebuild = true;
					break;
				}
			}
		}

		if (!$needRebuild) {
			return;
		}

		$combinedCSS = '';
		// Füge Datum/Uhrzeit-Kommentar hinzu
		$timestamp = date('Y-m-d H:i:s');
		$combinedCSS .= "/* compressed.css generated on $timestamp */\n\n";

		foreach ($cssFiles as $file) {
			// Prüfe ob es eine gleichnamige .php Datei gibt
			$phpFile = preg_replace('/\.css$/', '.php', $file);
			
			if (fs::fileExists($phpFile)) {
				// PHP-Datei ausführen und Output nehmen
				ob_start();
				include $phpFile;
				$css = ob_get_clean();
			} else {
				// Normale CSS-Datei lesen
				$css = fs::file_get_contents($file);
			}
			
			$minified = self::minifyCSS($css);
			$combinedCSS .= "/* " . basename($file) . " */\n" . $minified . "\n";
		}

		fs::file_put_contents($compressedPath, $combinedCSS);
	}

	private static function minifyCSS($css) {
		// Kommentare entfernen
		$css = preg_replace('!/\*.*?\*/!s', '', $css);
		// Leerzeichen und Zeilenumbrüche optimieren
		$css = preg_replace('/\s+/', ' ', $css);
		$css = preg_replace('/\s*([{};:,])\s*/', '$1', $css);
		$css = trim($css);
		return $css;
	}
}
