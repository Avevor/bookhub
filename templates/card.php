<?php
/**
 * Card Template
 * Creates consistent card styling for dashboard elements
 *
 * @param string $title Card title
 * @param string $content Card content (HTML allowed)
 * @param array $options Additional options (icon, class, etc.)
 */
function display_card($title, $content, $options = []) {
    $icon = $options['icon'] ?? '';
    $card_class = $options['class'] ?? 'dashboard-card';
    $link = $options['link'] ?? '';
    $link_text = $options['link_text'] ?? 'View More';

    $card_content = "<div class='{$card_class}'>";
    if ($icon) {
        $card_content .= "<div class='card-icon'>{$icon}</div>";
    }
    $card_content .= "<div class='card-title'>{$title}</div>";
    $card_content .= "<div class='card-content'>{$content}</div>";
    if ($link) {
        $card_content .= "<div class='card-footer'><a href='{$link}'>{$link_text}</a></div>";
    }
    $card_content .= "</div>";

    echo $card_content;
}
?>

<style>
.dashboard-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.card-icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.card-title {
    font-size: 1.2em;
    font-weight: bold;
    margin-bottom: 10px;
    color: #333;
}

.card-content {
    color: #666;
    line-height: 1.5;
}

.card-footer {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.card-footer a {
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
}

.card-footer a:hover {
    text-decoration: underline;
}
</style>
