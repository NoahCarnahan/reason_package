<?php
	
	$GLOBALS[ '_content_manager_class_names' ][ basename( __FILE__) ] = 'locationManager';
	//include_once( SETTINGS_INC . 'map_settings.php');
	
	class locationManager extends ContentManager
	{
		/**
		 * @todo There should probably be a better way to alter the _no_tidy array
		 */
		function alter_data()
		{
			parent::alter_data();
			$this->_no_tidy[] = 'kml_snippet';
			$this->set_allowable_html_tags('kml_snippet','all');
		}
		
		function init_head_items()
		{
			$this->head_items->add_javascript("//maps.googleapis.com/maps/api/js?sensor=false");
			
			$this->head_items->add_javascript(JQUERY_URL, true); // uses jquery - jquery should be at top
			
			//$this->head_items->add_javascript('/global_stock/js/location_content_manager_v3.js');
			$this->head_items->add_javascript('/reason/js/content_managers/location.js');
			
		}
	}
?>
