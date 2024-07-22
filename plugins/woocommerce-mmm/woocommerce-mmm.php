<?php
/*
Plugin Name: WooCommerce MMM
Description: Adds multi-merchant functionality to WooCommerce, allowing products to be managed and sold by multiple stores with custom attributes for each store.
Version: 1.4
Authors: Yilmaz Mustafa, Sergey Ryskin, ChatGPT
*/

### Block 1: Plugin Setup and Activation

// include wordpress-md2html class
require_once plugin_dir_path(__FILE__) . 'wordpress-md2html.php';

if (!defined('ABSPATH')) {
    exit;
}

// Activation hook to create custom tables
register_activation_hook(__FILE__, 'mmm_create_tables');

function mmm_create_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $stores_table = "CREATE TABLE {$wpdb->prefix}stores (
        store_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        store_name VARCHAR(255) NOT NULL,
        store_url VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        token VARCHAR(255) NOT NULL,
        secret VARCHAR(255) NOT NULL,
        logo_url VARCHAR(255) DEFAULT NULL,
        background_url VARCHAR(255) DEFAULT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        validated TINYINT(1) NOT NULL DEFAULT 0,
        created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (store_id)
    ) $charset_collate;";

    $product_store_table = "CREATE TABLE {$wpdb->prefix}product_store (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        store_id BIGINT(20) UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (product_id) REFERENCES {$wpdb->prefix}posts(ID),
        FOREIGN KEY (store_id) REFERENCES {$wpdb->prefix}stores(store_id)
    ) $charset_collate;";

    $store_reviews_table = "CREATE TABLE {$wpdb->prefix}store_reviews (
        review_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        store_id BIGINT(20) UNSIGNED NOT NULL,
        rating TINYINT(1) NOT NULL,
        review TEXT NOT NULL,
        created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (review_id),
        FOREIGN KEY (store_id) REFERENCES {$wpdb->prefix}stores(store_id)
    ) $charset_collate;";

    $store_hours_table = "CREATE TABLE {$wpdb->prefix}store_hours (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        store_id BIGINT(20) UNSIGNED NOT NULL,
        day_of_week TINYINT(1) NOT NULL,
        open_time TIME NOT NULL,
        close_time TIME NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (store_id) REFERENCES {$wpdb->prefix}stores(store_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($stores_table);
    dbDelta($product_store_table);
    dbDelta($store_reviews_table);
    dbDelta($store_hours_table);
}

### Block 2: Product Data Tab and Fields

// Add a custom tab to the product data meta box
add_filter('woocommerce_product_data_tabs', 'mmm_add_product_data_tab');
function mmm_add_product_data_tab($tabs) {
    $tabs['store_data'] = array(
        'label'    => __('Store Data', 'woocommerce'),
        'target'   => 'store_data_options',
        'class'    => array('show_if_simple', 'show_if_variable'),
        'priority' => 21,
    );
    return $tabs;
}

// Add fields to the custom tab
add_action('woocommerce_product_data_panels', 'mmm_add_product_data_fields');
function mmm_add_product_data_fields() {
    global $post;
    ?>
    <div id='store_data_options' class='panel woocommerce_options_panel'>
        <div class='options_group'>
            <?php
            woocommerce_wp_text_input(array(
                'id'          => '_store_id',
                'label'       => __('Store ID', 'woocommerce'),
                'desc_tip'    => 'true',
                'description' => __('Enter the store ID.', 'woocommerce'),
                'type'        => 'text',
            ));
            ?>
        </div>
    </div>
    <?php
}

// Save custom fields
add_action('woocommerce_process_product_meta', 'mmm_save_product_data_fields');
function mmm_save_product_data_fields($post_id) {
    global $wpdb;
    $store_id = sanitize_text_field($_POST['_store_id']);

    if (!empty($store_id)) {
        update_post_meta($post_id, '_store_id', esc_attr($store_id));

        // Save the product-store relationship
        $existing_entry = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}product_store WHERE product_id = %d AND store_id = %d", $post_id, $store_id));
        if ($existing_entry == 0) {
            $wpdb->insert(
                "{$wpdb->prefix}product_store",
                array(
                    'product_id' => $post_id,
                    'store_id' => $store_id,
                )
            );
        }
    }
}

// Display store information on the frontend
add_action('woocommerce_single_product_summary', 'mmm_display_store_info', 20);
function mmm_display_store_info() {
    global $post;

    $store_id = get_post_meta($post->ID, '_store_id', true);

    if ($store_id) {
        global $wpdb;
        $store_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}stores WHERE store_id = %d", $store_id));
        echo '<p><strong>Store: </strong>' . esc_html($store_name) . '</p>';
    }
}

### Block 3: Admin Menu and Pages

#### Register Menu and Submenus

add_action('admin_menu', 'mmm_register_store_menu');
function mmm_register_store_menu() {
    add_menu_page(
        __('Stores', 'textdomain'),
        __('Stores', 'textdomain'),
        'manage_options',
        'store-management',
        'mmm_store_management_page',
        'dashicons-store',
        6
    );

    add_submenu_page(
        'store-management',
        __('All Stores', 'textdomain'),
        __('All Stores', 'textdomain'),
        'manage_options',
        'all-stores',
        'mmm_all_stores_page'
    );

    add_submenu_page(
        'store-management',
        __('Add New Store', 'textdomain'),
        __('Add New', 'textdomain'),
        'manage_options',
        'add-new-store',
        'mmm_add_new_store_page'
    );

    add_submenu_page(
        'store-management',
        __('Store Reviews', 'textdomain'),
        __('Reviews', 'textdomain'),
        'manage_options',
        'store-reviews',
        'mmm_store_reviews_page'
    );

    add_submenu_page(
        'store-management',
        __('Store Hours', 'textdomain'),
        __('Hours', 'textdomain'),
        'manage_options',
        'store-hours',
        'mmm_store_hours_page'
    );

    add_submenu_page(
        'store-management',
        __('CSV View', 'textdomain'),
        __('CSV View', 'textdomain'),
        'manage_options',
        'csv-view',
        'mmm_csv_view_page'
    );

    add_submenu_page(
        'store-management',
        __('API Usage', 'textdomain'),
        __('API Usage', 'textdomain'),
        'manage_options',
        'mmm-api-usage',
        'mmm_api_usage_page'
    );
}


#### Main Store Management Page

function mmm_store_management_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Store Management', 'textdomain'); ?></h1>
        <p><?php _e('Welcome to the Store Management section. Use the submenus to view all stores, add new stores, or manage reviews.', 'textdomain'); ?></p>

        <h2><?php _e('Generate Mock Stores', 'textdomain'); ?></h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="generate_mock_stores">
            <?php wp_nonce_field('generate_mock_stores', 'generate_mock_stores_nonce'); ?>
            <input type="submit" class="button button-primary" value="<?php _e('Generate Mock Stores', 'textdomain'); ?>">
        </form>

        <h2><?php _e('Remove All Stores', 'textdomain'); ?></h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="remove_all_stores">
            <?php wp_nonce_field('remove_all_stores', 'remove_all_stores_nonce'); ?>
            <input type="submit" class="button button-primary" value="<?php _e('Remove All Stores', 'textdomain'); ?>">
        </form>
    </div>
    <?php
}

