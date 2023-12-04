<?php
/**
 * Plugin Name: Scrobbble
 * Plugin URI:  https://jan.boddez.net/wordpress/scrobbble
 * Description: Consume audio scrobbles, just like Last.fm or Libre.fm would.
 * Version:     0.1.2
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

require __DIR__ . '/includes/class-blocks.php';
require __DIR__ . '/includes/class-scrobbble-api.php';
require __DIR__ . '/includes/class-scrobbble-api-2.php';
require __DIR__ . '/includes/class-scrobbble-cpt.php';
require __DIR__ . '/includes/class-scrobbble.php';
require __DIR__ . '/includes/functions.php';

$scrobbble = Scrobbble::get_instance();
$scrobbble->register();
