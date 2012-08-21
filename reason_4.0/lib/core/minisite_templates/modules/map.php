<?php
$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'MapModule';
reason_include_once( 'minisite_templates/modules/default.php' );
reason_include_once( 'classes/group_helper.php' );
//include_once( SETTINGS_INC . 'map_settings.php' );
include_once( CARL_UTIL_INC . 'dir_service/directory.php' );
//include_once(WEB_PATH . 'alumni/directory/search/person.php');
reason_include_once('classes/geocoder.php'); // in core/classes

/**
 * @todo Remove uneaded includes
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
		'form_selectset_nav' => array(), // array of select/radio/checkbox ids to provide filtering of form data
		'thor_filters' => array(), // 'field' => 'value' pairs to limit the results from the form data
		'bubble_template' => '',
		'bubble_requires_authentication' => true,
		'scatter_points' => false, // boolean or divisor -- smaller numbers equal larger scattering
	);
	
	var $members;
	var $mapItems;
	//var $mapkey;
	var $maps;
	var $es;
	var $geocache = array();
	var $contains_private_data = false;
	var $logged_in = false;
	var $_sub_map_markup = '';
	var $cleanup_rules = array(
		'map_filter' => array('function' => 'turn_into_string'), // a field id -- show only pins for which that field is non-empty (thor only)
	);
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

	function map_addresses($map)
	{
		$this->es = new entity_selector();
		$this->es->description = 'Selecting addresses for this map';
		$this->es->add_type( id_of('location_type') );
		$this->es->add_right_relationship( $map->id(), relationship_id_of('map_to_location') );
		$addresses = $this->es->run_one();         
		
		// If there are addresses associated with this map...
		//echo '<p>About to check for associated addresses</p>';
		if (!empty($addresses))
		{
			//echo '<p>There are associated addresses</p>';
			foreach ($addresses as $address) {
				$lat = $address->get_value('latitude');
				$lon = $address->get_value('longitude');

				// Define the display/geocoding versions of the address
				if ($address->get_value('city')) $city_plus = $address->get_value('city') . ', ';
				if ($address->get_value('state_region')) $city_plus .= $address->get_value('state_region') . ' ';
				if ($address->get_value('postal_code')) $city_plus .= $address->get_value('postal_code') . ' ';
				if ($address->get_value('country')) $city_plus .= $address->get_value('country') . ' ';
				$display = $address->get_value('name') . '<br />';
				if ($address->get_value('street1')) $display .= $address->get_value('street1') . '<br />';
				if ($address->get_value('street2')) $display .= $address->get_value('street2') . '<br />';
				$display .= $city_plus;
				
				if (empty($city_plus) && ($address->get_value('description')))
				{
					$display .= '<p>'.$address->get_value('description').'</p>';
				}
				
				if(!empty($this->params['bubble_template']))
				{
					$display = $this->_get_bubble($this->params['bubble_template'], $address->get_values());
				}
										
				// If the lat/lon isn't set, look it up
				if (empty($lat) || empty($lon))
				{
					if ($address->get_value('street2')) 
						$geo_addr = $address->get_value('street2') . ', ' . $city_plus; 
					elseif ($address->get_value('street1')) 
						$geo_addr = $address->get_value('street1') . ', ' . $city_plus; 
					else
						$geo_addr = $city_plus;
						
					// If the address maps, add the location to the address entity and map it
					if ($loc = $this->geocode($geo_addr))
					{
						$values['latitude'] = $loc['lat'];
						$values['longitude'] = $loc['lon'];
						reason_update_entity( $address->id(), get_user_id('root'), $values, false );
						$this->addpoint($map, $loc['lat'], $loc['lon'], $display);
					}
				// Otherwise, just map it
				} else {
					$this->addpoint($map, $address->get_value('latitude'), $address->get_value('longitude'), $display);	
				}
				
			}
		}
	}
	
	function map_from_form($map)
	{
		if(!empty($this->params['form_name']))
		{
			$form_id = id_of($this->params['form_name']);
			if(!empty($form_id))
			{
				if(!empty($this->params['form_address_full']) || !empty($this->params['form_address_city']))
				{
					include_once(THOR_INC.'thor_viewer.php');
					$thor = new ThorViewer();
					$thor->init_thor_viewer($form_id);
					if (count($this->params['thor_filters'])) $thor->set_filters($this->params['thor_filters']);
					$data = $thor->get_data();
					if(!empty($data))
					{
						$form = new entity($form_id);
					}
					
					$this->contains_private_data = true;
					
					//$checkboxgroups = $this->_get_checkbox_groups_from_form($form);
					$addresses = array();
					$filter_field = '';
					if($this->params['form_selectset_nav'])
					{
						if(!empty($this->request['map_filter']) && in_array($this->request['map_filter'],$this->params['form_selectset_nav']) )
						{
							$filter_field = $this->request['map_filter'];
							
						}
						foreach($this->params['form_selectset_nav'] as $navitem)
						{
							$this->_sub_map_markup .= '<a href="?map_filter_id='.urlencode($navitem).'">'.$navitem.'</a> ';
						}
					}
					foreach($data as $row)
					{
						if($filter_field && empty($row[$filter_field]))
							continue;
						if ($this->params['form_address_full'])
						{
							$geo_addr = $row[$this->params['form_address_full']];
							$display = ($this->params['form_address_name'] && $row[$this->params['form_address_name']]) ? htmlspecialchars($row[$this->params['form_address_name']], ENT_QUOTES) . '<br />' : '';
							// Someone may want to figure out how to split the address into multiple lines for display
							$display  .= htmlspecialchars($geo_addr);
						} else {
						
							// Define the display/geocoding versions of the address
							if ($this->params['form_address_city'] && $row[$this->params['form_address_city']]) 
								$city_plus = $row[$this->params['form_address_city']] . ', ';
							if ($this->params['form_address_state'] && $row[$this->params['form_address_state']]) 
								$city_plus .= $row[$this->params['form_address_state']] . ' ';
							if ($this->params['form_address_post_code'] && $row[$this->params['form_address_post_code']]) 
								$city_plus .= $row[$this->params['form_address_post_code']] . ' ';
							if ($this->params['form_address_country'] && $row[$this->params['form_address_country']]) 
								$city_plus .= $row[$this->params['form_address_country']] . ' ';
							$display = ($this->params['form_address_name'] && $row[$this->params['form_address_name']]) ? htmlspecialchars($row[$this->params['form_address_name']], ENT_QUOTES) . '<br />' : '';
							if ($this->params['form_address_street1'] && $row[$this->params['form_address_street1']]) 
								$display .= htmlspecialchars($row[$this->params['form_address_street1']], ENT_QUOTES) . '<br />';
							if ($this->params['form_address_street2'] && $row[$this->params['form_address_street2']]) 
								$display .= htmlspecialchars($row[$this->params['form_address_street2']], ENT_QUOTES) . '<br />';
							$display .= htmlspecialchars($city_plus, ENT_QUOTES);
							
							if ($this->params['form_address_street2'] && $row[$this->params['form_address_street2']]) 
								$geo_addr = $row[$this->params['form_address_street2']] . ', ' . $city_plus; 
							elseif ($this->params['form_address_street1'] && $row[$this->params['form_address_street1']]) 
								$geo_addr = $row[$this->params['form_address_street1']] . ', ' . $city_plus; 
							else
								$geo_addr = $city_plus;

						}
						// Quotes in the display address will kill the Javascript
						$display = str_replace('"', '/"', $display);
						
						if(!empty($this->params['bubble_template']))
						{
							$groups = $this->_get_checkbox_groups_from_form($form, $row);
							$display = $this->_get_bubble($this->params['bubble_template'], array_merge($row,$groups));
						}
						
						// If the viewer isn't logged in, don't show address data
						if (!$this->logged_in && $this->params['bubble_requires_authentication']) $display = '';

						if (isset($this->geocache[md5($geo_addr)] ))
						{
							// If the lat/lon is 0, this wasn't a codeable address, so we just have to throw it out.
							// Otherwise, we add it to the map.
							if ($this->geocache[md5($geo_addr)]['lat'] <> 0)
							{
								$this->addpoint($map, $this->geocache[md5($geo_addr)]['lat'],  
									$this->geocache[md5($geo_addr)]['lon'], $display);
							}
						}

						// If the lat/lon isn't set, look it up
						else
						{
							// If the address maps, cache the location and map it
							if ($loc = $this->geocode($geo_addr))
							{
								$values['latitude'] = $loc['lat'];
								$values['longitude'] = $loc['lon'];
								
								$this->cachepoint($geo_addr, $loc['lat'],  $loc['lon']);
								$this->addpoint($map, $loc['lat'], $loc['lon'], $display);
							} 
						}



						// somehow build up the address using the string in $this->params['form_address_format']
						$address = '';
						
						/*
						// The following is pseudocode due to confusion over how to use the caching structure
						// We don't necessarily have netids here, so I'm not sure how to use the geocache array or the 
						// cachepoint function to manage caching of the data
						
						if(!isset($this->geocache[$address]))
						{
							if($coords = $this->geocode($address))
							{
								$this->cachepoint(???);
								$this->addpoint($map, $coords['lat'], $coords['lon']);
							}
						}
						else
						{
							$this->addpoint($map, $this->geocache[$address]['lat'], $this->geocache[$address]['lon']);
						}
						
						
						*/
						
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
	
	function cachepoint($address, $lat, $lon)
	{
		$this->geocache[md5($address)] = array('address'=>$address, 'lat'=>$lat, 'lon'=>$lon);
	}
	
	function write_geocache($map)
	{
		$fh = fopen(REASON_CACHE_DIR . '/map_' . $map->id() . '.cache','w');
		fwrite($fh, serialize($this->geocache));
		fclose($fh);
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
	
	// Geo coordinates for maps are cached in the file system so that every address doesn't
	// have to be recoded every time
	function _get_cache_data($map)
	{
		if (file_exists(REASON_CACHE_DIR . '/map_' . $map->id() . '.cache') AND 
			$cachedata = file_get_contents(REASON_CACHE_DIR . '/map_' . $map->id() . '.cache'))
				$this->geocache = unserialize($cachedata);			
	}
	
	protected function _get_map_name($map)
	{
		return $map->get_value('name');
	}
	
	function run()
	{
		foreach($this->maps as $map) {
			$this->_get_cache_data($map);
			$this->mapItems[$map->id()] = array();
			$this->map_addresses($map);
			$this->map_from_form($map);
			$this->write_geocache($map);

			//Don't try to display more than 500 points on a map
			if (count($this->mapItems[$map->id()]) > 500) 
				$this->mapItems[$map->id()] = array_slice($this->mapItems[$map->id()], 0, 500);

			

			echo '<h3>' . $this->_get_map_name($map) . '</h3>';
			echo '<div id="map" style="display: none; width: ' . $map->get_value('map_width') . 'px; height: ' . $map->get_value('map_height') . 'px"></div>';
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
