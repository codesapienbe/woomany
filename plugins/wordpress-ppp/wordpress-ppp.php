<?php
/*
Plugin Name: WooCommerce PPP
Description: WooCommerce Portable Publish Platform. Converts the WooCommerce website into a Progressive Web App by adding service workers and a web app manifest, allowing offline browsing of the shop page and adding products to a wishlist.
Version: 1.0.1
Author: Yilmaz Mustafa, Sergey Ryskin, ChatGPT
*/

// Check if WooCommerce is active
function wc_ppp_check_woocommerce_active() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_ppp_woocommerce_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
add_action('admin_init', 'wc_ppp_check_woocommerce_active');

function wc_ppp_woocommerce_notice() {
    echo '<div class="error"><p>WooCommerce Portable Publish Platform requires WooCommerce to be installed and active.</p></div>';
}

// Enqueue the service worker script
function wc_ppp_enqueue_scripts() {
    echo '<script>
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/wp-content/plugins/wordpress-ppp/service-worker.js")
            .then(function(registration) {
                console.log("Service Worker registered with scope:", registration.scope);
            }).catch(function(error) {
                console.log("Service Worker registration failed:", error);
            });
        }
    </script>';
}
add_action('wp_footer', 'wc_ppp_enqueue_scripts');

// Add the web app manifest link
function wc_ppp_add_manifest_link() {
    echo '<link rel="manifest" href="/wp-content/plugins/wordpress-ppp/manifest.json">';
}
add_action('wp_head', 'wc_ppp_add_manifest_link');

// Create and serve the service worker script
function wc_ppp_create_service_worker() {
    $sw = '
    self.addEventListener("install", function(event) {
        event.waitUntil(
            caches.open("v1").then(function(cache) {
                return cache.addAll([
                    "/",
                    "/shop/",
                    "/wp-content/plugins/wordpress-ppp/offline.html",
                    // Add other assets here
                ]);
            })
        );
    });

    self.addEventListener("fetch", function(event) {
        event.respondWith(
            caches.match(event.request).then(function(response) {
                return response || fetch(event.request);
            }).catch(function() {
                return caches.match("/wp-content/plugins/wordpress-ppp/offline.html");
            })
        );
    });
    ';
    file_put_contents(plugin_dir_path(__FILE__) . 'service-worker.js', $sw);
}
register_activation_hook(__FILE__, 'wc_ppp_create_service_worker');

// Create and serve the manifest file
function wc_ppp_create_manifest() {
    $manifest = '{
        "name": "Your Shop Name",
        "short_name": "Shop",
        "start_url": "/shop/",
        "display": "standalone",
        "background_color": "#ffffff",
        "theme_color": "#000000",
        "icons": [
            {
                "src": "/wp-content/plugins/wordpress-ppp/icon-light-small.webp",
                "sizes": "192x192",
                "type": "image/webp"
            },
            {
                "src": "/wp-content/plugins/wordpress-ppp/icon-light-large.webp",
                "sizes": "512x512",
                "type": "image/webp"
            }
        ]
    }';
    file_put_contents(plugin_dir_path(__FILE__) . 'manifest.json', $manifest);
}
register_activation_hook(__FILE__, 'wc_ppp_create_manifest');

// Create an offline fallback page
function wc_ppp_create_offline_page() {
    $offline_page = '<!DOCTYPE html>
    <html>
    <head>
        <title>Offline</title>
    </head>
    <body>
        <h1>You are offline</h1>
        <p>You can browse the shop but cannot add items to the cart. Please check your internet connection and try again.</p>
        <a href="/shop/">Go to Shop</a>
    </body>
    </html>';
    file_put_contents(plugin_dir_path(__FILE__) . 'offline.html', $offline_page);
}
register_activation_hook(__FILE__, 'wc_ppp_create_offline_page');

// Add product to wishlist
function wc_ppp_add_to_wishlist() {
    // Implement wishlist logic here (could be via a custom table, or a plugin like YITH WooCommerce Wishlist)
}
add_action('wp_ajax_wc_ppp_add_to_wishlist', 'wc_ppp_add_to_wishlist');
add_action('wp_ajax_nopriv_wc_ppp_add_to_wishlist', 'wc_ppp_add_to_wishlist');
?>
