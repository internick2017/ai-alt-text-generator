<?php
/**
 * Settings page template. Adapts based on WordPress version:
 * WP 7.0+ hides the API key (uses native Connectors); WP 6.x shows it.
 *
 * @package AI_Alt_Text_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$aatg_has_connector = function_exists( 'wp_ai_client' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'AI Alt Text Generator', 'ai-alt-text-generator' ); ?></h1>

    <?php if ( $aatg_has_connector ) : ?>
        <div class="notice notice-success inline">
            <p>
                <?php esc_html_e( 'WordPress AI Connectors detected. This plugin uses your configured AI provider. Manage providers under Settings → Connectors.', 'ai-alt-text-generator' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'aatg_settings' ); ?>
        <table class="form-table" role="presentation">

            <?php if ( ! $aatg_has_connector ) : ?>
            <tr>
                <th scope="row"><label for="aatg_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ai-alt-text-generator' ); ?></label></th>
                <td>
                    <input type="password" id="aatg_openai_api_key" name="aatg_openai_api_key"
                           value="<?php echo esc_attr( get_option( 'aatg_openai_api_key', '' ) ); ?>"
                           class="regular-text" autocomplete="off" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aatg_model"><?php esc_html_e( 'Model', 'ai-alt-text-generator' ); ?></label></th>
                <td>
                    <?php $aatg_model = get_option( 'aatg_model', 'gpt-4o-mini' ); ?>
                    <select id="aatg_model" name="aatg_model">
                        <option value="gpt-4o-mini" <?php selected( $aatg_model, 'gpt-4o-mini' ); ?>>gpt-4o-mini</option>
                        <option value="gpt-4o" <?php selected( $aatg_model, 'gpt-4o' ); ?>>gpt-4o</option>
                    </select>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <th scope="row"><label for="aatg_language"><?php esc_html_e( 'Language', 'ai-alt-text-generator' ); ?></label></th>
                <td>
                    <input type="text" id="aatg_language" name="aatg_language"
                           value="<?php echo esc_attr( get_option( 'aatg_language', 'auto' ) ); ?>"
                           class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Use "auto" to match the site language, or enter a language name.', 'ai-alt-text-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Auto-generate', 'ai-alt-text-generator' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="aatg_auto_generate" value="1" <?php checked( get_option( 'aatg_auto_generate', false ) ); ?> />
                        <?php esc_html_e( 'Generate alt text automatically when an image is uploaded.', 'ai-alt-text-generator' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
