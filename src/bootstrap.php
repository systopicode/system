<?php

declare(strict_types=1);

/**
 * PanelSystem bootstrap — pure infrastructure, no site-specific logic.
 *
 * Constructs the core framework objects and makes them available as variables.
 * Root registration (addRoot calls) belongs in loader.php, not here.
 *
 * Usage in loader.php:
 *
 *   require __DIR__ . '/src/bootstrap.php';
 *
 *   // Register panels-roots (sys first, project last for last-wins overrides):
 *   if ($sysPanels?->exists) {
 *       $panelTree->addRoot(new PanelRootFolder($sysPanels, 'Systopic\System\Panels'));
 *   }
 *   if ($projectPanels?->exists) {
 *       $panelTree->addRoot(new PanelRootFolder($projectPanels, PROJECT_NAMESPACE . '\Panels'));
 *   }
 *
 *   // Register autoloader for module-based panel namespace resolution:
 *   PanelAutoloader::registerRoots($panelTree->getRoots());
 *   // Build tree and finish wiring:
 *   $panelTree->build(app::request()->modulePath);
 *   $nav = new PanelNavigator($panelTree, $layerTree, $domRenderer);
 *   $nav->setActive($panelTree->getRoot());
 *   PanelNode::setNavigator($nav);
 *   PanelNode::setTree($panelTree);
 *   PanelTree::setInstance($panelTree);
 *
 * After this file is included the following variables are available:
 *
 *   $domSession   — Systopic\System\Dom\Renderer\DomRendererSession
 *   $layerTree    — Systopic\System\Dom\Tree\DomLayerTree
 *   $domRenderer  — Systopic\System\Dom\Renderer\DomRenderer
 *   $panelTree    — Systopic\System\Panels\Tree\PanelTree
 *   $tplRenderer  — Systopic\System\Panels\Renderer\TemplateRenderer
 *
 * $nav is NOT created here — it depends on roots being registered first.
 */

use Systopic\System\Dom\Renderer\{DomRenderer, DomRendererSession};
use Systopic\System\Dom\Tree\DomLayerTree;
use Systopic\System\Panels\Renderer\TemplateRenderer;
use Systopic\System\Panels\Tree\PanelTree;

// ---- DOM layer ----
$domSession  = new DomRendererSession();
$layerTree   = new DomLayerTree();
$layerTree->setReferencesFromSession($domSession->loadReferencesByAddress());
$layerTree->setReferenceRoot($domSession->loadReferenceRoot());
$domRenderer = new DomRenderer($layerTree, $domSession);

// ---- Panel tree ----
$panelTree   = new PanelTree();

// ---- Template renderer ----
$tplRenderer = new TemplateRenderer();
