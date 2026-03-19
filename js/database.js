/**
 * Hybrid Database Layer
 * Manages data operations for both online (MySQL) and offline (IndexedDB) modes
 */

const Database = {
    /**
     * Initialize the database layer
     */
    async init() {
        await LocalDB.init();
        await this.seedDefaultDataIfNeeded();
        console.log('[Database] Initialized');
    },
    
    /**
     * Seed default data for first-time offline use
     */
    async seedDefaultDataIfNeeded() {
        const hasBooks = await LocalDB.count('books');
        if (hasBooks === 0) {
            try {
                const response = await fetch('data/seed-data.json');
                if (response.ok) {
                    const data = await response.json();
                    await LocalDB.seedData(data);
                    console.log('[Database] Seed data loaded');
                }
            } catch (e) {
                console.log('[Database] Using inline defaults');
                await this.seedInlineData();
            }
        }
    },
    
    /**
     * Seed inline data if JSON file not available
     */
    async seedInlineData() {
        const defaultData = {
            users: [
                { user_id: 1, username: 'admin', password: 'admin123', email: 'admin@bookshop.com', role_id: 1, status: 'Active' },
                { user_id: 2, username: 'sales', password: 'sales123', email: 'sales@bookshop.com', role_id: 2, status: 'Active' }
            ],
            books: [
                { book_id: 1, title: 'To Kill a Mockingbird', author: 'Harper Lee', isbn: '978-0-06-112008-4', price: 15.99, description: 'A classic novel', category: 'Fiction', grade: 'Grade 10', publisher: 'HarperCollins', created_at: new Date().toISOString() },
                { book_id: 2, title: '1984', author: 'George Orwell', isbn: '978-0-452-28423-4', price: 12.99, description: 'Dystopian novel', category: 'Fiction', grade: 'Grade 11', publisher: 'Penguin Books', created_at: new Date().toISOString() },
                { book_id: 3, title: 'The Great Gatsby', author: 'F. Scott Fitzgerald', isbn: '978-0-7432-7356-5', price: 10.99, description: 'Story of the Jazz Age', category: 'Fiction', grade: 'Grade 12', publisher: 'Scribner', created_at: new Date().toISOString() },
                { book_id: 4, title: 'Pride and Prejudice', author: 'Jane Austen', isbn: '978-0-14-143951-8', price: 9.99, description: 'Romantic novel', category: 'Fiction', grade: 'Grade 9', publisher: 'Penguin Classics', created_at: new Date().toISOString() },
                { book_id: 5, title: 'Harry Potter and the Philosopher\'s Stone', author: 'J.K. Rowling', isbn: '978-0-7475-3269-9', price: 20.99, description: 'First book in Harry Potter series', category: 'Fantasy', grade: 'Grade 8', publisher: 'Bloomsbury', created_at: new Date().toISOString() }
            ],
            inventory: [
                { inventory_id: 1, book_id: 1, quantity: 50, last_updated: new Date().toISOString() },
                { inventory_id: 2, book_id: 2, quantity: 30, last_updated: new Date().toISOString() },
                { inventory_id: 3, book_id: 3, quantity: 40, last_updated: new Date().toISOString() },
                { inventory_id: 4, book_id: 4, quantity: 25, last_updated: new Date().toISOString() },
                { inventory_id: 5, book_id: 5, quantity: 60, last_updated: new Date().toISOString() }
            ],
            suppliers: [
                { supplier_id: 1, name: 'BookWorld Distributors', contact_person: 'John Smith', email: 'john@bookworld.com', phone: '+1234567890', address: '123 Book St', created_at: new Date().toISOString() },
                { supplier_id: 2, name: 'Literary Supplies Inc', contact_person: 'Jane Doe', email: 'jane@literary.com', phone: '+1234567891', address: '456 Page Ave', created_at: new Date().toISOString() }
            ],
            system_settings: [
                { setting_id: 1, setting_key: 'shop_name', setting_value: 'School Bookshop' },
                { setting_id: 2, setting_key: 'shop_address', setting_value: '123 Education St' },
                { setting_id: 3, setting_key: 'shop_phone', setting_value: '+1-234-567-8900' },
                { setting_id: 4, setting_key: 'shop_email', setting_value: 'info@schoolbookshop.com' },
                { setting_id: 5, setting_key: 'currency', setting_value: 'USD' },
                { setting_id: 6, setting_key: 'school_logo', setting_value: '../images/school.png' }
            ]
        };
        await LocalDB.seedData(defaultData);
    },
    
    // BOOKS
    async getBooks() {
        if (ConnectionDetector.getStatus()) {
            try { return await this.getBooksOnline(); } catch (e) { console.log('[DB] Using offline'); }
        }
        return await LocalDB.getAll('books');
    },
    
    async getBooksOnline() {
        const response = await fetch('api/books.php?action=list');
        return await response.json();
    },
    
    async getBook(id) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/books.php?action=get&id=' + id);
                return await response.json();
            } catch (e) {}
        }
        return await LocalDB.get('books', parseInt(id));
    },
    
    async addBook(bookData) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/books.php?action=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(bookData)
                });
                return await response.json();
            } catch (e) {}
        }
        const id = await LocalDB.getNextId('books');
        const book = { book_id: id, created_at: new Date().toISOString() };
        Object.assign(book, bookData);
        await LocalDB.add('books', book);
        await LocalDB.add('inventory', { book_id: id, quantity: 0, last_updated: new Date().toISOString() });
        return { success: true, book_id: id };
    },
    
    async updateBook(id, bookData) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/books.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ book_id: id, ...bookData })
                });
                return await response.json();
            } catch (e) {}
        }
        const existing = await LocalDB.get('books', parseInt(id));
        if (existing) {
            const updated = { ...existing, ...bookData, updated_at: new Date().toISOString() };
            await LocalDB.put('books', updated);
        }
        return { success: true };
    },
    
    async deleteBook(id) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/books.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ book_id: id })
                });
                return await response.json();
            } catch (e) {}
        }
        await LocalDB.delete('books', parseInt(id));
        return { success: true };
    },
    
    // INVENTORY
    async getInventory() {
        if (ConnectionDetector.getStatus()) {
            try { return await this.getInventoryOnline(); } catch (e) {}
        }
        return await LocalDB.getAll('inventory');
    },
    
    async getInventoryOnline() {
        const response = await fetch('api/inventory.php?action=list');
        return await response.json();
    },
    
    async getBookInventory(bookId) {
        const items = await LocalDB.getByIndex('inventory', 'book_id', parseInt(bookId));
        return items.length > 0 ? items[0] : null;
    },
    
    async updateInventory(bookId, quantityChange, notes) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/inventory.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ book_id: bookId, quantity_change: quantityChange, notes: notes })
                });
                return await response.json();
            } catch (e) {}
        }
        const inventory = await this.getBookInventory(bookId);
        if (inventory) {
            inventory.quantity = Math.max(0, inventory.quantity + quantityChange);
            inventory.last_updated = new Date().toISOString();
            await LocalDB.put('inventory', inventory);
            await LocalDB.add('inventory_history', {
                book_id: parseInt(bookId),
                quantity_change: quantityChange,
                notes: notes,
                updated_by: (Auth.getUser() && Auth.getUser().username) || 'unknown',
                created_at: new Date().toISOString()
            });
        }
        return { success: true };
    },
    
    // PAYMENTS/SALES
    async getPayments() {
        if (ConnectionDetector.getStatus()) {
            try { return await this.getPaymentsOnline(); } catch (e) {}
        }
        return await LocalDB.getAll('payments');
    },
    
    async getPaymentsOnline() {
        const response = await fetch('api/payments.php?action=list');
        return await response.json();
    },
    
    async getTodayPayments() {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/payments.php?action=today');
                return await response.json();
            } catch (e) {}
        }
        const all = await LocalDB.getAll('payments');
        const today = new Date().toISOString().split('T')[0];
        return all.filter(function(p) { return p.payment_date && p.payment_date.split('T')[0] === today; });
    },
    
    async createPayment(paymentData) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/payments.php?action=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(paymentData)
                });
                return await response.json();
            } catch (e) {}
        }
        const id = await LocalDB.getNextId('payments');
        const receiptNumber = 'REC-' + Date.now();
        const payment = {
            payment_id: id,
            receipt_number: receiptNumber,
            book_id: paymentData.book_id,
            buyer_id: paymentData.buyer_id || null,
            buyer_name: paymentData.buyer_name,
            quantity: paymentData.quantity,
            total_amount: paymentData.total_amount,
            payment_method: paymentData.payment_method || 'Cash',
            payment_date: new Date().toISOString(),
            status: 'Completed',
            notes: paymentData.notes || ''
        };
        await LocalDB.add('payments', payment);
        await this.updateInventory(paymentData.book_id, -paymentData.quantity, 'Sale');
        return { success: true, payment_id: id, receipt_number: receiptNumber };
    },
    
    // SUPPLIERS
    async getSuppliers() {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/suppliers.php?action=list');
                return await response.json();
            } catch (e) {}
        }
        return await LocalDB.getAll('suppliers');
    },
    
    async addSupplier(supplierData) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/suppliers.php?action=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(supplierData)
                });
                return await response.json();
            } catch (e) {}
        }
        const id = await LocalDB.getNextId('suppliers');
        const supplier = { 
            supplier_id: id, 
            created_at: new Date().toISOString() 
        };
        Object.assign(supplier, supplierData);
        await LocalDB.add('suppliers', supplier);
        return { success: true, supplier_id: id };
    },
    
    async updateSupplier(id, supplierData) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/suppliers.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ supplier_id: id, ...supplierData })
                });
                return await response.json();
            } catch (e) {}
        }
        const existing = await LocalDB.get('suppliers', parseInt(id));
        if (existing) {
            const updated = { ...existing, ...supplierData, updated_at: new Date().toISOString() };
            await LocalDB.put('suppliers', updated);
        }
        return { success: true };
    },
    
    async deleteSupplier(id) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/suppliers.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ supplier_id: id })
                });
                return await response.json();
            } catch (e) {}
        }
        await LocalDB.delete('suppliers', parseInt(id));
        return { success: true };
    },
    
    // DASHBOARD STATS
    async getDashboardStats() {
        const books = await this.getBooks();
        const inventory = await this.getInventory();
        const todayPayments = await this.getTodayPayments();
        const suppliers = await this.getSuppliers();
        
        const totalStock = inventory.reduce(function(sum, item) { return sum + (item.quantity || 0); }, 0);
        const todaySales = todayPayments.reduce(function(sum, p) { return sum + parseFloat(p.total_amount || 0); }, 0);
        
        return {
            booksCount: books.length,
            inventoryCount: totalStock,
            todaySalesCount: todayPayments.length,
            todaySalesAmount: todaySales,
            suppliersCount: suppliers.length
        };
    },
    
    // SETTINGS
    async getSetting(key) {
        if (ConnectionDetector.getStatus()) {
            try {
                const response = await fetch('api/settings.php?key=' + key);
                const data = await response.json();
                return data.value;
            } catch (e) {}
        }
        const settings = await LocalDB.where('system_settings', function(s) { return s.setting_key === key; });
        return settings.length > 0 ? settings[0].setting_value : null;
    }
};

window.Database = Database;
