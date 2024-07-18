<?php
/*
Plugin Name: WooCommerce MMM
Description: Adds multi-merchant functionality to WooCommerce, allowing products to be managed and sold by multiple stores with custom attributes for each store.
Version: 1.4
Authors: Yilmaz Mustafa, Sergey Ryskin, ChatGPT
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Block 1: Plugin Header and Activation Hook

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


// Block 2: Product Data Tab and Fields

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
    $store_id = sanitize_text_field($_POST['_store_id']);

    if (!empty($store_id)) {
        update_post_meta($post_id, '_store_id', esc_attr($store_id));
    }
}

// Display store information on the frontend
add_action('woocommerce_single_product_summary', 'mmm_display_store_info', 20);
function mmm_display_store_info() {
    global $post;

    $store_id = get_post_meta($post->ID, '_store_id', true);

    if ($store_id) {
        echo '<p><strong>Store ID: </strong>' . esc_html($store_id) . '</p>';
    }
}


// Block 3: Admin Menu and Pages

// Add a menu item for managing stores
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
        __('MMM API Usage', 'textdomain'),
        __('MMM API Usage', 'textdomain'),
        'manage_options',
        'mmm-api-usage',
        'mmm_api_usage_page'
    );
}

// UPDATED: Display the main store management page
function mmm_store_management_page() {
    echo '<h1>' . __('Store Management', 'textdomain') . '</h1>';
    echo '<p>' . __('Welcome to the Store Management section. Use the submenus to view all stores, add new stores, or manage reviews.', 'textdomain') . '</p>';

    // Add buttons for export and import
    echo '<h2>' . __('Export/Import Stores', 'textdomain') . '</h2>';
    echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=export_stores'), 'export_stores') . '" class="button button-primary">' . __('Export Stores', 'textdomain') . '</a>';
    echo '<form method="post" action="' . admin_url('admin-post.php?action=import_stores') . '" enctype="multipart/form-data" style="display:inline-block; margin-left:10px;">';
    wp_nonce_field('import_stores', 'import_stores_nonce');
    echo '<input type="file" name="import_file" accept=".csv" required />';
    echo '<input type="submit" class="button button-primary" value="' . __('Import Stores', 'textdomain') . '" />';
    echo '</form>';
}


// Display all stores
function mmm_all_stores_page() {
    global $wpdb;

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 10;
    $offset = ($paged - 1) * $per_page;

    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $where = '';
    if ($search) {
        $where = $wpdb->prepare("WHERE store_name LIKE %s OR email LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
    }

    $total_stores = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}stores $where");
    $stores = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}stores $where LIMIT $offset, $per_page");

    $total_pages = ceil($total_stores / $per_page);

    echo '<div class="wrap">';
    echo '<h1>' . __('All Stores', 'textdomain') . '</h1>';
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

// Display the add new store page
function mmm_add_new_store_page() {
    if (isset($_POST['mmm_add_store'])) {
        mmm_add_store($_POST['store_name'], $_POST['store_url'], $_POST['email'], $_POST['phone'], $_POST['token'], $_POST['secret'], $_POST['logo_url'], $_POST['background_url']);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store added successfully.', 'textdomain') . '</p></div>';
    }

    echo '<h1>' . __('Add New Store', 'textdomain') . '</h1>';
    echo '<form method="post" action="">';
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


// Block 4: Store Reviews and Edit Store Page

// Display store reviews with filters
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

// Display the store hours page
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
            echo '<form method="post" action="" style="display:inline;">';
            echo '<input type="hidden" name="hour_id" value="' . esc_attr($hour->id) . '" />';
            echo '<input type="submit" name="mmm_delete_store_hour" value="' . __('Delete', 'textdomain') . '" class="button button-secondary" />';
            echo '</form>';
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

// New API usage page rendering function
function mmm_api_usage_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('MMM API Usage', 'textdomain'); ?></h1>
        <p><?php _e('Here you can see the usage of the WooCommerce MMM API.', 'textdomain'); ?></p>
        <h2><?php _e('Available Endpoints', 'textdomain'); ?></h2>
        <ul>
            <li><strong><?php _e('Get All Stores:', 'textdomain'); ?></strong> GET /wp-json/mmm/v1/stores</li>
            <li><strong><?php _e('Add Store:', 'textdomain'); ?></strong> POST /wp-json/mmm/v1/store</li>
            <li><strong><?php _e('Get Store by ID:', 'textdomain'); ?></strong> GET /wp-json/mmm/v1/store/(?P<id>\d+)</li>
            <li><strong><?php _e('Update Store:', 'textdomain'); ?></strong> POST /wp-json/mmm/v1/store/(?P<id>\d+)</li>
            <li><strong><?php _e('Delete Store:', 'textdomain'); ?></strong> DELETE /wp-json/mmm/v1/store/(?P<id>\d+)</li>
            <li><strong><?php _e('Export Stores:', 'textdomain'); ?></strong> GET /wp-json/mmm/v1/stores/export</li>
            <li><strong><?php _e('Import Stores:', 'textdomain'); ?></strong> POST /wp-json/mmm/v1/stores/import</li>
            <li><strong><?php _e('Remove All Stores:', 'textdomain'); ?></strong> POST /wp-json/mmm/v1/stores/remove_all</li>
            <li><strong><?php _e('Generate Mock Stores:', 'textdomain'); ?></strong> POST /wp-json/mmm/v1/stores/generate_mock</li>
            <li><strong><?php _e('Get Store Hours:', 'textdomain'); ?></strong> GET /wp-json/mmm/v1/store/(?P<id>\d+)/hours</li>
            <li><strong><?php _e('Add Store Hour:', 'textdomain'); ?></strong> POST /wp-json/mmm/v1/store/(?P<id>\d+)/hour</li>
            <li><strong><?php _e('Update Store Hour:', 'textdomain'); ?></strong> POST /wp-json/mmm/v1/store/hour/(?P<hour_id>\d+)</li>
            <li><strong><?php _e('Delete Store Hour:', 'textdomain'); ?></strong> DELETE /wp-json/mmm/v1/store/hour/(?P<hour_id>\d+)</li>
        </ul>
    </div>
    <?php
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

// Display the edit store page
add_action('admin_menu', 'mmm_register_edit_store_page');
function mmm_register_edit_store_page() {
    add_submenu_page(
        null,
        __('Edit Store', 'textdomain'),
        __('Edit Store', 'textdomain'),
        'manage_options',
        'edit-store',
        'mmm_edit_store_page'
    );
}

function mmm_edit_store_page() {
    if (isset($_POST['mmm_update_store'])) {
        mmm_update_store(intval($_POST['store_id']), $_POST['store_name'], $_POST['store_url'], $_POST['email'], $_POST['phone'], $_POST['token'], $_POST['secret'], $_POST['logo_url'], $_POST['background_url']);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store updated successfully.', 'textdomain') . '</p></div>';
    }

    if (isset($_POST['mmm_add_store_hour'])) {
        mmm_add_store_hour(intval($_POST['store_id']), intval($_POST['day_of_week']), $_POST['open_time'], $_POST['close_time']);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store hour added successfully.', 'textdomain') . '</p></div>';
    }

    if (isset($_POST['mmm_update_store_hour'])) {
        mmm_update_store_hour(intval($_POST['hour_id']), intval($_POST['day_of_week']), $_POST['open_time'], $_POST['close_time']);
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store hour updated successfully.', 'textdomain') . '</p></div>';
    }

    if (isset($_POST['mmm_delete_store_hour'])) {
        mmm_delete_store_hour(intval($_POST['hour_id']));
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Store hour deleted successfully.', 'textdomain') . '</p></div>';
    }

    $store_id = intval($_GET['id']);
    $store = mmm_get_store($store_id);
    $store_hours = mmm_get_store_hours($store_id);

    echo '<h1>' . __('Edit Store', 'textdomain') . '</h1>';
    echo '<form method="post" action="">';
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

    echo '<h2>' . __('Store Opening Hours', 'textdomain') . '</h2>';
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="store_id" value="' . esc_attr($store->store_id) . '" />';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th>' . __('Day of Week', 'textdomain') . '</th>';
    echo '<th>' . __('Open Time', 'textdomain') . '</th>';
    echo '<th>' . __('Close Time', 'textdomain') . '</th>';
    echo '<th>' . __('Actions', 'textdomain') . '</th>';
    echo '</tr>';

    foreach ($store_hours as $hour) {
        echo '<tr>';
        echo '<td>' . esc_html($hour->day_of_week) . '</td>';
        echo '<td>' . esc_html($hour->open_time) . '</td>';
        echo '<td>' . esc_html($hour->close_time) . '</td>';
        echo '<td>';
        echo '<form method="post" action="" style="display:inline;">';
        echo '<input type="hidden" name="hour_id" value="' . esc_attr($hour->id) . '" />';
        echo '<input type="submit" name="mmm_delete_store_hour" value="' . __('Delete', 'textdomain') . '" class="button button-secondary" />';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<td><input type="number" name="day_of_week" value="" min="1" max="7" required /></td>';
    echo '<td><input type="time" name="open_time" value="" required /></td>';
    echo '<td><input type="time" name="close_time" value="" required /></td>';
    echo '<td><input type="submit" name="mmm_add_store_hour" value="' . __('Add Hour', 'textdomain') . '" class="button button-primary" /></td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
}


// Block 5: Helper Functions and API Endpoints

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
    $wpdb->query("DELETE FROM {$wpdb->prefix}stores");
}

function mmm_generate_mock_stores($number_of_stores) {
    global $wpdb;

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
    if (!current_user_can('manage_options')) {
        return;
    }

    $stores = mmm_get_stores();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stores.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('store_id', 'store_name', 'store_url', 'email', 'phone', 'logo_url', 'background_url', 'active', 'validated', 'created', 'updated'));

    foreach ($stores as $store) {
        fputcsv($output, array(
            $store->store_id,
            $store->store_name,
            $store->store_url,
            $store->email,
            $store->phone,
            $store->logo_url,
            $store->background_url,
            $store->active,
            $store->validated,
            $store->created,
            $store->updated
        ));
    }

    fclose($output);
    exit;
}

function mmm_export_stores_endpoint(WP_REST_Request $request) {
    ob_start();
    mmm_export_stores();
    $csv = ob_get_clean();

    return new WP_REST_Response(array('csv' => $csv), 200);
}

function mmm_import_stores($file) {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        fgetcsv($handle); // Skip the header row

        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            mmm_add_store($data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8]);
        }

        fclose($handle);
    }
}

function mmm_import_stores_endpoint(WP_REST_Request $request) {
    $file = $request->get_file_params()['file'];

    mmm_import_stores($file);

    return new WP_REST_Response(null, 200);
}

function mmm_import_products_and_stores($file) {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
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

function mmm_import_products_and_stores_endpoint(WP_REST_Request $request) {
    $file = $request->get_file_params()['file'];

    mmm_import_products_and_stores($file);

    return new WP_REST_Response(null, 200);
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

// API Endpoints
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

?>
