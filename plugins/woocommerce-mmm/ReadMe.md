# WooCommerce MMM Plugin

## Plugin Information

- **Plugin Name**: WooCommerce MMM
- **Description**: Adds multi-merchant functionality to WooCommerce, allowing products to be managed and sold by multiple stores with custom attributes for each store.
- **Version**: 1.4
- **Authors**: Yilmaz Mustafa, Sergey Ryskin, ChatGPT

## Table of Contents

1. [Installation](#installation)
2. [Activation](#activation)
3. [Features](#features)
4. [Admin Menu and Pages](#admin-menu-and-pages)
5. [Helper Functions and API Endpoints](#helper-functions-and-api-endpoints)
6. [Changelog](#changelog)

## Installation

1. Download the plugin ZIP file.
2. In your WordPress admin dashboard, navigate to **Plugins** > **Add New**.
3. Click **Upload Plugin** and select the downloaded ZIP file.
4. Click **Install Now** and then **Activate** the plugin.

## Activation

Upon activation, the plugin will create the necessary database tables:

- `wp_stores`: To store information about each store.
- `wp_product_store`: To map products to stores.
- `wp_store_reviews`: To store reviews for each store.
- `wp_store_hours`: To store operating hours for each store.

## Features

### Multi-Merchant Functionality

- Add and manage multiple stores.
- Assign products to different stores.
- Custom attributes for each store (name, URL, email, phone, etc.).

### Product Data Tab and Fields

- Adds a custom tab in the WooCommerce product data meta box to enter store-specific information.

### Store Reviews

- Collect and display reviews for each store.

### Store Hours

- Define and display the operating hours for each store.

### Import/Export

- Import and export store data via CSV files.

### API Usage

- Exposes several REST API endpoints to manage stores and their attributes programmatically.

## Admin Menu and Pages

### Store Management

- **Stores**: Main page to manage stores.
- **All Stores**: List all stores with options to edit or delete.
- **Add New Store**: Form to add a new store.
- **Store Reviews**: Manage store reviews.
- **Store Hours**: Manage store hours.
- **Import/Export**: Import or export store data.
- **API Usage**: Information on API endpoints.

### Main Store Management Page

Provides options to generate mock stores and manage stores, reviews, and hours.

### Advanced Search and Filters

The plugin includes advanced search functionality for stores and reviews, with filters for stores, customers, and date ranges.

### Edit Store Page

Provides a form to update store information and manage store hours.

## Helper Functions and API Endpoints

### Functions

- `mmm_get_store($store_id)`: Retrieve store information by ID.
- `mmm_add_store($store_name, $store_url, $email, $phone, $token, $secret, $logo_url = null, $background_url = null)`: Add a new store.
- `mmm_get_stores()`: Retrieve all stores.
- `mmm_update_store($store_id, $store_name, $store_url, $email, $phone, $token, $secret, $logo_url = null, $background_url = null)`: Update store information.
- `mmm_delete_store($store_id)`: Delete a store.
- `mmm_generate_mock_stores($number_of_stores)`: Generate mock stores.
- `mmm_export_stores()`: Export store data as a CSV.
- `mmm_import_stores($file)`: Import store data from a CSV file.
- `mmm_add_store_hour($store_id, $day_of_week, $open_time, $close_time)`: Add store operating hours.
- `mmm_get_store_hours($store_id)`: Retrieve store operating hours.
- `mmm_update_store_hour($id, $day_of_week, $open_time, $close_time)`: Update store operating hours.
- `mmm_delete_store_hour($id)`: Delete store operating hours.

### API Endpoints

- **Get All Stores**: `GET /wp-json/mmm/v1/stores`
- **Add Store**: `POST /wp-json/mmm/v1/store`
- **Get Store by ID**: `GET /wp-json/mmm/v1/store/(?P<id>\d+)`
- **Update Store**: `POST /wp-json/mmm/v1/store/(?P<id>\d+)`
- **Delete Store**: `DELETE /wp-json/mmm/v1/store/(?P<id>\d+)`
- **Export Stores**: `GET /wp-json/mmm/v1/stores/export`
- **Import Stores**: `POST /wp-json/mmm/v1/stores/import`
- **Remove All Stores**: `POST /wp-json/mmm/v1/stores/remove_all`
- **Generate Mock Stores**: `POST /wp-json/mmm/v1/stores/generate_mock`
- **Get Store Hours**: `GET /wp-json/mmm/v1/store/(?P<id>\d+)/hours`
- **Add Store Hour**: `POST /wp-json/mmm/v1/store/(?P<id>\d+)/hour`
- **Update Store Hour**: `POST /wp-json/mmm/v1/store/hour/(?P<hour_id>\d+)`
- **Delete Store Hour**: `DELETE /wp-json/mmm/v1/store/hour/(?P<hour_id>\d+)`

# WooCommerce MMM Plugin

## Plugin Information

- **Plugin Name**: WooCommerce MMM
- **Description**: Adds multi-merchant functionality to WooCommerce, allowing products to be managed and sold by multiple stores with custom attributes for each store.
- **Version**: 1.4
- **Authors**: Yilmaz Mustafa, Sergey Ryskin, ChatGPT

## Table of Contents

1. [Installation](#installation)
2. [Activation](#activation)
3. [Features](#features)
4. [Admin Menu and Pages](#admin-menu-and-pages)
5. [Helper Functions and API Endpoints](#helper-functions-and-api-endpoints)
6. [Changelog](#changelog)

## Installation

1. Download the plugin ZIP file.
2. In your WordPress admin dashboard, navigate to **Plugins** > **Add New**.
3. Click **Upload Plugin** and select the downloaded ZIP file.
4. Click **Install Now** and then **Activate** the plugin.

## Activation

Upon activation, the plugin will create the necessary database tables:

- `wp_stores`: To store information about each store.
- `wp_product_store`: To map products to stores.
- `wp_store_reviews`: To store reviews for each store.
- `wp_store_hours`: To store operating hours for each store.

## Features

### Multi-Merchant Functionality

- Add and manage multiple stores.
- Assign products to different stores.
- Custom attributes for each store (name, URL, email, phone, etc.).

### Product Data Tab and Fields

- Adds a custom tab in the WooCommerce product data meta box to enter store-specific information.

### Store Reviews

- Collect and display reviews for each store.

### Store Hours

- Define and display the operating hours for each store.

### Import/Export

- Import and export store data via CSV files.

### API Usage

- Exposes several REST API endpoints to manage stores and their attributes programmatically.

## Admin Menu and Pages

### Store Management

- **Stores**: Main page to manage stores.
- **All Stores**: List all stores with options to edit or delete.
- **Add New Store**: Form to add a new store.
- **Store Reviews**: Manage store reviews.
- **Store Hours**: Manage store hours.
- **Import/Export**: Import or export store data.
- **API Usage**: Information on API endpoints.

### Main Store Management Page

Provides options to generate mock stores and manage stores, reviews, and hours.

### Advanced Search and Filters

The plugin includes advanced search functionality for stores and reviews, with filters for stores, customers, and date ranges.

### Edit Store Page

Provides a form to update store information and manage store hours.

## Helper Functions and API Endpoints

### Functions

- `mmm_get_store($store_id)`: Retrieve store information by ID.
- `mmm_add_store($store_name, $store_url, $email, $phone, $token, $secret, $logo_url = null, $background_url = null)`: Add a new store.
- `mmm_get_stores()`: Retrieve all stores.
- `mmm_update_store($store_id, $store_name, $store_url, $email, $phone, $token, $secret, $logo_url = null, $background_url = null)`: Update store information.
- `mmm_delete_store($store_id)`: Delete a store.
- `mmm_generate_mock_stores($number_of_stores)`: Generate mock stores.
- `mmm_export_stores()`: Export store data as a CSV.
- `mmm_import_stores($file)`: Import store data from a CSV file.
- `mmm_add_store_hour($store_id, $day_of_week, $open_time, $close_time)`: Add store operating hours.
- `mmm_get_store_hours($store_id)`: Retrieve store operating hours.
- `mmm_update_store_hour($id, $day_of_week, $open_time, $close_time)`: Update store operating hours.
- `mmm_delete_store_hour($id)`: Delete store operating hours.

### API Endpoints

- **Get All Stores**: `GET /wp-json/mmm/v1/stores`
- **Add Store**: `POST /wp-json/mmm/v1/store`
- **Get Store by ID**: `GET /wp-json/mmm/v1/store/(?P<id>\d+)`
- **Update Store**: `POST /wp-json/mmm/v1/store/(?P<id>\d+)`
- **Delete Store**: `DELETE /wp-json/mmm/v1/store/(?P<id>\d+)`
- **Export Stores**: `GET /wp-json/mmm/v1/stores/export`
- **Import Stores**: `POST /wp-json/mmm/v1/stores/import`
- **Remove All Stores**: `POST /wp-json/mmm/v1/stores/remove_all`
- **Generate Mock Stores**: `POST /wp-json/mmm/v1/stores/generate_mock`
- **Get Store Hours**: `GET /wp-json/mmm/v1/store/(?P<id>\d+)/hours`
- **Add Store Hour**: `POST /wp-json/mmm/v1/store/(?P<id>\d+)/hour`
- **Update Store Hour**: `POST /wp-json/mmm/v1/store/hour/(?P<hour_id>\d+)`
- **Delete Store Hour**: `DELETE /wp-json/mmm/v1/store/hour/(?P<hour_id>\d+)`

## Changelog

### Version 1.4

- Initial release with multi-merchant functionality.
- Added custom product data tab and fields.
- Store reviews and hours management.
- Import/Export functionality.
- REST API endpoints for store management.


## Changelog

### Version 1.4

- Initial release with multi-merchant functionality.
- Added custom product data tab and fields.
- Store reviews and hours management.
- Import/Export functionality.
- REST API endpoints for store management.

