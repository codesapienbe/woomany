<?php
/*
Plugin Name: WooCommerce CCC
Description: WooCommerce Customer Control Centre. Extends the WordPress REST API for user and WooCommerce management.
Version: 1.0.2
Author: Yilmaz Mustafa, Sergey Ryskin, ChatGPT
*/

use Firebase\JWT\JWT;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Twilio\Rest\Client;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CccApi {

    private $twilio_sid;
    private $twilio_token;
    private $twilio_from;

    public function __construct() {
        $this->load_twilio_config();

        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_save_twilio_config', array($this, 'save_twilio_config'));
    }

    private function load_twilio_config() {
        $this->twilio_sid = get_option('twilio_sid');
        $this->twilio_token = get_option('twilio_auth_token');
        $this->twilio_from = get_option('twilio_phone_number');
    }

    public function register_routes() {
        // User routes
        register_rest_route('woocommerce-ccc/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_user'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('woocommerce-ccc/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login_user'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('woocommerce-ccc/v1', '/user', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_info'),
            'permission_callback' => array($this, 'authenticate_request'),
        ));

        register_rest_route('woocommerce-ccc/v1', '/user/update', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_user_info'),
            'permission_callback' => array($this, 'authenticate_request'),
        ));

        register_rest_route('woocommerce-ccc/v1', '/user/password', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_user_password'),
            'permission_callback' => array($this, 'authenticate_request'),
        ));

        // WooCommerce routes
        register_rest_route('woocommerce-ccc/v1', '/customer', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_info'),
            'permission_callback' => array($this, 'authenticate_request'),
        ));

        register_rest_route('woocommerce-ccc/v1', '/customer/update', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_customer_info'),
            'permission_callback' => array($this, 'authenticate_request'),
        ));

        // OTP routes
        register_rest_route('woocommerce-ccc/v1', '/send-otp', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_otp'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('woocommerce-ccc/v1', '/verify-otp', array(
            'methods' => 'POST',
            'callback' => array($this, 'verify_otp'),
            'permission_callback' => '__return_true',
        ));
    }

    public function register_user(WP_REST_Request $request) {
        $email = sanitize_email($request->get_param('email'));
        $username = sanitize_text_field($request->get_param('username'));
        $password = sanitize_text_field($request->get_param('password'));
        $phone_number = sanitize_text_field($request->get_param('phone_number'));
        $otp = sanitize_text_field($request->get_param('otp'));

        if (empty($email) || empty($username) || empty($password) || empty($phone_number) || empty($otp)) {
            return new WP_Error('registration_failed', 'Required fields are missing.', array('status' => 400));
        }

        // Verify OTP
        if (!$this->verify_otp_code($phone_number, $otp)) {
            return new WP_Error('registration_failed', 'Invalid OTP.', array('status' => 400));
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed', $user_id->get_error_message(), array('status' => 400));
        }

        update_user_meta($user_id, 'phone_number', $phone_number);

        return rest_ensure_response(array('user_id' => $user_id));
    }

    public function login_user(WP_REST_Request $request) {
        $username = sanitize_text_field($request->get_param('username'));
        $password = sanitize_text_field($request->get_param('password'));

        if (empty($username) || empty($password)) {
            return new WP_Error('login_failed', 'Required fields are missing.', array('status' => 400));
        }

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error('login_failed', $user->get_error_message(), array('status' => 401));
        }

        $token = $this->generate_jwt($user);

        return rest_ensure_response(array('token' => $token));
    }

    public function get_user_info(WP_REST_Request $request) {
        $user = wp_get_current_user();
        if (empty($user->ID)) {
            return new WP_Error('user_not_found', 'User not found.', array('status' => 404));
        }
        return rest_ensure_response($user->data);
    }

    public function update_user_info(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            return new WP_Error('update_failed', 'User not found.', array('status' => 404));
        }

        $email = sanitize_email($request->get_param('email'));
        $first_name = sanitize_text_field($request->get_param('first_name'));
        $last_name = sanitize_text_field($request->get_param('last_name'));

        wp_update_user(array(
            'ID' => $user_id,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ));

        return rest_ensure_response(array('success' => true));
    }

    public function update_user_password(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            return new WP_Error('update_failed', 'User not found.', array('status' => 404));
        }

        $password = sanitize_text_field($request->get_param('password'));

        wp_set_password($password, $user_id);

        return rest_ensure_response(array('success' => true));
    }

    public function get_customer_info(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            return new WP_Error('customer_not_found', 'Customer not found.', array('status' => 404));
        }

        $customer = new WC_Customer($user_id);

        $data = array(
            'billing' => $customer->get_billing(),
            'shipping' => $customer->get_shipping(),
        );

        return rest_ensure_response($data);
    }

    public function update_customer_info(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (empty($user_id)) {
            return new WP_Error('customer_not_found', 'Customer not found.', array('status' => 404));
        }

        $customer = new WC_Customer($user_id);

        $billing = $request->get_param('billing');
        $shipping = $request->get_param('shipping');

        if (!empty($billing)) {
            $customer->set_billing($billing);
        }

        if (!empty($shipping)) {
            $customer->set_shipping($shipping);
        }

        $customer->save();

        return rest_ensure_response(array('success' => true));
    }

    private function authenticate_request(WP_REST_Request $request) {
        $auth_header = $request->get_header('authorization');
        if (!$auth_header) {
            return new WP_Error('authorization_failed', 'No authorization header provided', array('status' => 403));
        }

        list($token) = sscanf($auth_header, 'Bearer %s');
        if (!$token || !$this->validate_jwt($token)) {
            return new WP_Error('authorization_failed', 'Invalid token', array('status' => 403));
        }

        return true;
    }

    private function generate_jwt($user) {
        $payload = array(
            'iss' => get_bloginfo('url'),
            'iat' => time(),
            'exp' => time() + (DAY_IN_SECONDS * 7),
            'data' => array(
                'user_id' => $user->ID,
            ),
        );

        if (!defined('SECURE_AUTH_KEY')) {
            define('SECURE_AUTH_KEY', 'your_secure_auth_key_here'); // This is a security risk if not properly managed
        }
        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
        }
        return JWT::encode($payload, SECURE_AUTH_KEY);
    }

    private function validate_jwt($token) {
        try {
            $decoded = JWT::decode($token, SECURE_AUTH_KEY, array('HS256'));
            if (!isset($decoded->data->user_id)) {
                return false;
            }

            wp_set_current_user($decoded->data->user_id);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function add_admin_menu() {
        add_users_page(
            'Twilio Config',
            'Twilio Config',
            'manage_options',
            'twilio-config',
            array($this, 'render_twilio_config_page')
        );

        add_users_page(
            'CCC API Usage',
            'CCC API Usage',
            'manage_options',
            'ccc-api-usage',
            array($this, 'render_ccc_api_usage_page')
        );
    }

    public function render_twilio_config_page() {
        ?>
        <div class="wrap">
            <h1>Twilio Configuration</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_twilio_config">
                <?php wp_nonce_field('twilio_config_nonce', 'twilio_config_nonce_field'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="twilio_sid">Twilio SID</label></th>
                        <td><input name="twilio_sid" type="text" id="twilio_sid" value="<?php echo esc_attr(get_option('twilio_sid')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="twilio_auth_token">Twilio Auth Token</label></th>
                        <td><input name="twilio_auth_token" type="text" id="twilio_auth_token" value="<?php echo esc_attr(get_option('twilio_auth_token')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="twilio_phone_number">Twilio Phone Number</label></th>
                        <td><input name="twilio_phone_number" type="text" id="twilio_phone_number" value="<?php echo esc_attr(get_option('twilio_phone_number')); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button('Save Twilio Config'); ?>
            </form>
        </div>
        <?php
    }

    public function save_twilio_config() {
        if (!isset($_POST['twilio_config_nonce_field']) || !wp_verify_nonce($_POST['twilio_config_nonce_field'], 'twilio_config_nonce')) {
            wp_die('Nonce verification failed');
        }

        if (isset($_POST['twilio_sid'])) {
            update_option('twilio_sid', sanitize_text_field($_POST['twilio_sid']));
        }

        if (isset($_POST['twilio_auth_token'])) {
            update_option('twilio_auth_token', sanitize_text_field($_POST['twilio_auth_token']));
        }

        if (isset($_POST['twilio_phone_number'])) {
            update_option('twilio_phone_number', sanitize_text_field($_POST['twilio_phone_number']));
        }

        wp_redirect(add_query_arg('page', 'twilio-config', admin_url('users.php')));
        exit;
    }

    public function render_ccc_api_usage_page() {
        ?>
        <div class="wrap">
            <h1>CCC API Usage</h1>
            <h2>Available Endpoints</h2>
            <ul>
                <li><strong>Register User:</strong> POST /wp-json/woocommerce-ccc/v1/register</li>
                <li><strong>Login User:</strong> POST /wp-json/woocommerce-ccc/v1/login</li>
                <li><strong>Get User Info:</strong> GET /wp-json/woocommerce-ccc/v1/user</li>
                <li><strong>Update User Info:</strong> POST /wp-json/woocommerce-ccc/v1/user/update</li>
                <li><strong>Update User Password:</strong> POST /wp-json/woocommerce-ccc/v1/user/password</li>
                <li><strong>Get Customer Info:</strong> GET /wp-json/woocommerce-ccc/v1/customer</li>
                <li><strong>Update Customer Info:</strong> POST /wp-json/woocommerce-ccc/v1/customer/update</li>
                <li><strong>Send OTP:</strong> POST /wp-json/woocommerce-ccc/v1/send-otp</li>
                <li><strong>Verify OTP:</strong> POST /wp-json/woocommerce-ccc/v1/verify-otp</li>
            </ul>
            <h2>Example Usage with cURL</h2>
            <h3>Register User</h3>
            <pre><code>curl -X POST <?php echo home_url('/wp-json/woocommerce-ccc/v1/register'); ?> \
-H "Content-Type: application/json" \
-d '{"email":"user@example.com","username":"user","password":"password","phone_number":"1234567890","otp":"123456"}'</code></pre>

            <h3>Login User</h3>
            <pre><code>curl -X POST <?php echo home_url('/wp-json/woocommerce-ccc/v1/login'); ?> \
-H "Content-Type: application/json" \
-d '{"username":"user","password":"password"}'</code></pre>

            <h3>Get User Info</h3>
            <pre><code>curl -X GET <?php echo home_url('/wp-json/woocommerce-ccc/v1/user'); ?> \
-H "Authorization: Bearer YOUR_TOKEN"</code></pre>

            <h3>Update User Info</h3>
            <pre><code>curl -X POST <?php echo home_url('/wp-json/woocommerce-ccc/v1/user/update'); ?> \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_TOKEN" \
-d '{"email":"newemail@example.com","first_name":"First","last_name":"Last"}'</code></pre>

            <h3>Update User Password</h3>
            <pre><code>curl -X POST <?php echo home_url('/wp-json/woocommerce-ccc/v1/user/password'); ?> \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_TOKEN" \
-d '{"password":"newpassword"}'</code></pre>

            <h3>Get Customer Info</h3>
            <pre><code>curl -X GET <?php echo home_url('/wp-json/woocommerce-ccc/v1/customer'); ?> \
-H "Authorization: Bearer YOUR_TOKEN"</code></pre>

            <h3>Update Customer Info</h3>
            <pre><code>curl -X POST <?php echo home_url('/wp-json/woocommerce-ccc/v1/customer/update'); ?> \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_TOKEN" \
-d '{"billing":{"address_1":"123 Main St","city":"Anytown","state":"CA","postcode":"12345"},"shipping":{"address_1":"123 Main St","city":"Anytown","state":"CA","postcode":"12345"}}'</code></pre>

            <h3>Send OTP</h3>
            <pre><code>curl -X POST <?php echo home_url('/wp-json/woocommerce-ccc/v1/send-otp'); ?> \
-H "Content-Type: application/json" \
-d '{"phone_number":"1234567890"}'</code></pre>

            <h3>Verify OTP</h3>
            <pre><code>curl -X POST <?php echo home_url('/wp-json/woocommerce-ccc/v1/verify-otp'); ?> \
-H "Content-Type: application/json" \
-d '{"phone_number":"1234567890","otp":"123456"}'</code></pre>
        </div>
        <?php
    }
}

// Load plugin
new CccApi();

?>
