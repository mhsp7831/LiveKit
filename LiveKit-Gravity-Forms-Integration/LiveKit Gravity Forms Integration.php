<?php
/**
 * Plugin Name:       LiveKit Gravity Forms Integration
 * Description:       Provides a secure REST API endpoint to verify phone numbers from Gravity Forms entries for the LiveStream project.
 * Version:           1.0.0
 * Author:            MHSP :)
 * Author URI:        https://github.com/mhsp7831
 * License:           GPLv2 or later
 * Text Domain:       livestream-gf-integration
 */

// Deny direct script access for security
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Main plugin class
 */
class LiveStream_GF_Integration {

    private $option_name = 'livestream_gf_settings';
    private $settings = [];

    public function __construct() {
        // Load settings
        $this->settings = get_option( $this->option_name, [
            'api_key' => '',
            'form_id' => '',
            'field_id' => '',
        ]);

        // Register hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'admin_post_livestream_generate_api_key', [ $this, 'handle_generate_api_key' ] );
    }

    /**
     * Add the admin menu page.
     *
     */
    public function add_admin_menu() {
        add_options_page(
            'LiveStream Integration',
            'LiveStream Integration',
            'manage_options',
            'livestream-gf-integration',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register plugin settings.
     *
     */
    public function register_settings() {
        register_setting( $this->option_name, $this->option_name, [ $this, 'sanitize_settings' ] );
    }

    /**
     * Sanitize and validate settings input.
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = $this->settings; // Start with existing settings to keep API key

        if ( isset( $input['form_id'] ) ) {
            $sanitized_input['form_id'] = absint( $input['form_id'] );
        }
        if ( isset( $input['field_id'] ) ) {
            $sanitized_input['field_id'] = sanitize_text_field( $input['field_id'] );
        }

        return $sanitized_input;
    }

    /**
     * Handle the "Generate New API Key" action.
     *
     */
    public function handle_generate_api_key() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'livestream_generate_key' ) ) {
            wp_die( 'Security check failed.' );
        }

        $new_key = 'ls_' . wp_generate_password( 40, false );
        $this->settings['api_key'] = $new_key;
        update_option( $this->option_name, $this->settings );

