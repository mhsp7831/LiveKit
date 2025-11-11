<?php
/**
 * Plugin Name:       LiveKit Gravity Forms Integration
 * Description:       API endpoint for phone verification with form selection support
 * Version:           2.0.0
 * Author:            MHSP :)
 */

defined('ABSPATH') or die('No script kiddies please!');

class LiveStream_GF_Integration {

    private $option_name = 'livestream_gf_settings';
    private $settings = [];

    public function __construct() {
        $this->settings = get_option($this->option_name, ['api_key' => '']);

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_post_livestream_generate_api_key', [$this, 'handle_generate_api_key']);
    }

    public function register_rest_routes() {
        // Test connection endpoint
        register_rest_route('livestream/v1', '/test-connection', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_test_connection'],
            'permission_callback' => [$this, 'check_api_key'],
        ]);

        // Get all forms endpoint
        register_rest_route('livestream/v1', '/forms', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_forms'],
            'permission_callback' => [$this, 'check_api_key'],
        ]);

        // Get form fields endpoint
        register_rest_route('livestream/v1', '/forms/(?P<id>\d+)/fields', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_form_fields'],
            'permission_callback' => [$this, 'check_api_key'],
        ]);

        // Verify phone endpoint
        register_rest_route('livestream/v1', '/verify-phone', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_verify_phone'],
            'permission_callback' => [$this, 'check_api_key'],
        ]);
    }

    public function check_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        if (empty($api_key) || empty($this->settings['api_key'])) {
            return new WP_Error('rest_forbidden', 'API Key is missing.', ['status' => 401]);
        }
        
        if (!hash_equals($this->settings['api_key'], $api_key)) {
            return new WP_Error('rest_forbidden', 'Invalid API Key.', ['status' => 401]);
        }
        
        return true;
    }

    public function rest_test_connection($request) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Connection successful to ' . get_bloginfo('name'),
            'gravity_forms_active' => class_exists('GFAPI'),
            'site_url' => get_site_url()
        ], 200);
    }

    /**
     * Get list of all Gravity Forms
     */
    public function rest_get_forms($request) {
        if (!class_exists('GFAPI')) {
            return new WP_Error('gravity_forms_not_active', 'Gravity Forms is not active.', ['status' => 500]);
        }
        
        $forms = GFAPI::get_forms();
        
        $form_list = array_map(function($form) {
            return [
                'id' => $form['id'],
                'title' => $form['title'],
                'entries_count' => GFAPI::count_entries($form['id'])
            ];
        }, $forms);

        return new WP_REST_Response($form_list, 200);
    }

    /**
     * Get fields for a specific form
     */
    public function rest_get_form_fields($request) {
        if (!class_exists('GFAPI')) {
            return new WP_Error('gravity_forms_not_active', 'Gravity Forms is not active.', ['status' => 500]);
        }
        
        $form_id = $request['id'];
        $form = GFAPI::get_form($form_id);
        
        if (!$form) {
            return new WP_Error('form_not_found', 'Form not found.', ['status' => 404]);
        }

        $field_list = [];
        foreach ($form['fields'] as $field) {
            $field_list[] = [
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
            ];
        }
        
        return new WP_REST_Response($field_list, 200);
    }

    /**
     * Verify phone number
     */
    public function rest_verify_phone($request) {
        if (!class_exists('GFAPI')) {
            return new WP_Error('gravity_forms_not_active', 'Gravity Forms is not active.', ['status' => 500]);
        }
        
        $params = $request->get_json_params();
        $phone_number = $params['phone'] ?? '';
        $form_id = $params['form_id'] ?? '';
        $field_id = $params['field_id'] ?? '';

        if (empty($phone_number) || empty($form_id)) {
            return new WP_Error('missing_parameters', 
                'Missing required parameters (phone, form_id).', 
                ['status' => 400]
            );
        }

        // Auto-detect field_id if not provided
        if (empty($field_id)) {
            $field_id = $this->get_phone_field_id($form_id);
            if (!$field_id) {
                return new WP_Error('phone_field_not_found', 
                    'Could not auto-detect phone field. Please specify field_id.', 
                    ['status' => 400]
                );
            }
        }

        // Normalize phone number
        $normalized_phone = $this->normalize_phone($phone_number);
        
        // Generate search formats
        $search_formats = [$normalized_phone];
        if (preg_match('/9(\d{9})$/', $normalized_phone, $matches)) {
            $core_number = '9' . $matches[1];
            $search_formats[] = $core_number;
            $search_formats[] = '0' . $core_number;
        }
        $search_formats = array_unique($search_formats);
        
        // Build search criteria
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

        // Query Gravity Forms
        $entries = GFAPI::get_entries($form_id, $search_criteria);

        if (!empty($entries) && !is_wp_error($entries)) {
            return new WP_REST_Response([
                'authorized' => true,
                'phone' => $normalized_phone,
                'found' => true,
                'field_id_used' => $field_id,
                'searched_formats' => $search_formats
            ], 200);
        } else {
            return new WP_REST_Response([
                'authorized' => false,
                'phone' => $normalized_phone,
                'found' => false,
                'field_id_used' => $field_id,
                'searched_formats' => $search_formats,
                'error' => is_wp_error($entries) ? $entries->get_error_message() : null
            ], 200);
        }
    }

    /**
     * Auto-detect phone field in form
     */
    private function get_phone_field_id($form_id) {
        $form = GFAPI::get_form($form_id);
        if (!$form) return false;
        
        // First, look for field type = 'phone'
        foreach ($form['fields'] as $field) {
            if ($field->type === 'phone') {
                return (string) $field->id;
            }
        }
        
        // Second, look for phone-related labels
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

    /**
     * Normalize phone number
     */
    private function normalize_phone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (preg_match('/^09(\d{9})$/', $phone, $matches)) {
            $phone = '+989' . $matches[1];
        } elseif (preg_match('/^9(\d{9})$/', $phone, $matches)) {
            $phone = '+989' . $matches[1];
        } elseif (preg_match('/^989(\d{9})$/', $phone, $matches)) {
            $phone = '+989' . $matches[1];
        }
        
        return $phone;
    }

    // Admin page and key generation methods remain the same...
    public function add_admin_menu() {
        add_options_page(
            'LiveStream Integration',
            'LiveStream Integration',
            'manage_options',
            'livestream-gf-integration',
            [$this, 'render_settings_page']
        );
    }

    public function handle_generate_api_key() {
        if (!current_user_can('manage_options') || !check_admin_referer('livestream_generate_key')) {
            wp_die('Security check failed.');
        }

        $new_key = 'ls_' . wp_generate_password(40, false);
        $this->settings['api_key'] = $new_key;
        update_option($this->option_name, $this->settings);

        wp_redirect(admin_url('options-general.php?page=livestream-gf-integration&key_generated=true'));
        exit;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_gf_active = class_exists('GFAPI');
        ?>
        <div class="wrap">
            <h1>LiveStream Integration</h1>
            
            <?php if (isset($_GET['key_generated'])) : ?>
                <div class="notice notice-success">
                    <p><strong>New API Key generated successfully.</strong></p>
                </div>
            <?php endif; ?>
            
            <?php if (!$is_gf_active) : ?>
                <div class="notice notice-error">
                    <p><strong>Error:</strong> Gravity Forms plugin is required.</p>
                </div>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th>API Endpoint URL</th>
                    <td>
                        <input type="text" class="regular-text" 
                               value="<?php echo esc_url(get_rest_url(null, 'livestream/v1')); ?>" 
                               readonly onclick="this.select();" />
                        <p class="description">Copy this to your LiveStream Dashboard</p>
                    </td>
                </tr>
                <tr>
                    <th>API Key</th>
                    <td>
                        <input type="text" class="regular-text" 
                               value="<?php echo esc_attr($this->settings['api_key']); ?>" 
                               readonly onclick="this.select();" />
                        <?php
                        $generate_key_url = wp_nonce_url(
                            admin_url('admin-post.php?action=livestream_generate_api_key'),
                            'livestream_generate_key'
                        );
                        ?>
                        <a href="<?php echo esc_url($generate_key_url); ?>" class="button button-primary">
                            <?php echo empty($this->settings['api_key']) ? 'Generate First Key' : 'Generate New Key'; ?>
                        </a>
                        <p class="description">Copy this to your LiveStream Dashboard</p>
                    </td>
                </tr>
            </table>
            
            <div class="notice notice-info">
                <p><strong>📝 How it works:</strong></p>
                <ol style="margin-left: 20px;">
                    <li>Copy the API URL and Key above to your LiveStream Dashboard</li>
                    <li>In the Dashboard, click "Load Forms" to see all your Gravity Forms</li>
                    <li>Select the form containing phone numbers</li>
                    <li>The system will auto-detect the phone field (or you can select it manually)</li>
                </ol>
            </div>
            
            <?php if ($is_gf_active) : ?>
                <h2>Available Gravity Forms</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Form ID</th>
                            <th>Form Title</th>
                            <th>Entries</th>
                            <th>Phone Fields</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $forms = GFAPI::get_forms();
                        foreach ($forms as $form) {
                            $phone_fields = [];
                            foreach ($form['fields'] as $field) {
                                if ($field->type === 'phone' || 
                                    stripos($field->label, 'phone') !== false ||
                                    stripos($field->label, 'تلفن') !== false) {
                                    $phone_fields[] = $field->id . ' (' . $field->label . ')';
                                }
                            }
                            $entries_count = GFAPI::count_entries($form['id']);
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($form['id']); ?></code></td>
                                <td><?php echo esc_html($form['title']); ?></td>
                                <td><?php echo esc_html($entries_count); ?></td>
                                <td><?php echo !empty($phone_fields) ? implode(', ', $phone_fields) : '<em>No phone fields</em>'; ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize plugin
new LiveStream_GF_Integration();