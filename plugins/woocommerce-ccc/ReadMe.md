# WooCommerce CCC Plugin

>> WooCommerce CCC (Customer Control Center) is a WordPress plugin that extends the WordPress REST API to provide user and WooCommerce customer management functionality. It allows you to register users, authenticate users, retrieve user information, update user information, update user passwords, retrieve WooCommerce customer information, and update WooCommerce customer information.

## Plugin Information

- **Plugin Name**: WooCommerce CCC
- **Description**: WooCommerce Customer Control Centre. Extends the WordPress REST API for user and WooCommerce management.
- **Version**: 1.0.1
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

Upon activation, the plugin will register several REST API endpoints for managing users and WooCommerce customers.

## Features

### User Management

- **Register User**: Register a new user with username, email, and password.
- **Login User**: Authenticate a user and return a JWT token.
- **Get User Info**: Retrieve the current user's information.
- **Update User Info**: Update the current user's email, first name, and last name.
- **Update User Password**: Update the current user's password.

### WooCommerce Customer Management

- **Get Customer Info**: Retrieve the current WooCommerce customer's billing and shipping information.
- **Update Customer Info**: Update the current WooCommerce customer's billing and shipping information.

### JWT Authentication

- Generate and validate JWT tokens for secure API access.

## Admin Menu and Pages

### CCC API Usage Page

Provides information on available API endpoints and how to use them.

### Admin Menu Registration

- **CCC API Usage**: A page under the **Users** menu that displays available API endpoints and usage instructions.

## Helper Functions and API Endpoints

### Functions

- **register_user(WP_REST_Request $request)**: Registers a new user.
- **login_user(WP_REST_Request $request)**: Authenticates a user and returns a JWT token.
- **get_user_info(WP_REST_Request $request)**: Retrieves the current user's information.
- **update_user_info(WP_REST_Request $request)**: Updates the current user's email, first name, and last name.
- **update_user_password(WP_REST_Request $request)**: Updates the current user's password.
- **get_customer_info(WP_REST_Request $request)**: Retrieves the current WooCommerce customer's billing and shipping information.
- **update_customer_info(WP_REST_Request $request)**: Updates the current WooCommerce customer's billing and shipping information.
- **authenticate_request(WP_REST_Request $request)**: Authenticates a request using the JWT token.

### API Endpoints

- **Register User**: `POST /wp-json/woocommerce-ccc/v1/register`
- **Login User**: `POST /wp-json/woocommerce-ccc/v1/login`
- **Get User Info**: `GET /wp-json/woocommerce-ccc/v1/user`
- **Update User Info**: `POST /wp-json/woocommerce-ccc/v1/user/update`
- **Update User Password**: `POST /wp-json/woocommerce-ccc/v1/user/password`
- **Get Customer Info**: `GET /wp-json/woocommerce-ccc/v1/customer`
- **Update Customer Info**: `POST /wp-json/woocommerce-ccc/v1/customer/update`

## Changelog

### Version 1.0.1

- Initial release with user registration, login, and WooCommerce customer management functionality.
- JWT authentication for secure API access.
- Admin page for displaying available API endpoints and usage instructions.

