<?php

#[AllowDynamicProperties]
class domRenderer implements clientSingleton, clientData, \Systopic\System\Client\Contracts\PreloadsClientScript
{
	/**
	 * Set by loader.php after bootstrap so that clientData_export() can
	 * delegate to the new DomRenderer instance instead of the old domLayer statics.
	 */
	private static ?\Systopic\System\Dom\Renderer\DomRenderer $newInstance = null;

	public static function setNewInstance(\Systopic\System\Dom\Renderer\DomRenderer $r): void
	{
		self::$newInstance = $r;
	}

	static $modeStack = ['build'];

	static function mode()
	{
		return end(self::$modeStack);
	}

	static function checkMode($modes)
	{
		if (!is_array($modes)) {
			$modes = [$modes];
		}
		return in_array(end(self::$modeStack), $modes);
	}

	static function setMode($mode)
	{
		if (domLayer::active()) {
			domLayer::active()->renderMode = $mode;
		}
		self::$modeStack[] = $mode;
	}

	static function unsetMode()
	{
		array_pop(self::$modeStack);
	}

	static function renderStart()
	{
		domRenderer::setMode('render'); // -> direct output html
	}

	static function getDocument()
	{
		ob_start();
		domRenderer::setMode('document'); // -> render complete document in slices insert marker -> output html
		domLayer::create('getDocument', ['body']);
		domLayer::addSlice();
	}

	static function endGetDocument()
	{
		domLayer::closeLast();
		domRenderer::unsetMode();
	}

	static function endGetLayers()
	{
		domLayer::closeLast();
		domRenderer::unsetMode();
	}

	static function getLayers()
	{
		ob_start();
		domRenderer::setMode('layers'); // -> render complete document in slices insert marker -> output html
		domLayer::create('getDocument', ['body']);
		domLayer::addSlice();
	}

	static function getDiff()
	{
		ob_start();
		domRenderer::setMode('diff'); // -> render parts that have changed or not been rendered yet -> outpus json
		domLayer::create('getDiff', ['body']);
		domLayer::addSlice();
		domRenderer::addReference(domLayer::referenceRoot());
	}

	static function addReference($refLayer, $originalLayer = NULL)
	{
		$layer = $originalLayer ?? domLayer::active();
		if (!is_object($layer)) {
			http::redirect('./');
		}
		if (!is_object($refLayer)) {
			$layer->type = 'original';
			return FALSE;
		}
		$layer->reference = $refLayer;
		if ($layer->moduleName === $refLayer->moduleName) {
			// p("add Reference: $layer->moduleName === $refLayer->moduleName)");
			$layer->type = 'alias';
			$layer->reference->type = 'reference';
			return TRUE;
		} else {
			$layer->type = 'original';
			// $layer->reference->type = 'replace';
			$layer->reference->setTypeRec('replace');

			// p("referecnceCondition failed: $layer->moduleName --> $refLayer->moduleName");
		}
		// ? $layer->reference = $refLayer;
		return FALSE;
	}

	static function storeReference()
	{
		$_SESSION['referencesByModuleAddress'] = domLayer::$referencesByModuleAddress;
		$_SESSION['referenceDomLayers'] = domLayer::root();
	}

	static function endGetDiff()
	{
		domLayer::closeLast();
		domRenderer::unsetMode();
	}

	static $bodyClasses = [];

	static function addBodyClasses($classes)
	{
		self::$bodyClasses = $classes;
	}

	static function getBodyClasses()
	{
		return self::$bodyClasses;
	}

	public static function clientData_export()
	{
		if (self::$newInstance !== null) {
			return self::$newInstance->clientData_export();
		}
		$domLayers = domLayer::root() ?: ($_SESSION['referenceDomLayers'] ?? null);
		$return = (object) [
			'route' => 'singletons.dom.renderer',
			'data' => (object) [
				'layers' => $domLayers ? $domLayers->export() : [],
				'bodyClasses' => self::getBodyClasses()
			],
		];
		return $return;
	}

	// ************* DEBUG OUTPUT ********************
	static function debug()
	{
		// Delegate to the new instance whenever the bootstrap installed one.
		// Without this, the legacy debugLayer(domLayer $layer) signature
		// triggers a TypeError as soon as it touches the session
		// reference (which is now a Dom\Layer\DomLayer) — and that
		// TypeError happens inside client::flushResponse(), aborting the
		// AJAX bootstrap render with an empty body.
		if (self::$newInstance !== null) {
			return self::$newInstance->debug();
		}
		$container = html::create('div.domLayersContainer');
		if (domLayer::referenceRoot()) {
			$referenceDocument = self::debugLayer(domLayer::referenceRoot());
			$container->append($referenceDocument);
		} else {
			// die('no ref document');
		}
		if (domLayer::root()) {
			$document = self::debugLayer(domLayer::root());
			$container->append($document);
		}
		return $container;
	}

	static function debugLayer(domLayer $layer): htmlElement
	{
		$debug = html::create('div.layer')->class($layer->type);
		$module = $layer->moduleName;
		$label = $debug->append('pre')->text("$module:$layer->viewName");
		$info = htmlTable::create('keyvalue')
			->rows($layer->debugInfo())
			->class('info');
		$label->append($info);

		foreach ($layer->items as $item) { // children
			if ($item->isLayer()) {
				$debug->append(self::debugLayer($item));
			} else {
				$debug->append($item->debugSlice());
			}
		}
		return $debug;
	}
}
