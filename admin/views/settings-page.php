<?php
/**
 * Settings page template. Adapts based on WordPress version:
 * WP 7.0+ hides the API key (uses native Connectors); WP 6.x shows it.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$sag_has_connector = function_exists( 'wp_ai_client' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Smart Alt Generator', 'smart-alt-generator' ); ?></h1>

    <?php if ( $sag_has_connector ) : ?>
        <div class="notice notice-success inline">
            <p>
                <?php esc_html_e( 'WordPress AI Connectors detected. This plugin uses your configured AI provider. Manage providers under Settings → Connectors.', 'smart-alt-generator' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'sag_settings' ); ?>
        <table class="form-table" role="presentation">

            <?php if ( ! $sag_has_connector ) : ?>
            <tr>
                <th scope="row"><label for="sag_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'smart-alt-generator' ); ?></label></th>
                <td>
                    <input type="password" id="sag_openai_api_key" name="sag_openai_api_key"
                           value="<?php echo esc_attr( get_option( 'sag_openai_api_key', '' ) ); ?>"
                           class="regular-text" autocomplete="off" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sag_model"><?php esc_html_e( 'Model', 'smart-alt-generator' ); ?></label></th>
                <td>
                    <?php $sag_model = get_option( 'sag_model', 'gpt-4o-mini' ); ?>
                    <select id="sag_model" name="sag_model">
                        <option value="gpt-4o-mini" <?php selected( $sag_model, 'gpt-4o-mini' ); ?>>gpt-4o-mini</option>
                        <option value="gpt-4o" <?php selected( $sag_model, 'gpt-4o' ); ?>>gpt-4o</option>
                    </select>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <th scope="row"><label for="sag_language"><?php esc_html_e( 'Language', 'smart-alt-generator' ); ?></label></th>
                <td>
                    <input type="text" id="sag_language" name="sag_language"
                           value="<?php echo esc_attr( get_option( 'sag_language', 'auto' ) ); ?>"
                           class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Use "auto" to match the site language, or enter a language name.', 'smart-alt-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Auto-generate', 'smart-alt-generator' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sag_auto_generate" value="1" <?php checked( get_option( 'sag_auto_generate', false ) ); ?> />
                        <?php esc_html_e( 'Generate alt text automatically when an image is uploaded.', 'smart-alt-generator' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
