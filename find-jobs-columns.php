<?php
/**
 * Trova le colonne della tabella fp_pub_jobs
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
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Colonne fp_pub_jobs</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background: #f0f0f0; font-weight: bold; }
    .col-name { font-weight: bold; color: #0066cc; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style></head><body>";
echo "<h1>🔍 Colonne Tabella fp_pub_jobs</h1>";

$jobs_table = $wpdb->prefix . 'fp_pub_jobs';

// Verifica se la tabella esiste
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$jobs_table}'") === $jobs_table;

if (!$table_exists) {
    echo "<div class='section'>";
    echo "<p>❌ Tabella {$jobs_table} non trovata</p>";
    echo "</div></body></html>";
    exit;
}

// Mostra tutte le colonne
echo "<div class='section'>";
echo "<h2>1. Tutte le Colonne della Tabella fp_pub_jobs</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$jobs_table}");
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

// Mostra alcuni job di esempio
echo "<div class='section'>";
echo "<h2>2. Esempio di Job (primi 5)</h2>";
$jobs = $wpdb->get_results("SELECT * FROM {$jobs_table} LIMIT 5");

if (empty($jobs)) {
    echo "<p>Nessun job trovato</p>";
} else {
    foreach ($jobs as $idx => $job) {
        echo "<h3>Job #" . ($idx + 1) . " (ID: {$job->id})</h3>";
        echo "<table>";
        echo "<tr><th>Nome Colonna</th><th>Valore</th></tr>";
        
        foreach (get_object_vars($job) as $key => $value) {
            $value_display = is_null($value) ? '<em style="color: #999;">NULL</em>' : htmlspecialchars(substr((string)$value, 0, 200));
            echo "<tr>";
            echo "<td class='col-name'>{$key}</td>";
            echo "<td>{$value_display}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<hr>";
    }
}

echo "</div>";

// Mostra statistiche per un workspace specifico
echo "<div class='section'>";
echo "<h2>3. Statistiche per Workspace ID 2</h2>";

$workspace_id = 2;

// Ultimo post social pubblicato
$last_social = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$jobs_table} 
    WHERE remote_site_id = %d 
    AND post_type IN ('post', 'reel', 'story')
    AND status IN ('published', 'completed')
    ORDER BY COALESCE(published_at, scheduled_at) DESC 
    LIMIT 1",
    $workspace_id
));

if ($last_social) {
    echo "<h4>Ultimo Post Social Pubblicato:</h4>";
    echo "<pre>" . print_r($last_social, true) . "</pre>";
} else {
    echo "<p>Nessun post social trovato</p>";
}

// Conta articoli WordPress questo mese
$current_month_start = date('Y-m-01 00:00:00');
$current_month_end = date('Y-m-t 23:59:59');

$articles_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$jobs_table} 
    WHERE remote_site_id = %d 
    AND post_type = 'article'
    AND status IN ('published', 'completed')
    AND COALESCE(published_at, scheduled_at) >= %s
    AND COALESCE(published_at, scheduled_at) <= %s",
    $workspace_id,
    $current_month_start,
    $current_month_end
));

echo "<h4>Articoli WordPress pubblicati questo mese:</h4>";
echo "<p>{$articles_count}</p>";

// Configurazione dal content_config
$workspace = $wpdb->get_row($wpdb->prepare(
    "SELECT content_config FROM {$wpdb->prefix}fp_pub_remote_sites WHERE id = %d",
    $workspace_id
));

if ($workspace && !empty($workspace->content_config)) {
    $config = json_decode($workspace->content_config, true);
    if ($config && isset($config['articles_per_month'])) {
        $target = $config['articles_per_month'];
        echo "<h4>Target articoli mensili (da content_config):</h4>";
        echo "<p>{$target}</p>";
        echo "<h4>Avanzamento:</h4>";
        echo "<p>{$articles_count}/{$target}</p>";
        if ($articles_count == 0 && $target > 0) {
            echo "<p style='color: red; font-weight: bold;'>⚠️ Attenzione: 0/{$target} - Nessun articolo pubblicato!</p>";
        }
    }
}

echo "</div>";

echo "</body></html>";
