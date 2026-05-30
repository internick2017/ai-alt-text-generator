<?php
/**
 * Plugin Name:       AI Alt Text Generator
 * Plugin URI:        https://nickgranados.com/plugins/ai-alt-text-generator
 * Description:       Automatically generate descriptive alt text for images using AI. Supports WordPress 7.0 AI Connectors and OpenAI API for older versions.
 * Version:           1.0.1
 * Author:            Nick Granados
 * Author URI:        https://nickgranados.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-alt-text-generator
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 *
 * @package AI_Alt_Text_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'AATG_VERSION', '1.0.1' );
define( 'AATG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AATG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AATG_PLUGIN_FILE', __FILE__ );

require_once AATG_PLUGIN_DIR . 'includes/class-aatg-plugin.php';

register_activation_hook( __FILE__, array( 'AATG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AATG_Plugin', 'deactivate' ) );

AATG_Plugin::get_instance();
