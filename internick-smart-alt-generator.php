<?php
/**
 * Plugin Name:       AI Alt Text Generator for Images - Internick
 * Plugin URI:        https://github.com/internick2017/smart-alt-generator
 * Description:       AI alt text generator for your images. Bulk generate alt text, audit your media library, and improve accessibility and SEO.
 * Version:           1.3.1
 * Author:            Nick Granados
 * Author URI:        https://nickgranados.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       internick-smart-alt-generator
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'INSAG_VERSION', '1.3.1' );
define( 'INSAG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INSAG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INSAG_PLUGIN_FILE', __FILE__ );

require_once INSAG_PLUGIN_DIR . 'includes/class-sag-plugin.php';

register_activation_hook( __FILE__, array( 'INSAG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'INSAG_Plugin', 'deactivate' ) );

INSAG_Plugin::get_instance();
