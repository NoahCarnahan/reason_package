<?php
/**
 * I don't love the way icons and shadows work...
 * @author Noah Carnahan
 */
reason_include_once('classes/geocoder.php');
class reasonGeopoint
{

	private static $_geocoder;
	
	private $_label = '';
	private $_lat;
	private $_lon;
	
	private $_icon ='';
	private $_shadow ='';
	
	/**
	 * @param String $icon The name of a javascript variable pointing to a google.maps.MarkerImage
	 * @param String $shadow The name of a javascript variable pointing to a google.maps.MarkerImage
	 */
	function __construct($lat, $lon, $label='', $icon='',$shadow='')
	{
		$this->_lat = $lat;
		$this->_lon = $lon;
		$this->_label = $label;
		$this->_icon = $icon;
		$this->_shadow = $shadow;
	}
	
	/**
	 * Returns a new reasonGeopoint object from the given location entity
	 *
	 * @param Object $location A location entity
	 */
	public static function from_location($location)
	{
		$latlon = self::get_lat_lon_from_location($location);
		if (!empty($latlon))
			$point = new reasonGeopoint($latlon['lat'], $latlon['lon']);
		else
			$point = new reasonGeopoint(null, null);
		$point->set_label(self::get_label_from_location($location));
		return $point;
	}
	
	public static function from_address($address, $label ='')
	{
		$latlon = self::get_lat_lon_from_address($address);
		if(!empty($latlon))
			$point = new reasonGeopoint($latlon['lat'], $latlon['lon'], $label);
		else
			$point = new reasonGeopoint(null, null, $label);
		return $point;
	}
	
	private static function get_lat_lon_from_address($address)
	{
		if($latlon = self::geocode($address))
		{
			$lat = $latlon['lat'];
			$lon = $latlon['lon'];
		}
		if (!empty($lat) && !empty($lon))
		{
			return array('lat'=>$lat, 'lon'=>$lon);
		}
		return array();
	}
	
	/**
	 *
	 * @param Object $location
	 */
	private static function get_lat_lon_from_location($location)
	{
		$lat = $location->get_value('latitude');
		$lon = $location->get_value('longitude');
		
		if (empty($lat) || empty($lon))
		{
			$address = '';
			//I don't understand this logic...
			if ($location->get_value('street2')) 
				$address .= $location->get_value('street2') . ', ';
			elseif ($location->get_value('street1')) 
				$address .= $location->get_value('street1') . ', ';
		
			if ($location->get_value('city')) $address .= $location->get_value('city') . ', ';
			if ($location->get_value('state_region')) $address .= $location->get_value('state_region') . ' ';
			if ($location->get_value('postal_code')) $address .= $location->get_value('postal_code') . ' ';
			if ($location->get_value('country')) $address .= $location->get_value('country') . ' ';
			
			echo 'HERE!';
			
			$latlon = self::get_lat_lon_from_address($address);
			if ($latlon)
			{
				$values = array();
				$values['latitude'] = $latlon['lat'];
				$values['longitude'] = $latlon['lon'];
				reason_update_entity( $location->id(), get_user_id('root'), $values, false );
				return $latlon;
			}
			return array();
		}
		return array('lat'=>$lat, 'lon'=>$lon);
	}
	
	private static function get_label_from_location($location)
	{
		$label = $location->get_value('name') . '<br />';
		if ($location->get_value('street1')) $label .= $location->get_value('street1') . '<br />';
		if ($location->get_value('street2')) $label .= $location->get_value('street2') . '<br />';
		
		$city_plus = '';
		if ($location->get_value('city')) $city_plus .= $location->get_value('city') . ', ';
		if ($location->get_value('state_region')) $city_plus .= $location->get_value('state_region') . ' ';
		if ($location->get_value('postal_code')) $city_plus .= $location->get_value('postal_code') . ' ';
		if ($location->get_value('country')) $city_plus .= $location->get_value('country') . ' ';
		
		$label .= $city_plus;
		
		if (empty($city_plus) && ($location->get_value('description')))
		{
			$label .= '<p>'.$location->get_value('description').'</p>';
		}
		
		return $label;
	}
	