#### All Stores Page
function mmm_all_stores_page() {
    global $wpdb;

    if (isset($_POST['mmm_import_stores'])) {
        check_admin_referer('import_stores_action', 'import_stores_nonce');
        if (!empty($_FILES['import_file']['tmp_name'])) {
            mmm_import_stores($_FILES['import_file']);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Stores imported successfully.', 'textdomain') . '</p></div>';
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Please select a file to import.', 'textdomain') . '</p></div>';
        }
    }

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 10;
    $offset = ($paged - 1) * $per_page;

    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $where = '';
    if ($search) {
        $where = $wpdb->prepare("WHERE store_name LIKE %s OR email LIKE %s OR store_url LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
    }

    $total_stores = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}stores $where");
    $stores = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}stores $where LIMIT $offset, $per_page");

    $total_pages = ceil($total_stores / $per_page);

    echo '<div class="wrap">';
    echo '<h1>' . __('Stores', 'textdomain') . '</h1>';

    // Export and Import Buttons
    echo '<h2>' . __('Export Stores', 'textdomain') . '</h2>';
    echo '<form method="post" action="' . admin_url('admin.php?page=csv-view') . '">';
    wp_nonce_field('export_stores_action', 'export_stores_nonce');
    echo '<input type="submit" name="mmm_export_stores" class="button button-primary" value="' . __('View Stores as CSV', 'textdomain') . '" />';
    echo '</form>';

    echo '<h2>' . __('Import Stores', 'textdomain') . '</h2>';
    echo '<form method="post" action="" enctype="multipart/form-data">';
    wp_nonce_field('import_stores_action', 'import_stores_nonce');
    echo '<input type="file" name="import_file" accept=".csv" required />';
    echo '<input type="submit" name="mmm_import_stores" class="button button-primary" value="' . __('Import Stores', 'textdomain') . '" />';
    echo '</form>';

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="all-stores" />';
    echo '<p class="search-box">';
    echo '<label class="screen-reader-text" for="store-search-input">' . __('Search Stores', 'textdomain') . '</label>';
    echo '<input type="search" id="store-search-input" name="s" value="' . esc_attr($search) . '" />';
    echo '<input type="submit" id="search-submit" class="button" value="' . __('Search Stores', 'textdomain') . '" />';
    echo '</p>';
    echo '</form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>' . __('ID', 'textdomain') . '</th><th>' . __('Name', 'textdomain') . '</th><th>' . __('URL', 'textdomain') . '</th><th>' . __('Email', 'textdomain') . '</th><th>' . __('Phone', 'textdomain') . '</th><th>' . __('Logo URL', 'textdomain') . '</th><th>' . __('Background URL', 'textdomain') . '</th><th>' . __('Active', 'textdomain') . '</th><th>' . __('Validated', 'textdomain') . '</th><th>' . __('Created', 'textdomain') . '</th><th>' . __('Updated', 'textdomain') . '</th><th>' . __('Actions', 'textdomain') . '</th></tr></thead>';
    echo '<tbody>';

    if ($stores) {
        foreach ($stores as $store) {
            echo '<tr>';
            echo '<td>' . esc_html($store->store_id) . '</td>';
            echo '<td>' . esc_html($store->store_name) . '</td>';
            echo '<td>' . esc_html($store->store_url) . '</td>';
            echo '<td>' . esc_html($store->email) . '</td>';
            echo '<td>' . esc_html($store->phone) . '</td>';
            echo '<td>' . esc_html($store->logo_url) . '</td>';
            echo '<td>' . esc_html($store->background_url) . '</td>';
            echo '<td>' . esc_html($store->active) . '</td>';
            echo '<td>' . esc_html($store->validated) . '</td>';
            echo '<td>' . esc_html($store->created) . '</td>';
            echo '<td>' . esc_html($store->updated) . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('admin.php?page=edit-store&id=' . $store->store_id) . '">' . __('Edit', 'textdomain') . '</a> | ';
            echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=delete_store&store_id=' . $store->store_id), 'delete_store') . '" onclick="return confirm(\'' . __('Are you sure you want to delete this store?', 'textdomain') . '\')">' . __('Delete', 'textdomain') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="12">' . __('No stores found.', 'textdomain') . '</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo '<div class="tablenav">';
    echo '<div class="tablenav-pages">';
    echo paginate_links(array(
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'prev_text' => __('&laquo;', 'textdomain'),
        'next_text' => __('&raquo;', 'textdomain'),
        'total' => $total_pages,
        'current' => $paged
    ));
    echo '</div>';
    echo '</div>';

    echo '</div>';
}


#### Add New Store Page

function mmm_add_new_store_page() {
    if (isset($_POST['mmm_add_store'])) {
        check_admin_referer('add_new_store_action', 'add_new_store_nonce');
        mmm_add_store($_POST['store_name'], $_POST['store_url'], $_POST['email'], $_POST['phone'], $_POST['token'], $_POST['secret'], $_POST['logo_url'], $_POST['background_url']);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store added successfully.', 'textdomain') . '</p></div>';
    }

    echo '<h1>' . __('Add New Store', 'textdomain') . '</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('add_new_store_action', 'add_new_store_nonce');
    echo '<table class="form-table">';
    echo '<tr><th>' . __('Store Name', 'textdomain') . '</th><td><input type="text" name="store_name" required /></td></tr>';
    echo '<tr><th>' . __('Store URL', 'textdomain') . '</th><td><input type="url" name="store_url" required /></td></tr>';
    echo '<tr><th>' . __('Email', 'textdomain') . '</th><td><input type="email" name="email" required /></td></tr>';
    echo '<tr><th>' . __('Phone', 'textdomain') . '</th><td><input type="text" name="phone" required /></td></tr>';
    echo '<tr><th>' . __('Token', 'textdomain') . '</th><td><input type="text" name="token" required /></td></tr>';
    echo '<tr><th>' . __('Secret', 'textdomain') . '</th><td><input type="text" name="secret" required /></td></tr>';
    echo '<tr><th>' . __('Logo URL', 'textdomain') . '</th><td><input type="url" name="logo_url" /></td></tr>';
    echo '<tr><th>' . __('Background URL', 'textdomain') . '</th><td><input type="url" name="background_url" /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="mmm_add_store" value="' . __('Add Store', 'textdomain') . '" class="button button-primary" />';
    echo '</form>';
}

#### Store Reviews Page

function mmm_store_reviews_page() {
    global $wpdb;

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 10;
    $offset = ($paged - 1) * $per_page;

    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $store_filter = isset($_GET['store_id']) ? intval($_GET['store_id']) : '';
    $customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : '';
    $date_filter_start = isset($_GET['date_start']) ? sanitize_text_field($_GET['date_start']) : '';
    $date_filter_end = isset($_GET['date_end']) ? sanitize_text_field($_GET['date_end']) : '';

    $where = '';
    if ($search) {
        $where .= $wpdb->prepare("AND review LIKE %s", '%' . $wpdb->esc_like($search) . '%');
    }
    if ($store_filter) {
        $where .= $wpdb->prepare("AND store_id = %d", $store_filter);
    }
    if ($customer_filter) {
        $where .= $wpdb->prepare("AND customer_id = %d", $customer_filter);
    }
    if ($date_filter_start && $date_filter_end) {
        $where .= $wpdb->prepare("AND created BETWEEN %s AND %s", $date_filter_start, $date_filter_end);
    }

    $total_reviews = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}store_reviews WHERE 1=1 $where");
    $reviews = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}store_reviews WHERE 1=1 $where LIMIT $offset, $per_page");

    $total_pages = ceil($total_reviews / $per_page);

    echo '<div class="wrap">';
    echo '<h1>' . __('Store Reviews', 'textdomain') . '</h1>';
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="store-reviews" />';
    echo '<p class="search-box">';
    echo '<label class="screen-reader-text" for="review-search-input">' . __('Search Reviews', 'textdomain') . '</label>';
    echo '<input type="search" id="review-search-input" name="s" value="' . esc_attr($search) . '" />';
    echo '<input type="submit" id="search-submit" class="button" value="' . __('Search Reviews', 'textdomain') . '" />';
    echo '</p>';
    echo '<p class="filter-box">';
    echo '<label for="store-filter">' . __('Store', 'textdomain') . '</label>';
    echo '<select name="store_id" id="store-filter">';
    echo '<option value="">' . __('All Stores', 'textdomain') . '</option>';
    $stores = mmm_get_stores();
    foreach ($stores as $store) {
        echo '<option value="' . esc_attr($store->store_id) . '"' . selected($store->store_id, $store_filter, false) . '>' . esc_html($store->store_name) . '</option>';
    }
    echo '</select>';
    echo '<label for="customer-filter">' . __('Customer', 'textdomain') . '</label>';
    echo '<input type="number" name="customer_id" id="customer-filter" value="' . esc_attr($customer_filter) . '" />';
    echo '<label for="date-filter-start">' . __('Start Date', 'textdomain') . '</label>';
    echo '<input type="date" name="date_start" id="date-filter-start" value="' . esc_attr($date_filter_start) . '" />';
    echo '<label for="date-filter-end">' . __('End Date', 'textdomain') . '</label>';
    echo '<input type="date" name="date_end" id="date-filter-end" value="' . esc_attr($date_filter_end) . '" />';
    echo '<input type="submit" class="button" value="' . __('Filter', 'textdomain') . '" />';
    echo '</p>';
    echo '</form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>' . __('Review ID', 'textdomain') . '</th><th>' . __('Store ID', 'textdomain') . '</th><th>' . __('Rating', 'textdomain') . '</th><th>' . __('Review', 'textdomain') . '</th><th>' . __('Created', 'textdomain') . '</th><th>' . __('Actions', 'textdomain') . '</th></tr></thead>';
    echo '<tbody>';
    if ($reviews) {
        foreach ($reviews as $review) {
            echo '<tr>';
            echo '<td>' . esc_html($review->review_id) . '</td>';
            echo '<td>' . esc_html($review->store_id) . '</td>';
            echo '<td>' . esc_html($review->rating) . '</td>';
            echo '<td>' . esc_html($review->review) . '</td>';
            echo '<td>' . esc_html($review->created) . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('admin.php?page=edit-review&id=' . $review->review_id) . '">' . __('Edit', 'textdomain') . '</a> | ';
            echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=delete_review&review_id=' . $review->review_id), 'delete_review') . '" onclick="return confirm(\'' . __('Are you sure you want to delete this review?', 'textdomain') . '\')">' . __('Delete', 'textdomain') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">' . __('No reviews found.', 'textdomain') . '</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo '<div class="tablenav">';
    echo '<div class="tablenav-pages">';
    echo paginate_links(array(
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'prev_text' => __('&laquo;', 'textdomain'),
        'next_text' => __('&raquo;', 'textdomain'),
        'total' => $total_pages,
        'current' => $paged
    ));
    echo '</div>';
    echo '</div>';

    echo '</div>';
}

#### Store Hours Page

function mmm_store_hours_page() {
    global $wpdb;

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 10;
    $offset = ($paged - 1) * $per_page;

    $store_filter = isset($_GET['store_id']) ? intval($_GET['store_id']) : '';

    $where = '';
    if ($store_filter) {
        $where .= $wpdb->prepare("AND store_id = %d", $store_filter);
    }

    $total_hours = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}store_hours WHERE 1=1 $where");
    $store_hours = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}store_hours WHERE 1=1 $where LIMIT $offset, $per_page");

    $total_pages = ceil($total_hours / $per_page);

    echo '<div class="wrap">';
    echo '<h1>' . __('Store Hours', 'textdomain') . '</h1>';
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="store-hours" />';
    echo '<p class="filter-box">';
    echo '<label for="store-filter">' . __('Store', 'textdomain') . '</label>';
    echo '<select name="store_id" id="store-filter">';
    echo '<option value="">' . __('All Stores', 'textdomain') . '</option>';
    $stores = mmm_get_stores();
    foreach ($stores as $store) {
        echo '<option value="' . esc_attr($store->store_id) . '"' . selected($store->store_id, $store_filter, false) . '>' . esc_html($store->store_name) . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" class="button" value="' . __('Filter', 'textdomain') . '" />';
    echo '</p>';
    echo '</form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>' . __('ID', 'textdomain') . '</th><th>' . __('Store ID', 'textdomain') . '</th><th>' . __('Day of Week', 'textdomain') . '</th><th>' . __('Open Time', 'textdomain') . '</th><th>' . __('Close Time', 'textdomain') . '</th><th>' . __('Actions', 'textdomain') . '</th></tr></thead>';
    echo '<tbody>';
    if ($store_hours) {
        foreach ($store_hours as $hour) {
            echo '<tr>';
            echo '<td>' . esc_html($hour->id) . '</td>';
            echo '<td>' . esc_html($hour->store_id) . '</td>';
            echo '<td>' . esc_html($hour->day_of_week) . '</td>';
            echo '<td>' . esc_html($hour->open_time) . '</td>';
            echo '<td>' . esc_html($hour->close_time) . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('admin.php?page=edit-store-hour&id=' . $hour->id) . '">' . __('Edit', 'textdomain') . '</a> | ';
            echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=delete_store_hour&id=' . $hour->id), 'delete_store_hour') . '" onclick="return confirm(\'' . __('Are you sure you want to delete this store hour?', 'textdomain') . '\')">' . __('Delete', 'textdomain') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">' . __('No store hours found.', 'textdomain') . '</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo '<div class="tablenav">';
    echo '<div class="tablenav-pages">';
    echo paginate_links(array(
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'prev_text' => __('&laquo;', 'textdomain'),
        'next_text' => __('&raquo;', 'textdomain'),
        'total' => $total_pages,
        'current' => $paged
    ));
    echo '</div>';
    echo '</div>';

    echo '</div>';
}

### Block 4: Import-Export and API Usage Pages

#### Import-Export Page

function mmm_store_import_export_page() {
    if (isset($_POST['mmm_import_stores'])) {
        check_admin_referer('import_stores_action', 'import_stores_nonce');
        if (!empty($_FILES['import_file']['tmp_name'])) {
            mmm_import_stores($_FILES['import_file']);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Stores imported successfully.', 'textdomain') . '</p></div>';
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Please select a file to import.', 'textdomain') . '</p></div>';
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . __('Import/Export', 'textdomain') . '</h1>';
    echo '<p>' . __('In this page, you can import and export store data.', 'textdomain') . '</p>';

    echo '<h2>' . __('Export Stores', 'textdomain') . '</h2>';
    echo '<form method="post" action="' . admin_url('admin.php?page=csv-view') . '">';
    wp_nonce_field('export_stores_action', 'export_stores_nonce');
    echo '<input type="submit" name="mmm_export_stores" class="button button-primary" value="' . __('View Stores as CSV', 'textdomain') . '" />';
    echo '</form>';

    echo '<h2>' . __('Import Stores', 'textdomain') . '</h2>';
    echo '<form method="post" action="" enctype="multipart/form-data">';
    wp_nonce_field('import_stores_action', 'import_stores_nonce');
    echo '<input type="file" name="import_file" accept=".csv" required />';
    echo '<input type="submit" name="mmm_import_stores" class="button button-primary" value="' . __('Import Stores', 'textdomain') . '" />';
    echo '</form>';
    echo '</div>';
}

function mmm_csv_view_page() {
    echo '<div class="wrap">';
    echo '<h1>' . __('Stores CSV View', 'textdomain') . '</h1>';

    // Fetch stores data from the database
    $stores = mmm_get_stores();

    // Store the CSV content in a variable
    $csv_content = "ID,Name,URL,Email,Phone,Logo URL,Background URL,Active,Validated,Created,Updated\n";
    foreach ($stores as $store) {
        $csv_content .= "{$store->store_id},"
            . "{$store->store_name},"
            . "{$store->store_url},"
            . "{$store->email},"
            . "{$store->phone},"
            . "{$store->logo_url},"
            . "{$store->background_url},"
            . "{$store->active},"
            . "{$store->validated},"
            . "{$store->created},"
            . "{$store->updated}\n";
    }

    // Display the CSV content in a textarea
    echo '<textarea rows="20" cols="150">' . esc_textarea($csv_content) . '</textarea>';

    // Add a form with a download button
    echo '<form method="post" action="">';
    wp_nonce_field('download_csv_action', 'download_csv_nonce');
    echo '<input type="hidden" name="csv_content" value="' . esc_attr($csv_content) . '" />';
    echo '<input type="submit" name="download_csv" class="button button-primary" value="' . __('Download CSV', 'textdomain') . '" />';
    echo '</form>';

    echo '</div>';
}



#### API Usage Page

function mmm_api_usage_page() {
    // Get the path to the ReadMe.md file
    $readme_path = plugin_dir_path(__FILE__) . 'ReadMe.md';

    // Check if the ReadMe.md file exists
    if (file_exists($readme_path)) {
        // Get the content of the ReadMe.md file
        $markdown_content = file_get_contents($readme_path);

        // Check if the Parsedown class exists
        if (class_exists('Markdown2Html')) {
            // Create a new Parsedown instance
            $Parsedown = new Markdown2Html();

            // Convert Markdown content to HTML
            $html_content = $Parsedown->text($markdown_content);

            // Display the HTML content
            echo '<div class="wrap">';
            echo '<h1>' . __('MMM API Usage', 'textdomain') . '</h1>';
            echo $html_content;
            echo '</div>';
        } else {
            // Display an error message if Parsedown is not available
            error_log('Parsedown class not found.');
            echo '<div class="wrap">';
            echo '<h1>' . __('MMM API Usage', 'textdomain') . '</h1>';
            echo '<p>' . __('Parsedown class not found.', 'textdomain') . '</p>';
            echo '</div>';
        }
    } else {
        // Display an error message if the ReadMe.md file is not found
        error_log('ReadMe.md file not found: ' . $readme_path);
        echo '<div class="wrap">';
        echo '<h1>' . __('MMM API Usage', 'textdomain') . '</h1>';
        echo '<p>' . __('The ReadMe.md file is not found.', 'textdomain') . '</p>';
        echo '</div>';
    }
}


### Block 5: Helper Functions and API Endpoints

#### Helper Functions

function mmm_get_store($store_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}stores WHERE store_id = %d", $store_id));
}

function mmm_add_store($store_name, $store_url, $email, $phone, $token, $secret, $logo_url = null, $background_url = null) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'stores',
        array(
            'store_name' => sanitize_text_field($store_name),
            'store_url' => esc_url($store_url),
            'email' => sanitize_email($email),
            'phone' => sanitize_text_field($phone),
            'token' => sanitize_text_field($token),
            'secret' => sanitize_text_field($secret),
            'logo_url' => esc_url($logo_url),
            'background_url' => esc_url($background_url),
        )
    );

    return $wpdb->insert_id;
}

function mmm_get_stores() {
    global $wpdb;

    $stores = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}stores");
    return $stores;
}

function mmm_update_store($store_id, $store_name, $store_url, $email, $phone, $token, $secret, $logo_url = null, $background_url = null) {
    global $wpdb;

    $wpdb->update(
        $wpdb->prefix . 'stores',
        array(
            'store_name' => sanitize_text_field($store_name),
            'store_url' => esc_url($store_url),
            'email' => sanitize_email($email),
            'phone' => sanitize_text_field($phone),
            'token' => sanitize_text_field($token),
            'secret' => sanitize_text_field($secret),
            'logo_url' => esc_url($logo_url),
            'background_url' => esc_url($background_url),
        ),
        array('store_id' => $store_id)
    );
}

function mmm_delete_store($store_id) {
    global $wpdb;

    $wpdb->delete(
        $wpdb->prefix . 'stores',
        array('store_id' => $store_id)
    );
}

function mmm_remove_all_stores() {
    global $wpdb;
    // truncate first child tables (store_hours, store_reviews, product_store) then parent table (stores)
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}store_hours");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}store_reviews");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}product_store");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}stores");

    return $wpdb->rows_affected;
}

