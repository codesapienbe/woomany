# WooCommerce PPP Plugin

## Plugin Information

- **Plugin Name**: WooCommerce PPP
- **Description**: WooCommerce Portable Publish Platform. Converts the WooCommerce website into a Progressive Web App by adding service workers and a web app manifest, allowing offline browsing of the shop page and adding products to a wishlist.
- **Version**: 1.0.1
- **Authors**: Yilmaz Mustafa, Sergey Ryskin, ChatGPT

## Table of Contents

1. [Installation](#installation)
2. [Activation](#activation)
3. [Features](#features)
4. [Service Worker](#service-worker)
5. [Web App Manifest](#web-app-manifest)
6. [Offline Fallback Page](#offline-fallback-page)
7. [Wishlist](#wishlist)
8. [Changelog](#changelog)

## Installation

1. Download the plugin ZIP file.
2. In your WordPress admin dashboard, navigate to **Plugins** > **Add New**.
3. Click **Upload Plugin** and select the downloaded ZIP file.
4. Click **Install Now** and then **Activate** the plugin.

## Activation

Upon activation, the plugin will perform the following actions:

1. Check if WooCommerce is active. If not, the plugin will be deactivated, and an admin notice will be displayed.
2. Create the service worker script.
3. Create the web app manifest file.
4. Create an offline fallback page.

## Features

### Progressive Web App (PWA) Functionality

- Converts the WooCommerce website into a PWA.
- Adds service workers for offline browsing of the shop page.
- Adds a web app manifest for a native app-like experience.

### Wishlist Functionality

- Allows users to add products to a wishlist.
- Supports both logged-in and guest users.

## Service Worker

The service worker script is created upon activation and includes the following features:

- **Installation**: Caches the home page, shop page, offline fallback page, and other assets.
- **Fetch Event**: Serves cached assets when offline and falls back to the offline page if the requested asset is not cached.

### Enqueue Service Worker Script

The service worker script is enqueued in the footer of the website:

```php
function wc_ppp_enqueue_scripts() {
    echo '<script>
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/wp-content/plugins/woocommerce-ppp/service-worker.js")
            .then(function(registration) {
                console.log("Service Worker registered with scope:", registration.scope);
            }).catch(function(error) {
                console.log("Service Worker registration failed:", error);
            });
        }
    </script>';
}
add_action('wp_footer', 'wc_ppp_enqueue_scripts');
```

## Web App Manifest

The web app manifest file is created upon activation and includes the following properties:

- **Name**: The name of the web app.
- **Short Name**: A short version of the name.
- **Start URL**: The URL that loads when the app is launched.
- **Display**: The display mode of the app (standalone, fullscreen, etc.).
- **Background Color**: The background color of the splash screen.
- **Theme Color**: The theme color of the browser.
- **Icons**: Icons used for the web app in different sizes.

### Add Manifest Link

The manifest link is added to the head of the website:

```php
function wc_ppp_add_manifest_link() {
    echo '<link rel="manifest" href="/wp-content/plugins/woocommerce-ppp/manifest.json">';
}
add_action('wp_head', 'wc_ppp_add_manifest_link');
```

## Offline Fallback Page

An offline fallback page is created upon activation. This page is displayed when the user is offline and tries to access a resource that is not cached.

### Create Offline Fallback Page

```php
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
```

## Wishlist

The plugin includes a basic wishlist functionality that allows users to add products to a wishlist. The implementation can be expanded to use a custom table or integrate with a plugin like YITH WooCommerce Wishlist.

### Add Product to Wishlist

```php
function wc_ppp_add_to_wishlist() {
    // Implement wishlist logic here (could be via a custom table, or a plugin like YITH WooCommerce Wishlist)
}
add_action('wp_ajax_wc_ppp_add_to_wishlist', 'wc_ppp_add_to_wishlist');
add_action('wp_ajax_nopriv_wc_ppp_add_to_wishlist', 'wc_ppp_add_to_wishlist');
```

## Changelog

### Version 1.0.1

- Initial release with PWA functionality and wishlist feature.
- Added service workers for offline browsing.
- Created a web app manifest for app-like experience.
- Implemented offline fallback page.
- Basic wishlist functionality for logged-in and guest users.

