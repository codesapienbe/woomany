# WooCommerce-SSS

**WooCommerce Shared Secret Server** allows users to store, share, and manage their secrets. Secrets are encrypted and can only be viewed after entering a master password (PIN).

## Description

WooCommerce-SSS is a plugin for WordPress that provides a secure way for users to store and share secrets. The secrets are encrypted in the database, and users can only view them by entering a master password. The plugin adds a main menu in the WordPress admin area with submenus for managing secrets, adding new secrets, and setting the master password.

## Features

- **Store Secrets**: Users can store their secrets securely.
- **Share Secrets**: Users can share their secrets with other users.
- **Encrypt Secrets**: Secrets are encrypted using WordPress's hashing mechanism.
- **Master Password**: Secrets can only be viewed after entering a master password.
- **Admin Menu Integration**: Adds a main menu in the WordPress admin area with submenus for managing secrets.

## Installation

1. Upload the `woocommerce-sss` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. The plugin will automatically create the necessary database tables upon activation.

## Usage

### Set Master Password

1. Navigate to `Secrets` > `Set Master Password`.
2. Enter a master password (6-8 characters) and submit.

### Manage Secrets

1. Navigate to `Secrets` > `All Secrets`.
2. Enter the master password when prompted to view your secrets.

### Add New Secret

1. Navigate to `Secrets` > `Add New Secret`.
2. Fill in the title, secret content, and usernames (comma separated) of users to share the secret with.
3. Submit the form to save the secret.

### Edit/Delete Secrets

1. Navigate to `Secrets` > `All Secrets`.
2. Use the edit and delete buttons to manage your secrets.

## Screenshots

### 1. Set Master Password

![Set Master Password](screenshots/set-master-password.png)

### 2. Manage Secrets

![Manage Secrets](screenshots/manage-secrets.png)

### 3. Add New Secret

![Add New Secret](screenshots/add-new-secret.png)

## Changelog

### 1.0.1

- Initial release.

## Authors

- Yilmaz Mustafa
- Sergey Ryskin
- ChatGPT

## License

This plugin is licensed under the GPLv2 or later.
