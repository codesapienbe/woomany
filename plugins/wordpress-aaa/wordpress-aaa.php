<?php
/*
Plugin Name: WordPress AAA (API Assisted Automation)
Description: Automatically generates a Swagger (OpenAPI) specification file for the WordPress REST API and WooCommerce REST API, and provides a view for Swagger UI in HTML.
Version: 1.0.1
Author: Yilmaz Mustafa, Sergey Ryskin, Atilla Balin, and ChatGPT.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Swagger_UI_Viewer {

    private $upload_dir;
    private $swagger_file;

    public function __construct() {
        $upload_dir_info = wp_upload_dir();
        $this->upload_dir = $upload_dir_info['basedir'];
        $this->swagger_file = $this->upload_dir . '/swagger.yaml';

        add_action('rest_api_init', array($this, 'generate_swagger_yaml'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('init', array($this, 'swagger_ui_redirect'));
        add_action('admin_init', array($this, 'create_swagger_yaml_file'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Swagger UI Viewer',
            'Swagger UI Viewer',
            'manage_options',
            'swagger-ui-viewer',
            array($this, 'swagger_ui_viewer_page')
        );
    }

    public function swagger_ui_viewer_page() {
        echo '<div class="wrap">';
        echo '<h1>Swagger UI Viewer</h1>';
        echo '<p>View the <a href="' . home_url('/swagger-ui.html') . '" target="_blank">Swagger UI</a>.</p>';
        echo '</div>';
    }

    public function generate_swagger_yaml() {
        register_rest_route( 'wp/v2', '/swagger.yaml', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_swagger_yaml' ),
            'permission_callback' => '__return_true'
        ));
    }

    public function get_swagger_yaml() {
        $swagger = array(
            'openapi' => '3.0.0',
            'info' => array(
                'title' => get_bloginfo('name') . ' API',
                'version' => '1.0.0'
            ),
            'servers' => array(
                array(
                    'url' => home_url('/wp-json')
                )
            ),
            'paths' => array(),
            'components' => array(
                'securitySchemes' => array(
                    'BearerAuth' => array(
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    )
                )
            ),
            'security' => array(
                array('BearerAuth' => array())
            )
        );

        $wp_routes = $this->get_wp_rest_routes();
        $wc_routes = class_exists('WooCommerce') ? $this->get_wc_rest_routes() : array();

        $swagger['paths'] = array_merge($wp_routes, $wc_routes);

        return $swagger;
    }

    private function get_wp_rest_routes() {
        global $wp_rest_server;

        // Ensure the REST server is available
        if ( ! isset( $wp_rest_server ) ) {
            return array();
        }

        $routes = $wp_rest_server->get_routes();
        $paths = array();

        foreach ($routes as $route => $endpoints) {
            foreach ($endpoints as $endpoint) {
                $methods = array_map('strtoupper', $endpoint['methods']);
                foreach ($methods as $method) {
                    $paths[$route][$method] = array(
                        'summary' => $route,
                        'description' => isset($endpoint['args']['_description']) ? $endpoint['args']['_description'] : '',
                        'responses' => array(
                            '200' => array(
                                'description' => 'Successful response',
                            )
                        )
                    );
                }
            }
        }

        return $paths;
    }

    private function get_wc_rest_routes() {
        $wc_routes = array();
        $controllers = array(
            'WC_REST_Products_Controller',
            'WC_REST_Customers_Controller',
            'WC_REST_Orders_Controller'
        );

        foreach ($controllers as $controller_class) {
            if (class_exists($controller_class)) {
                $controller = new $controller_class();
                if (method_exists($controller, 'get_routes')) {
                    $routes = $controller->get_routes();
                    foreach ($routes as $route => $endpoint) {
                        $methods = array_map('strtoupper', $endpoint['methods']);
                        foreach ($methods as $method) {
                            $wc_routes[$route][$method] = array(
                                'summary' => $route,
                                'description' => isset($endpoint['args']['_description']) ? $endpoint['args']['_description'] : '',
                                'responses' => array(
                                    '200' => array(
                                        'description' => 'Successful response',
                                    )
                                )
                            );
                        }
                    }
                }
            }
        }

        return $wc_routes;
    }

    public function swagger_ui_redirect() {
        if ( isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/swagger-ui.html' ) {
            $swagger_url = home_url('/wp-content/uploads/swagger.yaml');
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Swagger UI</title>
                <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css" />
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
                <script>
                    const ui = SwaggerUIBundle({
                        url: "' . $swagger_url . '",
                        dom_id: "#swagger-ui",
                    });
                </script>
            </body>
            </html>';
            exit;
        }
    }

    public function create_swagger_yaml_file() {
        $swagger = $this->get_swagger_yaml();
        $yaml = $this->array_to_yaml($swagger);

        if ( ! file_exists($this->upload_dir) ) {
            wp_mkdir_p($this->upload_dir);
        }

        file_put_contents($this->swagger_file, $yaml);
    }

    private function array_to_yaml($array) {
        $yaml = '';
        foreach ($array as $key => $value) {
            $yaml .= $this->yaml_format($key, $value, 0);
        }
        return $yaml;
    }

    private function yaml_format($key, $value, $indent) {
        $yaml = str_repeat('  ', $indent) . $key . ':';
        if (is_array($value)) {
            $yaml .= "\n";
            foreach ($value as $sub_key => $sub_value) {
                $yaml .= $this->yaml_format($sub_key, $sub_value, $indent + 1);
            }
        } else {
            $yaml .= ' ' . $this->yaml_escape($value) . "\n";
        }
        return $yaml;
    }

    private function yaml_escape($value) {
        if (is_string($value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }
        return $value;
    }
}

new WP_Swagger_UI_Viewer();
?>
