<?php
/**
 * Trova i nomi reali delle colonne nella tabella fp_pub_remote_sites
 */

define('WP_USE_THEMES', false);

$current_dir = __DIR__;
$wp_load = null;
$max_levels = 6;

for ($i = 0; $i < $max_levels; $i++) {
    $test_path = $current_dir . '/wp-load.php';
    if (file_exists($test_path)) {
        $wp_load = $test_path;
        break;
    }
    $parent_dir = dirname($current_dir);
    if ($parent_dir === $current_dir || $parent_dir === '/' || $parent_dir === 'C:\\') {
        break;
    }
    $current_dir = $parent_dir;
}

if ($wp_load && file_exists($wp_load)) {
    require_once $wp_load;
} else {
    die('wp-load.php non trovato');
}

global $wpdb;

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Trova Colonne Reali</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background: #f0f0f0; font-weight: bold; }
    .col-name { font-weight: bold; color: #0066cc; }
    .col-value { max-width: 500px; word-wrap: break-word; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style></head><body>";
echo "<h1>🔍 Trova Colonne Reali - fp_pub_remote_sites</h1>";

$publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';

// Mostra tutte le colonne
echo "<div class='section'>";
echo "<h2>1. Tutte le Colonne della Tabella</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$publisher_table}");
echo "<table>";
echo "<tr><th>Nome Colonna</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td class='col-name'>{$col->Field}</td>";
    echo "<td>{$col->Type}</td>";
    echo "<td>{$col->Null}</td>";
    echo "<td>" . ($col->Default !== null ? $col->Default : 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Mostra tutti i workspace con TUTTE le colonne e i loro valori
echo "<div class='section'>";
echo "<h2>2. Valori di Tutte le Colonne per Ogni Workspace</h2>";
$workspaces = $wpdb->get_results("SELECT * FROM {$publisher_table} LIMIT 10");

if (empty($workspaces)) {
    echo "<p>Nessun workspace trovato</p>";
} else {
    foreach ($workspaces as $idx => $workspace) {
        echo "<h3>Workspace #" . ($idx + 1) . " (ID: {$workspace->id})</h3>";
        echo "<table>";
        echo "<tr><th>Nome Colonna</th><th>Valore</th></tr>";
        
        foreach (get_object_vars($workspace) as $key => $value) {
            $value_display = $value;
            if (is_null($value)) {
                $value_display = '<em style="color: #999;">NULL</em>';
            } elseif ($value === '') {
                $value_display = '<em style="color: #999;">(vuoto)</em>';
            } else {
                // Se è JSON, prova a decodificarlo
                if (is_string($value) && (substr($value, 0, 1) === '{' || substr($value, 0, 1) === '[')) {
                    $json_decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value_display = '<pre>' . htmlspecialchars(print_r($json_decoded, true)) . '</pre>';
                    } else {
                        $value_display = htmlspecialchars(substr($value, 0, 200));
                    }
                } else {
                    $value_display = htmlspecialchars(substr((string)$value, 0, 200));
                }
            }
            
            echo "<tr>";
            echo "<td class='col-name'>{$key}</td>";
            echo "<td class='col-value'>{$value_display}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<hr>";
    }
}

echo "</div>";

// Cerca pattern nei nomi delle colonne
echo "<div class='section'>";
echo "<h2>3. Colonne che Potrebbero Contenere Dati Rilevanti</h2>";

$relevant_patterns = array(
    'post' => array('ultimo', 'last', 'post', 'pubblicazione', 'publication'),
    'status' => array('status', 'stato', 'attention', 'attenzione', 'urgent', 'urgente'),
    'progress' => array('avanzamento', 'progress', 'articoli', 'articles', 'monthly')
);

foreach ($relevant_patterns as $category => $patterns) {
    echo "<h4>{$category}</h4>";
    $found = false;
    foreach ($columns as $col) {
        $col_lower = strtolower($col->Field);
        foreach ($patterns as $pattern) {
            if (strpos($col_lower, $pattern) !== false) {
                echo "<p>✅ <strong>{$col->Field}</strong> (tipo: {$col->Type})</p>";
                $found = true;
                break;
            }
        }
    }
    if (!$found) {
        echo "<p>❌ Nessuna colonna trovata</p>";
    }
}

echo "</div>";

echo "</body></html>";