function mmm_generate_mock_stores($number_of_stores) {
    global $wpdb;

    // make sure that number of stores is not more than 100.000 because it will take a lot of time

    if ($number_of_stores > 100000) {
        $number_of_stores = 100000;
        $error_message = 'Number of stores is limited to 100.000. Hence, only 100.000 stores will be generated.';
        error_log($error_message);
    }

    for ($i = 1; $i <= $number_of_stores; $i++) {
        $store_name = 'Mock Store ' . $i;
        $store_url = 'https://mockstore' . $i . '.com';
        $email = 'mock' . $i . '@mockstore.com';
        $phone = '123-456-7890';
        $token = md5(uniqid($i, true));
        $secret = md5(uniqid($i, true));
        $logo_url = 'https://mockstore' . $i . '.com/logo.png';
        $background_url = 'https://mockstore' . $i . '.com/background.png';

        mmm_add_store($store_name, $store_url, $email, $phone, $token, $secret, $logo_url, $background_url);
    }
}

function mmm_export_stores() {
    // Check user permissions and nonce for security
    if (!current_user_can('export_stores')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    check_admin_referer('export_stores_action', 'export_stores_nonce');

    // Fetch stores data from the database
    $stores = mmm_get_stores(); // This function needs to be defined to fetch store data

    // Set HTTP headers for CSV output
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stores.csv"');

    // Open the output stream
    $output = fopen('php://output', 'w');

    // Optionally add CSV header
    fputcsv($output, array('Store ID', 'Store Name', 'Store URL', 'Email', 'Phone'));

    // Output each store's data as a CSV row
    foreach ($stores as $store) {
        fputcsv($output, array($store->store_id, $store->store_name, $store->store_url, $store->email, $store->phone));
    }

    // Close the output stream
    fclose($output);

    // Terminate the script to prevent any further output
    exit;
}

function mmm_import_stores($file) {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (($handle = fopen($file['tmp_name'], 'r', 'UTF-8')) !== FALSE) {
        fgetcsv($handle); // Skip the header row

        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            mmm_add_store($data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8]);
        }

        fclose($handle);
    }
}

