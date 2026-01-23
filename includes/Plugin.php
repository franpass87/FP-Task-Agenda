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
        
        // Hook per cron job pulizia task archiviati
        add_action('fp_task_agenda_cleanup_archived', array($this, 'cleanup_archived_tasks'));
        
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
     * Pulizia task archiviati vecchi di 30 giorni
     */
    public function cleanup_archived_tasks() {
        // Elimina definitivamente i task archiviati da più di 30 giorni
        Database::cleanup_all_archived_tasks(30);
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
        
        // Schedula cron job per pulizia task archiviati (ogni giorno alle 3:00 AM)
        if (!wp_next_scheduled('fp_task_agenda_cleanup_archived')) {
            wp_schedule_event(strtotime('tomorrow 3:00 AM'), 'daily', 'fp_task_agenda_cleanup_archived');
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
        
        flush_rewrite_rules();
    }
}
