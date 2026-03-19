-- Book Inventory System Database Schema
CREATE DATABASE IF NOT EXISTS bookhubdb;
USE bookhubdb;

-- ==============================
-- Roles Table
-- ==============================
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    description TEXT
);

INSERT INTO roles (role_id, role_name, description)
VALUES (1, 'Admin', 'System Administrator with full access'),
       (2, 'Sales', 'Can sell books and manage sales');

-- ==============================
-- Users Table
-- ==============================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role_id INT,
    linked_id INT,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- ==============================
-- Books Table
-- ==============================
CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    isbn VARCHAR(20) UNIQUE,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL,
    grade VARCHAR(50) NOT NULL,
    publisher VARCHAR(255),
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==============================
-- Inventory Table
-- ==============================
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- ==============================
-- Suppliers Table
-- ==============================
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==============================
-- Book Suppliers (Many-to-Many)
-- ==============================
CREATE TABLE book_suppliers (
    book_id INT,
    supplier_id INT,
    PRIMARY KEY (book_id, supplier_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE CASCADE
);

-- ==============================
-- Payments Table
-- ==============================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL UNIQUE,
    book_id INT NOT NULL,
    buyer_id INT,
    buyer_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','Card','Online','Cheque') DEFAULT 'Cash',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Completed','Cancelled') DEFAULT 'Completed',
    notes TEXT,
    FOREIGN KEY (book_id) REFERENCES books(book_id),
    FOREIGN KEY (buyer_id) REFERENCES users(user_id)
);

-- ==============================
-- System Settings Table
-- ==============================
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==============================
-- Inventory History Table
-- ==============================
CREATE TABLE inventory_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    quantity_change INT NOT NULL,
    notes TEXT,
    updated_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
);

-- =====================================================
-- Page Access Settings Table (ENHANCED SCHEMA)
-- =====================================================
CREATE TABLE IF NOT EXISTS page_access_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(100) NOT NULL,
    page_display_name VARCHAR(255) NOT NULL,
    access_type ENUM('page', 'tab', 'action') DEFAULT 'page',
    parent_page VARCHAR(100) DEFAULT NULL,
    access_key VARCHAR(100) DEFAULT NULL,
    admin_enabled TINYINT(1) DEFAULT 1,
    sales_enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_access (page_name, access_type, parent_page, access_key)
);

-- =====================================================
-- FIX SECTION: For Existing Databases
-- Run these ALTER statements to add missing columns
-- =====================================================

-- Allow NULL values for isbn column (fixes duplicate entry error when no ISBN is provided)
ALTER TABLE books MODIFY COLUMN isbn VARCHAR(20) NULL;

-- Add image column to books table for storing book cover images
ALTER TABLE books ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL;

-- Add missing columns to page_access_settings table
ALTER TABLE page_access_settings ADD COLUMN IF NOT EXISTS page_display_name VARCHAR(255) NOT NULL AFTER page_name;
ALTER TABLE page_access_settings ADD COLUMN IF NOT EXISTS access_type ENUM('page', 'tab', 'action') DEFAULT 'page' AFTER page_display_name;
ALTER TABLE page_access_settings ADD COLUMN IF NOT EXISTS parent_page VARCHAR(100) DEFAULT NULL AFTER access_type;
ALTER TABLE page_access_settings ADD COLUMN IF NOT EXISTS access_key VARCHAR(100) DEFAULT NULL AFTER parent_page;

-- Update existing records with page_display_name
UPDATE page_access_settings SET page_display_name = 
    CASE page_name
        WHEN 'manage_books' THEN 'Manage Books'
        WHEN 'manage_inventory' THEN 'Inventory'
        WHEN 'manage_suppliers' THEN 'Suppliers'
        WHEN 'manage_payments' THEN 'Manage Payments'
        WHEN 'view_sales' THEN 'View Sales'
        WHEN 'sell_books' THEN 'Sell Books'
        WHEN 'manage_settings' THEN 'Settings'
        WHEN 'admin_dashboard' THEN 'Dashboard'
        ELSE page_name
    END