function mmm_import_products_and_stores($file) {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (($handle = fopen($file['tmp_name'], 'r', 'UTF-8')) !== FALSE) {
        fgetcsv($handle); // Skip the header row

        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            // Add or update store
            $store_id = mmm_add_store($data[5], $data[6], $data[7], $data[8], $data[9], $data[10]);

            // Add or update product
            $product_id = wc_get_product_id_by_sku($data[0]);
            if (!$product_id) {
                $product_id = wp_insert_post(array(
                    'post_title' => $data[1],
                    'post_content' => $data[2],
                    'post_status' => 'publish',
                    'post_type' => 'product',
                ));
                update_post_meta($product_id, '_sku', $data[0]);
            }

            // Update product meta
            update_post_meta($product_id, '_store_id', $store_id);

            // Add categories
            $categories = explode('>', $data[3]);
            wp_set_object_terms($product_id, $categories, 'product_cat');

            // Add tags
            $tags = explode(',', $data[4]);
            wp_set_object_terms($product_id, $tags, 'product_tag');
        }

        fclose($handle);
    }
}

function mmm_add_store_hour($store_id, $day_of_week, $open_time, $close_time) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'store_hours',
        array(
            'store_id' => intval($store_id),
            'day_of_week' => intval($day_of_week),
            'open_time' => $open_time,
            'close_time' => $close_time,
        )
    );

    return $wpdb->insert_id;
}

