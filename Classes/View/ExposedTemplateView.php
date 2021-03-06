<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Claus Due <claus@wildside.dk>, Wildside A/S
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * ExposedTemplateView. Allows access to registered template and viewhelper
 * variables from a Fluid template.
 *
 * @package Flux
 * @subpackage MVC/View
 */
class Tx_Flux_View_ExposedTemplateView extends Tx_Fluid_View_TemplateView implements Tx_Extbase_MVC_View_ViewInterface {

	/**
	 * @var Tx_Flux_Service_FluxService
	 */
	protected $configurationService;

	/**
	 * @param Tx_Flux_Service_FluxService $configurationService
	 * @return void
	 */
	public function injectDebugService(Tx_Flux_Service_FluxService $configurationService) {
		$this->configurationService = $configurationService;
	}

	/**
	 * @param string $sectionName
	 * @param string $formName
	 * @return Tx_Flux_Form_Form|NULL
	 */
	public function getForm($sectionName = 'Configuration', $formName = 'form') {
		/** @var Tx_Flux_Form $form */
		$form = $this->getStoredVariable('Tx_Flux_ViewHelpers_FlexformViewHelper', $formName, $sectionName);
		return $form;
	}

	/**
	 * @param string $sectionName
	 * @param string $gridName
	 * @return Tx_Flux_Form_Container_Grid
	 */
	public function getGrid($sectionName = 'Configuration', $gridName = 'grid') {
		/** @var Tx_Flux_Form_Container_Grid[] $grids */
		/** @var Tx_Flux_Form_Container_Grid $grid */
		$grids = $this->getStoredVariable('Tx_Flux_ViewHelpers_FlexformViewHelper', 'grids', $sectionName);
		$grid = NULL;
		if (TRUE === isset($grids[$gridName])) {
			$grid = $grids[$gridName];
		}
		return $grid;
	}

	/**
	 * Get a variable stored in the Fluid template
	 * @param string $viewHelperClassName Class name of the ViewHelper which stored the variable
	 * @param string $name Name of the variable which the ViewHelper stored
	 * @param string $sectionName Optional name of a section in which the ViewHelper was called
	 * @return mixed
	 * @throws Exception
	 */
	protected function getStoredVariable($viewHelperClassName, $name, $sectionName = NULL) {
		if ($this->controllerContext instanceof Tx_Extbase_MVC_Controller_ControllerContext === FALSE) {
			throw new RuntimeException('ExposedTemplateView->getStoredVariable requires a ControllerContext, none exists (getStoredVariable method)', 1343521593);
		}
		$this->baseRenderingContext->setControllerContext($this->controllerContext);
		$this->templateParser->setConfiguration($this->buildParserConfiguration());
		$parsedTemplate = $this->getParsedTemplate();
		$this->startRendering(Tx_Fluid_View_AbstractTemplateView::RENDERING_TEMPLATE, $parsedTemplate, $this->baseRenderingContext);
		if (FALSE === empty($sectionName)) {
			$this->renderSection($sectionName, $this->baseRenderingContext->getTemplateVariableContainer()->getAll());
		} else {
			$this->render();
		}
		$this->stopRendering();
		if (FALSE === $this->baseRenderingContext->getViewHelperVariableContainer()->exists($viewHelperClassName, $name)) {
			return NULL;
		}
		$stored = $this->baseRenderingContext->getViewHelperVariableContainer()->get($viewHelperClassName, $name);
		$this->configurationService->message('Flux View ' . get_class($this) . ' is able to read stored configuration from file ' .
			$this->getTemplatePathAndFilename(), t3lib_div::SYSLOG_SEVERITY_INFO);
		return $stored;
	}

	/**
	 * @return Tx_Fluid_Core_Parser_ParsedTemplateInterface
	 */
	public function getParsedTemplate() {
		$templateIdentifier = $this->getTemplateIdentifier();
		$source = $this->getTemplateSource();
		if (FALSE === isset($this->templateCompiler) || 0 < $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['flux']['setup']['disableCompiler']) {
			$parsedTemplate = $this->templateParser->parse($source);
		} elseif (TRUE === $this->templateCompiler->has($templateIdentifier)) {
			$parsedTemplate = $this->templateCompiler->get($templateIdentifier);
		} else {
			$parsedTemplate = $this->templateParser->parse($source);
		}
		return $parsedTemplate;
	}

	/**
	 * Renders a section from the specified template w/o requring a call to the
	 * main render() method - allows for cherry-picking sections to render.
	 * @param string $sectionName
	 * @param array $variables
	 * @param boolean $optional
	 * @return string
	 */
	public function renderStandaloneSection($sectionName, $variables, $optional = TRUE) {
		$content = NULL;
		$this->baseRenderingContext->setControllerContext($this->controllerContext);
		$this->startRendering(Tx_Fluid_View_AbstractTemplateView::RENDERING_TEMPLATE, $this->getParsedTemplate(), $this->baseRenderingContext);
		$content = $this->renderSection($sectionName, $variables, $optional);
		$this->stopRendering();
		return $content;
	}

