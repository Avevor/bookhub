<?php
/**
 * Table Template
 * Creates consistent table styling across the application
 *
 * @param array $headers Array of header strings
 * @param array $rows Array of arrays containing row data
 * @param array $options Additional options (classes, etc.)
 */
function display_table($headers, $rows, $options = []) {
    $table_class = $options['class'] ?? 'data-table';
    $empty_message = $options['empty_message'] ?? 'No data available';

    echo "<table class='{$table_class}'>";

    // Table headers
    echo "<thead><tr>";
    foreach ($headers as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr></thead>";

    // Table body
    echo "<tbody>";
    if (empty($rows)) {
        $colspan = count($headers);
        echo "<tr><td colspan='{$colspan}' style='text-align: center; padding: 20px;'>{$empty_message}</td></tr>";
    } else {
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "</tr>";
        }
    }
    echo "</tbody>";

    echo "</table>";
}
?>

<style>
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background-color: #f8f9fa;
    font-weight: bold;
    color: #333;
}

.data-table tbody tr:hover {
    background-color: #f8f9fa;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}
</style>