function mmm_get_store_hours($store_id) {
    global $wpdb;

    $store_hours = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}store_hours WHERE store_id = %d ORDER BY day_of_week", $store_id));
    return $store_hours;
}

function mmm_update_store_hour($id, $day_of_week, $open_time, $close_time) {
    global $wpdb;

    $wpdb->update(
        $wpdb->prefix . 'store_hours',
        array(
            'day_of_week' => intval($day_of_week),
            'open_time' => $open_time,
            'close_time' => $close_time,
        ),
        array('id' => intval($id))
    );
}

function mmm_delete_store_hour($id) {
    global $wpdb;

    $wpdb->delete(
        $wpdb->prefix . 'store_hours',
        array('id' => intval($id))
    );
}

#### API Endpoints

add_action('rest_api_init', function () {
    register_rest_route('mmm/v1', '/stores', array(
        'methods' => 'GET',
        'callback' => 'mmm_get_stores_endpoint',
    ));

    register_rest_route('mmm/v1', '/store', array(
        'methods' => 'POST',
        'callback' => 'mmm_add_store_endpoint',
    ));

    register_rest_route('mmm/v1', '/store/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'mmm_get_store_endpoint',
    ));

    register_rest_route('mmm/v1', '/store/(?P<id>\d+)', array(
        'methods' => 'POST',
        'callback' => 'mmm_update_store_endpoint',
    ));

    register_rest_route('mmm/v1', '/store/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'mmm_delete_store_endpoint',
    ));

    register_rest_route('mmm/v1', '/stores/export', array(
        'methods' => 'GET',
        'callback' => 'mmm_export_stores_endpoint',
    ));

    register_rest_route('mmm/v1', '/stores/import', array(
        'methods' => 'POST',
        'callback' => 'mmm_import_stores_endpoint',
    ));

    register_rest_route('mmm/v1', '/stores/remove_all', array(
        'methods' => 'POST',
        'callback' => 'mmm_remove_all_stores_endpoint',
    ));

    register_rest_route('mmm/v1', '/stores/generate_mock', array(
        'methods' => 'POST',
        'callback' => 'mmm_generate_mock_stores_endpoint',
    ));

    register_rest_route('mmm/v1', '/store/(?P<id>\d+)/hours', array(
        'methods' => 'GET',
        'callback' => 'mmm_get_store_hours_endpoint',
    ));

    register_rest_route('mmm/v1', '/store/(?P<id>\d+)/hour', array(
        'methods' => 'POST',
        'callback' => 'mmm_add_store_hour_endpoint',
    ));

    register_rest_route('mmm/v1', '/store/hour/(?P<hour_id>\d+)', array(
        'methods' => 'POST',
        'callback' => 'mmm_update_store_hour_endpoint',
    ));

    register_rest_route('mmm/v1', '/store/hour/(?P<hour_id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'mmm_delete_store_hour_endpoint',
    ));
});

function mmm_get_stores_endpoint(WP_REST_Request $request) {
    return new WP_REST_Response(mmm_get_stores(), 200);
}

function mmm_add_store_endpoint(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $store_id = mmm_add_store($params['store_name'], $params['store_url'], $params['email'], $params['phone'], $params['token'], $params['secret']);
    return new WP_REST_Response(array('store_id' => $store_id), 201);
}

function mmm_get_store_endpoint(WP_REST_Request $request) {
    $store_id = $request['id'];
    global $wpdb;
    $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}stores WHERE store_id = %d", $store_id));
    return new WP_REST_Response($store, 200);
}

function mmm_update_store_endpoint(WP_REST_Request $request) {
    $store_id = $request['id'];
    $params = $request->get_json_params();
    mmm_update_store($store_id, $params['store_name'], $params['store_url'], $params['email'], $params['phone'], $params['token'], $params['secret']);
    return new WP_REST_Response(null, 200);
}

function mmm_delete_store_endpoint(WP_REST_Request $request) {
    $store_id = $request['id'];
    mmm_delete_store($store_id);
    return new WP_REST_Response(null, 204);
}

