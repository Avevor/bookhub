/**
 * Local Database (IndexedDB)
 * Provides offline storage capabilities for the Book Hub
 */

const LocalDB = {
    dbName: 'bookhubDB',
    dbVersion: 1,
    db: null,
    
    /**
     * Initialize the local database
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => {
                console.error('[LocalDB] Failed to open database:', request.error);
                reject(request.error);
            };
            
            request.onsuccess = () => {
                this.db = request.result;
                console.log('[LocalDB] Database opened successfully');
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                console.log('[LocalDB] Upgrading database...');
                
                // Create object stores (tables)
                if (!db.objectStoreNames.contains('users')) {
                    const userStore = db.createObjectStore('users', { keyPath: 'user_id', autoIncrement: true });
                    userStore.createIndex('email', 'email', { unique: true });
                    userStore.createIndex('username', 'username', { unique: true });
                }
                
                if (!db.objectStoreNames.contains('books')) {
                    const bookStore = db.createObjectStore('books', { keyPath: 'book_id', autoIncrement: true });
                    bookStore.createIndex('isbn', 'isbn', { unique: true });
                    bookStore.createIndex('category', 'category', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('inventory')) {
                    const inventoryStore = db.createObjectStore('inventory', { keyPath: 'inventory_id', autoIncrement: true });
                    inventoryStore.createIndex('book_id', 'book_id', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('suppliers')) {
                    const supplierStore = db.createObjectStore('suppliers', { keyPath: 'supplier_id', autoIncrement: true });
                }
                
                if (!db.objectStoreNames.contains('payments')) {
                    const paymentStore = db.createObjectStore('payments', { keyPath: 'payment_id', autoIncrement: true });
                    paymentStore.createIndex('receipt_number', 'receipt_number', { unique: true });
                    paymentStore.createIndex('payment_date', 'payment_date', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('system_settings')) {
                    const settingsStore = db.createObjectStore('system_settings', { keyPath: 'setting_id', autoIncrement: true });
                    settingsStore.createIndex('setting_key', 'setting_key', { unique: true });
                }
                
                if (!db.objectStoreNames.contains('book_suppliers')) {
                    db.createObjectStore('book_suppliers', { keyPath: ['book_id', 'supplier_id'] });
                }
                
                if (!db.objectStoreNames.contains('inventory_history')) {
                    const historyStore = db.createObjectStore('inventory_history', { keyPath: 'history_id', autoIncrement: true });
                    historyStore.createIndex('book_id', 'book_id', { unique: false });
                }
                
                console.log('[LocalDB] Database schema created');
            };
        });
    },
    
    /**
     * Add a record to a store
     */
    async add(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.add(data);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Update a record in a store
     */
    async put(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Get a record by key
     */
    async get(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(key);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Get all records from a store
     */
    async getAll(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Delete a record by key
     */
    async delete(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Query records by index
     */
    async getByIndex(storeName, indexName, value) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const index = store.index(indexName);
            const request = index.getAll(value);
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Query with filter
     */
    async where(storeName, filterFn) {
        const all = await this.getAll(storeName);
        return all.filter(filterFn);
    },
    
    /**
     * Clear all data from a store
     */
    async clear(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.clear();
            
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Get count of records
     */
    async count(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.count();
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },
    
    /**
     * Bulk add records
     */
    async bulkAdd(storeName, dataArray) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            dataArray.forEach(data => {
                store.add(data);
            });
            
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        });
    },
    
    /**
     * Get the next auto-increment ID
     */
    async getNextId(storeName) {
        const count = await this.count(storeName);
        return count + 1;
    },
    
    /**
     * Seed initial data
     */
    async seedData(data) {
        try {
            // Clear existing data
            await this.clear('users');
            await this.clear('books');
            await this.clear('inventory');
            await this.clear('suppliers');
            await this.clear('payments');
            await this.clear('system_settings');
            await this.clear('book_suppliers');
            await this.clear('inventory_history');
            
            // Add new data
            if (data.users) await this.bulkAdd('users', data.users);
            if (data.books) await this.bulkAdd('books', data.books);
            if (data.inventory) await this.bulkAdd('inventory', data.inventory);
            if (data.suppliers) await this.bulkAdd('suppliers', data.suppliers);
            if (data.system_settings) await this.bulkAdd('system_settings', data.system_settings);
            if (data.book_suppliers) await this.bulkAdd('book_suppliers', data.book_suppliers);
            if (data.inventory_history) await this.bulkAdd('inventory_history', data.inventory_history);
            
            console.log('[LocalDB] Data seeded successfully');
            return true;
        } catch (error) {
            console.error('[LocalDB] Failed to seed data:', error);
            return false;
        }
    }
};

// Export for use
window.LocalDB = LocalDB;
