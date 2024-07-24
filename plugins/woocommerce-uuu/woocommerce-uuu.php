<?php
/*
Plugin Name: woocommerce-uuu
Description: Import WooCommerce products from CSV or JSON files including product images.
Version: 1.0.1
Author: Yilmaz Mustafa, Sergey Ryskin, Atilla Balin, ChatGPT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add menu page
add_action('admin_menu', 'wpi_add_admin_menu');
function wpi_add_admin_menu() {
    add_menu_page('Product Importer', 'Product Importer', 'manage_options', 'product-importer', 'wpi_import_page');
}

// Display import page
function wpi_import_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce Product Importer</h1>
        <form id="wpi-import-form" method="post" enctype="multipart/form-data">
            <input type="file" name="import_file" id="import_file" required>
            <input type="submit" name="import_submit" value="Import Products" class="button button-primary">
        </form>
        <div id="wpi-import-results"></div>
    </div>
    <script>
        document.getElementById('wpi-import-form').onsubmit = function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            fetch(ajaxurl + '?action=wpi_import_products', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('wpi-import-results').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
    <?php
}

// Handle file upload and import
add_action('wp_ajax_wpi_import_products', 'wpi_import_products');
function wpi_import_products() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (empty($_FILES['import_file'])) {
        wp_send_json_error('No file uploaded');
    }

    $file = $_FILES['import_file']['tmp_name'];
    $file_type = wp_check_filetype($_FILES['import_file']['name'])['ext'];

    if ($file_type === 'csv') {
        $data = wpi_parse_csv($file);
    } elseif ($file_type === 'json') {
        $data = wpi_parse_json($file);
    } else {
        wp_send_json_error('Invalid file type');
    }

    if (empty($data)) {
        wp_send_json_error('Failed to parse file');
    }

    foreach ($data as $product) {
        wpi_import_product($product);
    }

    wp_send_json_success('Products imported successfully');
}

// Parse CSV file
function wpi_parse_csv($file) {
    $csv = array_map('str_getcsv', file($file));
    $headers = array_map('strtolower', array_shift($csv));
    $data = [];
    foreach ($csv as $row) {
        $data[] = array_combine($headers, $row);
    }
    return $data;
}

// Parse JSON file and convert to CSV format
function wpi_parse_json($file) {
    $json = json_decode(file_get_contents($file), true);
    if (empty($json)) {
        return [];
    }

    // Convert JSON to CSV format
    $headers = array_keys($json[0]);
    $csv_data = [implode(',', $headers)];
    foreach ($json as $row) {
        $csv_data[] = implode(',', array_map('strval', $row));
    }

    $csv_temp = tmpfile();
    foreach ($csv_data as $line) {
        fputcsv($csv_temp, str_getcsv($line));
    }
    fseek($csv_temp, 0);
    return wpi_parse_csv(stream_get_contents($csv_temp));
}

// Import a single product
function wpi_import_product($product) {
    $product_id = wc_get_product_id_by_sku($product['sku']);
    if (!$product_id) {
        $product_id = wp_insert_post([
            'post_title' => $product['name'],
            'post_content' => $product['description'],
            'post_status' => 'publish',
            'post_type' => 'product',
        ]);
        update_post_meta($product_id, '_sku', $product['sku']);
        update_post_meta($product_id, '_price', $product['price']);
    }

    if (!empty($product['image'])) {
        $image_id = wpi_upload_image($product['image']);
        if ($image_id) {
            set_post_thumbnail($product_id, $image_id);
        }
    }

    if (!empty($product['category'])) {
        wp_set_object_terms($product_id, $product['category'], 'product_cat');
    }
}

// Upload image to media library
function wpi_upload_image($image_path) {
    $upload = wp_upload_bits(basename($image_path), null, file_get_contents($image_path));
    if ($upload['error']) {
        return false;
    }

    $wp_filetype = wp_check_filetype($upload['file'], null);
    $attachment = [
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($upload['file']),
        'post_content' => '',
        'post_status' => 'inherit'
    ];

    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}
?>