function mmm_remove_all_stores_endpoint(WP_REST_Request $request) {
    mmm_remove_all_stores();
    return new WP_REST_Response(null, 204);
}

function mmm_generate_mock_stores_endpoint(WP_REST_Request $request) {
    $params = $request->get_json_params();
    mmm_generate_mock_stores($params['number_of_stores']);
    return new WP_REST_Response(null, 201);
}

function mmm_export_stores_endpoint(WP_REST_Request $request) {
    ob_start();
    mmm_export_stores();
    $csv = ob_get_clean();

    return new WP_REST_Response(array('csv' => $csv), 200);
}

function mmm_import_stores_endpoint(WP_REST_Request $request) {
    $file = $request->get_file_params()['file'];

    if ($file && $file['tmp_name']) {
        mmm_import_stores($file);
        return new WP_REST_Response(null, 200);
    } else {
        return new WP_Error('no_file', 'No file uploaded', array('status' => 400));
    }
}

function mmm_get_store_hours_endpoint(WP_REST_Request $request) {
    $store_id = $request['id'];
    return new WP_REST_Response(mmm_get_store_hours($store_id), 200);
}

function mmm_add_store_hour_endpoint(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $store_id = $request['id'];
    $hour_id = mmm_add_store_hour($store_id, $params['day_of_week'], $params['open_time'], $params['close_time']);
    return new WP_REST_Response(array('hour_id' => $hour_id), 201);
}

function mmm_update_store_hour_endpoint(WP_REST_Request $request) {
    $hour_id = $request['hour_id'];
    $params = $request->get_json_params();
    mmm_update_store_hour($hour_id, $params['day_of_week'], $params['open_time'], $params['close_time']);
    return new WP_REST_Response(null, 200);
}

function mmm_delete_store_hour_endpoint(WP_REST_Request $request) {
    $hour_id = $request['hour_id'];
    mmm_delete_store_hour($hour_id);
    return new WP_REST_Response(null, 204);
}

### Block 6: Handling Form Submissions

// Add the actions for handling form submissions:

// Handle CSV download action
add_action('admin_init', 'mmm_handle_csv_download');

function mmm_handle_csv_download() {
    if (isset($_POST['download_csv']) && check_admin_referer('download_csv_action', 'download_csv_nonce')) {
        if (isset($_POST['csv_content'])) {
            $csv_content = sanitize_textarea_field($_POST['csv_content']);
            $csv_filename = 'stores.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=' . $csv_filename);
            header('Pragma: no-cache');
            header('Expires: 0');

            echo $csv_content;
            exit;
        }
    }
}


// Handle generate mock stores action
add_action('admin_post_generate_mock_stores', 'mmm_handle_generate_mock_stores');
function mmm_handle_generate_mock_stores() {
    check_admin_referer('generate_mock_stores', 'generate_mock_stores_nonce');
    mmm_generate_mock_stores(10); // Adjust the number of mock stores as needed
    wp_redirect(admin_url('admin.php?page=store-management'));
    exit;
}

// Handle remove all stores action
add_action('admin_post_remove_all_stores', 'mmm_handle_remove_all_stores');
function mmm_handle_remove_all_stores() {
    check_admin_referer('remove_all_stores', 'remove_all_stores_nonce');
    mmm_remove_all_stores();
    wp_redirect(admin_url('admin.php?page=store-management'));
    // notify user that all stores have been removed
    echo '<div class="notice notice-success is-dismissible"><p>' . __('All stores have been removed.', 'textdomain') . '</p></div>';
    exit;
}

// Handle delete store action
add_action('admin_post_delete_store', 'mmm_delete_store_action');
function mmm_delete_store_action() {
    if (isset($_GET['store_id']) && check_admin_referer('delete_store')) {
        mmm_delete_store(intval($_GET['store_id']));
        wp_redirect(admin_url('admin.php?page=all-stores'));
        exit;
    }
}

// Handle delete review action
add_action('admin_post_delete_review', 'mmm_delete_review_action');
function mmm_delete_review_action() {
    global $wpdb;
    if (isset($_GET['review_id']) && check_admin_referer('delete_review')) {
        $wpdb->delete("{$wpdb->prefix}store_reviews", array('review_id' => intval($_GET['review_id'])));
        wp_redirect(admin_url('admin.php?page=store-reviews'));
        exit;
    }
}

// Handle delete store hour action
add_action('admin_post_delete_store_hour', 'mmm_delete_store_hour_action');
function mmm_delete_store_hour_action() {
    if (isset($_GET['id']) && check_admin_referer('delete_store_hour')) {
        mmm_delete_store_hour(intval($_GET['id']));
        wp_redirect(admin_url('admin.php?page=store-hours'));
        exit;
    }
}

### Block 7: Register Edit Store and Store Hour Pages

#### Register Edit Pages

add_action('admin_menu', 'mmm_register_edit_pages');
function mmm_register_edit_pages() {
    add_submenu_page(
        null,
        __('Edit Store', 'textdomain'),
        __('Edit Store', 'textdomain'),
        'manage_options',
        'edit-store',
        'mmm_edit_store_page'
    );

    add_submenu_page(
        null,
        __('Edit Store Hour', 'textdomain'),
        __('Edit Store Hour', 'textdomain'),
        'manage_options',
        'edit-store-hour',
        'mmm_edit_store_hour_page'
    );
}

#### Edit Store Page

function mmm_edit_store_page() {
    if (isset($_POST['mmm_update_store'])) {
        check_admin_referer('update_store_action', 'update_store_nonce');
        mmm_update_store(intval($_POST['store_id']), $_POST['store_name'], $_POST['store_url'], $_POST['email'], $_POST['phone'], $_POST['token'], $_POST['secret'], $_POST['logo_url'], $_POST['background_url']);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store updated successfully.', 'textdomain') . '</p></div>';
    }

    $store_id = intval($_GET['id']);
    $store = mmm_get_store($store_id);

    echo '<h1>' . __('Edit Store', 'textdomain') . '</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('update_store_action', 'update_store_nonce');
    echo '<input type="hidden" name="store_id" value="' . esc_attr($store->store_id) . '" />';
    echo '<table class="form-table">';
    echo '<tr><th>' . __('Store Name', 'textdomain') . '</th><td><input type="text" name="store_name" value="' . esc_attr($store->store_name) . '" required /></td></tr>';
    echo '<tr><th>' . __('Store URL', 'textdomain') . '</th><td><input type="url" name="store_url" value="' . esc_attr($store->store_url) . '" required /></td></tr>';
    echo '<tr><th>' . __('Email', 'textdomain') . '</th><td><input type="email" name="email" value="' . esc_attr($store->email) . '" required /></td></tr>';
    echo '<tr><th>' . __('Phone', 'textdomain') . '</th><td><input type="text" name="phone" value="' . esc_attr($store->phone) . '" required /></td></tr>';
    echo '<tr><th>' . __('Token', 'textdomain') . '</th><td><input type="text" name="token" value="' . esc_attr($store->token) . '" required /></td></tr>';
    echo '<tr><th>' . __('Secret', 'textdomain') . '</th><td><input type="text" name="secret" value="' . esc_attr($store->secret) . '" required /></td></tr>';
    echo '<tr><th>' . __('Logo URL', 'textdomain') . '</th><td><input type="url" name="logo_url" value="' . esc_attr($store->logo_url) . '" /></td></tr>';
    echo '<tr><th>' . __('Background URL', 'textdomain') . '</th><td><input type="url" name="background_url" value="' . esc_attr($store->background_url) . '" /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="mmm_update_store" value="' . __('Update Store', 'textdomain') . '" class="button button-primary" />';
    echo '</form>';
}

