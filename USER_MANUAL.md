# BookHub User Manual

A comprehensive guide to using the BookHub School Bookshop Management System.

---

## Table of Contents

1. [Logging In](#logging-in)
2. [Admin Dashboard](#admin-dashboard)
3. [Managing Books](#managing-books)
4. [Selling Books](#selling-books)
5. [Inventory Management](#inventory-management)
6. [Viewing Sales & Reports](#viewing-sales--reports)
7. [Managing Suppliers](#managing-suppliers)
8. [System Settings](#system-settings)
9. [Sales User Features](#sales-user-features)
10. [Receipts](#receipts)
11. [Password Management](#password-management)

---

## Logging In

### Accessing the System

1. Open your web browser and navigate to: `http://localhost/bookhub/`
2. You will be redirected to the login page

### Login Credentials

| Role  | Email               | Password   |
|-------|---------------------|------------|
| Admin | admin@bookshop.com  | admin123   |
| Sales | sales@bookshop.com  | admin123   |

### Login Steps

1. Enter your email address in the **Email** field
2. Enter your password in the **Password** field
3. Click **Submit** to log in
4. You will be automatically redirected to your dashboard based on your role

### Forgot Password

If you forget your password:
1. Click **Forgot password?** on the login page
2. Enter your registered email address
3. Check your email for the reset link
4. Follow the instructions to create a new password

---

## Admin Dashboard

The Admin Dashboard is the main control panel for the system.

### Access

Navigate to: `admin/admin_dashboard.php` (or click "Dashboard" from sidebar)

### Features

1. **Statistics Cards** - View key metrics at a glance:
   - 📚 **Books**: Total number of books in the system
   - 📦 **Total Stock**: Combined inventory quantity
   - 💰 **Today's Sales**: Number of sales transactions today
   - 🏢 **Suppliers**: Total number of suppliers

2. **Quick Access Cards** - Click any card to navigate to that section:
   - Manage Books
   - Inventory
   - Today's Sales
   - Suppliers
   - Reports
   - Settings

3. **Welcome Message** - Shows your username at the top

---

## Managing Books

### Access

Navigate to: `admin/manage_books.php`

### Viewing Books

The page displays a table with all books containing:
- **#**: Row number
- **Title**: Book title
- **Author**: Book author
- **ISBN**: ISBN number
- **Category**: Book category (e.g., Fiction, Science)
- **Grade**: Grade level (e.g., Grade 1-12)
- **Price**: Book price
- **Stock**: Current inventory quantity
- **Actions**: Edit and Delete buttons

### Stock Status Indicators

- 🟢 **Green**: In stock (10+ items)
- 🟡 **Yellow**: Low stock (1-9 items)
- 🔴 **Red**: Out of stock (0 items)

### Searching and Filtering

1. **Search**: Enter text in the search box to find books by:
   - Title
   - Author
   - ISBN

2. **Filter by Category**: Select a category from the dropdown

3. **Filter by Grade**: Select a grade level from the dropdown

4. Click **Filter** to apply filters, or **Reset** to clear all filters

### Adding a New Book

1. Click the **➕ Add New Book** button
2. Fill in the book details:
   - **Title** * (required)
   - **Author**
   - **ISBN**
   - **Price** * (required)
   - **Description**
   - **Category** * (select from dropdown or add new)
   - **Grade** * (select from dropdown)
   - **Publisher**
   - **Book Cover Image** (optional - upload an image)
3. Click **Save Book** to add it to the inventory

### Editing a Book

1. Click the **✏️ Edit** button next to the book
2. Modify the desired fields
3. Click **Update Book** to save changes

### Deleting a Book

1. Click the **🗑️ Delete** button next to the book
2. Confirm the deletion in the popup dialog
3. The book will be permanently removed from the system

> ⚠️ **Warning**: Deleting a book also removes its inventory records. This action cannot be undone.

---

## Selling Books

### Access

Navigate to: `admin/sell_books.php` or `sales-user/sales_sell_books.php`

### Two Selling Methods

#### Method 1: Single Book Sale (Cart System)

This method allows you to build a cart with multiple books:

1. **Browse Books**: View all available books in the grid
2. **Search**: Use the search box to find specific books
3. **Add to Cart**:
   - Enter quantity in the number field
   - Click **Add** button
4. **Review Cart**: See all selected books on the right side
5. **Adjust Cart**:
   - Remove individual items using ❌
   - Click **Clear All** to empty the cart
6. **Complete Sale**:
   - Enter **Customer Name**
   - Click **Complete Sale** button

#### Method 2: Sell by Grade

This method is useful for bulk sales to a class or grade:

1. **Select Grade**: Choose a grade from the dropdown (e.g., Grade 10)
2. **View Books**: All books for that grade will be displayed
3. **Select Books**:
   - Check the boxes next to books to include
   - Use "Select All" to choose all books
4. **Set Quantities**: Enter quantity for each book
5. **Enter Buyer Details**:
   - Buyer Name * (required)
   - Payment Method (Cash, Credit Card, Debit Card, Mobile Money, Bank Transfer)
   - Notes (optional)
6. **Complete Sale**: Click **Complete Sale** button

### Today's Statistics

The top of the page shows today's sales stats:
- Number of transactions
- Books sold
- Total revenue

### Recent Sales

The bottom of the page shows the 10 most recent sales with:
- Date/Time
- Book Title
- Grade
- Quantity
- Total Amount
- Payment Method
- Status

---

## Inventory Management

### Access

Navigate to: `admin/manage_inventory.php`

### Features

1. **Inventory List**: View all books with their current stock levels
2. **Add Stock**: Increase inventory for existing books
3. **Stock History**: Track all inventory changes

### Viewing Inventory

The inventory table shows:
- Book Title
- Author
- Category
- Grade
- Current Stock
- Last Updated Date

### Adding Stock

1. Find the book you want to restock
2. Click **Add Stock** or **+** button
3. Enter the quantity to add
4. Add a note (optional) - e.g., "Received from supplier"
5. Click **Add Stock** to update inventory

### Stock History

View the history tab to see:
- Date of change
- Book title
- Quantity change (+/-)
- Notes
- Updated by (username)

---

## Viewing Sales & Reports

### Access

Navigate to: `admin/view_sales.php`

### Features

#### Summary Statistics

- **Transactions**: Total number of sales
- **Books Sold**: Total quantity of books sold
- **Total Revenue**: Total money earned
- **Avg. Sale**: Average transaction value

#### Payment Methods Breakdown

View sales grouped by payment method:
- Cash
- Credit Card
- Debit Card
- Mobile Money
- Bank Transfer

Each method shows:
- Number of transactions
- Total amount

#### Filtering Sales

Use the filters to narrow down results:

1. **Date Range**: Select "From" and "To" dates
2. **Payment Method**: Filter by specific payment type
3. **Search**: Search by:
   - Buyer name
   - Book title
   - Receipt number

4. Click **Filter** to apply, or **Reset** to clear

#### Sales Table

The main table shows all sales with columns:
- **Receipt #**: Click to view/print receipt
- **Date**: Transaction date and time
- **Buyer**: Customer name
- **Book**: Book title sold
- **Grade**: Grade level
- **Qty**: Quantity sold
- **Total**: Total amount
- **Payment**: Payment method used
- **Status**: Transaction status (usually "Completed")

---

## Managing Suppliers

### Access

Navigate to: `admin/manage_suppliers.php`

### Adding a New Supplier

1. Click **➕ Add Supplier** button
2. Fill in supplier details:
   - **Name** * (required)
   - Contact Person
   - Email
   - Phone
   - Address
3. Click **Save Supplier**

### Editing a Supplier

1. Click **Edit** button next to the supplier
2. Modify the details
3. Click **Update Supplier**

### Deleting a Supplier

1. Click **Delete** button
2. Confirm the deletion

### Linking Books to Suppliers

1. Edit a supplier
2. Use the book selection to associate books with this supplier
3. Save the changes

---

## System Settings

### Access

Navigate to: `admin/manage_settings.php`

### Settings Tabs

#### 1. General Settings (🏪 Shop Information)

Configure basic shop details:
- **Shop Name**: Your bookshop name
- **Currency**: Select from USD ($), EUR (€), GBP (£), GHS (₵)
- **Shop Address**: Physical address
- Click **Save Settings**

#### 2. Grades (🎓)

Manage available grade levels:
- Enter grades separated by commas
- Example: Grade 1,Grade 2,Grade 3
- Click **Save Grades**

#### 3. Categories (🏷️)

Manage book categories:
- Enter categories separated by commas
- Example: Fiction,Non-Fiction,Science,Mathematics
- Click **Save Categories**

#### 4. Display (🖥️)

Control what's shown on dashboards:
- **Show Statistics Cards**: Toggle to show/hide stats on dashboard
- Click **Save Settings**

#### 5. Access Control (🔐)

Manage what Sales users can access:

**Pages Tab**:
- Toggle each page ON/OFF for Sales users
- Click **Save All Changes**

**Nested Controls**:
- Some pages have tabs and actions that can be individually controlled

**Bulk Actions**:
- ✓ Enable All: Give Sales access to everything
- ✗ Disable All: Remove most Sales access (keep basic features)
- ↺ Reset: Revert to last saved state

#### 6. Users (👥)

**Adding a New User**:
1. Fill in the form:
   - Username *
   - Email *
   - Password *
   - Role (Admin or Sales)
2. Click **Add User**

**Managing Existing Users**:
- View all users in the table
- See: ID, Username, Email, Role, Status, Created Date
- Delete users (cannot delete yourself)

---

## Sales User Features

### Access

Sales users access their dashboard at: `sales-user/sales_dashboard.php`

### Available Features

1. **Dashboard**: View sales statistics
2. **Sell Books**: Process sales (cart-based and by-grade methods)
3. **Manage Books**: View and search books (read-only for Sales)
4. **View Sales**: View sales history

### Sales Dashboard Statistics

- Total Books in system
- Today's Sales amount
- Transactions Today
- Low Stock Items count

### Quick Actions

- 💰 **Sell Books**: Open sales page
- ➕ **Add Book**: Add new book (if enabled)
- 📚 **View Books**: Browse book inventory
- 🧾 **View Sales**: See sales history

### Differences from Admin

- Cannot access Settings
- Cannot manage Suppliers
- Cannot delete books
- May have limited page access based on Admin's Access Control settings

---

## Receipts

### Viewing a Receipt

1. After completing a sale, you're automatically redirected to the receipt
2. Or find a sale in "View Sales" and click the Receipt # link

### Receipt Information

The receipt includes:
- 🏪 Shop Name and Address
- 📅 Date/Time of sale
- 🧾 Receipt Number (unique identifier)
- 👤 Customer Name
- 📚 Items Purchased:
  - Book Title
  - Quantity
  - Unit Price
  - Subtotal
- 💰 **Total Amount**
- 💳 Payment Method
- 📝 Notes (if any)

### Printing a Receipt

- Use your browser's print function (Ctrl+P or Cmd+P)
- The print layout is optimized for standard receipt printers

---

## Password Management

### Changing Your Password

1. Log in to the system
2. Navigate to **Settings** → **Users** tab (Admin only)
3. Find your user account
4. Note: Contact an Admin to change passwords, or use the forgot password feature

### Password Requirements

- Minimum 6 characters (recommended: 8+)
- Use a mix of letters and numbers
- Change passwords regularly for security

### Session Timeout

- The system automatically logs you out after 45 minutes of inactivity
- If your session expires, you'll be redirected to the login page with a message

---

## Security Best Practices

1. **Logout Always**: Click logout when leaving the computer
2. **Don't Share Credentials**: Keep your username and password private
3. **Change Default Passwords**: Update the default admin123 password immediately
4. **Review Access Settings**: Regularly check who has access to what
5. **Monitor Sales Reports**: Review sales data for any unusual activity

---

## Troubleshooting

### Common Issues

#### Can't Log In
- Check your email and password are correct
- Clear browser cache and cookies
- Contact Admin to verify your account is active

#### Can't See Certain Pages
- You may not have permission (especially for Sales users)
- Contact Admin to request access

#### Database Connection Error
- Ensure MySQL is running on port 3307
- Check credentials in `config/db.php`
- Verify database exists in phpMyAdmin

#### Books Not Showing in Sales
- Check if the book has inventory (stock > 0)
- Verify the book is assigned to the correct grade

#### Receipt Not Found
- Verify the receipt number is correct
- Check the sale was completed successfully

---

## Support

For additional help:
- Contact your system administrator
- Refer to README.md for technical setup details
- Check the database for any error messages

---

*Manual Version 1.0 - BookHub School Bookshop Management System*
*Developed by Avid Solutions*
