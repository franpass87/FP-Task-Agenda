<?php
/**
 * Script temporaneo per verificare lo stato dei task nel database
 * 
 * Esegui questo script direttamente dal browser o via WP-CLI per verificare
 * se i task sono ancora presenti nel database (anche se archiviati)
 * 
 * URL: http://tuosito.com/wp-content/plugins/FP-Task-Agenda/check-tasks.php
 */

// Carica WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Accesso negato. Devi essere un amministratore.');
}

global $wpdb;

$table_name = $wpdb->prefix . 'fp_task_agenda';

echo "<h1>Verifica Task nel Database</h1>";

// Verifica se la tabella esiste
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if (!$table_exists) {
    echo "<p style='color: red;'><strong>ERRORE:</strong> La tabella $table_name non esiste!</p>";
    exit;
}

echo "<p style='color: green;'><strong>OK:</strong> La tabella $table_name esiste.</p>";

// Conta tutti i task (inclusi archiviati)
$total_all = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "<p><strong>Totale task nel database (inclusi archiviati):</strong> $total_all</p>";

// Conta task non archiviati
$total_active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL");
echo "<p><strong>Task attivi (non archiviati):</strong> $total_active</p>";

// Conta task archiviati
$total_archived = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NOT NULL");
echo "<p><strong>Task archiviati:</strong> $total_archived</p>";

// Mostra alcuni esempi di task
echo "<h2>Esempi di Task nel Database</h2>";

$tasks = $wpdb->get_results("SELECT id, title, status, deleted_at, created_at FROM $table_name ORDER BY created_at DESC LIMIT 10");

if (empty($tasks)) {
    echo "<p style='color: red;'><strong>NESSUN TASK TROVATO NEL DATABASE!</strong></p>";
    echo "<p>Questo significa che i task sono stati effettivamente eliminati.</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Titolo</th><th>Stato</th><th>Archiviato</th><th>Creato</th></tr>";
    foreach ($tasks as $task) {
        $archived = $task->deleted_at ? 'Sì (' . $task->deleted_at . ')' : 'No';
        echo "<tr>";
        echo "<td>{$task->id}</td>";
        echo "<td>" . esc_html($task->title) . "</td>";
        echo "<td>{$task->status}</td>";
        echo "<td>$archived</td>";
        echo "<td>{$task->created_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Verifica struttura tabella
echo "<h2>Struttura Tabella</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
echo "<ul>";
foreach ($columns as $column) {
    echo "<li><strong>{$column->Field}</strong> ({$column->Type})</li>";
}
echo "</ul>";

// Verifica versione database salvata
$db_version = get_option('fp_task_agenda_db_version');
echo "<h2>Versione Database</h2>";
if ($db_version) {
    echo "<p><strong>Versione database salvata:</strong> $db_version</p>";
} else {
    echo "<p style='color: orange;'><strong>ATTENZIONE:</strong> Nessuna versione database salvata. Questo potrebbe indicare un problema.</p>";
}

echo "<p><strong>Versione plugin:</strong> " . FP_TASK_AGENDA_VERSION . "</p>";

// Se ci sono task archiviati, mostra opzione per ripristinarli
if ($total_archived > 0) {
    echo "<h2 style='color: green;'>✅ TROVATI TASK ARCHIVIATI!</h2>";
    echo "<p><strong>Ci sono $total_archived task archiviati che possono essere ripristinati.</strong></p>";
    echo "<p>Vai alla pagina <a href='" . admin_url('admin.php?page=fp-task-agenda-archived') . "'>Task Archiviati</a> per ripristinarli.</p>";
    
    // Mostra alcuni task archiviati
    $archived_tasks = $wpdb->get_results("SELECT id, title, status, deleted_at FROM $table_name WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 5");
    if (!empty($archived_tasks)) {
        echo "<h3>Ultimi task archiviati:</h3>";
        echo "<ul>";
        foreach ($archived_tasks as $task) {
            echo "<li><strong>" . esc_html($task->title) . "</strong> - Archiviato il " . date('d/m/Y H:i', strtotime($task->deleted_at)) . "</li>";
        }
        echo "</ul>";
    }
}

// Se non ci sono task attivi ma ci sono archiviati
if ($total_active == 0 && $total_archived > 0) {
    echo "<div style='background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
    echo "<h3 style='margin-top: 0;'>🔍 DIAGNOSI</h3>";
    echo "<p><strong>I tuoi task sono stati archiviati (soft delete), non eliminati definitivamente!</strong></p>";
    echo "<p>Puoi ripristinarli tutti dalla pagina <a href='" . admin_url('admin.php?page=fp-task-agenda-archived') . "' style='font-weight: bold;'>Task Archiviati</a>.</p>";
    echo "</div>";
}

// Se non ci sono task né attivi né archiviati
if ($total_all == 0) {
    echo "<div style='background: #f8d7da; border: 2px solid #dc3545; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
    echo "<h3 style='margin-top: 0; color: #721c24;'>⚠️ PROBLEMA RILEVATO</h3>";
    echo "<p style='color: #721c24;'><strong>Nessun task trovato nel database.</strong></p>";
    echo "<p style='color: #721c24;'>I task sono stati eliminati definitivamente. Purtroppo non è possibile recuperarli.</p>";
    echo "<p style='color: #721c24;'><strong>Possibili cause:</strong></p>";
    echo "<ul style='color: #721c24;'>";
    echo "<li>Il cron job di pulizia ha eliminato i task archiviati dopo 30 giorni</li>";
    echo "<li>Un problema durante l'aggiornamento ha causato la perdita dei dati</li>";
    echo "<li>Un'interruzione durante una migrazione del database</li>";
    echo "</ul>";
    echo "<p style='color: #721c24;'><strong>Soluzione:</strong> Il sistema di versioning implementato ora previene questo problema in futuro.</p>";
    echo "</div>";
}