#### Edit Store Hour Page

function mmm_edit_store_hour_page() {
    if (isset($_POST['mmm_update_store_hour'])) {
        check_admin_referer('update_store_hour_action', 'update_store_hour_nonce');
        mmm_update_store_hour(intval($_POST['hour_id']), intval($_POST['day_of_week']), $_POST['open_time'], $_POST['close_time']);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store hour updated successfully.', 'textdomain') . '</p></div>';
    }

    $hour_id = intval($_GET['id']);
    $store_hour = mmm_get_store_hour($hour_id);

    echo '<h1>' . __('Edit Store Hour', 'textdomain') . '</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('update_store_hour_action', 'update_store_hour_nonce');
    echo '<input type="hidden" name="hour_id" value="' . esc_attr($store_hour->id) . '" />';
    echo '<table class="form-table">';
    echo '<tr><th>' . __('Day of Week', 'textdomain') . '</th><td><input type="number" name="day_of_week" value="' . esc_attr($store_hour->day_of_week) . '" min="1" max="7" required /></td></tr>';
    echo '<tr><th>' . __('Open Time', 'textdomain') . '</th><td><input type="time" name="open_time" value="' . esc_attr($store_hour->open_time) . '" required /></td></tr>';
    echo '<tr><th>' . __('Close Time', 'textdomain') . '</th><td><input type="time" name="close_time" value="' . esc_attr($store_hour->close_time) . '" required /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="mmm_update_store_hour" value="' . __('Update Store Hour', 'textdomain') . '" class="button button-primary" />';
    echo '</form>';
}

### Block 8: Additional Helper Functions

#### Get Store Hour by ID

function mmm_get_store_hour($hour_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}store_hours WHERE id = %d", $hour_id));
}

### Block 9: Adding Store Name Filter for Products

// Add store name filter to the products list
add_action('restrict_manage_posts', 'mmm_add_store_name_filter');
function mmm_add_store_name_filter() {
    global $typenow, $wpdb;

    if ($typenow != 'product') {
        return;
    }

    $store_name = isset($_GET['store_name']) ? sanitize_text_field($_GET['store_name']) : '';

    // Get distinct store names
    $stores = $wpdb->get_results("SELECT DISTINCT store_name FROM {$wpdb->prefix}stores ORDER BY store_name ASC");

    echo '<input type="text" name="store_name" id="store_name_filter" placeholder="' . __('Store Name', 'textdomain') . '" value="' . esc_attr($store_name) . '" />';
    echo '<select name="store_id" id="store_id_filter">';
    echo '<option value="">' . __('Select a Store', 'textdomain') . '</option>';
    foreach ($stores as $store) {
        $selected = ($store_name == $store->store_name) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr($store->store_id) . '"' . $selected . '>' . esc_html($store->store_name) . '</option>';
    }
    echo '</select>';
}

// Adjust the Query to Filter Products by Store Name
add_action('pre_get_posts', 'mmm_filter_products_by_store_name');
function mmm_filter_products_by_store_name($query) {
    global $pagenow, $wpdb;

    if ($pagenow != 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] != 'product') {
        return;
    }

    if (!empty($_GET['store_name'])) {
        $store_name = sanitize_text_field($_GET['store_name']);
        $store_ids = $wpdb->get_col($wpdb->prepare("SELECT store_id FROM {$wpdb->prefix}stores WHERE store_name = %s", $store_name));

        if (!empty($store_ids)) {
            $product_ids = $wpdb->get_col("SELECT product_id FROM {$wpdb->prefix}product_store WHERE store_id IN (" . implode(',', array_map('intval', $store_ids)) . ")");
            $query->query_vars['post__in'] = empty($product_ids) ? array(0) : $product_ids;
        }
    }
}

add_action('admin_footer', 'mmm_inline_store_name_filter_script');
function mmm_inline_store_name_filter_script() {
    global $typenow;

    if ($typenow != 'product') {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#store_name_filter').on('keyup', function() {
                var input = $(this).val().toLowerCase();
                $('#store_id_filter option').each(function() {
                    var text = $(this).text().toLowerCase();
                    if (text.indexOf(input) !== -1 || input === '') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            $('#store_id_filter').on('change', function() {
                $('#store_name_filter').val($(this).find('option:selected').text());
            });
        });
    </script>
    <?php
}

add_filter('parse_query', 'mmm_save_store_name_filter_in_query_string');
function mmm_save_store_name_filter_in_query_string($query) {
    global $pagenow, $typenow;

    if ($pagenow != 'edit.php' || $typenow != 'product') {
        return;
    }

    if (!empty($_GET['store_name'])) {
        $query->set('meta_query', array(
            array(
                'key'     => '_store_id',
                'value'   => sanitize_text_field($_GET['store_name']),
                'compare' => 'LIKE',
            ),
        ));
    }
}

add_filter('manage_edit-product_columns', 'mmm_add_store_name_column');
function mmm_add_store_name_column($columns) {
    $columns['store_name'] = __('Store Name', 'textdomain');
    return $columns;
}

add_action('manage_product_posts_custom_column', 'mmm_display_store_name_column', 10, 2);
function mmm_display_store_name_column($column, $post_id) {
    if ($column == 'store_name') {
        $store_id = get_post_meta($post_id, '_store_id', true);
        if ($store_id) {
            global $wpdb;
            $store_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}stores WHERE store_id = %d", $store_id));
            echo esc_html($store_name);
        } else {
            echo __('No Store', 'textdomain');
        }
    }
}

### Block 10: Adding Store Name Filter for Reviews

// Add store name filter to the reviews list

add_action('restrict_manage_posts', 'mmm_add_store_name_filter_for_reviews');

function mmm_add_store_name_filter_for_reviews() {
    global $typenow, $wpdb;

    if ($typenow != 'product') {
        return;
    }

    $store_name = isset($_GET['store_name']) ? sanitize_text_field($_GET['store_name']) : '';

    // Get distinct store names
    $stores = $wpdb->get_results("SELECT DISTINCT store_name FROM {$wpdb->prefix}stores ORDER BY store_name ASC");

    echo '<input type="text" name="store_name" id="store_name_filter" placeholder="' . __('Store Name', 'textdomain') . '" value="' . esc_attr($store_name) . '" />';
    echo '<select name="store_id" id="store_id_filter">';
    echo '<option value="">' . __('Select a Store', 'textdomain') . '</option>';
    foreach ($stores as $store) {
        $selected = ($store_name == $store->store_name) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr($store->store_id) . '"' . $selected . '>' . esc_html($store->store_name) . '</option>';
    }
    echo '</select>';
}

