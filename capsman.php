<?php
/*
Plugin Name: Capability Manager
Plugin URI: http://alkivia.org/wordpress/capsman
Description: Manage user capabilities and roles.
Version: 1.2.5
Author: Jordi Canals
Author URI: http://alkivia.org
 */

/**
 * Capability Manager. Main Plugin File.
 * Plugin to create and manage Roles and Capabilities.
 *
 * @version		$Rev$
 * @author		Jordi Canals
 * @copyright   Copyright (C) 2009, 2010 Jordi Canals
 * @license		GNU General Public License version 2
 * @link		http://alkivia.org
 * @package		Alkivia
 * @subpackage	CapsMan
 *

	Copyright 2009, 2010 Jordi Canals <devel@jcanals.cat>

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	version 2 as published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

define ( 'CMAN_PATH', dirname(__FILE__));

/**
 * Sets an admin warning regarding required PHP version.
 *
 * @hook action 'admin_notices'
 * @return void
 */
function _cman_php_warning() {

	$data = get_plugin_data(__FILE__);
	load_plugin_textdomain('capsman', false, basename(dirname(__FILE__)) .'/lang');

	echo '<div class="error"><p><strong>' . __('Warning:', 'capsman') . '</strong> '
		. sprintf(__('The active plugin %s is not compatible with your PHP version.', 'capsman') .'</p><p>',
			'&laquo;' . $data['Name'] . ' ' . $data['Version'] . '&raquo;')
		. sprintf(__('%s is required for this plugin.', 'capsman'), 'PHP-5 ')
		. '</p></div>';
}

// ============================================ START PROCEDURE ==========

// Check required PHP version.
if ( version_compare(PHP_VERSION, '5.0.0', '<') ) {
	// Send an armin warning
	add_action('admin_notices', '_cman_php_warning');
} else {
	// Run the plugin
	include ( CMAN_PATH . '/manager.php' );
	$objCapsManager = new cmanCapsManager(__FILE__, 'capsman');

	if ( $objCapsManager ) {	// The system is compatible
		include_once ( CMAN_PATH . '/framework/formating.php' );
		include_once ( CMAN_PATH . '/framework/roles.php' );
	}
}
