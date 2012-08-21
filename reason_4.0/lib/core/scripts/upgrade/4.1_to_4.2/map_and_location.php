<?php

include_once('reason_header.php');
reason_include_once('classes/field_to_entity_table_class.php');
reason_include_once('function_libraries/util.php');
reason_include_once('function_libraries/user_functions.php');
reason_include_once('function_libraries/admin_actions.php');
reason_include_once('classes/entity_selector.php');

$GLOBALS['_reason_upgraders']['4.1_to_4.2']['map_and_location'] = 'ReasonUpgrader_MapAndLocation';

class ReasonUpgrader_MapAndLocation implements reasonUpgraderInterface
{
	protected $user_id;
	
	var $map_type_details = array(
		'new'=> 0,
		'unique_name'=>'map_type',
		'plural_name'=>'Maps');
	
	var $location_type_details = array(
		'new' => 0,
		'unique_name'=>'location_type',
		'custom_content_handler'=>'location.php',
		'plural_name'=>'Locations');

	//Note: if adding in the rest of these relatioships, they must have 'details' added
	var $map_relationships = array(
		//array('a_side'=>'site', 'b_side'=>'map_type', 'name'=>'site_owns_map_type'),
		//array('a_side'=>'site', 'b_side'=>'map_type', 'name'=>'site_borrows_map_type'),
		//array('a_side'=>'map_type', 'b_side'=>'map_type', 'name'=>'map_type_archive'),
		array('a_side'=>'minisite_page', 'b_side'=>'map_type', 'name'=>'page_to_map', 'details'=>
			array (
				'description'=>'This relationship allows a map to be displayed on a page',
				'directionality'=>'unidirectional',
				'connections'=>'many_to_many',
				'required'=>'no',
				'is_sortable'=>'no',
				'display_name'=>'Add map to page',
				'description_reverse_direction'=>'Page(s) displaying this map'
			)
		),
		//array('a_side'=>'map_type', 'b_side'=>'location_type', 'name'=>'map_to_location'),
		//array('a_side'=>'map_type', 'b_side'=>'group_type', 'name'=>'map_to_group')
		);
	
	var $location_relationships = array(
		//array('a_side'=>'site', 'b_side'=>'location_type', 'name'=>'site_owns_location_type'),
		//array('a_side'=>'site', 'b_side'=>'location_type', 'name'=>'site_borrows_location_type'),
		//array('a_side'=>'location_type', 'b_side'=>'location_type', 'name'=>'location_type_archive'),
		array('a_side'=>'map_type', 'b_side'=>'location_type', 'name'=>'map_to_location', 'details'=>
			array(
				'description'=>'This relationship allows locations to be added to a map',
				'directionality'=>'unidirectional',
				'connections'=>'many_to_many',
				'required'=>'no',
				'is_sortable'=>'no',
				'display_name'=>'Add location to map',
				'description_reverse_direction'=>'Map(s) displaying this location'
			)
		),
		//array('a_side'=>'quote_type', 'b_side'=>'location_type', 'name'=>'quote_refers_to_location'),
		//array('a_side'=>'quote_type', 'b_side'=>'location_type', 'name'=>'quote_is_about_location'),
		//array('a_side'=>'office_department_type', 'b_side'=>'location_type', 'name'=>'office_department_has_location'),
		//array('a_side'=>'location_type', 'b_side'=>'image', 'name'=>'location_has_emblematic_image'),
		//array('a_side'=>'av', 'b_side'=>'location_type', 'name'=>'av_refers_to_location'),
		//array('a_side'=>'av', 'b_side'=>'location_type', 'name'=>'av_is_about_location'),
		//array('a_side'=>'image', 'b_side'=>'location_type', 'name'=>'image_depicts_location')
		);

	public function user_id( $user_id = NULL)
	{
		if(!empty($user_id))
			return $this->_user_id = $user_id;
		else
			return $this->_user_id;
	}
	/**
	 * Get the title of the upgrader
	 * @return string
	 */
	public function title()
	{
		return 'Add Map and Location types';
	}
	/**
	 * Get a description of what this upgrade script will do
	 * 
	 * @return string HTML description
	 */
	public function description()
	{
		return '<p>This script sets up Map types and Location types for use with the map module.</p>';
	}
	/**
     * Do a test run of the upgrader
     * @return string HTML report
     */
	public function test()
	{
		$buf = '';
		$buf .= $this->add_location_type('test');
		$buf .= $this->add_map_type('test');
		return $buf;
	}
	/**
	 * Run the upgrader
	 *
	 * @return string HTML report
	 */
	public function run()
	{
		$buf = '';
		$buf .= $this->add_location_type('run');
		$buf .= $this->add_map_type('run');
		return $buf;
	}
	