        wp_redirect( admin_url( 'options-general.php?page=livestream-gf-integration&key_generated=true' ) );
        exit;
    }

    /**
     * Render the HTML for the settings page.
     *
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $is_gf_active = class_exists( 'GFAPI' );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php if ( isset( $_GET['key_generated'] ) ) : ?>
                <div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible">
                    <p><strong>New API Key generated successfully.</strong></p>
                </div>
            <?php endif; ?>
            
            <?php if ( ! $is_gf_active ) : ?>
                <div class="notice notice-error">
                    <p><strong>Error:</strong> Gravity Forms plugin is not active. This integration requires Gravity Forms.</p>
                </div>
            <?php endif; ?>

            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_name );
                ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">API Endpoint URL</th>
                            <td>
                                <code><?php echo esc_url( get_rest_url( null, 'livestream/v1' ) ); ?></code>
                                <p class="description">Copy this URL into your LiveStream Dashboard settings.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Key</th>
                            <td>
                                <input type="text" class="regular-text" value="<?php echo esc_attr( $this->settings['api_key'] ); ?>" readonly />
                                <?php
                                // Create the secure URL for our admin action
                                $generate_key_url = wp_nonce_url(
                                    admin_url( 'admin-post.php?action=livestream_generate_api_key' ),
                                    'livestream_generate_key'
                                );
                                ?>

                                <?php if ( empty( $this->settings['api_key'] ) ) : ?>
                                    <a href="<?php echo esc_url( $generate_key_url ); ?>" class="button button-primary">Generate First Key</a>
                                <?php else : ?>
                                    <a href="<?php echo esc_url( $generate_key_url ); ?>" class="button button-secondary" style="margin-right: 10px;">Generate New Key</a>
                                <?php endif; ?>
                                <p class="description">Copy this key into your LiveStream Dashboard.</p>
                            </td>
                        </tr>
                        
                        <?php if ( $is_gf_active ) : ?>
                        <tr>
                            <th scope="row"><label for="livestream_form_id">Gravity Form</label></th>
                            <td>
                                <?php
                                $forms = GFAPI::get_forms();
                                ?>
                                <select id="livestream_form_id" name="<?php echo $this->option_name; ?>[form_id]">
                                    <option value="">-- Select a Form --</option>
                                    <?php foreach ( $forms as $form ) : ?>
                                        <option value="<?php echo esc_attr( $form['id'] ); ?>" <?php selected( $this->settings['form_id'], $form['id'] ); ?>>
                                            <?php echo esc_html( $form['title'] ); ?> (ID: <?php echo esc_attr( $form['id'] ); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select the form containing the phone numbers.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="livestream_field_id">Phone Field ID</label></th>
                            <td>
                                <input type="text" id="livestream_field_id" name="<?php echo $this->option_name; ?>[field_id]" value="<?php echo esc_attr( $this->settings['field_id'] ); ?>" class="regular-text" placeholder="e.g., 3 or 5.1" />
                                <p class="description">Enter the Field ID for the phone number. You can get this from the form editor.</p>
                            </td>
                        </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register the REST API routes.
     *
     */
    public function register_rest_routes() {
        register_rest_route( 'livestream/v1', '/test-connection', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_test_connection' ],
            'permission_callback' => [ $this, 'check_api_key' ],
        ] );

        register_rest_route( 'livestream/v1', '/forms', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_get_forms' ],
            'permission_callback' => [ $this, 'check_api_key' ],
        ] );

        register_rest_route( 'livestream/v1', '/forms/(?P<id>\d+)/fields', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_get_form_fields' ],
            'permission_callback' => [ $this, 'check_api_key' ],
        ] );

        register_rest_route( 'livestream/v1', '/verify-phone', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_verify_phone' ],
            'permission_callback' => [ $this, 'check_api_key' ],
        ] );
    }

    /**
     * Permission callback to check the API key.
     *
     */
    public function check_api_key( $request ) {
        $api_key = $request->get_header( 'X-API-Key' );
        if ( empty( $api_key ) || empty( $this->settings['api_key'] ) ) {
            return new WP_Error( 'rest_forbidden', 'API Key is missing.', [ 'status' => 401 ] );
        }
        
        if ( ! hash_equals( $this->settings['api_key'], $api_key ) ) {
            return new WP_Error( 'rest_forbidden', 'Invalid API Key.', [ 'status' => 401 ] );
        }
        
        return true;
    }

    /**
     * API Callback: Test Connection.
     *
     */
    public function rest_test_connection( $request ) {
        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Connection successful to ' . get_bloginfo( 'name' ),
        ], 200 );
    }

    /**
     * API Callback: Get list of Gravity Forms.
     *
     */
    public function rest_get_forms( $request ) {
        if ( ! class_exists( 'GFAPI' ) ) {
            return new WP_Error( 'gravity_forms_not_active', 'Gravity Forms is not active.', [ 'status' => 500 ] );
        }
        $forms = GFAPI::get_forms();
        $form_list = array_map( function( $form ) {
            return [ 'id' => $form['id'], 'title' => $form['title'] ];
        }, $forms );

        return new WP_REST_Response( $form_list, 200 );
    }

    /**
     * API Callback: Get fields for a specific form.
     *
     */
    public function rest_get_form_fields( $request ) {
        if ( ! class_exists( 'GFAPI' ) ) {
            return new WP_Error( 'gravity_forms_not_active', 'Gravity Forms is not active.', [ 'status' => 500 ] );
        }
        $form_id = $request['id'];
        $form = GFAPI::get_form( $form_id );
        if ( ! $form ) {
            return new WP_Error( 'form_not_found', 'Form not found.', [ 'status' => 404 ] );
        }

        $field_list = [];
        foreach( $form['fields'] as $field ) {
            $field_list[] = [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
            ];
        }
        
        return new WP_REST_Response( $field_list, 200 );
    }

    /**
     * API Callback: Verify Phone Number.
     * Modified verify phone with auto-detection
     */
    public function rest_verify_phone($request) {
        if (!class_exists('GFAPI')) {
            return new WP_Error('gravity_forms_not_active', 'Gravity Forms is not active.', ['status' => 500]);
        }
        
        $params = $request->get_json_params();
        $phone_number = $params['phone'] ?? '';
        $form_id = $params['form_id'] ?? $this->settings['form_id'];
        $field_id = $params['field_id'] ?? $this->settings['field_id'];

        if (empty($phone_number) || empty($form_id)) {
            return new WP_Error('missing_parameters', 'Missing required parameters (phone, form_id).', ['status' => 400]);
        }

        // AUTO-DETECT: If field_id is empty, try to find it
        if (empty($field_id)) {
            $field_id = $this->get_phone_field_id($form_id);
            
            if (!$field_id) {
                return new WP_Error('phone_field_not_found', 
                    'Could not find a phone field in the form. Please specify field_id manually.', 
                    ['status' => 400]
                );
            }
        }

        // Rest of the verification logic...
        $normalized_phone = $this->normalize_phone($phone_number);
        
        $search_formats = [];
        $search_formats[] = $normalized_phone;
        
        if (preg_match('/9(\d{9})$/', $normalized_phone, $matches)) {
            $core_number = '9' . $matches[1];
            $search_formats[] = $core_number;
            $search_formats[] = '0' . $core_number;
        }
        
        $search_formats = array_unique($search_formats);
        
        $field_filters = [];
        foreach ($search_formats as $format) {
            $field_filters[] = [
                'key' => $field_id,
                'value' => $format,
                'operator' => 'is',
            ];
        }

        $search_criteria = [
            'status' => 'active',
            'field_filters' => ['mode' => 'any']
        ];
        
        foreach ($field_filters as $filter) {
            $search_criteria['field_filters'][] = $filter;
        }

        $entries = GFAPI::get_entries($form_id, $search_criteria);

        if (!empty($entries) && !is_wp_error($entries)) {
            return new WP_REST_Response([
                'authorized' => true,
                'phone' => $normalized_phone,
                'found' => true,
                'field_id_used' => $field_id // Show which field was used
            ], 200);
        } else {
            return new WP_REST_Response([
                'authorized' => false,
                'phone' => $normalized_phone,
                'found' => false,
                'field_id_used' => $field_id,
                'error' => is_wp_error($entries) ? $entries->get_error_message() : null
            ], 200);
        }
    }

    /**
     * Simple phone normalization function.
     *
     */
    private function normalize_phone( $phone ) {
        // Remove all non-numeric characters except '+'
        $phone = preg_replace( '/[^0-9+]/', '', $phone );
        
        // Normalize Iranian numbers: 09... -> +989...
        if ( preg_match( '/^09(\d{9})$/', $phone, $matches ) ) {
            $phone = '+989' . $matches[1];
        }
        // 9... -> +989...
        elseif ( preg_match( '/^9(\d{9})$/', $phone, $matches ) ) {
             $phone = '+989' . $matches[1];
        }
        // 989... -> +989...
        elseif ( preg_match( '/^989(\d{9})$/', $phone, $matches ) ) {
            $phone = '+989' . $matches[1];
        }
        
        return $phone;
    }
    
    /**
     * Auto-detect phone field in a Gravity Form
     * 
     * @param int $form_id The Gravity Form ID
     * @return string|false The field ID if found, false otherwise
     */
    private function get_phone_field_id($form_id) {
        if (!class_exists('GFAPI')) {
            return false;
        }
        
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            return false;
        }
        
        // Search for phone field
        foreach ($form['fields'] as $field) {
            // Check if field type is 'phone'
            if ($field->type === 'phone') {
                return (string) $field->id;
            }
        }
        
        // If no phone field found, search in field labels
        foreach ($form['fields'] as $field) {
            $label = strtolower($field->label);
            if (strpos($label, 'phone') !== false || 
                strpos($label, 'mobile') !== false ||
                strpos($label, 'تلفن') !== false ||
                strpos($label, 'موبایل') !== false) {
                return (string) $field->id;
            }
        }
        
        return false;
    }
}

// Initialize the plugin
new LiveStream_GF_Integration();