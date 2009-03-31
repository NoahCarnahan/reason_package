<?php
/**
 * @package reason
 * @subpackage minisite_templates
 */
	
	/**
	 * Include parent class; register module with Reason
	 */
	reason_include_once( 'minisite_templates/modules/default.php' );

	$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'NavigationTopModule';
	
	/**
	 * A minisite module that presents the "top nav" (e.g. tab navigation) of the current navigation class
	 */
	class NavigationTopModule extends DefaultMinisiteModule
	{
		function has_content()
		{
			return $this->parent->pages->top_nav_has_content();
		}
		function run()
		{
			echo '<div id="topNavigation">';
			$this->parent->pages->show_top_nav();
			echo '</div>';
		}
		function get_documentation()
		{
			if($this->has_content())
			{
				return '<p>Displays the top-level navigation for the site</p>';
			}
			else
			{
				return false;
			}
		}
	}

?>
