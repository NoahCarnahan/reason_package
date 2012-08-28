<?php
/**
 * @package reason
 * @subpackage content_previewers
 */
reason_include_once( 'classes/map.php' );
$GLOBALS[ '_content_previewer_class_names' ][ basename( __FILE__) ] = 'map_previewer';

class map_previewer extends default_previewer
{
	public $reason_map;
	public $map_displayer;

	function init($id, &$page)
	{
		parent::init($id, $page);
		$this->reason_map = new reasonMap(new entity( $this->get_value('id')));
		$this->map_displayer = new defaultReasonMapDisplayer();
		$this->map_displayer->set_head_items($this->admin_page->module->head_items);
		$this->map_displayer->set_scatter(true);
		$this->map_displayer->set_height($this->get_value('map_height'));
		$this->map_displayer->set_width($this->get_value('map_width'));
		$this->map_displayer->set_name('Map preview:');
		$this->map_displayer->set_description($this->get_value('description'));
		$this->map_displayer->add_geopoints($this->reason_map->get_geopoints());
	}

	function display_relationships()
	{
		echo $this->map_displayer->get_markup();
		parent::display_relationships();
	}
	
	function get_value($val)
	{
		return $this->_entity->_values[$val];
	}
}
?>
