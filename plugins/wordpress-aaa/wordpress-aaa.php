<?php
/*
Plugin Name: WordPress AAA
Description: API Assisted Automation generates a Swagger (OpenAPI) specification file for the WordPress REST API and WooCommerce REST API, accessible via Swagger UI.
Version: 1.0.1
Author: Yilmaz Mustafa
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Swagger_Generator {

    public function __construct() {
        add_action('rest_api_init', array($this, 'generate_swagger_yaml'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('init', array($this, 'swagger_ui_redirect'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Swagger Generator',
            'Swagger Generator',
            'manage_options',
            'swagger-generator',
            array($this, 'swagger_generator_page')
        );
    }

    public function swagger_generator_page() {
        echo '<div class="wrap">';
        echo '<h1>Swagger Generator</h1>';
        echo '<p>Download the <a href="' . home_url('/wp-json/wp/v2/swagger.yaml') . '" target="_blank">Swagger YAML file</a>.</p>';
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

        header('Content-Type: application/x-yaml');
        echo yaml_emit($swagger);
        exit;
    }

    private function get_wp_rest_routes() {
        global $wp_rest_server;
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

        return $wc_routes;
    }

    public function swagger_ui_redirect() {
        if ( isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/swagger-ui.html' ) {
            $swagger_url = home_url('/wp-json/wp/v2/swagger.yaml');
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
}

new WP_Swagger_Generator();
?>