	private static function get_geocoder()
	{
		if (empty(self::$_geocoder))
		{
			self::$_geocoder = new geocoder();
		}
		return self::$_geocoder;
	}
	
	private static function geocode($address)
	{
		if ($result = self::get_geocoder()->get_geocode($address))
		{
			$result['lat'] = $result['latitude'];
			$result['lon'] = $result['longitude'];
			return $result;
		}
		return false;
	}
	
	function set_label($label)
	{
		$this->_label = $label;
	}
	
	function get_label()
	{
		return $this->_label;
	}
	
	function get_latitude()
	{
		return $this->_lat;
	}
	
	function get_longitude()
	{
		return $this->_lon;
	}
	
	function get_icon()
	{
		return $this->_icon;
	}
	function get_shadow()
	{
		return $this->_shadow;
	}
}

class reasonMap
{
	/**A map entity*/
	private $_map;
	/**An array of reasonGeopoint objects*/
	private $_geopoints = null;
	
	/**
	 * @param object $map A map entity.
	 */
	function __construct($map)
	{
		$this->_map = $map;
	}
	
	/**
	 * @return Array An array of reasonGeopoint objects
	 */
	function get_geopoints()
	{
		if ($this->_geopoints === null)
		{
			$es = new entity_selector();
			$es->description = 'Selecting locations for this map';
			$es->add_type( id_of('location_type') );
			$es->add_right_relationship( $this->_map->id(), relationship_id_of('map_to_location') );
			$locations = $es->run_one();
			
			$this->_geopoints = array();
			foreach ($locations as $location)
			{
				$this->_geopoints[] = reasonGeopoint::from_location($location);
			}
		}
		return $this->_geopoints;
	}
	
}

interface reasonMapDisplayer
{
	function set_height($height);
	function set_width($width);
	function set_name($name);
	function set_description($description);
	function set_sub_map_markup($mark);
	function set_scatter($amount);
	function set_display_limit($lim);
	/**
	 * Add the given geopoints to be displayed
	 * @param Array $geopoints An array of reasonGeopoint objects to be added to the map
	 */
	function add_geopoints($geopoints);
	/**
	 * Add a geopoint to be displayed
	 * @param Object $geopoint A reasonGeopoint object to be added to the map
	 */
	function add_geopoint($geopoint);
	/**
	 * Add the necessary javascript to the given head items object
	 * @param Object $head_items A head items object to add javascript to
	 */
	function set_head_items($head_items);
	/**
	 * @return String HTML markup to display the map.
	 */
	function get_markup();
}

/**
 * @todo Should add_geopoints and add_geopoint check to make sure they don't add duplicates?
 * @todo Things to add: point scattering
 						multiple mapping functions
 						map description
 						private data warning
 						limit map to 500 points
 						sub map markup
 * @todo Maybe map name, description, and sub markup should just be controlled by other things?
 * @todo Should get_markup really be making default height decissions and things like that?
 */
class defaultReasonMapDisplayer
{
	private static $_last_id = 1;

	public $_geopoints = array();
	public $_id;
	public $_width = 0;
	public $_height = 0;
	public $_display_limit = 500;
	public $_name;
	public $_scatter = false;
	public $_description; //Note that this will be inside <p> tags.
	public $_sub_map_markup;
	
	function __construct()
	{
		self::$_last_id += 1;
		$this->_id = self::$_last_id;
	}
	
	/**
	 * Set to null for unlimited
	 * @param Mixed $lim The maximum number of points to be displayed on the map.
	 */
	function set_display_limit($lim)
	{
		$this->_display_limit = $lim;
	}
	
	function set_sub_map_markup($mark)
	{
		$this->_sub_map_markup = $mark;
	}
	
