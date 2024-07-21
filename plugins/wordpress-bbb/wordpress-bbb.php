<?php
/*
Plugin Name: WordPress BBB
Description: Build with Batch Binder. Consolidated import/export functionalities for WordPress and WooCommerce.
Version: 1.0.1
Author: Yilmaz Mustafa, Sergey Ryskin, Atilla Balin, ChatGPT
Text Domain: wordpress-bbb
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Main Plugin Class
class WP_BBB_Plugin
{
    public function __construct()
    {
        // Register hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Initialize components
        $this->initialize_import_export();
        $this->initialize_scheduler();
        $this->initialize_api();
        $this->initialize_logger();
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Batch Builder',
            'Batch Builder',
            'manage_options',
            'wp-bbb',
            array($this, 'admin_dashboard'),
            'dashicons-migrate'
        );
    }

    public function admin_dashboard()
    {
        echo '<div class="wrap">
                <h1>WP III Dashboard</h1>
                <form method="post" action="' . admin_url('admin-post.php') . '" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="wp_iii_import">
                    <h2>Import Data</h2>
                    <select name="import_type">
                        <option value="post">Post</option>
                        <option value="page">Page</option>
                        <option value="tag">Tag</option>
                        <option value="product">Product</option>
                        <option value="media">Media</option>
                        <option value="category">Category</option>
                        <option value="attribute">Attribute</option>
                        <option value="store">Store</option>
                        <option value="user">User</option>
                        <option value="customer">Customer</option>
                        <option value="order">Order</option>
                        <option value="invoice">Invoice</option>
                    </select>
                    <input type="file" name="import_file">
                    <button type="submit">Import</button>
                </form>

                <form method="post" action="' . admin_url('admin-post.php') . '">
                    <input type="hidden" name="action" value="wp_iii_export">
                    <h2>Export Data</h2>
                    <select name="export_type">
                        <option value="post">Post</option>
                        <option value="page">Page</option>
                        <option value="tag">Tag</option>
                        <option value="product">Product</option>
                        <option value="media">Media</option>
                        <option value="category">Category</option>
                        <option value="attribute">Attribute</option>
                        <option value="store">Store</option>
                        <option value="user">User</option>
                        <option value="customer">Customer</option>
                        <option value="order">Order</option>
                        <option value="invoice">Invoice</option>
                    </select>
                    <button type="submit">Export</button>
                </form>
            </div>';
    }

    public function enqueue_admin_scripts()
    {
        // Add your CSS file here if you have one
        // wp_enqueue_style('wp-bbb-admin-styles', plugin_dir_url(__FILE__) . 'admin/css/admin-styles.css');
    }

    private function initialize_import_export()
    {
        // Add import/export functionality
        add_action('admin_post_wp_iii_import', array($this, 'handle_import'));
        add_action('admin_post_wp_iii_export', array($this, 'handle_export'));
    }

    public function handle_import()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        if (isset($_FILES['import_file']) && !empty($_FILES['import_file']['tmp_name'])) {
            $import_type = sanitize_text_field($_POST['import_type']);
            $file = $_FILES['import_file']['tmp_name'];

            switch ($import_type) {
                case 'post':
                    $this->import_posts($file);
                    break;
                case 'page':
                    $this->import_pages($file);
                    break;
                case 'tag':
                    $this->import_tags($file);
                    break;
                case 'product':
                    $this->import_products($file);
                    break;
                case 'media':
                    $this->import_media($file);
                    break;
                case 'category':
                    $this->import_categories($file);
                    break;
                case 'attribute':
                    $this->import_attributes($file);
                    break;
                case 'store':
                    $this->import_stores($file);
                    break;
                case 'user':
                    $this->import_users($file);
                    break;
                case 'customer':
                    $this->import_customers($file);
                    break;
                case 'order':
                    $this->import_orders($file);
                    break;
                case 'invoice':
                    $this->import_invoices($file);
                    break;
            }
        }
    }

    public function handle_export()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $export_type = sanitize_text_field($_POST['export_type']);

        switch ($export_type) {
            case 'post':
                $this->export_posts();
                break;
            case 'page':
                $this->export_pages();
                break;
            case 'tag':
                $this->export_tags();
                break;
            case 'product':
                $this->export_products();
                break;
            case 'media':
                $this->export_media();
                break;
            case 'category':
                $this->export_categories();
                break;
            case 'attribute':
                $this->export_attributes();
                break;
            case 'store':
                $this->export_stores();
                break;
            case 'user':
                $this->export_users();
                break;
            case 'customer':
                $this->export_customers();
                break;
            case 'order':
                $this->export_orders();
                break;
            case 'invoice':
                $this->export_invoices();
                break;
        }
    }

    private function import_posts($file)
    {
        $data = file_get_contents($file);
        $posts = json_decode($data, true);

        foreach ($posts as $post) {
            wp_insert_post($post);
        }
    }

    private function import_pages($file)
    {
        $data = file_get_contents($file);
        $pages = json_decode($data, true);

        foreach ($pages as $page) {
            wp_insert_post(array_merge($page, ['post_type' => 'page']));
        }
    }

    private function import_tags($file)
    {
        $data = file_get_contents($file);
        $tags = json_decode($data, true);

        foreach ($tags as $tag) {
            wp_insert_term($tag['name'], 'post_tag', $tag);
        }
    }

    private function import_products($file)
    {
        $data = file_get_contents($file);
        $products = json_decode($data, true);

        foreach ($products as $product) {
            wp_insert_post(array_merge($product, ['post_type' => 'product']));
        }
    }

    private function import_media($file)
    {
        $data = file_get_contents($file);
        $media_items = json_decode($data, true);

        foreach ($media_items as $media) {
            wp_insert_attachment($media);
        }
    }

    private function import_categories($file)
    {
        $data = file_get_contents($file);
        $categories = json_decode($data, true);

        foreach ($categories as $category) {
            wp_insert_term($category['name'], 'category', $category);
        }
    }

    private function import_attributes($file)
    {
        $data = file_get_contents($file);
        $attributes = json_decode($data, true);

        foreach ($attributes as $attribute) {
            $attribute_id = wc_create_attribute(array(
                'name' => $attribute['name'],
                'slug' => $attribute['slug'],
                'type' => $attribute['type'],
                'order_by' => $attribute['order_by'],
                'has_archives' => $attribute['has_archives'],
            ));

            if (!is_wp_error($attribute_id) && isset($attribute['terms'])) {
                foreach ($attribute['terms'] as $term) {
                    wp_insert_term($term['name'], 'pa_' . $attribute['slug'], array('slug' => $term['slug']));
                }
            }
        }
    }

    private function import_stores($file)
    {
        $data = file_get_contents($file);
        $stores = json_decode($data, true);

        foreach ($stores as $store) {
            $store_id = wp_insert_post(array(
                'post_title' => $store['Name'],
                'post_type' => 'store',
                'post_status' => 'publish',
            ));
        }
    }

    private function import_users($file)
    {
        $data = file_get_contents($file);
        $users = json_decode($data, true);

        foreach ($users as $user) {
            wp_insert_user($user);
        }
    }

    private function import_customers($file)
    {
        $data = file_get_contents($file);
        $customers = json_decode($data, true);

        foreach ($customers as $customer) {
            wp_insert_user(array_merge($customer, ['role' => 'customer']));
        }
    }

    private function import_orders($file)
    {
        $data = file_get_contents($file);
        $orders = json_decode($data, true);

        foreach ($orders as $order) {
            wc_create_order($order);
        }
    }

    private function import_invoices($file)
    {
        // Implement invoice import logic here
    }

    private function export_posts()
    {
        $posts = get_posts(['numberposts' => -1]);
        $data = json_encode($posts);
        $this->output_file('posts.json', $data);
    }

    private function export_pages()
    {
        $pages = get_posts(['post_type' => 'page', 'numberposts' => -1]);
        $data = json_encode($pages);
        $this->output_file('pages.json', $data);
    }

    private function export_tags()
    {
        $tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
        $data = json_encode($tags);
        $this->output_file('tags.json', $data);
    }

    private function export_products()
    {
        $products = get_posts(['post_type' => 'product', 'numberposts' => -1]);
        $data = json_encode($products);
        $this->output_file('products.json', $data);
    }

    private function export_media()
    {
        $media_items = get_posts(['post_type' => 'attachment', 'numberposts' => -1]);
        $data = json_encode($media_items);
        $this->output_file('media.json', $data);
    }

    private function export_categories()
    {
        $categories = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
        $data = json_encode($categories);
        $this->output_file('categories.json', $data);
    }

    private function export_attributes()
    {
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes = array();

        foreach ($attribute_taxonomies as $taxonomy) {
            $terms = get_terms(array('taxonomy' => 'pa_' . $taxonomy->attribute_name, 'hide_empty' => false));
            $attributes[] = array(
                'name' => $taxonomy->attribute_label,
                'slug' => $taxonomy->attribute_name,
                'type' => $taxonomy->attribute_type,
                'order_by' => $taxonomy->attribute_orderby,
                'has_archives' => $taxonomy->attribute_public,
                'terms' => $terms
            );
        }

        $data = json_encode($attributes);
        $this->output_file('attributes.json', $data);
    }

    private function export_stores()
    {
        // Implement store export logic here
    }

    private function export_users()
    {
        $users = get_users();
        $data = json_encode($users);
        $this->output_file('users.json', $data);
    }

    private function export_customers()
    {
        $customers = get_users(['role' => 'customer']);
        $data = json_encode($customers);
        $this->output_file('customers.json', $data);
    }

    private function export_orders()
    {
        $orders = wc_get_orders(['limit' => -1]);
        $data = json_encode($orders);
        $this->output_file('orders.json', $data);
    }

    private function export_invoices()
    {
        // Implement invoice export logic here
    }

    private function output_file($filename, $data)
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $data;
        exit;
    }

    private function initialize_scheduler()
    {
        if (!wp_next_scheduled('wp_iii_scheduled_import_export')) {
            wp_schedule_event(time(), 'hourly', 'wp_iii_scheduled_import_export');
        }

        add_action('wp_iii_scheduled_import_export', array($this, 'scheduled_import_export'));
    }

    public function scheduled_import_export()
    {
        // Perform scheduled import/export tasks
    }

    private function initialize_api()
    {
        add_action('rest_api_init', function () {
            register_rest_route('wp-bbb/v1', '/import', array(
                'methods' => 'POST',
                'callback' => array($this, 'api_import'),
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ));

            register_rest_route('wp-bbb/v1', '/export', array(
                'methods' => 'GET',
                'callback' => array($this, 'api_export'),
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ));
        });
    }

    public function api_import($request)
    {
        // Handle API import functionality
        return new WP_REST_Response('Import successful', 200);
    }

    public function api_export($request)
    {
        // Handle API export functionality
        return new WP_REST_Response('Export successful', 200);
    }

    private function initialize_logger()
    {
        add_action('wp_iii_log', array($this, 'log'));
    }

    public function log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }
}

// Initialize the plugin
new WP_BBB_Plugin();
?>