WHERE page_display_name IS NULL OR page_display_name = '';

-- Delete existing inventory tab permissions (with duplicate page_names)
DELETE FROM page_access_settings 
WHERE page_name = 'manage_inventory' AND access_type = 'tab';

-- Add missing page access settings with UNIQUE page_names
INSERT IGNORE INTO page_access_settings (page_name, page_display_name, access_type, parent_page, access_key, admin_enabled, sales_enabled) VALUES
('settings_general', 'General', 'tab', 'manage_settings', 'general', 1, 1),
('settings_grades', 'Grades', 'tab', 'manage_settings', 'grades', 1, 1),
('settings_categories', 'Categories', 'tab', 'manage_settings', 'categories', 1, 1),
('settings_display', 'Display', 'tab', 'manage_settings', 'display', 1, 1),
('settings_access', 'Access', 'tab', 'manage_settings', 'access', 1, 1),
('settings_users', 'Users', 'tab', 'manage_settings', 'users', 1, 1),
('settings_add_user', 'Add User', 'action', 'manage_settings', 'add_user', 1, 1),
('settings_delete_user', 'Delete User', 'action', 'manage_settings', 'delete_user', 1, 1),
('books_add', 'Add Book', 'action', 'manage_books', 'add', 1, 1),
('books_edit', 'Edit Book', 'action', 'manage_books', 'edit', 1, 1),
('books_delete', 'Delete Book', 'action', 'manage_books', 'delete', 1, 0),
('sell_create', 'Create Sale', 'action', 'sell_books', 'create', 1, 1),
('sell_view_receipt', 'View Receipt', 'action', 'sell_books', 'receipt', 1, 1),
-- Tab permissions for inventory page with UNIQUE page_names
('inventory_list', 'Inventory List', 'tab', 'manage_inventory', 'inventory', 1, 1),
('inventory_add_stock', 'Add Stock', 'tab', 'manage_inventory', 'add-stock', 1, 1),
('inventory_history', 'History', 'tab', 'manage_inventory', 'history', 1, 1);

-- =====================================================
-- Default Users (password: admin123 for all)
-- =====================================================
INSERT IGNORE INTO users (username, password, email, role_id, status) VALUES 
('admin', '$2y$10$0WbNJGMyuqyEmiqAoKRtcePHL/6/evqnj7nxNmVqEzd91DkhFGL0G', 'admin@bookshop.com', 1, 'Active');

INSERT IGNORE INTO users (username, password, email, role_id, status) VALUES 
('sales', '$2y$10$0WbNJGMyuqyEmiqAoKRtcePHL/6/evqnj7nxNmVqEzd91DkhFGL0G', 'sales@bookshop.com', 2, 'Active');

-- Default Page Access Settings
-- =====================================================
INSERT IGNORE INTO page_access_settings (page_name, page_display_name, access_type, parent_page, access_key, admin_enabled, sales_enabled) VALUES
('admin_dashboard', 'Dashboard', 'page', NULL, NULL, 1, 1),
('manage_books', 'Manage Books', 'page', NULL, NULL, 1, 1),
('add_book', 'Add Book', 'page', NULL, NULL, 1, 1),
('sell_books', 'Sell Books', 'page', NULL, NULL, 1, 1),
('manage_inventory', 'Inventory', 'page', NULL, NULL, 1, 1),
('view_sales', 'View Sales', 'page', NULL, NULL, 1, 1),
('manage_payments', 'Manage Payments', 'page', NULL, NULL, 1, 1),
('manage_suppliers', 'Suppliers', 'page', NULL, NULL, 1, 0),
('manage_settings', 'Settings', 'page', NULL, NULL, 1, 0);


-- =====================================================
-- Password Reset Tokens Table
-- =====================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);
