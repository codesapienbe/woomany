<?php
/**
 * Plugin Name: WooCommerce SSS
 * Description: WooCommerce Shared Secret Server allows users to store, share, and manage their secrets. Secrets are encrypted and can only be viewed after entering a master password (PIN).
 * Version: 1.0.1
 * Author: Yilmaz Mustafa, Sergey Ryskin, ChatGPT.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once(ABSPATH . 'wp-includes/class-phpass.php');

// Create the necessary database tables
function woocommerce_sss_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name_secrets = $wpdb->prefix . 'sss_secrets';
    $table_name_shared = $wpdb->prefix . 'sss_shared';
    $table_name_master_password = $wpdb->prefix . 'sss_master_password';

    $sql = "CREATE TABLE $table_name_secrets (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        secret_title VARCHAR(255) NOT NULL,
        secret_content LONGTEXT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;

    CREATE TABLE $table_name_shared (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        secret_id BIGINT(20) UNSIGNED NOT NULL,
        shared_with BIGINT(20) UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (secret_id) REFERENCES $table_name_secrets(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_with) REFERENCES $wpdb->users(ID) ON DELETE CASCADE
    ) $charset_collate;

    CREATE TABLE $table_name_master_password (
        user_id BIGINT(20) UNSIGNED NOT NULL,
        master_password VARCHAR(255) NOT NULL,
        PRIMARY KEY (user_id),
        FOREIGN KEY (user_id) REFERENCES $wpdb->users(ID) ON DELETE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'woocommerce_sss_create_tables');

// Add main menu and submenus
function woocommerce_sss_add_menu_page() {
    add_menu_page(
        'Secrets Management',
        'Secrets',
        'manage_options',
        'secrets-management',
        'woocommerce_sss_all_secrets_page',
        'dashicons-lock',
        6
    );

    add_submenu_page(
        'secrets-management',
        'All Secrets',
        'All Secrets',
        'manage_options',
        'secrets-management',
        'woocommerce_sss_all_secrets_page'
    );

    add_submenu_page(
        'secrets-management',
        'Add New Secret',
        'Add New Secret',
        'manage_options',
        'add-new-secret',
        'woocommerce_sss_add_new_secret_page'
    );

    add_submenu_page(
        'secrets-management',
        'Set Master Password',
        'Set Master Password',
        'manage_options',
        'set-master-password',
        'woocommerce_sss_set_master_password_page'
    );
}
add_action('admin_menu', 'woocommerce_sss_add_menu_page');

// Master Password Page
function woocommerce_sss_set_master_password_page() {
    if (!is_user_logged_in()) {
        echo '<p>Please <a href="' . wp_login_url() . '">log in</a> to set your master password.</p>';
        return;
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $table_name_master_password = $wpdb->prefix . 'sss_master_password';

    if (isset($_POST['set_master_password'])) {
        $new_master_password = sanitize_text_field($_POST['new_master_password']);
        if (strlen($new_master_password) >= 6 && strlen($new_master_password) <= 8) {
            $encrypted_master_password = wp_hash_password($new_master_password);
            $existing_password = $wpdb->get_var($wpdb->prepare("SELECT master_password FROM $table_name_master_password WHERE user_id = %d", $current_user_id));

            if ($existing_password) {
                $wpdb->update(
                    $table_name_master_password,
                    array(
                        'master_password' => $encrypted_master_password,
                    ),
                    array('user_id' => $current_user_id)
                );
                echo '<p>Master password updated successfully.</p>';
            } else {
                $wpdb->insert(
                    $table_name_master_password,
                    array(
                        'user_id' => $current_user_id,
                        'master_password' => $encrypted_master_password,
                    )
                );
                echo '<p>Master password set successfully.</p>';
            }
        } else {
            echo '<p>Master password must be 6-8 characters long.</p>';
        }
    }

    echo '<h2>Set Master Password</h2>';
    echo '<form method="post">';
    echo '<label for="new_master_password">Master Password (6-8 characters):</label>';
    echo '<input type="password" id="new_master_password" name="new_master_password" maxlength="8"><br>';
    echo '<input type="submit" name="set_master_password" value="Set Master Password">';
    echo '</form>';
}

// All Secrets Page
function woocommerce_sss_all_secrets_page() {
    if (!is_user_logged_in()) {
        echo '<p>Please <a href="' . wp_login_url() . '">log in</a> to manage your secrets.</p>';
        return;
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $table_name_secrets = $wpdb->prefix . 'sss_secrets';
    $table_name_shared = $wpdb->prefix . 'sss_shared';
    $table_name_master_password = $wpdb->prefix . 'sss_master_password';

    // Check if master password is set
    $master_password = $wpdb->get_var($wpdb->prepare("SELECT master_password FROM $table_name_master_password WHERE user_id = %d", $current_user_id));

    // Check if master password is provided for the session
    if (!isset($_SESSION['master_password_valid']) || !$_SESSION['master_password_valid']) {
        if (isset($_POST['master_password'])) {
            $entered_master_password = sanitize_text_field($_POST['master_password']);
            $hasher = new PasswordHash(8, true);
            if ($hasher->CheckPassword($entered_master_password, $master_password)) {
                $_SESSION['master_password_valid'] = true;
            } else {
                echo '<p>Incorrect master password.</p>';
            }
        }

        if (!isset($_SESSION['master_password_valid']) || !$_SESSION['master_password_valid']) {
            echo '<h2>Enter Master Password</h2>';
            echo '<form method="post">';
            echo '<label for="master_password">Master Password:</label>';
            echo '<input type="password" id="master_password" name="master_password" maxlength="8"><br>';
            echo '<input type="submit" value="Submit">';
            echo '</form>';
            return;
        }
    }

    if (isset($_POST['action'])) {
        $action = sanitize_text_field($_POST['action']);
        $secret_id = isset($_POST['secret_id']) ? intval($_POST['secret_id']) : 0;

        switch ($action) {
            case 'create':
                $title = sanitize_text_field($_POST['title']);
                $secret_content = sanitize_text_field($_POST['secret_content']);
                $shared_with = isset($_POST['shared_with']) ? array_map('sanitize_text_field', explode(',', $_POST['shared_with'])) : array();

                $encrypted_secret = wp_hash_password($secret_content);
                $wpdb->insert(
                    $table_name_secrets,
                    array(
                        'user_id' => $current_user_id,
                        'secret_title' => $title,
                        'secret_content' => $encrypted_secret,
                    )
                );

                $new_secret_id = $wpdb->insert_id;
                foreach ($shared_with as $user_login) {
                    $user = get_user_by('login', $user_login);
                    if ($user) {
                        $wpdb->insert(
                            $table_name_shared,
                            array(
                                'secret_id' => $new_secret_id,
                                'shared_with' => $user->ID,
                            )
                        );
                    }
                }
                break;

            case 'update':
                if ($secret_id) {
                    $title = sanitize_text_field($_POST['title']);
                    $secret_content = sanitize_text_field($_POST['secret_content']);
                    $shared_with = isset($_POST['shared_with']) ? array_map('sanitize_text_field', explode(',', $_POST['shared_with'])) : array();

                    $encrypted_secret = wp_hash_password($secret_content);
                    $wpdb->update(
                        $table_name_secrets,
                        array(
                            'secret_title' => $title,
                            'secret_content' => $encrypted_secret,
                        ),
                        array('id' => $secret_id)
                    );

                    $wpdb->delete($table_name_shared, array('secret_id' => $secret_id));
                    foreach ($shared_with as $user_login) {
                        $user = get_user_by('login', $user_login);
                        if ($user) {
                            $wpdb->insert(
                                $table_name_shared,
                                array(
                                    'secret_id' => $secret_id,
                                    'shared_with' => $user->ID,
                                )
                            );
                        }
                    }
                }
                break;

            case 'delete':
                if ($secret_id) {
                    $wpdb->delete($table_name_secrets, array('id' => $secret_id));
                }
                break;

            case 'view':
                if ($secret_id) {
                    $secret_content = $wpdb->get_var($wpdb->prepare("SELECT secret_content FROM $table_name_secrets WHERE id = %d", $secret_id));
                    $hasher = new PasswordHash(8, true);

                    if ($hasher->CheckPassword($master_password, $secret_content)) {
                        echo '<p>Secret: ' . esc_html($secret_content) . '</p>';
                    } else {
                        echo '<p>Incorrect master password.</p>';
                    }
                }
                break;
        }
    }

    $user_secrets = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name_secrets WHERE user_id = %d", $current_user_id));

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">All Secrets</h1>';
    echo '<a href="admin.php?page=add-new-secret" class="page-title-action">Add New</a>';
    echo '<hr class="wp-header-end">';

    if ($user_secrets) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Title</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($user_secrets as $secret) {
            $secret_id = $secret->id;
            $shared_with = $wpdb->get_col($wpdb->prepare("SELECT user_login FROM $wpdb->users u JOIN $table_name_shared s ON u.ID = s.shared_with WHERE s.secret_id = %d", $secret_id));
            echo '<tr>';
            echo '<td>' . esc_html($secret->secret_title) . '</td>';
            echo '<td>';
            echo '<a href="admin.php?page=add-new-secret&secret_id=' . esc_attr($secret_id) . '" class="button">Edit</a>';
            echo ' <form method="post" style="display:inline;">';
            echo '<input type="hidden" name="action" value="delete">';
            echo '<input type="hidden" name="secret_id" value="' . esc_attr($secret_id) . '">';
            echo ' <input type="submit" value="Delete" class="button button-secondary">';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>You have no secrets.</p>';
    }
    echo '</div>';
}

// Add New Secret Page
function woocommerce_sss_add_new_secret_page() {
    if (!is_user_logged_in()) {
        echo '<p>Please <a href="' . wp_login_url() . '">log in</a> to add a new secret.</p>';
        return;
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $table_name_secrets = $wpdb->prefix . 'sss_secrets';
    $table_name_shared = $wpdb->prefix . 'sss_shared';

    $secret_id = isset($_GET['secret_id']) ? intval($_GET['secret_id']) : 0;
    $secret = $secret_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_secrets WHERE id = %d", $secret_id)) : null;

    if ($secret && $secret->user_id != $current_user_id) {
        echo '<p>You are not authorized to edit this secret.</p>';
        return;
    }

    if ($_POST) {
        $action = $secret_id ? 'update' : 'create';
        $title = sanitize_text_field($_POST['title']);
        $secret_content = sanitize_text_field($_POST['secret_content']);
        $shared_with = isset($_POST['shared_with']) ? array_map('sanitize_text_field', explode(',', $_POST['shared_with'])) : array();

        $encrypted_secret = wp_hash_password($secret_content);

        if ($action == 'create') {
            $wpdb->insert(
                $table_name_secrets,
                array(
                    'user_id' => $current_user_id,
                    'secret_title' => $title,
                    'secret_content' => $encrypted_secret,
                )
            );

            $secret_id = $wpdb->insert_id;
        } else {
            $wpdb->update(
                $table_name_secrets,
                array(
                    'secret_title' => $title,
                    'secret_content' => $encrypted_secret,
                ),
                array('id' => $secret_id)
            );

            $wpdb->delete($table_name_shared, array('secret_id' => $secret_id));
        }

        foreach ($shared_with as $user_login) {
            $user = get_user_by('login', $user_login);
            if ($user) {
                $wpdb->insert(
                    $table_name_shared,
                    array(
                        'secret_id' => $secret_id,
                        'shared_with' => $user->ID,
                    )
                );
            }
        }

        echo '<p>Secret saved successfully.</p>';
    }

    $secret_title = $secret ? $secret->secret_title : '';
    $secret_content = $secret ? $secret->secret_content : '';
    $shared_with = $secret ? $wpdb->get_col($wpdb->prepare("SELECT user_login FROM $wpdb->users u JOIN $table_name_shared s ON u.ID = s.shared_with WHERE s.secret_id = %d", $secret_id)) : array();

    echo '<div class="wrap">';
    echo '<h1>' . ($secret_id ? 'Edit Secret' : 'Add New Secret') . '</h1>';
    echo '<form method="post">';
    echo '<label for="title">Title:</label>';
    echo '<input type="text" id="title" name="title" value="' . esc_attr($secret_title) . '"><br>';
    echo '<label for="secret_content">Secret:</label>';
    echo '<input type="text" id="secret_content" name="secret_content" value="' . esc_attr($secret_content) . '"><br>';
    echo '<label for="shared_with">Share with (usernames, comma separated):</label>';
    echo '<input type="text" id="shared_with" name="shared_with" value="' . esc_attr(implode(',', $shared_with)) . '"><br>';
    echo '<input type="submit" value="Save Secret">';
    echo '</form>';
    echo '</div>';
}
?>
