<?php
/**
 * Test debug per verifica post FP Publisher
 * 
 * Esegui questo file direttamente nel browser per vedere cosa viene trovato
 */

define('WP_USE_THEMES', false);

// Cerca wp-load.php partendo dalla directory corrente e salendo
$current_dir = __DIR__;
$wp_load = null;
$max_levels = 6; // Massimo 6 livelli da salire

for ($i = 0; $i < $max_levels; $i++) {
    $test_path = $current_dir . '/wp-load.php';
    if (file_exists($test_path)) {
        $wp_load = $test_path;
        break;
    }
    $parent_dir = dirname($current_dir);
    // Evita loop infiniti
    if ($parent_dir === $current_dir || $parent_dir === '/' || $parent_dir === 'C:\\') {
        break;
    }
    $current_dir = $parent_dir;
}

if ($wp_load && file_exists($wp_load)) {
    require_once $wp_load;
} else {
    // Mostra info di debug
    $debug_info = "wp-load.php non trovato.\n";
    $debug_info .= "Directory corrente: " . __DIR__ . "\n";
    $debug_info .= "Percorsi provati:\n";
    $test_dir = __DIR__;
    for ($i = 0; $i < $max_levels; $i++) {
        $debug_info .= "  - " . $test_dir . "/wp-load.php\n";
        $test_dir = dirname($test_dir);
        if ($test_dir === dirname($test_dir)) break;
    }
    die('<pre>' . htmlspecialchars($debug_info) . '</pre>');
}

global $wpdb;

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Debug FP Publisher Integration</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #f0f0f0; font-weight: bold; }
</style></head><body>";
echo "<h1>🔍 Debug FP Publisher Integration</h1>";

// Verifica se la tabella esiste
$publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$publisher_table}'") === $publisher_table;

echo "<div class='section'>";
echo "<h2>1. Verifica Tabella</h2>";
if ($table_exists) {
    echo "<p class='success'>✅ Tabella trovata: {$publisher_table}</p>";
} else {
    echo "<p class='error'>❌ Tabella NON trovata: {$publisher_table}</p>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Mostra tutte le colonne
echo "<div class='section'>";
echo "<h2>2. Colonne Disponibili</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$publisher_table}");
$column_names = array_map(function($col) { return $col->Field; }, $columns);
echo "<p>Colonne trovate: " . count($column_names) . "</p>";
echo "<pre>" . print_r($column_names, true) . "</pre>";
echo "</div>";

// Ottieni tutti i workspace
echo "<div class='section'>";
echo "<h2>3. Workspace Trovati</h2>";
$workspaces = $wpdb->get_results("SELECT * FROM {$publisher_table} LIMIT 20");
if (empty($workspaces)) {
    echo "<p class='error'>❌ Nessun workspace trovato</p>";
} else {
    echo "<p class='success'>✅ Trovati " . count($workspaces) . " workspace</p>";
    
    // Per ogni workspace, mostra i valori rilevanti
    echo "<table>";
    echo "<tr><th>ID</th><th>Nome</th><th>Colonne Rilevanti</th></tr>";
    
    foreach ($workspaces as $workspace) {
        echo "<tr>";
        echo "<td>{$workspace->id}</td>";
        echo "<td><strong>" . esc_html($workspace->name) . "</strong></td>";
        echo "<td>";
        
        // Cerca colonne rilevanti
        $relevant_data = array();
        
        // Cerca colonna ultimo post
        foreach ($column_names as $col_name) {
            $col_lower = strtolower($col_name);
            if ((strpos($col_lower, 'ultimo') !== false || strpos($col_lower, 'last') !== false) && 
                (strpos($col_lower, 'post') !== false)) {
                $value = isset($workspace->$col_name) ? $workspace->$col_name : 'NULL';
                $relevant_data[] = "<strong>{$col_name}:</strong> " . esc_html(substr((string)$value, 0, 100));
            }
        }
        
        // Cerca colonna status
        foreach ($column_names as $col_name) {
            $col_lower = strtolower($col_name);
            if (strpos($col_lower, 'status') !== false || strpos($col_lower, 'stato') !== false) {
                $value = isset($workspace->$col_name) ? $workspace->$col_name : 'NULL';
                $relevant_data[] = "<strong>{$col_name}:</strong> " . esc_html((string)$value);
            }
        }
        
        // Cerca colonna avanzamento
        foreach ($column_names as $col_name) {
            $col_lower = strtolower($col_name);
            if (strpos($col_lower, 'avanzamento') !== false || 
                strpos($col_lower, 'progress') !== false ||
                strpos($col_lower, 'articoli') !== false) {
                $value = isset($workspace->$col_name) ? $workspace->$col_name : 'NULL';
                $relevant_data[] = "<strong>{$col_name}:</strong> " . esc_html(substr((string)$value, 0, 200));
            }
        }
        
        if (empty($relevant_data)) {
            echo "<span class='warning'>⚠️ Nessuna colonna rilevante trovata</span>";
        } else {
            echo "<ul style='margin: 0; padding-left: 20px;'>";
            foreach ($relevant_data as $data) {
                echo "<li>{$data}</li>";
            }
            echo "</ul>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}
echo "</div>";

// Test della logica di verifica
echo "<div class='section'>";
echo "<h2>4. Test Logica Verifica</h2>";

if (!empty($workspaces)) {
    require_once dirname(__FILE__) . '/includes/PublisherIntegration.php';
    
    $test_results = array();
    foreach ($workspaces as $workspace) {
        $workspace_id = $workspace->id;
        $workspace_name = $workspace->name;
        
        echo "<h3>Workspace: {$workspace_name} (ID: {$workspace_id})</h3>";
        
        // Test check_social_posts
        echo "<h4>Test check_social_posts:</h4>";
        ob_start();
        $social_result = FP\TaskAgenda\PublisherIntegration::check_social_posts($workspace_id, $workspace_name);
        $social_output = ob_get_clean();
        
        if ($social_result) {
            echo "<p class='success'>✅ Task social creata (ID: {$social_result})</p>";
        } else {
            echo "<p class='warning'>⚠️ Nessuna task social creata</p>";
        }
        
        // Test check_wordpress_posts
        echo "<h4>Test check_wordpress_posts:</h4>";
        ob_start();
        $wp_result = FP\TaskAgenda\PublisherIntegration::check_wordpress_posts($workspace_id, $workspace_name);
        $wp_output = ob_get_clean();
        
        if ($wp_result) {
            echo "<p class='success'>✅ Task WordPress creata (ID: {$wp_result})</p>";
        } else {
            echo "<p class='warning'>⚠️ Nessuna task WordPress creata</p>";
        }
        
        echo "<hr>";
    }
}

echo "</div>";

// Mostra tutti i dati raw del primo workspace per debug
if (!empty($workspaces)) {
    echo "<div class='section'>";
    echo "<h2>5. Dati Raw Primo Workspace</h2>";
    echo "<pre>" . print_r($workspaces[0], true) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
