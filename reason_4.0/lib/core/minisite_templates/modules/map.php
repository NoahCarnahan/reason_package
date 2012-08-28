<?php
$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'MapModule';
reason_include_once( 'minisite_templates/modules/default.php' );
reason_include_once( 'classes/group_helper.php' );
include_once( CARL_UTIL_INC . 'dir_service/directory.php' );
include_once(THOR_INC . 'thor.php');
reason_include_once('classes/map.php'); // in core/classes

/**
 * Major changes in this version (compared to the Carleton local map.php)
 * 	- Forms
 *		- form parameters now should be given thor element labels, not names. So now in page_types
 *		  We have lines like this: 'form_address_name' => 'Your Name',
 *		  instead of this: 'form_address_name' => 'id_x8201tv7Jb',
 *		- When using the 'bubble_template' parameter thor column labels have replaced thor
 *		  column labels to specify where in the string replacements should take place.
 *		- Removed the 'form_selectset_nav' parameter and the 'map_filter' request variable
 *		  (No modules seemed to use them)
 *		- $this->contains_private_data had previously always been set. It is now only set if
 *		  bubble_requires_authentication is true.
 *		- Added parameter 'thor_filters_operator' to designate a logical connective to use
 *		  between filters.
 *		- Removed the module's caching of geocoded addresses because the geocoder class
 *		  already does that.
 *		- Javascript now supports multiple maps IF a form is not specified. Specifying
 *		  forms will make it go kablooy
 *		- Now supports multiple maps
 *		- The only allowable parameter is called 'customizations' now. 'customizations' is
 *		  an array indexed by map unique name. At each index is an array that supports all
 *		  the old parameters listed below.
 *
 *
 * @todo Understand checkbox stuff...
 * @todo sub_map_markup is a thing, but I don't see it being set anywhere... perhaps classes
 * that extend this one make use of it?
 *
 * @todo Remove uneeded includes
 */
class MapModule extends DefaultMinisiteModule
{
	/**
	'cusomtizations' should point to an array, which should be indexed by unique map name.
	The map names should point to other arrays which may contain the folowing parameters:
		'form_name' =>'', // unique name of a Reason form
		'form_address_name' => '', // The person or place name associated with the address
		'form_address_full' => '', // a full, geocodable address, if preferred to the parts below:
		'form_address_street1' => '',
		'form_address_street2' => '', 
		'form_address_city' => '', 
		'form_address_state' => '',
		'form_address_post_code' => '',
		'form_address_country' => '', 
		'thor_filters' => array(), // 'field' => 'value' pairs to limit the results from the form data
		'thor_filters_operator' => 'OR', // logical conective to link the filters. use AND or OR.
		'bubble_template' => '', //A template for building info boxes from form data. The template is not applied to location entities
		'bubble_requires_authentication' => true,
		'scatter_points' => false, // boolean or divisor -- smaller numbers equal larger scattering
	*/
	var $acceptable_params = array(
		'customizations' => array(),
	);

	
	var $maps;
	var $reason_maps = array(); //indexed by map id (These are probably NUMERIC indexes)
	var $map_displayers = array(); // indexed by map id (These are probably NUMERIC indexes)
	
	var $es;
	var $contains_private_data = array(); //indexed by map id (initialized to false in init())
	var $logged_in = false;
	var $_sub_map_markup = '';
	
	function init( $args = array() )
	{	
		parent::init( $args );
		
		if ($this->user_netid = reason_check_authentication()) $this->logged_in = true;
		
		$this->es = new entity_selector();
		$this->es->description = 'Selecting maps for this page';
		$this->es->add_type( id_of('map_type') );
		$this->es->add_right_relationship( $this->parent->cur_page->id(), relationship_id_of('page_to_map') );
		$this->maps = $this->es->run_one();
		
		foreach ($this->maps as $map)
		{
			$this->reason_maps[$map->id()] = new reasonMap($map);
			$this->map_displayers[$map->id()] = new defaultReasonMapDisplayer();
			
			$this->map_displayers[$map->id()]->set_height($map->get_value('map_height'));
			$this->map_displayers[$map->id()]->set_width($map->get_value('map_width'));
			$this->map_displayers[$map->id()]->set_name($map->get_value('name'));
			$this->map_displayers[$map->id()]->set_description($map->get_value('description'));
			$this->map_displayers[$map->id()]->set_sub_map_markup($this->_sub_map_markup);
			$this->map_displayers[$map->id()]->set_display_limit(500);
			$scat = $this->get_param($map, 'scatter_points');
			if ($scat == null) $scat = false;
			$this->map_displayers[$map->id()]->set_scatter($scat);
		
			$this->contains_private_data[$map->id()] = false;
			
			$this->init_geopoints($map);
		}
		
		//Add the apropriate head items to the module
		if (!empty($this->map_displayers))
		{	//It does not matter which map displayer we use to add the head items since they will all do the same thing.
			reset($this->map_displayers)->set_head_items($this->get_head_items());
		}			
	}
	
