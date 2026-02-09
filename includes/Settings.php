<?php
/**
 * Pagina Impostazioni
 * 
 * Gestisce le impostazioni del plugin
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_save'));
    }
    
    /**
     * Registra le impostazioni
     */
    public function register_settings() {
        register_setting('fp_task_agenda_settings_group', 'fp_task_agenda_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }
    
    /**
     * Sanitizza i dati delle impostazioni
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        $defaults = $this->get_defaults();
        
        if (isset($input['items_per_page'])) {
            $val = absint($input['items_per_page']);
            $sanitized['items_per_page'] = max(10, min(100, $val));
        } else {
            $sanitized['items_per_page'] = $defaults['items_per_page'];
        }
        
        if (isset($input['auto_cleanup_days'])) {
            $val = absint($input['auto_cleanup_days']);
            $sanitized['auto_cleanup_days'] = max(7, min(90, $val));
        } else {
            $sanitized['auto_cleanup_days'] = $defaults['auto_cleanup_days'];
        }
        
        $sanitized['publisher_sync_enabled'] = !empty($input['publisher_sync_enabled']);
        $sanitized['show_completed'] = !empty($input['show_completed']);
        
        return wp_parse_args($sanitized, $defaults);
    }
    
    /**
     * Restituisce i valori di default
     */
    private function get_defaults() {
        return array(
            'items_per_page' => 20,
            'show_completed' => true,
            'auto_cleanup_days' => 30,
            'publisher_sync_enabled' => true
        );
    }
    
    /**
     * Gestisce il salvataggio
     */
    public function handle_save() {
        if (isset($_POST['fp_task_agenda_settings_nonce']) &&
            wp_verify_nonce($_POST['fp_task_agenda_settings_nonce'], 'fp_task_agenda_save_settings') &&
            current_user_can('manage_options')) {
            $settings = $this->sanitize_settings($_POST['fp_task_agenda_settings'] ?? array());
            update_option('fp_task_agenda_settings', $settings);
            add_action('admin_notices', array($this, 'saved_notice'));
        }
    }
    
    /**
     * Notice di salvataggio
     */
    public function saved_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Impostazioni salvate.', 'fp-task-agenda') . '</p></div>';
    }
    
    /**
     * Renderizza la pagina impostazioni
     */
    public function render_settings_page() {
        $settings = wp_parse_args(
            get_option('fp_task_agenda_settings', array()),
            $this->get_defaults()
        );
        include FP_TASK_AGENDA_PLUGIN_DIR . 'includes/admin-templates/settings-page.php';
    }
}
