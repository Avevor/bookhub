# Offline Book Inventory System - Implementation Plan

## 1. Information Gathered

### Current System Architecture:
- **Backend**: PHP with MySQL database (XAMPP)
- **Frontend**: HTML, CSS, JavaScript with jQuery/Bootstrap
- **Authentication**: PHP sessions with MySQL user table
- **Data Storage**: MySQL tables (users, books, inventory, payments, suppliers, etc.)

### Online Dependencies Identified:
1. `config/db.php` - MySQL connection required for every page
2. `pages/login.php` - Database authentication
3. `includes/session_check.php` - Session validation
4. External CDN: Font Awesome, jQuery, Bootstrap
5. XAMPP server required to run PHP

## 2. Plan: Convert to Offline-First PWA

### Approach: Progressive Web App (PWA) with IndexedDB
This will allow the system to:
- Work completely offline
- Store data locally in the browser
- Sync when connection is restored (optional future feature)

### Implementation Steps:

#### Phase 1: Convert PHP to Static HTML/JS
- [ ] Create `index.html` - Landing/login page
- [ ] Create `admin.html` - Admin dashboard (convert from PHP)
- [ ] Create `sales.html` - Sales dashboard
- [ ] Create `inventory.html` - Inventory management
- [ ] Create `books.html` - Book management

#### Phase 2: Implement Local Data Storage
- [ ] Create `js/db.js` - IndexedDB wrapper for local storage
- [ ] Create `js/auth.js` - Local authentication system
- [ ] Create `js/sync.js` - Optional sync functionality

#### Phase 3: Replace External Dependencies
- [ ] Download and include local copies of:
  - jQuery (for compatibility)
  - Bootstrap CSS/JS
  - Font Awesome
- [ ] Convert external CDN links to local files

#### Phase 4: Convert Database Schema
- [ ] Export MySQL schema to JSON
- [ ] Create seed data for offline use
- [ ] Implement local data models

#### Phase 5: Convert Authentication
- [ ] Implement client-side login with hashed passwords
- [ ] Store user sessions in localStorage
- [ ] Role-based access control (Admin/Sales)

### File Changes Required:

| Original File | New File | Purpose |
|--------------|----------|---------|
| `pages/login.php` | `index.html` | Login page |
| `admin/admin_dashboard.php` | `admin.html` | Admin dashboard |
| `admin/manage_books.php` | `books.html` | Book management |
| `admin/manage_inventory.php` | `inventory.html` | Inventory |
| `admin/sell_books.php` | `sell.html` | Sales interface |
| `config/db.php` | `js/db.js` | Local database |
| `includes/session_check.php` | `js/auth.js` | Auth system |

### Dependent Files to Edit:
- All PHP files converted to HTML
- All database queries replaced with IndexedDB
- CSS/JS linked locally instead of CDN

### Followup Steps:
1. Test offline functionality
2. Create data export/import feature
3. Add sync capability (future enhancement)
4. Create installer for distribution

## 3. Estimated Complexity: Medium-High

This transformation requires:
- Converting ~20+ PHP files to HTML
- Implementing IndexedDB for local storage
- Rewriting authentication logic
- Downloading and bundling external dependencies