	/**
	 * Returns the value of $this->params['customizations'][mapname][param] if it exists,
	 * or null otherwise.
	 *
	 * @param Object $map A map entity.
	 * @param String $param The name of the page_types parameter te be retrieved
	 * @return Mixed 
	 */
	private function get_param($map, $param)
	{
		if (isset($this->params['customizations'][$map->get_value('unique_name')]))
		{
			if (isset($this->params['customizations'][$map->get_value('unique_name')][$param]))
			{
				return $this->params['customizations'][$map->get_value('unique_name')][$param];
			}
		}
		return null;
	}
	
	/**
	 * Add all reaosnGeopoints to the mapDisplayer associated with the given map via location entities
	 *
	 * @param Object $map A map entity.	
	 */
	function init_geopoints_from_locations($map)
	{
		$this->map_displayers[$map->id()]->add_geopoints($this->reason_maps[$map->id()]->get_geopoints());
	}
	
	/**
	 * Add all reaosnGeopoints to the mapDisplayer associated with the given map via forms
	 *
	 * @param Object $map A map entity.	
	 */
	function init_geopoints_from_form($map)
	{
		//Check for Form data and add apropriate geopoints
		$displayer = $this->map_displayers[$map->id()];
		
		if($form_id = $this->get_form($map))
		{
			$form = new entity($form_id);
			$xml = $form->get_value('thor_content');
			$table = 'form_' . $form->id();
			$tc = new ThorCore($xml, $table);
			
			//Get the apropriate rows from the form
			if ($this->get_param($map, 'thor_filters'))
			{
				//Get filters
				$new_filts = array();
				foreach($this->get_param($map, 'thor_filters') as $key=>$val)
				{
					$new_filts[$tc->get_column_name($key)] = $val; 
				}
				if ($this->get_param($map, 'thor_filters_operator') == null)
					$op = 'OR';
				else
					$op = strtoupper($this->get_param($map, 'thor_filters_operator')); 
				$rows = $tc->get_rows_for_keys($new_filts, $op);
			} else {
				$rows = $tc->get_rows();
			}
			
			if ($rows)
			{	//Build a reasonGeopoint from each returned row of data.
				foreach ($rows as $row)
				{
					if ($this->get_param($map, 'form_address_full'))
					{
						//If a full address is given, use that for the geocoding address
						$geo_addr = $this->_get_value_from_row($row, $tc, 'form_address_full', $map);
						$display = ($this->get_param($map, 'form_address_name') && $this->_get_value_from_row($row, $tc, 'form_address_name', $map)) ? htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_name', $map), ENT_QUOTES) . '<br />' : '';
						// Someone may want to figure out how to split the address into multiple lines for display
						$display  .= htmlspecialchars($geo_addr);
						
					} else {
						//If a full address is not given, build up a geocoding address from the other fields.
						if ($this->get_param($map, 'form_address_city') && $this->_get_value_from_row($row, $tc, 'form_address_city', $map))
							$city_plus = $this->_get_value_from_row($row, $tc, 'form_address_city', $map) . ', ';
						if ($this->get_param($map, 'form_address_state') && $this->_get_value_from_row($row, $tc, 'form_address_state', $map))
							$city_plus .= $this->_get_value_from_row($row, $tc, 'form_address_state', $map) . ' ';
						if ($this->get_param($map, 'form_address_post_code') && $this->_get_value_from_row($row, $tc, 'form_address_post_code', $map))
							$city_plus .= $this->_get_value_from_row($row, $tc, 'form_address_post_code', $map) . ' ';
						if ($this->get_param($map, 'form_address_country') && $this->_get_value_from_row($row, $tc, 'form_address_country', $map))
							$city_plus .= $this->_get_value_from_row($row, $tc, 'form_address_country', $map) . ' ';
							
						if ($this->get_param($map, 'form_address_street2') && $this->_get_value_from_row($row, $tc, 'form_address_street2', $map))
							$geo_addr = $this->_get_value_from_row($row, $tc, 'form_address_street2', $map) . ', ' . $city_plus; 
						elseif ($this->get_param($map, 'form_address_street1') && $this->_get_value_from_row($row, $tc, 'form_address_street1', $map)) 
							$geo_addr = $this->_get_value_from_row($row, $tc, 'form_address_street1', $map) . ', ' . $city_plus;
						else
							$geo_addr = $city_plus;
							
						// Build up the text of the info bubble
						$display = ($this->get_param($map, 'form_address_name') && $this->_get_value_from_row($row, $tc, 'form_address_name', $map)) ? htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_name', $map), ENT_QUOTES) . '<br />' : '';
						if ($this->get_param($map, 'form_address_street1') && $this->_get_value_from_row($row, $tc, 'form_address_street1', $map))
							$display .= htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_street1', $map), ENT_QUOTES) . '<br />';
						if ($this->get_param($map, 'form_address_street2') && $this->_get_value_from_row($row, $tc, 'form_address_street2', $map))
							$display .= htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_street2', $map), ENT_QUOTES) . '<br />';
						$display .= htmlspecialchars($city_plus, ENT_QUOTES);
						
					}
					// Quotes in the display address will kill the Javascript
					$display = str_replace('"', '/"', $display);
					
					$temp = $this->get_param($map, 'bubble_template');
					if(!empty($temp))
					{
						$groups = $this->_get_checkbox_groups_from_form($form, $row);
						$data = array_merge($row,$groups);
						foreach($data as $key=>$val)
						{
							$new_key = $tc->get_column_label($key);
							if ($new_key)
							{
								$data[$new_key] = $val;
								unset($data[$key]);
							}
						}
						$display = $this->_get_bubble($this->get_param($map, 'bubble_template'), $data);
					}
					
					// If the viewer isn't logged in, don't show address data
					if (!$this->logged_in && $this->get_param($map, 'bubble_requires_authentication'))
					{
						$display = '';
						$this->contains_private_data[$map->id()] = true;
					}

					$geopoint = reasonGeopoint::from_address($geo_addr, $display);
					$displayer->add_geopoint($geopoint);		
				}
			}
		}
	}
	
