<?php
/*
Plugin Name: WooCommerce CCC
Description: WooCommerce Customer Control Centre. Extends the WordPress REST API for user and WooCommerce management.
Version: 1.0.1
Author: Yilmaz Mustafa, Serger Ryskin, ChatGPT
*/

// Include JWT library
use Firebase\JWT\JWT;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CccApi {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    public function register_routes() {
        // User routes
        register_rest_route( 'woocommerce-ccc/v1', '/register', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'register_user' ),
            'permission_callback' => '__return_true',
        ));

        register_rest_route( 'woocommerce-ccc/v1', '/login', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'login_user' ),
            'permission_callback' => '__return_true',
        ));

        register_rest_route( 'woocommerce-ccc/v1', '/user', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_user_info' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ));

        register_rest_route( 'woocommerce-ccc/v1', '/user/update', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'update_user_info' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ));

        register_rest_route( 'woocommerce-ccc/v1', '/user/password', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'update_user_password' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ));

        // WooCommerce routes
        register_rest_route( 'woocommerce-ccc/v1', '/customer', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_customer_info' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ));

        register_rest_route( 'woocommerce-ccc/v1', '/customer/update', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'update_customer_info' ),
            'permission_callback' => array( $this, 'authenticate_request' ),
        ));
    }

    public function register_user( WP_REST_Request $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );
        $username = sanitize_text_field( $request->get_param( 'username' ) );
        $password = sanitize_text_field( $request->get_param( 'password' ) );

        if ( empty( $email ) || empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'registration_failed', 'Required fields are missing.', array( 'status' => 400 ) );
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return new WP_Error( 'registration_failed', $user_id->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array( 'user_id' => $user_id ) );
    }

    public function login_user( WP_REST_Request $request ) {
        $username = sanitize_text_field( $request->get_param( 'username' ) );
        $password = sanitize_text_field( $request->get_param( 'password' ) );

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'login_failed', 'Required fields are missing.', array( 'status' => 400 ) );
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'login_failed', $user->get_error_message(), array( 'status' => 401 ) );
        }

        $token = $this->generate_jwt( $user );

        return rest_ensure_response( array( 'token' => $token ) );
    }

    public function get_user_info( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        if ( empty( $user->ID ) ) {
            return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $user->data );
    }

    public function update_user_info( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( empty( $user_id ) ) {
            return new WP_Error( 'update_failed', 'User not found.', array( 'status' => 404 ) );
        }

        $email = sanitize_email( $request->get_param( 'email' ) );
        $first_name = sanitize_text_field( $request->get_param( 'first_name' ) );
        $last_name = sanitize_text_field( $request->get_param( 'last_name' ) );

        wp_update_user( array(
            'ID'         => $user_id,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
        ));

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function update_user_password( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( empty( $user_id ) ) {
            return new WP_Error( 'update_failed', 'User not found.', array( 'status' => 404 ) );
        }

        $password = sanitize_text_field( $request->get_param( 'password' ) );

        wp_set_password( $password, $user_id );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function get_customer_info( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( empty( $user_id ) ) {
            return new WP_Error( 'customer_not_found', 'Customer not found.', array( 'status' => 404 ) );
        }

        $customer = new WC_Customer( $user_id );

        $data = array(
            'billing'  => $customer->get_billing(),
            'shipping' => $customer->get_shipping(),
        );

        return rest_ensure_response( $data );
    }

    public function update_customer_info( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( empty( $user_id ) ) {
            return new WP_Error( 'customer_not_found', 'Customer not found.', array( 'status' => 404 ) );
        }

        $customer = new WC_Customer( $user_id );

        $billing = $request->get_param( 'billing' );
        $shipping = $request->get_param( 'shipping' );

        if ( ! empty( $billing ) ) {
            $customer->set_billing( $billing );
        }

        if ( ! empty( $shipping ) ) {
            $customer->set_shipping( $shipping );
        }

        $customer->save();

        return rest_ensure_response( array( 'success' => true ) );
    }

    private function authenticate_request( WP_REST_Request $request ) {
        $auth_header = $request->get_header( 'authorization' );
        if ( ! $auth_header ) {
            return new WP_Error( 'authorization_failed', 'No authorization header provided', array( 'status' => 403 ) );
        }

        list( $token ) = sscanf( $auth_header, 'Bearer %s' );
        if ( ! $token || ! $this->validate_jwt( $token ) ) {
            return new WP_Error( 'authorization_failed', 'Invalid token', array( 'status' => 403 ) );
        }

        return true;
    }

    private function generate_jwt( $user ) {
        $payload = array(
            'iss' => get_bloginfo( 'url' ),
            'iat' => time(),
            'exp' => time() + ( DAY_IN_SECONDS * 7 ),
            'data' => array(
                'user_id' => $user->ID,
            ),
        );

        if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
            define( 'SECURE_AUTH_KEY', 'your_secure_auth_key_here' ); // This is a security risk if not properly managed
        }
        if ( ! defined( 'DAY_IN_SECONDS' ) ) {
            define( 'DAY_IN_SECONDS', 86400 );
        }
        return JWT::encode( $payload, SECURE_AUTH_KEY );
    }

    private function validate_jwt( $token ) {
        try {
            $decoded = JWT::decode( $token, SECURE_AUTH_KEY, array( 'HS256' ) );
            if ( ! isset( $decoded->data->user_id ) ) {
                return false;
            }

            wp_set_current_user( $decoded->data->user_id );
            return true;
        } catch ( Exception $e ) {
            return false;
        }
    }

    public function add_admin_menu() {
        add_users_page(
            'CCC API Usage',
            'CCC API Usage',
            'manage_options',
            'ccc-api-usage',
            array( $this, 'render_admin_page' )
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>CCC API Usage</h1>
            <p>Here you can see the usage of the WooCommerce CCC API.</p>
            <h2>Available Endpoints</h2>
            <ul>
                <li><strong>Register User:</strong> POST /wp-json/woocommerce-ccc/v1/register</li>
                <li><strong>Login User:</strong> POST /wp-json/woocommerce-ccc/v1/login</li>
                <li><strong>Get User Info:</strong> GET /wp-json/woocommerce-ccc/v1/user</li>
                <li><strong>Update User Info:</strong> POST /wp-json/woocommerce-ccc/v1/user/update</li>
                <li><strong>Update User Password:</strong> POST /wp-json/woocommerce-ccc/v1/user/password</li>
                <li><strong>Get Customer Info:</strong> GET /wp-json/woocommerce-ccc/v1/customer</li>
                <li><strong>Update Customer Info:</strong> POST /wp-json/woocommerce-ccc/v1/customer/update</li>
            </ul>
        </div>
        <?php
    }
}

// Load plugin
new CccApi();
