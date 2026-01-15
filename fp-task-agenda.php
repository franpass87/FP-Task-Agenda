<?php
/**
 * Plugin Name: FP Task Agenda
 * Plugin URI: https://www.francescopasseri.com
 * Description: Agenda semplice per gestire task e attività da fare - ideale per consulenti di digital marketing
 * Version: 1.0.0
 * Author: Francesco Passeri
 * Author URI: https://www.francescopasseri.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fp-task-agenda
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definisci costanti del plugin
define('FP_TASK_AGENDA_VERSION', '1.0.0');
define('FP_TASK_AGENDA_PLUGIN_DIR', dirname(__FILE__) . '/');
define('FP_TASK_AGENDA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FP_TASK_AGENDA_PLUGIN_FILE', __FILE__);
define('FP_TASK_AGENDA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Carica Composer autoload (Best Practice PSR-4)
$autoload_file = FP_TASK_AGENDA_PLUGIN_DIR . 'vendor/autoload.php';

if (file_exists($autoload_file)) {
    require_once $autoload_file;
} else {
    // Prova a eseguire composer install automaticamente se composer è disponibile
    $composer_json = FP_TASK_AGENDA_PLUGIN_DIR . 'composer.json';
    if (file_exists($composer_json) && !file_exists($autoload_file)) {
        // Prova a trovare composer
        $composer_paths = array(
            'composer', // Nel PATH
            __DIR__ . '/composer.phar', // Nella root del plugin
            dirname(ABSPATH) . '/composer.phar', // Nella root di WordPress
        );
        
        $composer_cmd = null;
        foreach ($composer_paths as $path) {
            if (is_executable($path) || (strpos($path, 'composer') !== false && is_file($path))) {
                $composer_cmd = $path;
                break;
            }
        }
        
        // Se composer è disponibile, prova a eseguire composer install
        if ($composer_cmd) {
            $plugin_dir = escapeshellarg(FP_TASK_AGENDA_PLUGIN_DIR);
            $old_dir = getcwd();
            chdir(FP_TASK_AGENDA_PLUGIN_DIR);
            
            // Esegui composer install in background (non bloccante)
            @exec($composer_cmd . ' install --no-dev --optimize-autoloader --quiet 2>&1', $output, $return_code);
            
            chdir($old_dir);
            
            // Se composer install ha funzionato, ricarica la pagina
            if ($return_code === 0 && file_exists($autoload_file)) {
                require_once $autoload_file;
            } else {
                // Fallback: mostra il messaggio di errore
                add_action('admin_notices', 'fp_task_agenda_composer_notice');
                return;
            }
        } else {
            // Composer non disponibile, mostra messaggio
            add_action('admin_notices', 'fp_task_agenda_composer_notice');
            return;
        }
    } else {
        add_action('admin_notices', 'fp_task_agenda_composer_notice');
        return;
    }
}

/**
 * Mostra il messaggio per eseguire composer install
 */
function fp_task_agenda_composer_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    $plugin_dir = FP_TASK_AGENDA_PLUGIN_DIR;
    $composer_json_exists = file_exists($plugin_dir . 'composer.json');
    
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('FP Task Agenda:', 'fp-task-agenda') . '</strong> ';
    
    if ($composer_json_exists) {
        echo esc_html__('Il folder vendor/ non è stato trovato. Per risolvere il problema:', 'fp-task-agenda');
        echo '<br><strong>' . esc_html__('Opzione 1 (Consigliata):', 'fp-task-agenda') . '</strong> ';
        echo esc_html__('Esegui via SSH:', 'fp-task-agenda') . ' <code>cd ' . esc_html(str_replace(ABSPATH, '', $plugin_dir)) . ' && composer install --no-dev</code>';
        echo '<br><strong>' . esc_html__('Opzione 2:', 'fp-task-agenda') . '</strong> ';
        echo esc_html__('Includi il folder vendor/ nel repository Git per evitare questo problema in futuro.', 'fp-task-agenda');
    } else {
        echo esc_html__('Errore: composer.json non trovato.', 'fp-task-agenda');
    }
    
    echo '</p></div>';
}

// Usa i namespace delle classi
use FP\TaskAgenda\Plugin;

/**
 * Inizializza il plugin
 */
function fp_task_agenda_init() {
    if (!defined('ABSPATH')) {
        return false;
    }
    
    try {
        // Carica traduzioni
        load_plugin_textdomain('fp-task-agenda', false, dirname(FP_TASK_AGENDA_PLUGIN_BASENAME) . '/languages');
        
        // Inizializza il plugin principale
        return Plugin::get_instance();
    } catch (Exception $e) {
        error_log('[FP-TASK-AGENDA] Errore fatale durante l\'inizializzazione: ' . $e->getMessage());
        return false;
    }
}

// Hook di attivazione
register_activation_hook(__FILE__, function() {
    if (class_exists('\FP\TaskAgenda\Plugin')) {
        Plugin::activate();
    }
});

// Hook di disattivazione
register_deactivation_hook(__FILE__, function() {
    if (class_exists('\FP\TaskAgenda\Plugin')) {
        Plugin::deactivate();
    }
});

// Avvia il plugin
add_action('plugins_loaded', 'fp_task_agenda_init', 10);
