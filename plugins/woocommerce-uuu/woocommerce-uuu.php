<?php
/*
Plugin Name: WooCommerce UUU
Description: Universal Upload Utility helps to import WooCommerce products from CSV or JSON files including product images, and export template files.
Version: 1.0.2
Author: Yilmaz Mustafa, Sergey Ryskin, Atilla Balin, ChatGPT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add sub-menu page under Products
add_action('admin_menu', 'wpi_add_admin_menu');
function wpi_add_admin_menu() {
    add_submenu_page('edit.php?post_type=product', 'Product Importer', 'Product Importer', 'manage_options', 'product-importer', 'wpi_import_page', 'dashicons-upload');
}

// Display import/export page
function wpi_import_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce Product Importer</h1>
        <form id="wpi-import-form" method="post" enctype="multipart/form-data">
            <h2>Import Products</h2>
            <input type="file" name="import_file" id="import_file" required>
            <input type="submit" name="import_submit" value="Import Products" class="button button-primary">
        </form>
        <div id="wpi-import-results"></div>
        <h2>Export Template</h2>
        <form id="wpi-export-form" method="get" action="<?php echo admin_url('admin-ajax.php'); ?>">
            <input type="hidden" name="action" value="wpi_export_template">
            <select name="template_format">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
                <option value="xml">XML</option>
            </select>
            <input type="submit" value="Export Template" class="button button-secondary">
        </form>
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

// Handle template export
add_action('wp_ajax_wpi_export_template', 'wpi_export_template');
function wpi_export_template() {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    $format = isset($_GET['template_format']) ? sanitize_text_field($_GET['template_format']) : 'csv';
    $filename = "woocommerce_product_template.$format";

    $template_data = [
        ['sku', 'name', 'price', 'description', 'category', 'image'],
        ['123', 'Sample Product', '19.99', 'This is a sample product.', 'Category', '/path/to/image.jpg']
    ];

    switch ($format) {
        case 'json':
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=$filename");
            echo json_encode(array_slice(array_map(function($row) use ($template_data) {
                return array_combine($template_data[0], $row);
            }, array_slice($template_data, 1)), 0));
            break;

        case 'xml':
            header('Content-Type: text/xml');
            header("Content-Disposition: attachment; filename=$filename");
            $xml_data = new SimpleXMLElement('<products/>');
            foreach (array_slice($template_data, 1) as $row) {
                $product = $xml_data->addChild('product');
                foreach ($template_data[0] as $key => $value) {
                    $product->addChild($value, $row[$key]);
                }
            }
            echo $xml_data->asXML();
            break;

        case 'csv':
        default:
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=$filename");
            $output = fopen('php://output', 'w');
            foreach ($template_data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            break;
    }

    exit;
}
?>
