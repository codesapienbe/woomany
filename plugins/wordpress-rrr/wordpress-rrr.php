<?php
/*
Plugin Name: WordPress Review and Reset as Root
Description: Manage wp-config.php settings and provide instructions for reloading Apache.
Version: 1.0.1
Author: Yilmaz Mustafa, Sergey Ryskin, Atilla Balin, ChatGPT
*/

// Add a new menu item to the admin sidebar
add_action('admin_menu', 'wp_rrr_menu');
add_action('rest_api_init', 'wp_rrr_register_api');

function wp_rrr_menu() {
    add_menu_page('Configurations', 'Configurations', 'manage_options', 'wp-rrr', 'wp_rrr_page', 'dashicons-admin-generic');
    add_submenu_page('wp-rrr', 'PHP Settings', 'PHP', 'manage_options', 'wp-rrr-php', 'wp_rrr_php_page');
    add_submenu_page('wp-rrr', 'Debug Settings', 'Debug', 'manage_options', 'wp-rrr-debug', 'wp_rrr_debug_page');
    add_submenu_page('wp-rrr', 'Memory Limit', 'Memory Limit', 'manage_options', 'wp-rrr-memory', 'wp_rrr_memory_page');
    add_submenu_page('wp-rrr', 'File Editor', 'File Editor', 'manage_options', 'wp-rrr-editor', 'wp_rrr_editor_page');
}

function wp_rrr_page() {
    echo '<h1>WP Review and Reset as Root</h1>';
    echo '<p>Select a sub-menu to configure specific settings.</p>';
}

function wp_rrr_php_page() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_max_filesize'])) {
        $upload_max_filesize = sanitize_text_field($_POST['upload_max_filesize']);
        $post_max_size = sanitize_text_field($_POST['post_max_size']);
        $memory_limit = sanitize_text_field($_POST['memory_limit']);
        $new_config = "
        @ini_set( 'upload_max_size' , '$upload_max_filesize' );
        @ini_set( 'post_max_size', '$post_max_size');
        @ini_set( 'memory_limit', '$memory_limit' );
        ";
        file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
        echo '<div class="updated"><p>Settings saved. Please reload Apache.</p></div>';
    }

    ?>
    <h1>PHP Settings</h1>
    <form method="post">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">upload_max_filesize</th>
                <td><input type="text" name="upload_max_filesize" value="64M" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">post_max_size</th>
                <td><input type="text" name="post_max_size" value="64M" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">memory_limit</th>
                <td><input type="text" name="memory_limit" value="256M" /></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="Save Changes" />
        </p>
    </form>
    <h2>Instructions</h2>
    <p>To apply these settings, please reload Apache using the following command in your terminal:</p>
    <pre><code>sudo service apache2 reload</code></pre>
    <?php
}

function wp_rrr_debug_page() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wp_debug'])) {
        $wp_debug = sanitize_text_field($_POST['wp_debug']) === 'true' ? 'true' : 'false';
        $new_config = "
        define('WP_DEBUG', $wp_debug);
        ";
        file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
        echo '<div class="updated"><p>Settings saved. Please reload Apache.</p></div>';
    }

    ?>
    <h1>Debug Settings</h1>
    <form method="post">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">WP_DEBUG</th>
                <td>
                    <select name="wp_debug">
                        <option value="true">Enabled</option>
                        <option value="false">Disabled</option>
                    </select>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="Save Changes" />
        </p>
    </form>
    <h2>Instructions</h2>
    <p>To apply these settings, please reload Apache using the following command in your terminal:</p>
    <pre><code>sudo service apache2 reload</code></pre>
    <?php
}

function wp_rrr_memory_page() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wp_memory_limit'])) {
        $wp_memory_limit = sanitize_text_field($_POST['wp_memory_limit']);
        $new_config = "
        define('WP_MEMORY_LIMIT', '$wp_memory_limit');
        ";
        file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
        echo '<div class="updated"><p>Settings saved. Please reload Apache.</p></div>';
    }

    ?>
    <h1>Memory Limit</h1>
    <form method="post">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">WP_MEMORY_LIMIT</th>
                <td><input type="text" name="wp_memory_limit" value="128M" /></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="Save Changes" />
        </p>
    </form>
    <h2>Instructions</h2>
    <p>To apply these settings, please reload Apache using the following command in your terminal:</p>
    <pre><code>sudo service apache2 reload</code></pre>
    <?php
}

