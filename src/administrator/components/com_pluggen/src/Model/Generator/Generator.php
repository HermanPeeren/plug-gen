<?php
/**
 * @package     Extension Generator

 * @subpackage  Pluggen component
 * @version     0.0.2
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2026. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE.txt
 */

namespace administrator\components\com_pluggen\src\Model\Generator;

// Get Twig: use the Composer autoloader
// todo: use the DIC and add this Twig-service
// todo: inject in constructor!
require_once JPATH_LIBRARIES . '/yepr/vendor/autoload.php';

use Joomla\Database\DatabaseInterface;
use Joomla\CMS\MVC\View\GenericDataException;
use	Twig\Loader\FilesystemLoader;
use	Twig\Environment;
use Twig\Extension\ExtensionInterface;

/**
 * Abstract Generator Class: concrete generators are extended from this.
 * A concrete  Generator has an AST as input and outputs (a part of) the files in the output type.
 * For instance: a Table generator (= concrete generator) for Joomla6 (= output type) generates the Table files
 * todo: make it more general than only for a plugin; also other extensions or non-Joomla output.
 *
 * @package     Yepr\Component\Pluggen\Generator
 */
abstract class Generator
{
	/**
	 * The type of output we generate files for, for instance "Joomla6"
	 * There must be a subdirectory with this name with the concrete generators
	 * and when templates are used, they must be in a subdirectory under /generator_templates with that same name
	 *
	 * @var string
	 */
	protected string $outputType;

	/**
	 * The Abstract Syntax Tree (AST) = the form-data of the project as 1 object with hierarchical properties
	 *
	 * @var object
	 */
	protected object $AST;

	/**
	 * The Language String Utilities (a Twig extension) to make language strings and put them in the language files
	 *
	 * @var ExtensionInterface
	 */
	protected ExtensionInterface $languageStringUtil;

	/**
	 * The path to the administrator-side of com_pluggen
	 *
	 * @var string
	 */
	protected string $pluggenAdminPath;

	/**
	 * The cache for Twig
	 *
	 * @var string
	 */
	protected string $twigCache;

	/**
	 * The plugin name
	 *
	 * @var string
	 */
	protected string $pluginName;

	/**
	 * The plugin type
	 *
	 * @var string
	 */
	protected string $pluginType;

	/**
	 * Generator Class Constructor
	 *
	 * @param   string        $outputType The name of the type of output (e.g. "Joomla6"); must exist
	 * @param   object        $AST        The Abstract Syntax Tree (AST) = the form-data of the project
	 */
	public function __construct(string $outputType, object $AST, ExtensionInterface $languageStringUtil)
	{
		$this->outputType = $outputType;
		$this->AST = $AST;
		$this->languageStringUtil = $languageStringUtil;

		// Plugin name
		$this->pluginName = $AST->plugin_name;
		$this->pluginType = $AST->plugin_type;

		// General paths to admin part of pluggen-component and Twig-cache
		$this->pluggenAdminPath = JPATH_ROOT . '/administrator/components/com_pluggen/';
		$this->twigCache         = $this->pluggenAdminPath . 'compilation_cache';
	}

	/**
	 * Method to be implemented in concrete generator. Implements the actual generation of files.
	 *
	 * @return array the log of the concrete generator; logs which files were generated
	 */
	abstract public function generate(): array;

	/**
	 * Generate a file with a template, using values from the AST.
	 * To be called from the concrete generator generate()-method when a template is used to create the file.
	 *
	 * @param   string  $templateFilePath   Path in the template-directory to the template file  (ending with /)
	 * @param   string  $templateFileName   Filename of the template
	 * @param   string  $generatedFilePath  Path in the generated-files-directory to the generated file (ending with /)
	 * @param   string  $generatedFileName  Filename of the generated file
	 * @param   array   $templateVariables  Array of variables to be used in template
	 *
	 * @return array $log a log of what file has been created
	 *
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \Twig\Error\LoaderError
	 */
	protected function generateFileWithTemplate
	(
		string $templateFilePath,
		string $templateFileName,
		string $generatedFilePath,
		string $generatedFileName,
		array  $templateVariables = []
	) : array
	{
		// Initialise variables
		$log = [];

		// The name of the plugin (without 'plg_' prefix, without the type and possibly with capitals)
		$pluginName = $this->pluginName;
		$pluginType = $this->pluginType;

		// What kind of output do you want to generate? For instance: 'Joomla6'
		$outputType = $this->outputType;

		// Path to administrator-side of com_pluggen
		$pluggenAdminPath = $this->pluggenAdminPath;

		// Path to generated files of plugin todo: more general to package and to other plugins, modules or components...
		$generatedFilesPathPlugin = $pluggenAdminPath . '/generated/' . $pluginType .'/' . $pluginName .'/'
			. $outputType . '/plg_'.strtolower($pluginType). '_'.strtolower($pluginName) . '/';

		// Render the template
		$generatedContent= $this->renderTemplateFragment($templateFilePath, $templateFileName, $templateVariables);

		// Create the directory for the generated file if it doesn't exist
		$generatedDirectory = $generatedFilesPathPlugin . $generatedFilePath;
		if (!file_exists($generatedDirectory)) {
			mkdir($generatedDirectory, 0755, true);
		}

		// Write the file
		$myfile = fopen( $generatedDirectory .$generatedFileName, "w") or die("Unable to open file!");
		fwrite($myfile, $generatedContent);
		fclose($myfile);
		$log[] = $generatedFileName . ' generated';

		return $log;
	}

	/**
	 * Render template fragment, using template variables.
	 * To be called from the concrete generator generate()-method when a sub-template must be rendered.
	 * And is called from $this->generateFileWithTemplate()
	 *
	 * @param   string  $templateFilePath   Path in the template-directory to the template file  (ending with /)
	 * @param   string  $templateFileName   Filename of the template
	 *
	 * @return string The generated (sub-)template
	 *
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \Twig\Error\LoaderError
	 */
	protected function renderTemplateFragment
	(
		string $templateFilePath,
		string $templateFileName,
		array  $templateVariables = []
	) : string
	{
		// What kind of output do you want to generate? For instance: 'Joomla6'
		$outputType = $this->outputType;

		// Path to administrator-side of com_pluggen
		$pluggenAdminPath = $this->pluggenAdminPath;

		// Path to generator-templates of a specific output type
		$generatorTemplatePath = $pluggenAdminPath . 'generator_templates/'. $outputType . '/';

		// Path to template
		$templatePath = $generatorTemplatePath . $templateFilePath;

		// Get Twig to render the template todo: do you have to instantiate a new twig or can you change the templatePath?
		$loader = new FilesystemLoader($templatePath);
		$twig = new Environment($loader, ['cache' => $this->twigCache]);

		// Add extension to Twig to render the language strings and put them in files
		$twig->addExtension($this->languageStringUtil);

		// Render the template
		$generatedContent= $twig->render($templateFileName, $templateVariables);

		return $generatedContent;
	}

}