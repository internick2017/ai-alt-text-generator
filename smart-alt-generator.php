<?php
/**
 * Plugin Name:       Smart Alt Generator
 * Plugin URI:        https://nickgranados.com/plugins/smart-alt-generator
 * Description:       Automatically generate descriptive alt text for images using AI. Supports WordPress 7.0 AI Connectors and OpenAI API for older versions.
 * Version:           1.1.1
 * Author:            Nick Granados
 * Author URI:        https://nickgranados.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smart-alt-generator
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'SAG_VERSION', '1.1.1' );
define( 'SAG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAG_PLUGIN_FILE', __FILE__ );

require_once SAG_PLUGIN_DIR . 'includes/class-sag-plugin.php';

register_activation_hook( __FILE__, array( 'SAG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SAG_Plugin', 'deactivate' ) );

SAG_Plugin::get_instance();