	/**
	 * Add all necesary reaosnGeopoints to the mapDisplayer associated with the given map
	 *
	 * @param Object $map A map entity.
	 */
	function init_geopoints($map)
	{
		$this->init_geopoints_from_locations($map);
		$this->init_geopoints_from_form($map);
	}
	
	/**
	 * This function returns the id of a form if it is specified in the page type to be
	 * mapped onto the given map and if it is set up correctly. Otherwise, returns null
	 * @param Object $map A map entity
	 * @return Mixed Returns either the form id or null if no form is associated with the given map
	 */
	private function get_form($map)
	{
		$name = $this->get_param($map, 'form_name');
		if(!empty($name)) {
			$form_id = id_of($this->get_param($map, 'form_name'));
			if (!empty($form_id))
			{
				if($this->get_param($map, 'form_address_full') != null || $this->get_param($map, 'form_address_city') != null)
					return $form_id; 
			}
		}
		return null;
	}	
	
	/**
	 * Helper function for map_from_form(). Takes a row (array returned from $tc->get_row)
	 * a ThorCore and a $this->params key.
	 */
	private function _get_value_from_row($row, $tc, $param, $map)
	{		
		$col_label = $this->get_param($map, $param);
		return $row[$tc->get_column_name($col_label)];
	}

	function _get_checkbox_groups_from_form($form, $data = array())
	{
		static $form_groups = array();
		
		if(!isset($form_groups[$form->id()]))
		{
			// checkboxgroup
			$groups = array();
			$form_xml_obj = new XMLParser($form->get_value('thor_content'));
			$form_xml_obj->Parse();
			foreach ($form_xml_obj->document->tagChildren as $k=>$v)
			{
				$tagname = is_object($v) ? $v->tagName : '';
				if ('checkboxgroup' == $tagname)
				{
					$group_attrs = $v->tagAttrs;
					$groups[$group_attrs['id']] = array();
					foreach($v->tagChildren as $element_child)
					{
						$child_attrs = $element_child->tagAttrs;
						$groups[$group_attrs['id']][] = $child_attrs['id'];
					}
				}
			}
			$form_groups[$form->id()] = $groups;
		}
		
		if(empty($data))
		{
			return $form_groups[$form->id()];
		}
		else
		{
			$ret = array();
			foreach($form_groups[$form->id()] as $group_id=>$box_ids)
			{
				$ret[$group_id] = array();
				foreach($box_ids as $box_id)
				{
					if(!empty($data[$box_id]))
					{
						$ret[$group_id][$box_id] = $data[$box_id];
					}
				}
			}
			return $ret;
		}
	}
	
	function _get_bubble($template, $data = array() )
	{
		foreach($data as $key=>$val)
		{
			if(is_array($val))
			{
				$rep = str_replace(array("\n","\r"),'',nl2br(htmlspecialchars(implode(', ',$val),ENT_QUOTES)));
				$template = str_replace('[['.$key.']]',$rep,$template);
			}
			else
			{
				$template = str_replace('[['.$key.']]',str_replace(array("\n","\r"),'',nl2br(htmlspecialchars($val,ENT_QUOTES))),$template);
			}
		}
		return $template;
	}
	
	function run()
	{
		foreach($this->maps as $map)
		{
			$displayer = $this->map_displayers[$map->id()];
			if ($this->contains_private_data[$map->id()] && !$this->logged_in && $this->get_param($map, 'bubble_requires_authentication'))
				$displayer->set_description($displayer->get_description() . '(<a href="' . REASON_LOGIN_URL . '">Log in</a> to view names and addresses.)');
			echo $displayer->get_markup();
		}
	}
}
?>