// Adjust the Query to Filter Reviews by Store Name

add_action('pre_get_posts', 'mmm_filter_reviews_by_store_name');

function mmm_filter_reviews_by_store_name($query) {
    global $pagenow, $wpdb;

    if ($pagenow != 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] != 'product') {
        return;
    }

    if (!empty($_GET['store_name'])) {
        $store_name = sanitize_text_field($_GET['store_name']);
        $store_ids = $wpdb->get_col($wpdb->prepare("SELECT store_id FROM {$wpdb->prefix}stores WHERE store_name = %s", $store_name));

        if (!empty($store_ids)) {
            $product_ids = $wpdb->get_col("SELECT product_id FROM {$wpdb->prefix}product_store WHERE store_id IN (" . implode(',', array_map('intval', $store_ids)) . ")");
            $query->query_vars['post__in'] = empty($product_ids) ? array(0) : $product_ids;
        }
    }
}

add_action('admin_footer', 'mmm_inline_store_name_filter_script_for_reviews');

function mmm_inline_store_name_filter_script_for_reviews() {
    global $typenow;

    if ($typenow != 'product') {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#store_name_filter').on('keyup', function() {
                var input = $(this).val().toLowerCase();
                $('#store_id_filter option').each(function() {
                    var text = $(this).text().toLowerCase();
                    if (text.indexOf(input) !== -1 || input === '') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            $('#store_id_filter').on('change', function() {
                $('#store_name_filter').val($(this).find('option:selected').text());
            });
        });
    </script>
    <?php
}

add_filter('parse_query', 'mmm_save_store_name_filter_in_query_string_for_reviews');

function mmm_save_store_name_filter_in_query_string_for_reviews($query) {
    global $pagenow, $typenow;

    if ($pagenow != 'edit.php' || $typenow != 'product') {
        return;
    }

    if (!empty($_GET['store_name'])) {
        $query->set('meta_query', array(
            array(
                'key'     => '_store_id',
                'value'   => sanitize_text_field($_GET['store_name']),
                'compare' => 'LIKE',
            ),
        ));
    }
}

add_filter('manage_edit-product_columns', 'mmm_add_store_name_column_for_reviews');

function mmm_add_store_name_column_for_reviews($columns) {
    $columns['store_name'] = __('Store Name', 'textdomain');
    return $columns;
}

add_action('manage_product_posts_custom_column', 'mmm_display_store_name_column_for_reviews', 10, 2);

function mmm_display_store_name_column_for_reviews($column, $post_id) {
    if ($column == 'store_name') {
        $store_id = get_post_meta($post_id, '_store_id', true);
        if ($store_id) {
            global $wpdb;
            $store_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}stores WHERE store_id = %d", $store_id));
            echo esc_html($store_name);
        } else {
            echo __('No Store', 'textdomain');
        }
    }
}

### Block 11: Adding Store Name Filter for Store Hours

// Add store name filter to the store hours list

add_action('restrict_manage_posts', 'mmm_add_store_name_filter_for_store_hours');

function mmm_add_store_name_filter_for_store_hours() {
    global $typenow, $wpdb;

    if ($typenow != 'product') {
        return;
    }

    $store_name = isset($_GET['store_name']) ? sanitize_text_field($_GET['store_name']) : '';

    // Get distinct store names
    $stores = $wpdb->get_results("SELECT DISTINCT store_name FROM {$wpdb->prefix}stores ORDER BY store_name ASC");

    echo '<input type="text" name="store_name" id="store_name_filter" placeholder="' . __('Store Name', 'textdomain') . '" value="' . esc_attr($store_name) . '" />';
    echo '<select name="store_id" id="store_id_filter">';
    echo '<option value="">' . __('Select a Store', 'textdomain') . '</option>';
    foreach ($stores as $store) {
        $selected = ($store_name == $store->store_name) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr($store->store_id) . '"' . $selected . '>' . esc_html($store->store_name) . '</option>';
    }
    echo '</select>';
}

// Adjust the Query to Filter Store Hours by Store Name

add_action('pre_get_posts', 'mmm_filter_store_hours_by_store_name');

function mmm_filter_store_hours_by_store_name($query) {
    global $pagenow, $wpdb;

    if ($pagenow != 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] != 'product') {
        return;
    }

    if (!empty($_GET['store_name'])) {
        $store_name = sanitize_text_field($_GET['store_name']);
        $store_ids = $wpdb->get_col($wpdb->prepare("SELECT store_id FROM {$wpdb->prefix}stores WHERE store_name = %s", $store_name));

        if (!empty($store_ids)) {
            $product_ids = $wpdb->get_col("SELECT product_id FROM {$wpdb->prefix}product_store WHERE store_id IN (" . implode(',', array_map('intval', $store_ids)) . ")");
            $query->query_vars['post__in'] = empty($product_ids) ? array(0) : $product_ids;
        }
    }
}

add_action('admin_footer', 'mmm_inline_store_name_filter_script_for_store_hours');

function mmm_inline_store_name_filter_script_for_store_hours() {
    global $typenow;

    if ($typenow != 'product') {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#store_name_filter').on('keyup', function() {
                var input = $(this).val().toLowerCase();
                $('#store_id_filter option').each(function() {
                    var text = $(this).text().toLowerCase();
                    if (text.indexOf(input) !== -1 || input === '') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            $('#store_id_filter').on('change', function() {
                $('#store_name_filter').val($(this).find('option:selected').text());
            });
        });
    </script>
    <?php
}

add_filter('parse_query', 'mmm_save_store_name_filter_in_query_string_for_store_hours');

function mmm_save_store_name_filter_in_query_string_for_store_hours($query) {
    global $pagenow, $typenow;

    if ($pagenow != 'edit.php' || $typenow != 'product') {
        return;
    }

    if (!empty($_GET['store_name'])) {
        $query->set('meta_query', array(
            array(
                'key'     => '_store_id',
                'value'   => sanitize_text_field($_GET['store_name']),
                'compare' => 'LIKE',
            ),
        ));
    }
}

add_filter('manage_edit-product_columns', 'mmm_add_store_name_column_for_store_hours');

function mmm_add_store_name_column_for_store_hours($columns) {
    $columns['store_name'] = __('Store Name', 'textdomain');
    return $columns;
}

add_action('manage_product_posts_custom_column', 'mmm_display_store_name_column_for_store_hours', 10, 2);

function mmm_display_store_name_column_for_store_hours($column, $post_id) {
    if ($column == 'store_name') {
        $store_id = get_post_meta($post_id, '_store_id', true);
        if ($store_id) {
            global $wpdb;
            $store_name = $wpdb->get_var($wpdb->prepare("SELECT store_name FROM {$wpdb->prefix}stores WHERE store_id = %d", $store_id));
            echo esc_html($store_name);
        } else {
            echo __('No Store', 'textdomain');
        }
    }
}