	function add_location_type($mode, &$was_successful = true)
	{
		$buf = '';
		if(! ($mode == 'test' || $mode == 'run'))
		{
			$was_successful = false;
			$buf .= '<p>Invalid mode provided to function add_location_type. $mode must be "run" or "test".</p>'."\n";
			return $buf;

		}
		if (reason_unique_name_exists('location_type', false))
		{
			$buf .= '<p>location_type already exists. No need to create</p>'."\n";
			$was_successful = false;
			return $buf;
		}
		if ($mode == 'run')
		{
			reason_create_entity(id_of('master_admin'), id_of('type'), $this->user_id(), 'Location', $this->location_type_details);
			if(reason_unique_name_exists('location_type', false))
			{
				$location_type_id = id_of('location_type');
				create_default_rels_for_new_type($location_type_id);
				//Add the meta and show_hide table
				$this->add_entity_table_to_type('meta','location_type');
				$this->add_entity_table_to_type('show_hide','location_type');
				//set up the location table
				$this->create_entity_table('location','location_type', $this->user_id());
				$fields = array('street1'=>array('db_type' => 'tinytext'),
								'street2'=>array('db_type' => 'tinytext'),
								'city'=>array('db_type' => 'tinytext'),
								'state_region'=>array('db_type' => 'tinytext'),
								'postal_code'=>array('db_type' => 'tinytext'),
								'country'=>array('db_type' => 'tinytext'),
								'slug'=>array('db_type' => 'tinytext'),
								'latitude'=>array('db_type' => 'double'),
								'longitude'=>array('db_type' => 'double'),
								'kml_snippet'=>array('db_type' => 'text'));
				$updater = new FieldToEntityTable('location', $fields);
				$updater->update_entity_table();
				$updater->report();
				
				$buf .= '<p>location_type successfully created</p>'."\n";
				$was_successful = true;
				return $buf;
			}
			else
			{
				$buf .= '<p>Unable to create location_type</p>';
				$was_successful = false;
				return $buf;
			}
		}
		else
		{
			$buf .= '<p>Would have created location_type, and added the appropriate entity tables</p>';
			$was_successful = true;
			return $buf;
		}
	}
	
	function add_map_type($mode, &$was_successful = true)
	{
		$buf = '';
		if(! ($mode == 'test' || $mode == 'run'))
		{
			$was_successful = false;
			$buf .= '<p>Invalid mode provided to function add_location_type. $mode must be "run" or "test".</p>'."\n";
			return $buf;

		}
		if (reason_unique_name_exists('map_type', false))
		{
			$buf .= '<p>map_type already exists. No need to create</p>'."\n";
			$was_successful = false;
			return $buf;
		}
		if ($mode == 'run')
		{
			reason_create_entity(id_of('master_admin'), id_of('type'), $this->user_id(), 'Map', $this->map_type_details);
			if(reason_unique_name_exists('map_type', false))
			{
				$map_type_id = id_of('map_type');
				create_default_rels_for_new_type($map_type_id);
				
				//Add the meta table
				$this->add_entity_table_to_type('meta','map_type');
				//set up the map table
				$this->create_entity_table('map','map_type', $this->user_id());
				$fields = array('map_height'=>array('db_type' => 'smallint'),
								'map_width'=>array('db_type' => 'smallint'));
				$updater = new FieldToEntityTable('map', $fields);
				$updater->update_entity_table();
				$updater->report();
				
				$buf .= '<p>map_type successfully created</p>'."\n";
				$was_successful = true;
				return $buf;
			}
			else
			{
				$buf .= '<p>Unable to create '.$this->map_type_details['unique_name'].'</p>';
				$was_successful = false;
				return $buf;
			}
		}
		else
		{
			$buf .= '<p>Would have created map_type, and added the appropriate entity tables</p>'."\n";
			$was_successful = true;
			return $buf;
		}
	}
	
	/**
	 * Return true if the entity table exists, false otherwise
	 * @param string $et unique name of entity table
	 */
	function check_for_entity_table($et)
	{
		$es = new entity_selector(id_of('master_admin'));
		$es->add_type(id_of('content_table'));
		$es->add_relation('entity.name = "'.$et.'"');
		$results = $es->run_one();
		$result_count = count($results);
		if ($result_count == 0) return false;
		else return true;
	}
	
	/**
	 * I am pretty sure that this function creates an entity table with the given name
	 * and attaches to the given type.
	 */
	function create_entity_table($table_name, $type_unique_name, $username_or_id)
	{
		if (!$this->check_for_entity_table($table_name))
		{
			$type_id = id_of($type_unique_name);
			create_reason_table($table_name, $type_id, $username_or_id);
			return true;
		}
		return false;
	}
	
	/**
	 * Attach the given entity table and given type. Both must already exist.
	 * @param string $et Entity table name
	 * @param string $type The unique name of a type
	 */
	function add_entity_table_to_type($et, $type)
	{
		$pub_type_id = id_of($type);	
		$es = new entity_selector( id_of('master_admin') );
		$es->add_type( id_of('content_table') );
		$es->add_right_relationship($pub_type_id, relationship_id_of('type_to_table') );
		$es->add_relation ('entity.name = "'.$et.'"');
		$entities = $es->run_one();
		if (empty($entities))
		{
			$es2 = new entity_selector();
			$es2->add_type(id_of('content_table'));
			$es2->add_relation('entity.name = "'.$et.'"');
			$es2->set_num(1);
			$tables = $es2->run_one();
			if(!empty($tables))
			{
				$table = current($tables);
				create_relationship($pub_type_id,$table->id(),relationship_id_of('type_to_table'));
				return true;
			}
		}
		return false;
	}
	
}

?>