function wp_rrr_editor_page() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['disallow_file_edit'])) {
        $disallow_file_edit = sanitize_text_field($_POST['disallow_file_edit']) === 'true' ? 'true' : 'false';
        $new_config = "
        define('DISALLOW_FILE_EDIT', $disallow_file_edit);
        ";
        file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
        echo '<div class="updated"><p>Settings saved. Please reload Apache.</p></div>';
    }

    ?>
    <h1>File Editor</h1>
    <form method="post">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">DISALLOW_FILE_EDIT</th>
                <td>
                    <select name="disallow_file_edit">
                        <option value="true">Disabled</option>
                        <option value="false">Enabled</option>
                    </select>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="Save Changes" />
        </p>
    </form>
    <h2>Instructions</h2>
    <p>To apply these settings, please reload Apache using the following command in your terminal:</p>
    <pre><code>sudo service apache2 reload</code></pre>
    <?php
}

function wp_rrr_register_api() {
    register_rest_route('wp-rrr/v1', '/config/php', array(
        'methods' => 'GET',
        'callback' => 'wp_rrr_get_php_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));

    register_rest_route('wp-rrr/v1', '/config/php', array(
        'methods' => 'POST',
        'callback' => 'wp_rrr_set_php_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));

    register_rest_route('wp-rrr/v1', '/config/debug', array(
        'methods' => 'GET',
        'callback' => 'wp_rrr_get_debug_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));

    register_rest_route('wp-rrr/v1', '/config/debug', array(
        'methods' => 'POST',
        'callback' => 'wp_rrr_set_debug_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));

    register_rest_route('wp-rrr/v1', '/config/memory', array(
        'methods' => 'GET',
        'callback' => 'wp_rrr_get_memory_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));

    register_rest_route('wp-rrr/v1', '/config/memory', array(
        'methods' => 'POST',
        'callback' => 'wp_rrr_set_memory_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));

    register_rest_route('wp-rrr/v1', '/config/editor', array(
        'methods' => 'GET',
        'callback' => 'wp_rrr_get_editor_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));

    register_rest_route('wp-rrr/v1', '/config/editor', array(
        'methods' => 'POST',
        'callback' => 'wp_rrr_set_editor_config',
        'permission_callback' => 'wp_rrr_permissions_check',
    ));
}

function wp_rrr_permissions_check($request) {
    return current_user_can('manage_options');
}

function wp_rrr_get_php_config() {
    $config = array(
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
    );
    return new WP_REST_Response($config, 200);
}

function wp_rrr_set_php_config(WP_REST_Request $request) {
    $upload_max_filesize = sanitize_text_field($request->get_param('upload_max_filesize'));
    $post_max_size = sanitize_text_field($request->get_param('post_max_size'));
    $memory_limit = sanitize_text_field($request->get_param('memory_limit'));
    $new_config = "
    @ini_set( 'upload_max_size' , '$upload_max_filesize' );
    @ini_set( 'post_max_size', '$post_max_size');
    @ini_set( 'memory_limit', '$memory_limit' );
    ";
    file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
    return new WP_REST_Response(array('message' => 'Settings saved. Please reload Apache.'), 200);
}

function wp_rrr_get_debug_config() {
    return new WP_REST_Response(array('WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false), 200);
}

function wp_rrr_set_debug_config(WP_REST_Request $request) {
    $wp_debug = sanitize_text_field($request->get_param('wp_debug')) === 'true' ? 'true' : 'false';
    $new_config = "
    define('WP_DEBUG', $wp_debug);
    ";
    file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
    return new WP_REST_Response(array('message' => 'Settings saved. Please reload Apache.'), 200);
}

function wp_rrr_get_memory_config() {
    return new WP_REST_Response(array('WP_MEMORY_LIMIT' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '128M'), 200);
}

function wp_rrr_set_memory_config(WP_REST_Request $request) {
    $wp_memory_limit = sanitize_text_field($request->get_param('wp_memory_limit'));
    $new_config = "
    define('WP_MEMORY_LIMIT', '$wp_memory_limit');
    ";
    file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
    return new WP_REST_Response(array('message' => 'Settings saved. Please reload Apache.'), 200);
}

function wp_rrr_get_editor_config() {
    return new WP_REST_Response(array('DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') ? DISALLOW_FILE_EDIT : false), 200);
}

function wp_rrr_set_editor_config(WP_REST_Request $request) {
    $disallow_file_edit = sanitize_text_field($request->get_param('disallow_file_edit')) === 'true' ? 'true' : 'false';
    $new_config = "
    define('DISALLOW_FILE_EDIT', $disallow_file_edit);
    ";
    file_put_contents(ABSPATH . 'wp-config.php', PHP_EOL . $new_config, FILE_APPEND | LOCK_EX);
    return new WP_REST_Response(array('message' => 'Settings saved. Please reload Apache.'), 200);
}
