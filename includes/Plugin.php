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
        
        // Verifica e aggiorna il database se necessario (all'avvio, non solo all'attivazione)
        add_action('admin_init', array($this, 'maybe_update_database'), 1);
        
        // Hook per cron job task ricorrenti
        add_action('fp_task_agenda_recurring_tasks', array($this, 'generate_recurring_tasks'));
        
        // Hook per cron job pulizia task archiviati
        add_action('fp_task_agenda_cleanup_archived', array($this, 'cleanup_archived_tasks'));
        
        // Hook per cron job verifica post mancanti FP Publisher
        add_action('fp_task_agenda_check_publisher_posts', array($this, 'check_publisher_missing_posts'));
        
        // REST API
        add_action('rest_api_init', array(RestApi::class, 'register_routes'));
        
        // Carica admin solo se siamo nell'admin
        if (is_admin()) {
            Admin::get_instance();
            Settings::get_instance();
        }
    }
    
    /**
     * Verifica e aggiorna il database se necessario
     * 
     * Questo viene chiamato all'avvio del plugin per assicurarsi che
     * il database sia sempre aggiornato senza perdere dati.
     */
    public function maybe_update_database() {
        // Solo per amministratori
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $current_db_version = get_option('fp_task_agenda_db_version', '0');
        $required_db_version = Database::DB_VERSION;
        
        // Se la versione del database è inferiore a quella richiesta, aggiorna
        if (version_compare($current_db_version, $required_db_version, '<')) {
            // Esegui migrazione sicura (aggiunge solo colonne mancanti)
            Database::create_tables();
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
            // Recupera recurrence_day (potrebbe non esistere in vecchie installazioni)
            $recurrence_day = isset($parent_task->recurrence_day) ? $parent_task->recurrence_day : null;
            
            // Calcola la prossima data di ricorrenza usando il giorno specifico se impostato
            $next_date = self::calculate_next_recurrence_date(
                $parent_task->due_date ? $parent_task->due_date : $parent_task->completed_at,
                $parent_task->recurrence_type,
                $parent_task->recurrence_interval,
                $recurrence_day
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
                'recurrence_day' => $recurrence_day,
                'recurrence_parent_id' => $parent_task->id,
                'next_recurrence_date' => self::calculate_next_recurrence_date(
                    $next_date, 
                    $parent_task->recurrence_type, 
                    $parent_task->recurrence_interval,
                    $recurrence_day
                ),
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
     * 
     * @param string $start_date Data di partenza
     * @param string $type Tipo ricorrenza: daily, weekly, monthly
     * @param int $interval Intervallo (ogni N giorni/settimane/mesi)
     * @param int|null $recurrence_day Giorno specifico: 1-31 per monthly, 0-6 per weekly (0=domenica)
     * @return string Data formattata Y-m-d H:i:s
     */
    public static function calculate_next_recurrence_date_static($start_date, $type, $interval = 1, $recurrence_day = null) {
        return self::calculate_next_recurrence_date($start_date, $type, $interval, $recurrence_day);
    }
    
    /**
     * Calcola la prossima data di ricorrenza
     * 
     * @param string $start_date Data di partenza
     * @param string $type Tipo ricorrenza: daily, weekly, monthly
     * @param int $interval Intervallo (ogni N giorni/settimane/mesi)
     * @param int|null $recurrence_day Giorno specifico: 1-31 per monthly, 0-6 per weekly (0=domenica)
     * @return string Data formattata Y-m-d H:i:s
     */
    private static function calculate_next_recurrence_date($start_date, $type, $interval = 1, $recurrence_day = null) {
        if (empty($start_date)) {
            $start_date = current_time('mysql');
        }
        
        $date = new \DateTime($start_date);
        $now = new \DateTime(current_time('mysql'));
        
        switch ($type) {
            case 'daily':
                $date->modify("+{$interval} days");
                break;
                
            case 'weekly':
                if (!empty($recurrence_day) && $recurrence_day >= 0 && $recurrence_day <= 6) {
                    // Mappa giorno numerico a stringa per PHP DateTime
                    $days_map = array(
                        0 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday'
                    );
                    $target_day = $days_map[$recurrence_day];
                    
                    // Vai alla prossima settimana secondo l'intervallo
                    $date->modify("+{$interval} weeks");
                    
                    // Poi vai al giorno specifico di quella settimana
                    $current_day = (int) $date->format('w');
                    if ($current_day !== $recurrence_day) {
                        // Torna all'inizio della settimana e poi vai al giorno desiderato
                        $diff = $recurrence_day - $current_day;
                        if ($diff !== 0) {
                            $date->modify("{$diff} days");
                        }
                    }
                } else {
                    $date->modify("+{$interval} weeks");
                }
                break;
                
            case 'monthly':
                if (!empty($recurrence_day) && $recurrence_day >= 1 && $recurrence_day <= 31) {
                    // Vai al mese successivo secondo l'intervallo
                    $date->modify("+{$interval} months");
                    
                    // Imposta il giorno specifico del mese
                    $year = (int) $date->format('Y');
                    $month = (int) $date->format('n');
                    
                    // Verifica quanti giorni ha il mese target
                    $days_in_month = (int) $date->format('t');
                    
                    // Se il giorno richiesto è maggiore dei giorni del mese, usa l'ultimo giorno
                    $actual_day = min($recurrence_day, $days_in_month);
                    
                    // Imposta la data al giorno corretto
                    $date->setDate($year, $month, $actual_day);
                    
                    // Se la data calcolata è già passata (o è oggi), vai al prossimo ciclo
                    if ($date <= $now) {
                        $date->modify("+{$interval} months");
                        // Ricalcola i giorni del mese
                        $days_in_month = (int) $date->format('t');
                        $actual_day = min($recurrence_day, $days_in_month);
                        $year = (int) $date->format('Y');
                        $month = (int) $date->format('n');
                        $date->setDate($year, $month, $actual_day);
                    }
                } else {
                    $date->modify("+{$interval} months");
                }
                break;
        }
        
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Restituisce items_per_page dalle impostazioni
     */
    public static function get_items_per_page() {
        $settings = get_option('fp_task_agenda_settings', array());
        return isset($settings['items_per_page']) ? max(10, min(100, (int) $settings['items_per_page'])) : 20;
    }
    
    /**
     * Restituisce auto_cleanup_days dalle impostazioni
     */
    public static function get_auto_cleanup_days() {
        $settings = get_option('fp_task_agenda_settings', array());
        return isset($settings['auto_cleanup_days']) ? max(7, min(90, (int) $settings['auto_cleanup_days'])) : 30;
    }
    
    /**
     * Verifica se la sincronizzazione FP Publisher è abilitata
     */
    public static function get_publisher_sync_enabled() {
        $settings = get_option('fp_task_agenda_settings', array());
        return isset($settings['publisher_sync_enabled']) ? (bool) $settings['publisher_sync_enabled'] : true;
    }
    
    /**
     * Verifica se mostrare i task completati nelle liste
     */
    public static function get_show_completed() {
        $settings = get_option('fp_task_agenda_settings', array());
        return isset($settings['show_completed']) ? (bool) $settings['show_completed'] : true;
    }
    
    /**
     * Pulizia task archiviati
     */
    public function cleanup_archived_tasks() {
        Database::cleanup_all_archived_tasks(self::get_auto_cleanup_days());
    }
    
    /**
     * Verifica post mancanti in FP Publisher e crea task automaticamente
     */
    public function check_publisher_missing_posts() {
        if (!self::get_publisher_sync_enabled()) {
            return;
        }
        PublisherIntegration::check_missing_posts();
    }
    
    /**
     * Attivazione plugin
     */
    public static function activate() {
        // Crea le tabelle necessarie (ora con controllo versione - non cancella dati esistenti)
        Database::create_tables();
        
        // Crea opzioni di default se non esistono
        if (!get_option('fp_task_agenda_settings')) {
            update_option('fp_task_agenda_settings', array(
                'items_per_page' => 20,
                'show_completed' => true,
                'auto_cleanup_days' => 30,
                'publisher_sync_enabled' => true
            ));
        }
        
        // Salva la versione del plugin per riferimento
        update_option('fp_task_agenda_version', FP_TASK_AGENDA_VERSION);
        
        // Schedula cron job per task ricorrenti (ogni giorno alle 2:00 AM)
        if (!wp_next_scheduled('fp_task_agenda_recurring_tasks')) {
            wp_schedule_event(strtotime('tomorrow 2:00 AM'), 'daily', 'fp_task_agenda_recurring_tasks');
        }
        
        // Schedula cron job per pulizia task archiviati (ogni giorno alle 3:00 AM)
        if (!wp_next_scheduled('fp_task_agenda_cleanup_archived')) {
            wp_schedule_event(strtotime('tomorrow 3:00 AM'), 'daily', 'fp_task_agenda_cleanup_archived');
        }
        
        // Schedula cron job per verifica post mancanti FP Publisher (ogni giorno alle 4:00 AM)
        if (!wp_next_scheduled('fp_task_agenda_check_publisher_posts')) {
            wp_schedule_event(strtotime('tomorrow 4:00 AM'), 'daily', 'fp_task_agenda_check_publisher_posts');
        }
    }
    
    /**
     * Disattivazione plugin
     */
    public static function deactivate() {
        // Rimuovi cron job task ricorrenti
        $timestamp = wp_next_scheduled('fp_task_agenda_recurring_tasks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fp_task_agenda_recurring_tasks');
        }
        
        // Rimuovi cron job pulizia archiviati
        $timestamp_cleanup = wp_next_scheduled('fp_task_agenda_cleanup_archived');
        if ($timestamp_cleanup) {
            wp_unschedule_event($timestamp_cleanup, 'fp_task_agenda_cleanup_archived');
        }
        
        // Rimuovi cron job verifica post mancanti FP Publisher
        $timestamp_publisher = wp_next_scheduled('fp_task_agenda_check_publisher_posts');
        if ($timestamp_publisher) {
            wp_unschedule_event($timestamp_publisher, 'fp_task_agenda_check_publisher_posts');
        }
        
        flush_rewrite_rules();
    }
}
