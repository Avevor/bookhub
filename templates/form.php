<?php
/**
 * Form Template
 * Creates consistent form styling and structure
 *
 * @param array $fields Array of field configurations
 * @param string $action Form action URL
 * @param string $method Form method (POST/GET)
 * @param array $options Additional options
 */
function display_form($fields, $action = '', $method = 'POST', $options = []) {
    $form_class = $options['class'] ?? 'form-container';
    $submit_text = $options['submit_text'] ?? 'Submit';
    $submit_class = $options['submit_class'] ?? 'btn-submit';

    echo "<div class='{$form_class}'>";
    echo "<form action='{$action}' method='{$method}'>";

    foreach ($fields as $field) {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $value = $field['value'] ?? '';
        $required = $field['required'] ?? false;
        $options_list = $field['options'] ?? [];
        $placeholder = $field['placeholder'] ?? '';

        echo "<label>" . htmlspecialchars($label);
        if ($required) echo " *";
        echo "</label>";

        switch ($type) {
            case 'select':
                echo "<select name='{$name}'" . ($required ? ' required' : '') . ">";
                echo "<option value=''>Select " . htmlspecialchars($label) . "</option>";
                foreach ($options_list as $option_value => $option_label) {
                    $selected = ($value == $option_value) ? ' selected' : '';
                    echo "<option value='{$option_value}'{$selected}>{$option_label}</option>";
                }
                echo "</select>";
                break;

            case 'textarea':
                echo "<textarea name='{$name}' placeholder='{$placeholder}'" . ($required ? ' required' : '') . ">{$value}</textarea>";
                break;

            default:
                echo "<input type='{$type}' name='{$name}' value='{$value}' placeholder='{$placeholder}'" . ($required ? ' required' : '') . ">";
                break;
        }
    }

    echo "<button type='submit' class='{$submit_class}'>{$submit_text}</button>";
    echo "</form>";
    echo "</div>";
}
?>

<style>
.form-container {
    margin: 20px 0;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background: #f9f9f9;
}

.form-container label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-container input,
.form-container select,
.form-container textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-container input:focus,
.form-container select:focus,
.form-container textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0,123,255,0.3);
}

.form-container textarea {
    resize: vertical;
    min-height: 80px;
}

.btn-submit {
    background-color: #007bff;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: background-color 0.2s ease;
}

.btn-submit:hover {
    background-color: #0056b3;
}
</style>
