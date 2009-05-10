<?php
/**
 * User Community Builder. Main Plugin Class.
 * Plugin to create and manage communities in any WordPress blog.
 * 
 * @version		$Rev$
 * @author		Jordi Canals
 * @package		Community
 * @link		http://alkivia.org/plugins/community
 * @license		http://www.gnu.org/licenses/gpl.html GNU General Public License v3

	Copyright 2009 Jordi Canals <gpl@alkivia.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

include_once ( dirname(__FILE__) . '/framework/plugins.php');
include_once ( CMAN_PATH . '/framework/roles.php' );

/**
 * Class ucomCommunity.
 * Sets the main environment for all Community components.
 * 
 * @author Jordi Canals
 */
class cmanCapsManager extends cmanPlugin
{
	/**
	 * Array with all capabilities to be managed. (Depends on user caps).
	 * The array keys are the capability, the value is its screen name.
	 * @var array
	 */
	private $capabilities = array();
	
	/**
	 * Array with roles that can be managed. (Depends on user roles).
	 * The array keys are the role name, the value is its translated name.  
	 * @var array
	 */
	private $roles = array();
	
	/**
	 * Current role we are managing
	 * @var string
	 */
	private $current;
	
	/**
	 * Maximum level current manager can assign to a user.
	 * @var int
	 */
	private $max_level;
	
	/**
	 * Sets default settings values.
	 * 
	 * @return void
	 */
	protected function setDefaults() {
		$this->generateSysNames();
		$this->defaults = array(
			'form-rows' => 5,
			'syscaps' => $this->capabilities
		);
	}
	
	/**
	 * Activates the plugin and sets the new capability 'Manage Capabilities'
	 * @return void
	 */
	protected function activate() {
		$role = get_role('administrator');
		$role->add_cap('manage_capabilities');
	}
	
	/**
	 * Adds admin panel menus. (At plugins loading time. This is before plugins_loaded).
	 * User needs to have 'manage_capabilities' to access this menus.
	 * This is set as an action in the parent class constructor.
	 * 
	 * @hook action admin_menu
	 * @return void
	 */
	function _adminMenus() {
		// First we check if user is administrator and can 'manage_capabilities'.
		$this->adminAlwaysManage();
		
		add_users_page( __('Capability Manager', $this->ID),  __('Capabilities', $this->ID), 'manage_capabilities', $this->p_dirs['subdir'], array(&$this, '_generalManager'));
	}
	
	/**
	 * Chacks if user is administrator and cannot manage capabilities.
	 * Resets the 'manage_capabilities' to admin as it cannot be removed from admin.
	 * 
	 * @return void
	 */
	private function adminAlwaysManage() {
		if ( current_user_can('administrator') && ! current_user_can('manage_capabilities') ) {
			$role = get_role('administrator');
			$role->add_cap('manage_capabilities');
		}
	}
	
	/**
	 * Includes global settings admin.
	 * 
	 * @hook add_submenu_page
	 * @return void
	 */
	function _generalManager() {
		
		if ( ! current_user_can('manage_capabilities') && ! current_user_can('administrator') ) {		// Verify user permissions.
			wp_die('<strong>' .__('What do you think you\'re doing?!?', $this->ID) . '</strong>');
		}
		
		global $wp_roles;
		$this->current = get_option('default_role');	// By default we manage the default role.
		
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer('capsman-general-manager');
			$this->processAdminGeneral();
		}

		$this->generateNames();
		$roles = array_keys($this->roles);
		if ( ! in_array($this->current, $roles) ) {
			$this->current = array_shift($roles);
		}
		
