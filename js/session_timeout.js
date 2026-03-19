/**
 * Session Timeout Warning Script
 * Warns users before session expires and allows them to stay logged in
 */

(function() {
    'use strict';
    
    // Session timeout in milliseconds (45 minutes = 2700000ms)
    // This should match the server-side session_timeout (2700 seconds)
    var SESSION_TIMEOUT = 2700000; // 45 minutes
    
    // Warning time before session expires (5 minutes)
    var WARNING_TIME = 300000; // 5 minutes
    
    // Check interval (every 30 seconds)
    var CHECK_INTERVAL = 30000; // 30 seconds
    
    var warningShown = false;
    var warningTimer = null;
    var checkInterval = null;
    
    // Store last activity time
    var lastActivity = Date.now();
    
    /**
     * Show session timeout warning
     */
    function showWarning() {
        if (warningShown) return;
        warningShown = true;
        
        // Create warning modal
        var modal = document.createElement('div');
        modal.id = 'session-timeout-warning';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            font-family: Arial, sans-serif;
        `;
        
        var modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        `;
        
        modalContent.innerHTML = `
            <h2 style="color: #ffc107; margin-top: 0; font-size: 2em;">⚠️ Session Warning</h2>
            <p style="color: #666; font-size: 1.1em;">
                Your session is about to expire due to inactivity.
            </p>
            <p style="color: #333; font-weight: bold;">
                You will be logged out in <span id="countdown" style="color: #dc3545; font-size: 1.3em;">5</span> minutes.
            </p>
            <p style="color: #666;">
                Would you like to stay logged in?
            </p>
            <div style="margin-top: 25px; display: flex; gap: 15px; justify-content: center;">
                <button id="stay-logged-in" style="
                    background: linear-gradient(135deg, #28a745, #20c997);
                    color: white;
                    border: none;
                    padding: 12px 30px;
                    border-radius: 8px;
                    font-size: 1em;
                    font-weight: bold;
                    cursor: pointer;
                    transition: transform 0.2s;
                ">Yes, Keep Me Logged In</button>
                <button id="logout-now" style="
                    background: #6c757d;
                    color: white;
                    border: none;
                    padding: 12px 30px;
                    border-radius: 8px;
                    font-size: 1em;
                    cursor: pointer;
                ">Logout Now</button>
            </div>
        `;
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        
        // Add countdown
        var countdown = 5;
        var countdownElement = modalContent.querySelector('#countdown');
        var countdownInterval = setInterval(function() {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(countdownInterval);
            }
        }, 1000);
        
        // Stay logged in button
        modalContent.querySelector('#stay-logged-in').addEventListener('click', function() {
            // Send AJAX request to refresh session
            fetch('../pages/refresh_session.php', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    hideWarning();
                    resetTimers();
                }
            })
            .catch(function() {
                // If refresh fails, just hide warning and reset locally
                hideWarning();
                resetTimers();
            });
        });
        
        // Logout now button
        modalContent.querySelector('#logout-now').addEventListener('click', function() {
            window.location.href = '../pages/logout.php';
        });
        
        // Auto-logout after 5 minutes if no action
        setTimeout(function() {
            if (document.getElementById('session-timeout-warning')) {
                window.location.href = '../pages/logout.php';
            }
        }, WARNING_TIME);
    }
    
    /**
     * Hide session timeout warning
     */
    function hideWarning() {
        var modal = document.getElementById('session-timeout-warning');
        if (modal) {
            modal.remove();
        }
        warningShown = false;
    }
    
    /**
     * Reset all timers
     */
    function resetTimers() {
        lastActivity = Date.now();
        
        if (warningTimer) {
            clearTimeout(warningTimer);
        }
        
        // Set warning timer
        warningTimer = setTimeout(function() {
            showWarning();
        }, SESSION_TIMEOUT - WARNING_TIME);
        
        if (checkInterval) {
            clearInterval(checkInterval);
        }
        
        // Set periodic check
        checkInterval = setInterval(checkSession, CHECK_INTERVAL);
    }
    
    /**
     * Check session status
     */
    function checkSession() {
        var elapsed = Date.now() - lastActivity;
        
        if (elapsed >= SESSION_TIMEOUT - WARNING_TIME && !warningShown) {
            showWarning();
        } else if (elapsed >= SESSION_TIMEOUT) {
            // Session expired
            window.location.href = '../pages/logout.php';
        }
    }
    
    /**
     * Track user activity
     */
    function trackActivity() {
        lastActivity = Date.now();
        
        if (warningShown) {
            hideWarning();
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Only run on authenticated pages (not login page)
        var isLoginPage = window.location.pathname.includes('/pages/login.php');
        
        if (!isLoginPage) {
            resetTimers();
            
            // Track user activity
            var activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart'];
            activityEvents.forEach(function(event) {
                document.addEventListener(event, trackActivity, { passive: true });
            });
        }
    }
})();
