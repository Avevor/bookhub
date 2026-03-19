<?php
/**
 * Enhanced Message Template
 * Displays success/error messages with animations and auto-dismiss functionality
 *
 * @param string $message The message text
 * @param string $type 'success', 'error', 'warning', or 'info' (default: 'error')
 * @param bool $auto_dismiss Whether to auto-dismiss the message (default: true)
 * @param int $duration Auto-dismiss duration in milliseconds (default: 5000)
 */
function display_message($message, $type = 'error', $auto_dismiss = true, $duration = 5000) {
    if (empty($message)) return;

    $valid_types = ['success', 'error', 'warning', 'info'];
    $type = in_array($type, $valid_types) ? $type : 'error';

    $icons = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];

    $icon = $icons[$type];
    $id = 'message_' . uniqid();

    echo "<div id='{$id}' class='message {$type}' data-auto-dismiss='{$auto_dismiss}' data-duration='{$duration}'>";
    echo "<div class='message-content'>";
    echo "<span class='message-icon'>{$icon}</span>";
    echo "<span class='message-text'>" . htmlspecialchars($message) . "</span>";
    echo "</div>";
    echo "<button type='button' class='message-close' onclick='dismissMessage(\"{$id}\")' aria-label='Close message'>×</button>";
    echo "</div>";
}
?>

<style>
.message {
    margin: 15px 0;
    padding: 15px 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-left: 4px solid;
    animation: slideIn 0.3s ease-out;
    position: relative;
    overflow: hidden;
}

.message::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    animation: shimmer 2s infinite;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.message.success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border-left-color: #28a745;
}

.message.error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border-left-color: #dc3545;
}

.message.warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
    border-left-color: #ffc107;
}

.message.info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
    border-left-color: #17a2b8;
}

.message-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.message-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.message-text {
    font-weight: 500;
    font-size: 14px;
    line-height: 1.4;
}

.message-close {
    background: none;
    border: none;
    color: inherit;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
    flex-shrink: 0;
    opacity: 0.7;
}

.message-close:hover {
    opacity: 1;
    background: rgba(0, 0, 0, 0.1);
    transform: scale(1.1);
}

.message.fade-out {
    animation: fadeOut 0.3s ease-out forwards;
}

@keyframes fadeOut {
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}

/* Progress bar for auto-dismiss */
.message[data-auto-dismiss="1"]::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(255, 255, 255, 0.7);
    animation: progressBar linear forwards;
}

@keyframes progressBar {
    from { width: 100%; }
    to { width: 0%; }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .message {
        padding: 12px 15px;
        margin: 10px 0;
        gap: 10px;
    }

    .message-text {
        font-size: 13px;
    }

    .message-icon {
        font-size: 18px;
    }

    .message-close {
        font-size: 20px;
        width: 20px;
        height: 20px;
    }
}
</style>

<script>
function dismissMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.classList.add('fade-out');
        setTimeout(() => {
            if (message.parentNode) {
                message.parentNode.removeChild(message);
            }
        }, 300);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss messages
    const messages = document.querySelectorAll('.message[data-auto-dismiss="1"]');
    messages.forEach(message => {
        const duration = parseInt(message.dataset.duration) || 5000;

        // Set progress bar animation duration
        const progressBar = message.querySelector('::after');
        if (progressBar) {
            progressBar.style.animationDuration = duration + 'ms';
        }

        // Auto dismiss
        setTimeout(() => {
            dismissMessage(message.id);
        }, duration);
    });
});
</script>