		$wp_roles = new WP_Roles();
		include ( CMAN_PATH . '/admin-general.php');
	}

	/**
	 * Processes and saves the changes in the general capabilities form.
	 * @return unknown_type
	 */
	private function processAdminGeneral() {
		global $wp_roles;
		
		if (! isset($_POST['action']) || 'update' != $_POST['action'] ) {
			akv_admin_error(__('Bad form Received', $this->ID));
			return;
		}
		
		$post = stripslashes_deep($_POST);
		$this->saveRoleCapabilities($post['current'], $post['caps'], $post['level']);
		$this->current = $post['current'];
		
		// Select a new role.
		if ( isset($post['Change']) && __('Change', $this->ID) == $post['Change'] ) {
			$this->current = $post['role'];
			
		// Create a new role.
		} elseif ( isset($post['Create']) && __('Create', $this->ID) == $post['Create'] ) {
			if ( $newrole = $this->createRole($post['create-name']) ) {
				akv_admin_notify(__('New role created.', $this->ID));
				$this->current = $newrole;
			} else {
				akv_admin_error(__('Error: Failed creating the new role.', $this->ID));
			}
		
		// Copy current role to a new one.
		} elseif ( isset($post['Copy']) && __('Copy', $this->ID) == $post['Copy'] ) {
			$current = get_role($post['current']);
			if ( $newrole = $this->createRole($post['copy-name'], $current->capabilities) ) {
				akv_admin_notify(__('New role created.', $this->ID));
				$this->current = $newrole;
			} else {
				akv_admin_error(__('Error: Failed creating the new role.', $this->ID));
			}
			
		// Save role changes. Already saved at start with self::saveRoleCapabilities().
		}elseif ( isset($post['Save']) && __('Save Changes', $this->ID) == $post['Save'] ) {
			akv_admin_notify(__('New capabilities saved.', $this->ID));
			
		// Create New Capability and adds it to current role.
		} elseif ( isset($post['AddCap']) &&  __('Add to role', $this->ID) == $post['AddCap'] ) {
			$wp_roles = new WP_Roles();
			
			if ( $newname = $this->createNewName($post['capability-name']) ) {
				$wp_roles->add_cap($post['current'], $newname['name']);
				akv_admin_notify(__('New capability added to role.', $this->ID));
			} else {
				akv_admin_error(__('Incorrect capability name.', $this->ID));
			}
		} else {
			akv_admin_error(__('Bad form received.', $this->ID));
		}
	}
	
	/**
	 * Callback function to create names.
	 * Replaces underscores by spaces and uppercases the first letter.
	 * 
	 * @access private
	 * @param string $cap Capability name.
	 * @return string	The generated name.
	 */
	function _capNamesCB( $cap ) {
		$cap = str_replace('_', ' ', $cap);
		$cap = ucfirst($cap);
	
		return $cap;
	}
	
	/**
	 * Generates an array with the capability names.
	 * The key is the capability and the value the created screen name.
	 * 
	 * @uses self::_capNamesCB()
	 * @return void
	 */
	private function generateSysNames() {
		$this->max_level = 10;
		$this->roles = akv_get_roles(true);
		$caps = array();
		
		foreach ( array_keys($this->roles) as $role ) {
			$role_caps = get_role($role);
			$caps = array_merge($caps, $role_caps->capabilities);
		}
		
		$keys = array_keys($caps);
		$names = array_map(array(&$this, '_capNamesCB'), $keys);
		$this->capabilities = array_combine($keys, $names);

		if ( is_array($this->settings['syscaps']) ) {
			$this->capabilities = array_merge($this->settings['syscaps'], $this->capabilities);
		}

		asort($this->capabilities);	
	}
	
	/**
	 * Generates an array with the user capability names.
	 * If user has 'administrator' role, system roles are generated.
	 * The key is the capability and the value the created screen name.
	 * A user cannot manage more capabilities that has himself (Except for administrators). 
	 * 
	 * @uses self::_capNamesCB()
	 * @return void
	 */
	private function generateNames() {
		if ( current_user_can('administrator') ) {
			$this->generateSysNames();
			return;
		}
		
		global $user_ID;
		$user = new WP_User($user_ID);
		$this->max_level = akv_caps2level($user->allcaps); 
		
		$keys = array_keys($user->allcaps);
		$names = array_map(array(&$this, '_capNamesCB'), $keys);
		$this->capabilities = array_combine($keys, $names);
		
		$roles = akv_get_roles(true);
		unset($roles['administrator']);
		
		foreach ( $user->roles as $role ) {			// Unset the roles from capability list.
			unset ( $this->capabilities[$role] );
			unset ( $roles[$role]);					// User cannot manage his roles.
		} 
		asort($this->capabilities);

		foreach ( array_keys($roles) as $role ) {
			$r = get_role($role);
			$level = akv_caps2level($r->capabilities);

			if ( $level > $this->max_level ) {
				unset($roles[$role]);
			}
		}

		$this->roles = $roles;
	}
	
	/**
	 * Creates a new role/capability name from user input name.
	 * Name rules are:
	 * 		- 2-40 charachers lenght.
	 * 		- Only letters, digits, spaces and underscores.
	 * 		- Must to start with a letter.
	 *  
	 * @param string $name	Name from user input.
	 * @return array|false An array with the name and display_name, or false if not valid $name.
	 */
	private function createNewName( $name ) {
		// Allow max 40 characters, letters, digits and spaces
		$name = trim(substr($name, 0, 40));
		$pattern = '/^[a-zA-Z][a-zA-Z0-9 _]+$/';

		if ( preg_match($pattern, $name) ) {
			$roles = akv_get_roles();
			
			$name = strtolower($name);
			$name = str_replace(' ', '_', $name);
			if ( in_array($name, $roles) || array_key_exists($name, $this->capabilities) ) {
				return false;	// Already a role or capability with this name.
			}
			
			$display = explode('_', $name);
			$display = array_map('ucfirst', $display);
			$display = implode(' ', $display);
			
			return compact('name', 'display'); 
		} else {
			return false;
		}
	}
	
	/**
	 * Creates a new role.
	 * 
	 * @param string $name	Role name to create.
	 * @param array $caps	Role capabilities.
	 * @return string|false	Returns the name of the new role created or false if failed.
	 */
	private function createRole( $name, $caps = array() ) {
		$role = $this->createNewName($name);
		if ( ! is_array($role) ) {
			return false;
		}
		
		$new_role = add_role($role['name'], $role['display'], $caps);
		if ( is_object($new_role) ) {
			return $role['name'];
		} else {
			return false;
		}
	}
	
	 /**
	  * Saves capability changes to roles.
	  * 
	  * @param string $role_name Role name to change its capabilities
	  * @param array $caps New capabilities for the role.
	  * @return void
	  */
	private function saveRoleCapabilities( $role_name, $caps, $level ) {
		
		$this->generateNames();
		$role = get_role($role_name);
		
		$old_caps = array_intersect_key($role->capabilities, $this->capabilities);
		$new_caps = ( is_array($caps) ) ? array_map('intval', $caps) : array();
		$new_caps = array_merge($new_caps, akv_level2caps($level));

		// Find caps to add and remove
		$add_caps = array_diff_key($new_caps, $old_caps);
		$del_caps = array_diff_key($old_caps, $new_caps);
		
		if ( ! current_user_can('administrator') ) {
			unset($add_caps['manage_capabilities']);
			unset($del_caps['manage_capabilities']);
		} 
				
		if ( 'administrator' == $role_name && isset($del_caps['manage_capabilities']) ) {
			unset($del_caps['manage_capabilities']);
			akv_admin_error(__('You cannot remove Manage Capabilities from Administrators', $this->ID));
		}
		// Add new capabilities to role
		foreach ( $add_caps as $cap => $grant ) {
			$role->add_cap($cap);
		}
		
		// Remove capabilities from role
		foreach ( $del_caps as $cap => $grant) {
			$role->remove_cap($cap);
		}		
	}
}
?>