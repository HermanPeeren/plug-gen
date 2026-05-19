<?php
/**
 * @package     Plugin Generator
 * @subpackage  Joomla6 Generator
 * @version     0.0.2
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2026. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE.txt
 */

namespace administrator\components\com_pluggen\src\Model\Generator\Joomla6;

use administrator\components\com_pluggen\src\Model\Generator\Generator;

/**
 * A concrete generator to create the general plugin files of a J6-plugin with templates
 * generated files: manifest-file
 *
 * @package     Extension Generator
 */
class PluginGeneral extends Generator
{
	/**
	 * Generate the files. This method is called from the model.
	 *
	 * @return array the log of this concrete generator; logs which files were generated
	 */
	public function generate(): array
	{
		// Initialise variables
		$project = $this->AST;
		$log = [];
		$logAppend = function ($append) use(&$log) {$log = array_merge($log, $append);};

		// The name of the plugin (without 'plg_' prefix, without the type, and with capital first character)
		$pluginName = ucfirst($this->pluginName);
		$pluginType = ucfirst($this->pluginType);

		$manifest = $project->extensions->plugin->manifest;

		// What kind of output do you want to generate? For instance: 'Joomla6'
		$outputType = $this->outputType;

		$baseTemplateFilePath = 'plugin/';
		$templateFilePath = $baseTemplateFilePath;
		$baseGeneratedFilePath = '';
		$generatedFilePath = $baseGeneratedFilePath;
		$templateVariables = ['pluginName' => $pluginName];
		$templateVariables = ['pluginType' => $pluginType];

		$templateVariables['description'] = $project->plugin_description;

		// Loop over the pages to make a map of page_id to page-definition
		$pageMap = [];
		foreach ($project->pages as $page)
		{
			$pageMap[$page->page_id] = $page;
		}

		// --- create plugin manifest file ---
		$templateFileName = 'pluginManifest.xml';
		$generatedFileName = strtolower($pluginName).'.xml';

		$templateVariables['author_name'] = $manifest->author_name;
		$templateVariables['author_email'] = $manifest->author_email;
		$templateVariables['author_url'] = $manifest->author_url;
		$templateVariables['copyright'] = $manifest->copyright;
		$templateVariables['license'] = $manifest->license;
		$templateVariables['company_namespace'] = $manifest->company_namespace;
		$templateVariables['projectName'] = $project->name;
		$templateVariables['version'] = $manifest->version;
		$templateVariables['creation_date'] = $manifest->creation_date;

		$logAppend($this->generateFileWithTemplate($templateFilePath, $templateFileName, $generatedFilePath, $generatedFileName, $templateVariables));

		return $log;
	}
}