	function set_name($name)
	{
		$this->_name = $name;
	}
	
	function set_description($description)
	{
		$this->_description = $description;
	}
	
	function get_description()
	{
		return $this->_description;
	}
	
	/**
	 * @param True, False, or an ammount?
	 */
	function set_scatter($amount)
	{
		$this->_scatter = $amount;
	}
	
	function set_width($width)
	{
		$this->_width = $width;
	}
	
	function set_height($height)
	{
		$this->_height = $height;
	}
	
	function add_geopoints($geopoints)
	{
		$this->_geopoints = array_merge($this->_geopoints, $geopoints);
	}
	
	function add_geopoint($geopoint)
	{
		$this->_geopoints[] = $geopoint;
	}
	
	function set_head_items($head_items)
	{
		$head_items->add_javascript(JQUERY_URL, true);
		$head_items->add_javascript('//maps.googleapis.com/maps/api/js?sensor=false');
		$head_items->add_javascript(REASON_HTTP_BASE_PATH.'/modules/map/google_maps_V3.js');
	}
	
	function apply_scatter($degree)
	{
		$scat = $this->_scatter;
		if ($this->_scatter === true)
			$scat = 1500;
		if ($scat)
			$degree += ( ( rand(0,20) - 10 ) / 10 ) / $scat;
		return $degree;
	}
	
	/**
	 * Returns the markup for the map without any headings or descriptions
	 */
	function get_map_only_markup()
	{
		$points = $this->_geopoints;
		if ($this->_display_limit != null)
		{
			if(count($points) > $this->_display_limit)
				$points = array_slice($points, 0, $this->_display_limit);
		}
		
		$height_style = 'height: '.$this->_height.'px;';
		if ($this->_height == 0) $height_style = 'height: 350px;';
		
		$width_style = 'width: '.$this->_width.'px;';
		if ($this->_width == 0) $width_style = '';
		
		$buf = '';
		$buf .= '<div class="map" data-map-id="'.strval($this->_id).'" style="display: none; ' . $width_style . $height_style .'"></div>'."\n";
		$buf .= '<div class="mapInfo" data-map-id="'.strval($this->_id).'">'."\n";
		$buf .= '<h3>Your browser has javascript disabled and/or it does not support Google maps</h3>'."\n";
		$buf .= '<h4>The following information would have been displayed on a map:</h4>'."\n";
		$buf .= '<ul class="mapCommands" data-map-id="'.strval($this->_id).'">'."\n";
		foreach($points as $point)
		{
			if ($point->get_latitude() && $point->get_longitude())
			{
				$buf .= '<li class = "showPoint">'."\n";
				$buf .= '<div class="displayText">'.$point->get_label().'</div>'."\n";
				$buf .= '<div class="latlon"><span class="lat">'.$this->apply_scatter($point->get_latitude()).'</span>, <span class="lon">'.$this->apply_scatter($point->get_longitude()).'</span></div>'."\n";
				$buf .= '<div class="icon" style="visibility:hidden;">'.$point->get_icon().'</div>'."\n";
				$buf .= '<div class="shadow" style="visibility:hidden;">'.$point->get_shadow().'</div>'."\n";
				$buf .= '</li>'."\n";
			}
		}
		$buf .= '</ul>'."\n";
		$buf .= '</div>'."\n";
		return $buf;
	}
	/**
	 * Returns the markup for the map including headings, titles, and descriptions.
	 */
	function get_markup()
	{
		$buf = '';
		if (!empty($this->_name))
			$buf .= '<h3>' . $this->_name . '</h3>'."\n";
		if (!empty($this->_description))
			$buf .= '<p>' . $this->_description . '</p>'."\n";
		$buf .= $this->get_map_only_markup();
		if (!empty($this->_sub_map_markup))
			$buf .= '<div class="subForm">'.$this->_sub_map_markup.'</div>'."\n";
			
		return $buf;
	}
}

?> 