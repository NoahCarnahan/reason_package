<?php
$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'MapModule';
reason_include_once( 'minisite_templates/modules/default.php' );
reason_include_once( 'classes/group_helper.php' );
include_once( CARL_UTIL_INC . 'dir_service/directory.php' );
include_once(THOR_INC . 'thor.php');
reason_include_once('classes/geocoder.php'); // in core/classes

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
 *		- Removed the module's cacheing of geocoded addresses because the geocoder class
 *		  already does that.
 *
 *
 * @todo Understand checkbox stuff...
 * @todo Location types and Maps types should have cool paper map icons on the sidebar
 * perhaps?
 * @todo Is this module intended to be able to map from multiple sources at once? Because it
 * probably doesn't do that very well... (see next item)
 * @todo There is a problem if you map from addresses and from form at the same time (potentialy)
 * If there is a bubble template, then the bubbles from location type entities just display the template.
 *
 * @todo Remove uneeded includes
 * @todo Update the suporting javascript to support multiple maps per page.
 */
class MapModule extends DefaultMinisiteModule
{
	var $acceptable_params = array(
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
		'bubble_template' => '',
		'bubble_requires_authentication' => true,
		'scatter_points' => false, // boolean or divisor -- smaller numbers equal larger scattering
	);
	
	var $members;
	var $mapItems;
	var $maps;
	var $es;
	var $contains_private_data = false;
	var $logged_in = false;
	var $_sub_map_markup = '';
	var $cleanup_rules = array();
	var $_geocoder;

	
	function init( $args = array() )
	{
		$this->_geocoder = new geocoder();

		$this->redir_link_text = 'the map';
		
		if ($this->user_netid = reason_check_authentication()) $this->logged_in = true;			
		
		parent::init( $args );
		
		$this->get_head_items()->add_javascript(JQUERY_URL, true);
		$this->parent->add_head_item('script', array('language' => 'JavaScript', 'type' => 'text/javascript', 'src' =>'//maps.googleapis.com/maps/api/js?sensor=false'), ' ');
		$this->parent->add_head_item('script', array('language' => 'JavaScript', 'type' => 'text/javascript', 'src' => '/reason/modules/map/google_maps_V3.js'), ' ');

		$this->es = new entity_selector();
		$this->es->description = 'Selecting maps for this page';
		$this->es->add_type( id_of('map_type') );
		$this->es->add_right_relationship( $this->parent->cur_page->id(), relationship_id_of('page_to_map') );
		$this->maps = $this->es->run_one();						
	}

	function map_locations($map)
	{
		$this->es = new entity_selector();
		$this->es->description = 'Selecting locations for this map';
		$this->es->add_type( id_of('location_type') );
		$this->es->add_right_relationship( $map->id(), relationship_id_of('map_to_location') );
		$locations = $this->es->run_one();         
		
		// If there are locations associated with this map...
		if (!empty($locations))
		{
			foreach ($locations as $location) {
				$lat = $location->get_value('latitude');
				$lon = $location->get_value('longitude');

				// Define the display/geocoding versions of the location
				if ($location->get_value('city')) $city_plus = $location->get_value('city') . ', ';
				if ($location->get_value('state_region')) $city_plus .= $location->get_value('state_region') . ' ';
				if ($location->get_value('postal_code')) $city_plus .= $location->get_value('postal_code') . ' ';
				if ($location->get_value('country')) $city_plus .= $location->get_value('country') . ' ';
				$display = $location->get_value('name') . '<br />';
				if ($location->get_value('street1')) $display .= $location->get_value('street1') . '<br />';
				if ($location->get_value('street2')) $display .= $location->get_value('street2') . '<br />';
				$display .= $city_plus;
				
				if (empty($city_plus) && ($location->get_value('description')))
				{
					$display .= '<p>'.$location->get_value('description').'</p>';
				}
				
				if(!empty($this->params['bubble_template']))
				{
					$display = $this->_get_bubble($this->params['bubble_template'], $location->get_values());
				}
										
				// If the lat/lon isn't set, look it up
				if (empty($lat) || empty($lon))
				{
					if ($location->get_value('street2')) 
						$geo_addr = $location->get_value('street2') . ', ' . $city_plus; 
					elseif ($location->get_value('street1')) 
						$geo_addr = $location->get_value('street1') . ', ' . $city_plus; 
					else
						$geo_addr = $city_plus;
						
					// If the location maps, add the lat/lon to the location entity and map it
					if ($loc = $this->geocode($geo_addr))
					{
						$values['latitude'] = $loc['lat'];
						$values['longitude'] = $loc['lon'];
						reason_update_entity( $location->id(), get_user_id('root'), $values, false );
						$this->addpoint($map, $loc['lat'], $loc['lon'], $display);
					}
				// Otherwise, just map it
				} else {
					$this->addpoint($map, $location->get_value('latitude'), $location->get_value('longitude'), $display);	
				}
				
			}
		}
	}
	
