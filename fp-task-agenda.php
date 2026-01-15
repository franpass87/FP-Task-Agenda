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
if (file_exists(FP_TASK_AGENDA_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once FP_TASK_AGENDA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    add_action('admin_notices', function() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('FP Task Agenda:', 'fp-task-agenda') . '</strong> ';
        echo esc_html__('Esegui', 'fp-task-agenda') . ' <code>composer install</code> ';
        echo esc_html__('nella cartella del plugin.', 'fp-task-agenda');
        echo '</p></div>';
    });
    return;
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
