<?php
/**
 * Plugin Name: Scrobbble
 * Plugin URI:  https://jan.boddez.net/wordpress/scrobbble
 * Description: Consume audio scrobbles, just like Last.fm or Libre.fm would.
 * Version:     0.1.1
 * Author:      Jan Boddez
 * Author URI:  https://jan.boddez.net/
 * License:     GNU General Public License v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: scrobbble
 *
 * @author  Jan Boddez <jan@boddez.net>
 * @package Scrobbble
 */

namespace Scrobbble;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require dirname( __FILE__ ) . '/includes/class-blocks.php';
require dirname( __FILE__ ) . '/includes/class-scrobbble-api.php';
require dirname( __FILE__ ) . '/includes/class-scrobbble-cpt.php';
require dirname( __FILE__ ) . '/includes/class-scrobbble.php';

$scrobbble = Scrobbble::get_instance();
$scrobbble->register();