	/**
	 * @param string $actionName
	 * @return string
	 * @throws Exception
	 */
	public function getTemplatePathAndFilename($actionName = NULL) {
		if (TRUE === empty($actionName)) {
			$actionName = $this->controllerContext->getRequest()->getControllerActionName();
		}
		$actionName = ucfirst($actionName);
		$paths = $this->expandGenericPathPattern($this->templatePathAndFilenamePattern, FALSE, FALSE);
		foreach ($paths as &$templatePathAndFilename) {
			$templatePathAndFilename = str_replace('@action', $actionName, $templatePathAndFilename);
			if (TRUE === file_exists($templatePathAndFilename)) {
				return $templatePathAndFilename;
			}
		}
		return $this->templatePathAndFilename;
	}

	/**
	 * @param string $pattern Pattern to be resolved
	 * @param boolean $bubbleControllerAndSubpackage if TRUE, then we successively split off parts from "@controller" and "@subpackage" until both are empty.
	 * @param boolean $formatIsOptional if TRUE, then half of the resulting strings will have ."@format" stripped off, and the other half will have it.
	 * @return array unix style path
	 */
	protected function expandGenericPathPattern($pattern, $bubbleControllerAndSubpackage, $formatIsOptional) {
		$extensionKey = $this->controllerContext->getRequest()->getControllerExtensionKey();
		$configurations = $this->configurationService->getViewConfigurationForExtensionName($extensionKey);
		$pathOverlayConfigurations = $this->buildPathOverlayConfigurations($configurations);
		$templateRootPath = $backupTemplateRootPath = $this->getTemplateRootPath();
		$partialRootPath = $backupPartialRootPath = $this->getPartialRootPath();
		$layoutRootPath = $backupLayoutRootPath = $this->getLayoutRootPath();
		$paths = parent::expandGenericPathPattern($pattern, $bubbleControllerAndSubpackage, $formatIsOptional);
		foreach ($pathOverlayConfigurations as $overlayPaths) {
			if (FALSE === empty($overlayPaths['templateRootPath'])) {
				$templateRootPath = $overlayPaths['templateRootPath'];
				$this->setTemplateRootPath($templateRootPath);
			}
			if (FALSE === empty($overlayPaths['partialRootPath'])) {
				$partialRootPath = $overlayPaths['partialRootPath'];
				$this->setPartialRootPath($partialRootPath);
			}
			if (FALSE === empty($overlayPaths['layoutRootPath'])) {
				$layoutRootPath = $overlayPaths['layoutRootPath'];
				$this->setLayoutRootPath($layoutRootPath);
			}
			$subset = parent::expandGenericPathPattern($pattern, $bubbleControllerAndSubpackage, $formatIsOptional);
			$paths = array_merge($paths, $subset);
		}
		$paths = array_unique($paths);
		$paths = array_reverse($paths);
		$paths = $this->trimPathStringRecursive($paths);
		return $paths;
	}

	/**
	 * @param array $configuration
	 * @return array
	 */
	protected function buildPathOverlayConfigurations($configuration) {
		$templateRootPath = NULL;
		$partialRootPath = NULL;
		$layoutRootPath = NULL;
		$overlays = array();
		$paths = array();
		if (TRUE === isset($configuration['overlays'])) {
			$overlays = $configuration['overlays'];
		}
		foreach ($overlays as $overlaySubpackageKey => $overlay) {
			if (TRUE === isset($overlay['templateRootPath'])) {
				$templateRootPath = t3lib_div::getFileAbsFileName($overlay['templateRootPath']);
			}
			if (TRUE === isset($overlay['partialRootPath'])) {
				$partialRootPath = t3lib_div::getFileAbsFileName($overlay['partialRootPath']);
			}
			if (TRUE === isset($overlay['layoutRootPath'])) {
				$layoutRootPath = t3lib_div::getFileAbsFileName($overlay['layoutRootPath']);
			}
			$paths[$overlaySubpackageKey] = array(
				'templateRootPath' => $templateRootPath,
				'partialRootPath' => $partialRootPath,
				'layoutRootPath' => $layoutRootPath
			);
		}
		$paths = array_reverse($paths);
		$paths[] = array(
			'templateRootPath' => $this->getTemplateRootPath(),
			'partialRootPath' => $this->getPartialRootPath(),
			'layoutRootPath' => $this->getLayoutRootPath()
		);
		$paths = $this->trimPathStringRecursive($paths);
		return $paths;
	}

	/**
	 * @param mixed $stringOrArray
	 * @return string
	 */
	private function trimPathStringRecursive($stringOrArray) {
		if (TRUE === is_array($stringOrArray)) {
			foreach ($stringOrArray as $key => $value) {
				$stringOrArray[$key] = $this->trimPathStringRecursive($value);
			}
			return $stringOrArray;
		}
		$value = rtrim(str_replace('//', '/', $stringOrArray), '/');
		return $value;
	}

	/**
	 * We use a checksum of the template source as the template identifier
	 *
	 * @param string $actionName
	 * @return string
	 */
	protected function getTemplateIdentifier($actionName = NULL) {
		if (TRUE === method_exists(get_parent_class($this), __FUNCTION__)) {
			return parent::getTemplateIdentifier($actionName);
		}

		return 'viewhelpertest_' . sha1($this->templateSource);
	}

}
