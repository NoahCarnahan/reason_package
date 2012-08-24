<?php

class reasonGeopoint
{

	private static $_geocoder;
	
	private var $_label = '';
	private var $_lat;
	private var $_lon;

	function __construct($lat, $lon)
	{
		$this->$_lat = $lat;
		$this->$_lon = $lon;
	}
	
	/**
	 * Returns a new reasonGeopoint object from the given location entity
	 *
	 * @param Object $location A location entity
	 */
	public static function from_location($location)
	{
		$latlon = self::get_lat_lon_from_location($location)
		$point = new reasonGeopoint($latlon['lat'], $latlon['lon']);
		$point->set_label(self::get_label_from_location($location));
		return $point;
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
			
			if($latlon = self::geocode($address))
			{
				$values = array();
				$values['latitude'] = $latlon['lat'];
				$values['longitude'] = $latlon['lon'];
				reason_update_entity( $location->id(), get_user_id('root'), $values, false );
				$lat = $latlon['lat'];
				$lon = $latlon['lon'];
			}
		}
		if (!empty($lat) && !empty($lon))
		{
			return array('lat'=>$lat, 'lon'=>$lon);
		}
		return array();

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
	
	}
	
	function get_label()
	{
	
	}
	
	function get_latitude()
	{
	
	}
	
	function get_longitude()
	{
	
	}
}

class reasonMap
{

	private var $map;
	private var $geopoints;
	
	/**
	 * @param object $map A map entity.
	 */
	function __construct($map)
	{
		$this->map = $map;
	}
	
	/**
	 * @return Array An array of reasonGeopoint objects
	 */
	function get_geopoints()
	{
		if (empty($this->geopoints))
		{
			$es = new entity_selector();
			$es->description = 'Selecting locations for this map';
			$es->add_type( id_of('location_type') );
			$es->add_right_relationship( $this->map->id(), relationship_id_of('map_to_location') );
			$locations = $es->run_one();
			
			$this->geopoints = array()
			foreach ($locations as $location)
			{
				$this->geopoints[] = reasonGeopoint::from_location($location);
			}
		}
		return $this->geopoints;
	}
	
}

?> 