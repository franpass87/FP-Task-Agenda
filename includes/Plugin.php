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
        
        // Hook per cron job task ricorrenti
        add_action('fp_task_agenda_recurring_tasks', array($this, 'generate_recurring_tasks'));
        
        // Carica admin solo se siamo nell'admin
        if (is_admin()) {
            Admin::get_instance();
        }
    }
    
    /**
     * Genera task ricorrenti
     */
    public function generate_recurring_tasks() {
        global $wpdb;
        
        $table_name = Database::get_table_name();
        $now = current_time('mysql');
        
        // Trova task con ricorrenza che devono essere generati
        $tasks_to_repeat = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE recurrence_type IS NOT NULL 
            AND recurrence_type != '' 
            AND (next_recurrence_date IS NULL OR next_recurrence_date <= %s)
            AND status = 'completed'
            AND recurrence_parent_id IS NULL",
            $now
        ));
        
        foreach ($tasks_to_repeat as $parent_task) {
            // Calcola la prossima data di ricorrenza
            $next_date = self::calculate_next_recurrence_date(
                $parent_task->due_date ? $parent_task->due_date : $parent_task->completed_at,
                $parent_task->recurrence_type,
                $parent_task->recurrence_interval
            );
            
            // Crea il nuovo task
            $new_task_data = array(
                'title' => $parent_task->title,
                'description' => $parent_task->description,
                'priority' => $parent_task->priority,
                'status' => 'pending',
                'due_date' => $next_date,
                'client_id' => $parent_task->client_id,
                'recurrence_type' => $parent_task->recurrence_type,
                'recurrence_interval' => $parent_task->recurrence_interval,
                'recurrence_parent_id' => $parent_task->id,
                'next_recurrence_date' => self::calculate_next_recurrence_date($next_date, $parent_task->recurrence_type, $parent_task->recurrence_interval),
                'user_id' => $parent_task->user_id
            );
            
            Database::insert_task($new_task_data);
            
            // Aggiorna il parent task con la prossima data
            $wpdb->update(
                $table_name,
                array('next_recurrence_date' => $next_date),
                array('id' => $parent_task->id),
                array('%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Calcola la prossima data di ricorrenza (metodo pubblico statico)
     */
    public static function calculate_next_recurrence_date_static($start_date, $type, $interval = 1) {
        return self::calculate_next_recurrence_date($start_date, $type, $interval);
    }
    
    /**
     * Calcola la prossima data di ricorrenza
     */
    private static function calculate_next_recurrence_date($start_date, $type, $interval = 1) {
        if (empty($start_date)) {
            $start_date = current_time('mysql');
        }
        
        $date = new \DateTime($start_date);
        
        switch ($type) {
            case 'daily':
                $date->modify("+{$interval} days");
                break;
            case 'weekly':
                $date->modify("+{$interval} weeks");
                break;
            case 'monthly':
                $date->modify("+{$interval} months");
                break;
        }
        
        return $date->format('Y-m-d H:i:s');
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
        
        // Schedula cron job per task ricorrenti (ogni giorno alle 2:00 AM)
        if (!wp_next_scheduled('fp_task_agenda_recurring_tasks')) {
            wp_schedule_event(strtotime('tomorrow 2:00 AM'), 'daily', 'fp_task_agenda_recurring_tasks');
        }
    }
    
    /**
     * Disattivazione plugin
     */
    public static function deactivate() {
        // Rimuovi cron job
        $timestamp = wp_next_scheduled('fp_task_agenda_recurring_tasks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fp_task_agenda_recurring_tasks');
        }
        flush_rewrite_rules();
    }
}
