<?php
/**
 * @package     Pluggen

 * @subpackage  Pluggen component
 * @version     0.0.2
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace administrator\components\com_pluggen\src\Field;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;


/**
 * Create a drop-down field with all plugin types that are implemented in this component.
 */
class PluginTypesField extends ListField
{
	//The field class must know its own type through the variable $type.
	protected $type = 'PluginTypes';

	// get the options for the list field


	/**
	 * Get the options for the list field: all implemented plugin types.
	 * To be extended to all possible plugin types, including custom ones.
     *
	 *     - Task plugin
     *     - todo: more types
	 *
	 */
	public function getOptions()
	{
		// insert your JSON here to list all possible plugin types.
		$pluginTypesJson = '{
			 "task":"Task Plugin (com_scheduler)"
		}';

        // decode the JSON
        $pluginTypes = json_decode($pluginTypesJson, true);

        // use a for each to iterate over the JSON
		$htmlTypeOptions = [];
        foreach($pluginTypes as $pluginType => $text)
        {
	        // Set an array with the  value / text items.
	        $pluginTypeOptions[] = array("value" => $pluginType, "text" => $text);
        }

        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $pluginTypeOptions);
        return $options;
    }
}