	/**
	 * Helper function for map_from_form(). Takes a row (array returned from $tc->get_row)
	 * a ThorCore and a $this->params key.
	 */
	private function _get_value_from_row($row, $tc, $param)
	{
		return $row[$tc->get_column_name($this->params[$param])];
	}
	
	/**
	 * Checks the form related parameters passed to the module and returns a form id if
	 * valid parameters have been passed, otherwise returns false.
	 */
	private function _get_form_id()
	{
		if(!empty($this->params['form_name'])) {
			$form_id = id_of($this->params['form_name']);
			if(!empty($form_id)) {
				if(!empty($this->params['form_address_full']) || !empty($this->params['form_address_city'])){
					return $form_id;
				}
			}
		}
		return false;
	}
	
	function map_from_form($map)
	{
		//First check for the existence of a valid form
		if ($form_id = $this->_get_form_id())
		{
			$form = new entity($form_id);
			$xml = $form->get_value('thor_content');
			$table = 'form_' . $form->id();
			$tc = new ThorCore($xml, $table);
			
			//Get the apropriate rows from the form			
			if ($this->params['thor_filters'])
			{
				//Get filters
				$new_filts = array();
				foreach($this->params['thor_filters'] as $key=>$val)
				{
					$new_filts[$tc->get_column_name($key)] = $val; 
				}
				$op = strtoupper($this->params['thor_filters_operator']);
				$rows = $tc->get_rows_for_keys($new_filts, $op);
			} else {
				$rows = $tc->get_rows();
			}
			
			if ($rows)
			{
				foreach ($rows as $row)
				{
					if ($this->params['form_address_full'])
					{
						//If a full address is given, use that for the geocoding address
						$geo_addr = $this->_get_value_from_row($row, $tc, 'form_address_full');
						$display = ($this->params['form_address_name'] && $this->_get_value_from_row($row, $tc, 'form_address_name')) ? htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_name'), ENT_QUOTES) . '<br />' : '';
						// Someone may want to figure out how to split the address into multiple lines for display
						$display  .= htmlspecialchars($geo_addr);
						
					} else {
						//If a full address is not given, build up a geocoding address from the other fields.
						if ($this->params['form_address_city'] && $this->_get_value_from_row($row, $tc, 'form_address_city'))
							$city_plus = $this->_get_value_from_row($row, $tc, 'form_address_city') . ', ';
						if ($this->params['form_address_state'] && $this->_get_value_from_row($row, $tc, 'form_address_state'))
							$city_plus .= $this->_get_value_from_row($row, $tc, 'form_address_state') . ' ';
						if ($this->params['form_address_post_code'] && $this->_get_value_from_row($row, $tc, 'form_address_post_code'))
							$city_plus .= $this->_get_value_from_row($row, $tc, 'form_address_post_code') . ' ';
						if ($this->params['form_address_country'] && $this->_get_value_from_row($row, $tc, 'form_address_country'))
							$city_plus .= $this->_get_value_from_row($row, $tc, 'form_address_country') . ' ';
							
						if ($this->params['form_address_street2'] && $this->_get_value_from_row($row, $tc, 'form_address_street2'))
							$geo_addr = $this->_get_value_from_row($row, $tc, 'form_address_street2') . ', ' . $city_plus; 
						elseif ($this->params['form_address_street1'] && $this->_get_value_from_row($row, $tc, 'form_address_street1')) 
							$geo_addr = $this->_get_value_from_row($row, $tc, 'form_address_street1') . ', ' . $city_plus;
						else
							$geo_addr = $city_plus;
							
						// Build up the text of the info bubble
						$display = ($this->params['form_address_name'] && $this->_get_value_from_row($row, $tc, 'form_address_name')) ? htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_name'), ENT_QUOTES) . '<br />' : '';
						if ($this->params['form_address_street1'] && $this->_get_value_from_row($row, $tc, 'form_address_street1'))
							$display .= htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_street1'), ENT_QUOTES) . '<br />';
						if ($this->params['form_address_street2'] && $this->_get_value_from_row($row, $tc, 'form_address_street2'))
							$display .= htmlspecialchars($this->_get_value_from_row($row, $tc, 'form_address_street2'), ENT_QUOTES) . '<br />';
						$display .= htmlspecialchars($city_plus, ENT_QUOTES);
						
					}
					// Quotes in the display address will kill the Javascript
					$display = str_replace('"', '/"', $display);
					
					if(!empty($this->params['bubble_template']))
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
						$display = $this->_get_bubble($this->params['bubble_template'], $data);
					}
					
					// If the viewer isn't logged in, don't show address data
					if (!$this->logged_in && $this->params['bubble_requires_authentication'])
					{
						$display = '';
						$this->contains_private_data = true;
					}

					// If the address geocodes, map it.
					if ($loc = $this->geocode($geo_addr))
					{
						$this->addpoint($map, $loc['lat'], $loc['lon'], $display);
					} 		
				}
			}
		}
	}
	
	function geocode($address)
	{
		if ($result = $this->_geocoder->get_geocode($address))
		{
			$result['lat'] = $result['latitude'];
			$result['lon'] = $result['longitude'];
			return $result;
		}
		return false;
	}
	
	function addpoint($map, $lat, $lon, $text, $icon='', $shadow='')
	{
		//Note that icon and shadow should be the name of javascript variables that refer to google.maps.MarkerImages.
		
		if(!empty($this->params['scatter_points']))
		{
			if(!is_numeric($this->params['scatter_points']))
				$divisor = 1500;
			else
				$divisor = $this->params['scatter_points'];
			$lat += ( ( rand(0,20) - 10 ) / 10 ) / $divisor;
			$lon += ( ( rand(0,20) - 10 ) / 10 ) / $divisor;
		}
		$this->mapItems[$map->id()][] = array('func'=>'showPoint', 'latitude'=>$lat, 'longitude'=>$lon, 'displayText'=> $text, 'icon' => $icon, 'shadow' => $shadow);
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
	
	protected function _get_map_name($map)
	{
		return $map->get_value('name');
	}
	
	function build_map_items($map)
	{
		$this->map_locations($map);
		$this->map_from_form($map);
	}
	
	function run()
	{
		foreach($this->maps as $map) {
			$this->mapItems[$map->id()] = array();
			$this->build_map_items($map);

			//Don't try to display more than 500 points on a map
			if (count($this->mapItems[$map->id()]) > 500) 
				$this->mapItems[$map->id()] = array_slice($this->mapItems[$map->id()], 0, 500);
			
			$height_style = 'height: '.$map->get_value('map_height').'px;';
			if ($map->get_value('map_height') == 0) $height_style = 'height: 350px;';
			$width_style = 'width: '.$map->get_value('map_width').'px;';
			if ($map->get_value('map_width') == 0) $width_style = '';
			
			echo '<h3>' . $this->_get_map_name($map) . '</h3>';
			echo '<div id="map" style="display: none; ' . $width_style . $height_style .'"></div>';
			echo '<p>' . $map->get_value('description') ;
			if ($this->contains_private_data && !$this->logged_in && $this->params['bubble_requires_authentication']) echo '(<a href="' . REASON_LOGIN_URL . '">Log in</a> to view names and addresses.)';
			echo '</p>';
			
			echo '<div id="mapInfo">'."\n";
			echo '<h3>Your browser has javascript disabled and/or it does not support Google maps</h3>'."\n";
			echo '<h4>The following information would have been displayed on a map: </h4>'."\n";
			echo '<ul class="mapCommands">'."\n";
			foreach($this->mapItems[$map->id()] as $map_entry)
			{
				if ($map_entry['func'] == 'showPoint')
				{
					echo '<li class = "showPoint">'."\n";
						echo '<div class="displayText">'.$map_entry['displayText'].'</div>'."\n";
						echo '<div class="latlon"><span class="lat">'.$map_entry['latitude'].'</span>, <span class="lon">'.$map_entry['longitude'].'</span></div>'."\n";
						echo '<div class="icon" style="visibility:hidden;">'.$map_entry['icon'].'</div>'."\n";
						echo '<div class="shadow" style="visibility:hidden;">'.$map_entry['shadow'].'</div>'."\n";
					echo '</li>'."\n";
				}
				//NOTE: Add other conditionals here to add support for other interactions with the map.
			}
			echo '</ul>'."\n";
			echo '</div>'."\n";
			
			if($this->_sub_map_markup)
			{
				echo '<div class="subForm">'.$this->_sub_map_markup.'</div>'."\n";
			}
		}
	}
}
?>
