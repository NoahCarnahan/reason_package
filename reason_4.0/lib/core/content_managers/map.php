<?php
$GLOBALS[ '_content_manager_class_names' ][ basename( __FILE__) ] = 'mapManager';
reason_include_once('classes/geocoder.php'); // in core/classes

class mapManager extends ContentManager
{
	var $map_items = array();
	var $scatter_points = 1500; //1500
	var $_geocoder;
	
	function init_head_items()
	{
		parent::init_head_items();
		$this->head_items->add_javascript(JQUERY_URL, true);
		$this->head_items->add_javascript('//maps.googleapis.com/maps/api/js?sensor=false');
		$this->head_items->add_javascript('/reason/modules/map/google_maps_V3.js');

	}
	
	function retrieve_map_items()
	{
		$es = new entity_selector();
		$es->description = 'Selecting locations for this map';
		$es->add_type( id_of('location_type') );
		$es->add_right_relationship( $this->get_value('id'), relationship_id_of('map_to_location') );
		$locations = $es->run_one();
		// If there are locations associated with this map...
		if (!empty($locations))
		{
			foreach ($locations as $location) {
				$lat = $location->get_value('latitude');
				$lon = $location->get_value('longitude');

				// Define the display/geocoding versions of the location
				$city_plus = '';
				if ($location->get_value('city')) $city_plus .= $location->get_value('city') . ', ';
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
						$this->addpoint($loc['lat'], $loc['lon'], $display);
					}
				// Otherwise, just map it
				} else {
					$this->addpoint($location->get_value('latitude'), $location->get_value('longitude'), $display);	
				}
			}
		}
	}
	
	function generate_map_markup()
	{
		if (count($this->mapItems) > 500) 
				$this->mapItems = array_slice($this->mapItems, 0, 500);
			
		$height_style = 'height: '.$this->get_value('map_height').'px;';
		if ($this->get_value('map_height') == 0) $height_style = 'height: 350px;';
		$width_style = 'width: '.$this->get_value('map_width').'px;';
		if ($this->get_value('map_width') == 0) $width_style = '';
		
		echo '<h3>Map preview:</h3>';
		echo '<h3>' . $this->get_value('name') . '</h3>';
		echo '<div class="map" data-map-id="'.$this->get_value('id').'" style="display: none; ' . $width_style . $height_style .'"></div>';
		echo '<p>' . $this->get_value('description') ;
		echo '</p>';
		
		echo '<div class="mapInfo" data-map-id="'.$this->get_value('id').'">'."\n";
		echo '<h3>Your browser has javascript disabled and/or it does not support Google maps</h3>'."\n";
		echo '<h4>The following information would have been displayed on a map: </h4>'."\n";
		echo '<ul class="mapCommands" data-map-id="'.$this->get_value('id').'">'."\n";
		foreach($this->mapItems as $map_entry)
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
	}
	
	function show_form()
	{
		parent::show_form();
		$this->retrieve_map_items();
		$this->generate_map_markup();
	}
	
	function addpoint($lat, $lon, $text, $icon='', $shadow='')
	{
		//Note that icon and shadow should be the name of javascript variables that refer to google.maps.MarkerImages.
		$lat += ( ( rand(0,20) - 10 ) / 10 ) / $this->scatter_points;
		$lon += ( ( rand(0,20) - 10 ) / 10 ) / $this->scatter_points;
		$this->mapItems[] = array('func'=>'showPoint', 'latitude'=>$lat, 'longitude'=>$lon, 'displayText'=> $text, 'icon' => $icon, 'shadow' => $shadow);
	}
	
	function get_geocoder()
	{
		if(!isset($this->_geocoder))
			$this->_geocoder = new geocoder();
		return $this->_geocoder;
	}
	
	function geocode($address)
	{
		if ($result = $this->get_geocoder->get_geocode($address))
		{
			$result['lat'] = $result['latitude'];
			$result['lon'] = $result['longitude'];
			return $result;
		}
		return false;
	}
}
?>