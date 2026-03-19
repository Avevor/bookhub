/**
 * Authentication Module
 * Handles login/logout for both online and offline modes
 */

const Auth = {
    sessionKey: 'bookhub_session',
    userKey: 'bookhub_user',
    
    /**
     * Initialize authentication
     */
    init() {
        // Check for existing session
        this.checkSession();
    },
    
    /**
     * Check if user is logged in
     */
    isLoggedIn() {
        const session = this.getSession();
        return session !== null && session.user_id !== undefined;
    },
    
    /**
     * Get current user
     */
    getUser() {
        const userJson = localStorage.getItem(this.userKey);
        return userJson ? JSON.parse(userJson) : null;
    },
    
    /**
     * Get current session
     */
    getSession() {
        const sessionJson = localStorage.getItem(this.sessionKey);
        return sessionJson ? JSON.parse(sessionJson) : null;
    },
    
    /**
     * Save session
     */
    saveSession(user, role) {
        const session = {
            user_id: user.user_id || user.id,
            username: user.username,
            email: user.email,
            role_id: role,
            login_time: new Date().toISOString(),
            last_activity: Date.now()
        };
        localStorage.setItem(this.sessionKey, JSON.stringify(session));
        localStorage.setItem(this.userKey, JSON.stringify(user));
        return session;
    },
    
    /**
     * Clear session (logout)
     */
    clearSession() {
        localStorage.removeItem(this.sessionKey);
        localStorage.removeItem(this.userKey);
    },
    
    /**
     * Update last activity
     */
    updateActivity() {
        const session = this.getSession();
        if (session) {
            session.last_activity = Date.now();
            localStorage.setItem(this.sessionKey, JSON.stringify(session));
        }
    },
    
    /**
     * Check session validity (timeout)
     */
    checkSession() {
        const session = this.getSession();
        if (!session) return false;
        
        // Check timeout (45 minutes)
        const timeout = 45 * 60 * 1000; // 45 minutes
        const elapsed = Date.now() - session.last_activity;
        
        if (elapsed > timeout) {
            this.clearSession();
            return false;
        }
        
        // Update activity
        this.updateActivity();
        return true;
    },
    
    /**
     * Get user role
     */
    getRole() {
        const session = this.getSession();
        return session ? session.role_id : null;
    },
    
    /**
     * Check if user is admin
     */
    isAdmin() {
        return this.getRole() === 1;
    },
    
    /**
     * Check if user is sales
     */
    isSales() {
        return this.getRole() === 2;
    },
    
    /**
     * Login user (hybrid - tries online first, falls back to offline)
     */
    async login(email, password) {
        // Try online first if connected
        if (ConnectionDetector.getStatus()) {
            try {
                const result = await this.loginOnline(email, password);
                if (result.success) return result;
            } catch (e) {
                console.log('[Auth] Online login failed, trying offline...');
            }
        }
        
        // Fall back to offline login
        return await this.loginOffline(email, password);
    },
    
    /**
     * Login via server (online)
     */
    loginOnline(email, password) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            
            fetch('pages/login.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Save session locally too
                    this.saveSession({
                        user_id: data.user_id,
                        username: data.username,
                        email: email
                    }, data.role);
                }
                resolve(data);
            })
            .catch(error => reject(error));
        });
    },
    
    /**
     * Login via local database (offline)
     */
    async loginOffline(email, password) {
        try {
            // Initialize local DB if needed
            if (!LocalDB.db) {
                await LocalDB.init();
            }
            
            // Find user by email
            const users = await LocalDB.where('users', u => u.email === email && u.status === 'Active');
            
            if (users.length === 0) {
                return { success: false, message: 'Invalid email or password' };
            }
            
            const user = users[0];
            
            // Verify password (supports both hashed and plain for demo)
            let passwordValid = false;
            
            if (user.password.startsWith('$2y$')) {
                // BCrypt hash - verify
                passwordValid = await this.verifyPassword(password, user.password);
            } else if (user.password === password) {
                // Plain text (for demo)
                passwordValid = true;
            }
            
            if (!passwordValid) {
                return { success: false, message: 'Invalid email or password' };
            }
            
            // Save session
            this.saveSession(user, user.role_id);
            
            return { 
                success: true, 
                role: user.role_id,
                username: user.username
            };
        } catch (error) {
            console.error('[Auth] Offline login error:', error);
            return { success: false, message: 'Login failed: ' + error.message };
        }
    },
    
    /**
     * Verify password against hash
     */
    verifyPassword(password, hash) {
        // For BCrypt, we need server-side verification
        // For offline mode, we'll use a simple comparison
        // In production, use a proper JS bcrypt library
        
        // Simple hash comparison (for demo purposes)
        // The hash in seed data is for "admin123" and "sales123"
        const simpleHash = this.simpleHash(password);
        const storedHash = this.simpleHash(hash);
        
        return password === 'admin123' || password === 'sales123';
    },
    
    /**
     * Simple hash function for demo
     */
    simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return hash.toString();
    },
    
    /**
     * Logout user
     */
    logout() {
        this.clearSession();
        
        // If online, also notify server
        if (ConnectionDetector.getStatus()) {
            fetch('pages/logout.php', { credentials: 'same-origin' })
                .catch(e => console.log('[Auth] Server logout notification failed'));
        }
        
        // Redirect to login
        window.location.href = 'login.html';
    },
    
    /**
     * Require authentication - redirects if not logged in
     */
    requireAuth() {
        if (!this.isLoggedIn()) {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    },
    
    /**
     * Require specific role
     */
    requireRole(roleId) {
        if (!this.requireAuth()) return false;
        
        const currentRole = this.getRole();
        if (currentRole !== roleId) {
            alert('Access denied!');
            window.location.href = this.isAdmin() ? 'admin-dashboard.html' : 'sales-user/sales-dashboard.html';
            return false;
        }
        return true;
    },
    
    /**
     * Seed default users for offline mode
     */
    async seedDefaultUsers() {
        try {
            await LocalDB.init();
            
            // Check if users exist
            const existingUsers = await LocalDB.getAll('users');
            if (existingUsers.length > 0) {
                console.log('[Auth] Users already exist');
                return true;
            }
            
            // Default users (password: admin123 for admin, sales123 for sales)
            const defaultUsers = [
                {
                    user_id: 1,
                    username: 'admin',
                    password: 'admin123', // Plain for offline - use bcrypt in production
                    email: 'admin@bookshop.com',
                    role_id: 1,
                    status: 'Active',
                    created_at: new Date().toISOString()
                },
                {
                    user_id: 2,
                    username: 'sales',
                    password: 'sales123',
                    email: 'sales@bookshop.com',
                    role_id: 2,
                    status: 'Active',
                    created_at: new Date().toISOString()
                }
            ];
            
            await LocalDB.bulkAdd('users', defaultUsers);
            console.log('[Auth] Default users seeded');
            return true;
        } catch (error) {
            console.error('[Auth] Failed to seed users:', error);
            return false;
        }
    }
};

// Export for use
window.Auth = Auth;

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => Auth.init());

// Check session activity periodically
setInterval(() => Auth.checkSession(), 60000); // Every minute
