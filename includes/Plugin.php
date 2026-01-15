<?php
/**
 * Classe principale del plugin
 * 
 * Gestisce l'inizializzazione e il coordinamento delle varie componenti
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Inizializza il database
        Database::get_instance();
        
        // Carica admin solo se siamo nell'admin
        if (is_admin()) {
            Admin::get_instance();
        }
    }
    
    /**
     * Attivazione plugin
     */
    public static function activate() {
        // Crea le tabelle necessarie
        Database::create_tables();
        
        // Crea opzioni di default se non esistono
        if (!get_option('fp_task_agenda_settings')) {
            update_option('fp_task_agenda_settings', array(
                'items_per_page' => 20,
                'show_completed' => true,
                'auto_cleanup_days' => 30
            ));
        }
    }
    
    /**
     * Disattivazione plugin
     */
    public static function deactivate() {
        // Pulisci eventuali cron job se presenti in futuro
        flush_rewrite_rules();
    }
}
