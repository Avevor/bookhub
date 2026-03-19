# BookHub - School Bookshop Management System

A comprehensive PHP-based inventory and sales management system designed for school bookshop operations.

## Features

### User Roles
- **Admin**: Full access to all features including settings, suppliers, and reports
- **Sales User**: Limited access to sell books, manage inventory, and view sales

### Core Functionality
- 📚 **Book Management**: Add, edit, delete books with details (title, author, ISBN, price, category, grade, publisher)
- 📦 **Inventory Management**: Track stock levels, view inventory history
- 💰 **Sales Processing**: Sell books, generate receipts, track payments
- 🏢 **Supplier Management**: Manage book suppliers
- 📊 **Reports**: View sales reports and analytics
- ⚙️ **Settings**: System configuration (shop details, currency, grades, categories)

## Tech Stack

- **Backend**: PHP 7+
- **Database**: MySQL (XAMPP)
- **Frontend**: HTML5, CSS3, Bootstrap, JavaScript (jQuery)
- **Server**: Apache (XAMPP)

## Prerequisites

1. XAMPP (or any Apache + MySQL + PHP stack)
2. Web browser (Chrome, Firefox, Edge, etc.)
3. MySQL port: 3307 (default XAMPP configuration)

## Installation

### 1. Start XAMPP Services
- Start Apache and MySQL services in XAMPP Control Panel

### 2. Import Database
1. Open phpMyAdmin (http://localhost:3307/phpmyadmin)
2. Create a new database named `bookhubdb`
3. Import the SQL file: `database/bookhubdb.sql`

### 3. Configure Database Connection
The database configuration is in `config/db.php`. Default settings:
```
php
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bookhubdb';
$db_port = 3307;
```

### 4. Access the Application
Open your browser and navigate to:
```
http://localhost/bookhub/
```

## Default Login Credentials

| Role  | Email               | Password   |
|-------|---------------------|------------|
| Admin | admin@bookshop.com  | admin123   |
| Sales | sales@bookshop.com  | admin123   |

> ⚠️ **Security Note**: Change the default passwords after first login!

## Project Structure

```
bookhub/
├── admin/                    # Admin dashboard and management
│   ├── admin_dashboard.php   # Main admin dashboard
│   ├── add_book.php          # Add new book
│   ├── edit_book.php         # Edit book details
│   ├── manage_books.php      # Book listing
│   ├── manage_inventory.php  # Inventory management
│   ├── manage_payments.php   # Payment records
│   ├── manage_settings.php   # System settings
│   ├── manage_suppliers.php  # Supplier management
│   ├── sell_books.php        # Sales processing
│   └── view_sales.php        # Sales reports
├── sales-user/               # Sales user dashboard
│   ├── sales_dashboard.php   # Sales dashboard
│   ├── sales_add_book.php    # Add new book
│   ├── sales_manage_books.php# Book listing
│   ├── sales_sell_books.php  # Sales processing
│   └── sales_view_sales.php  # View sales
├── config/
│   └── db.php                # Database configuration
├── database/
│   └── bookhubdb.sql         # Database schema and sample data
├── includes/
│   ├── header.php            # Page header
│   ├── footer.php            # Page footer
│   ├── sidebar.php           # Navigation sidebar
│   ├── session_config.php    # Session configuration
│   ├── session_check.php     # Session validation
│   └── permission_helper.php # Role-based access
├── assets/
│   ├── css/                  # Stylesheets
│   └── images/               # Image assets
├── pages/
│   ├── login.php             # Login page
│   ├── logout.php            # Logout handler
│   ├── forgot_password.php   # Password reset
│   └── reset_password.php    # Password reset handler
├── js/                       # JavaScript files
└── uploads/                  # Uploaded files
    ├── logos/                # Shop logos
    └── photos/               # Book photos
```

## User Guide

### Admin Features

1. **Dashboard**: View statistics (total books, inventory, today's sales, suppliers)
2. **Manage Books**: Add, edit, delete books with full details
3. **Inventory**: View and manage stock levels
4. **Sell Books**: Process sales and generate receipts
5. **View Sales**: Access sales reports and analytics
6. **Suppliers**: Manage supplier information
7. **Settings**: Configure shop details, currency, grades, categories

### Sales User Features

1. **Dashboard**: View sales statistics
2. **Sell Books**: Process customer purchases
3. **Manage Books**: View and edit book details
4. **View Sales**: View sales history

## Database Schema

### Key Tables
- `users` - System users (admin, sales)
- `roles` - User roles (Admin, Sales)
- `books` - Book inventory
- `inventory` - Stock quantities
- `suppliers` - Supplier information
- `payments` - Sales transactions
- `system_settings` - Configuration settings
- `inventory_history` - Stock change history
- `page_access_settings` - Role-based access control

## Currency Settings

Supported currencies:
- USD ($)
- EUR (€)
- GBP (£)
- GHS (₵)

Configure currency in: **Admin → Settings → Display**

## Security Features

- Session management with timeout
- Role-based access control
- SQL injection prevention (prepared statements)
- Password hashing (bcrypt)
- CSRF protection

## Troubleshooting

### Database Connection Error
- Ensure MySQL is running on port 3307
- Check credentials in `config/db.php`
- Verify database `bookhubdb` exists

### Session Issues
- Clear browser cache/cookies
- Check session configuration in `includes/session_config.php`

### Permission Denied
- Ensure user has appropriate role
- Check page access settings in database

## Development

### Creating a New Page
1. Create PHP file in appropriate folder (admin/ or sales-user/)
2. Include session check: `include('../includes/session_check.php');`
3. Include database: `include('../config/db.php');`
4. Check permissions if needed

### Sample Code Structure
```
php
<?php
include('../includes/session_config.php');
include('../includes/session_check.php');
requireValidSession();
include('../config/db.php');

// Your code here
?>
<!DOCTYPE html>
<html>
<head>
    <title>Page Title</title>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    <?php include('../includes/sidebar.php'); ?>
    
    <!-- Page Content -->
    
    <?php include('../includes/footer.php'); ?>
</body>
</html>
```

## License

This project is developed by **Avid Solutions**.

## Support

For issues or questions, please contact the system administrator.
