<?php
/**
 * Gestione Database
 * 
 * Gestisce la creazione delle tabelle e le operazioni CRUD sui task
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor vuoto
    }
    
    /**
     * Crea le tabelle del plugin
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabella clienti
        $clients_table = $wpdb->prefix . 'fp_task_agenda_clients';
        $sql_clients = "CREATE TABLE IF NOT EXISTS $clients_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            source varchar(50) DEFAULT 'manual',
            source_id bigint(20) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY name (name),
            KEY source (source),
            KEY source_id (source_id)
        ) $charset_collate;";
        dbDelta($sql_clients);
        
        // Tabella template task
        $templates_table = $wpdb->prefix . 'fp_task_agenda_templates';
        $sql_templates = "CREATE TABLE IF NOT EXISTS $templates_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            priority varchar(20) DEFAULT 'normal',
            client_id bigint(20) NULL,
            due_date_offset int(11) DEFAULT 0,
            recurrence_type varchar(20) NULL,
            recurrence_interval int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            user_id bigint(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY client_id (client_id),
            KEY name (name)
        ) $charset_collate;";
        dbDelta($sql_templates);
        
        // Tabella task (aggiornata con client_id)
        $table_name = $wpdb->prefix . 'fp_task_agenda';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            priority varchar(20) DEFAULT 'normal',
            status varchar(20) DEFAULT 'pending',
            due_date datetime NULL,
            client_id bigint(20) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            completed_at datetime NULL,
            user_id bigint(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY user_id (user_id),
            KEY client_id (client_id),
            KEY due_date (due_date),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Aggiungi colonna client_id se non esiste (per installazioni esistenti)
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'client_id'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN client_id bigint(20) NULL AFTER due_date");
            $wpdb->query("ALTER TABLE $table_name ADD KEY client_id (client_id)");
        }
        
        // Aggiungi colonne per ricorrenza se non esistono
        $recurrence_columns = array(
            'recurrence_type' => "ALTER TABLE $table_name ADD COLUMN recurrence_type varchar(20) NULL AFTER client_id",
            'recurrence_interval' => "ALTER TABLE $table_name ADD COLUMN recurrence_interval int(11) NULL DEFAULT 1 AFTER recurrence_type",
            'recurrence_parent_id' => "ALTER TABLE $table_name ADD COLUMN recurrence_parent_id bigint(20) NULL AFTER recurrence_interval",
            'next_recurrence_date' => "ALTER TABLE $table_name ADD COLUMN next_recurrence_date datetime NULL AFTER recurrence_parent_id"
        );
        
        foreach ($recurrence_columns as $column_name => $sql) {
            $column_check = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM $table_name LIKE %s",
                $column_name
            ));
            
            if (empty($column_check)) {
                $wpdb->query($sql);
                if ($column_name === 'recurrence_parent_id') {
                    $wpdb->query("ALTER TABLE $table_name ADD KEY recurrence_parent_id (recurrence_parent_id)");
                }
                if ($column_name === 'next_recurrence_date') {
                    $wpdb->query("ALTER TABLE $table_name ADD KEY next_recurrence_date (next_recurrence_date)");
                }
            }
        }
    }
    
    /**
     * Ottiene il nome della tabella
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_task_agenda';
    }
    
    /**
     * Inserisce un nuovo task
     */
    public static function insert_task($data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'title' => '',
            'description' => '',
            'priority' => 'normal',
            'status' => 'pending',
            'due_date' => null,
            'client_id' => null,
            'recurrence_type' => null,
            'recurrence_interval' => 1,
            'recurrence_parent_id' => null,
            'next_recurrence_date' => null,
            'user_id' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validazione
        if (empty($data['title'])) {
            return new \WP_Error('missing_title', __('Il titolo è obbligatorio', 'fp-task-agenda'));
        }
        
        // Sanitizzazione e preparazione dati
        $insert_data = array();
        
        // Campi obbligatori
        $insert_data['title'] = sanitize_text_field($data['title']);
        $insert_data['description'] = sanitize_textarea_field($data['description']);
        $insert_data['priority'] = in_array($data['priority'], array('low', 'normal', 'high', 'urgent')) ? $data['priority'] : 'normal';
        $insert_data['status'] = in_array($data['status'], array('pending', 'in_progress', 'completed')) ? $data['status'] : 'pending';
        $insert_data['user_id'] = absint($data['user_id']);
        
        // Due date - converte YYYY-MM-DD in datetime se necessario
        if (!empty($data['due_date'])) {
            $due_date = sanitize_text_field($data['due_date']);
            // Se è solo una data (YYYY-MM-DD), aggiungi l'orario
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
                $due_date .= ' 00:00:00';
            }
            $insert_data['due_date'] = $due_date;
        } else {
            $insert_data['due_date'] = null;
        }
        
        // Client ID
        $insert_data['client_id'] = !empty($data['client_id']) ? absint($data['client_id']) : null;
        
        // Recurrence - inserisci solo se c'è una ricorrenza impostata
        $recurrence_type = !empty($data['recurrence_type']) && in_array($data['recurrence_type'], array('daily', 'weekly', 'monthly')) ? $data['recurrence_type'] : null;
        
        if (!empty($recurrence_type)) {
            // Inserisci i campi di ricorrenza solo se c'è una ricorrenza
            $insert_data['recurrence_type'] = $recurrence_type;
            $insert_data['recurrence_interval'] = !empty($data['recurrence_interval']) ? absint($data['recurrence_interval']) : 1;
            $insert_data['recurrence_parent_id'] = !empty($data['recurrence_parent_id']) ? absint($data['recurrence_parent_id']) : null;
            
            // Next recurrence date - converte in datetime se necessario
            if (!empty($data['next_recurrence_date'])) {
                $next_date = sanitize_text_field($data['next_recurrence_date']);
                // Se è solo una data (YYYY-MM-DD), aggiungi l'orario
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_date)) {
                    $next_date .= ' 00:00:00';
                }
                $insert_data['next_recurrence_date'] = $next_date;
            } else {
                $insert_data['next_recurrence_date'] = null;
            }
        }
        // Se non c'è ricorrenza, non inseriamo questi campi (evita errori se le colonne non esistono)
        
        // Rimuovi i campi NULL dall'array (il DB userà i default)
        $insert_data_clean = array();
        foreach ($insert_data as $key => $value) {
            if ($value !== null) {
                $insert_data_clean[$key] = $value;
            }
        }
        
        // Prepara i formati per i campi (solo quelli presenti, dopo la rimozione dei NULL)
        $formats = array();
        foreach ($insert_data_clean as $key => $value) {
            // Determina il formato in base al tipo di campo
            if (in_array($key, array('client_id', 'recurrence_interval', 'recurrence_parent_id', 'user_id'))) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        $result = $wpdb->insert($table_name, $insert_data_clean, $formats);
        
        if ($result === false) {
            $error_message = __('Errore durante il salvataggio del task', 'fp-task-agenda');
            if ($wpdb->last_error) {
                $error_message .= ': ' . $wpdb->last_error;
            }
            // Log aggiuntivo per debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Dati inserimento: ' . print_r($insert_data_clean, true));
                error_log('FP Task Agenda - Formati: ' . print_r($formats, true));
            }
            return new \WP_Error('db_error', $error_message);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Aggiorna un task esistente
     */
    public static function update_task($id, $data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Verifica che il task esista e appartenga all'utente corrente
        $task = self::get_task($id);
        if (!$task || $task->user_id != get_current_user_id()) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'));
        }
        
        $update_data = array();
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['priority']) && in_array($data['priority'], array('low', 'normal', 'high', 'urgent'))) {
            $update_data['priority'] = $data['priority'];
        }
        
        if (isset($data['status']) && in_array($data['status'], array('pending', 'in_progress', 'completed'))) {
            $update_data['status'] = $data['status'];
            
            // Se completato, imposta completed_at
            if ($data['status'] === 'completed' && $task->status !== 'completed') {
                $update_data['completed_at'] = current_time('mysql');
            } elseif ($data['status'] !== 'completed') {
                $update_data['completed_at'] = null;
            }
        }
        
        if (isset($data['due_date'])) {
            $update_data['due_date'] = !empty($data['due_date']) ? sanitize_text_field($data['due_date']) : null;
        }
        
        if (isset($data['client_id'])) {
            $update_data['client_id'] = !empty($data['client_id']) ? absint($data['client_id']) : null;
        }
        
        if (isset($data['recurrence_type'])) {
            $update_data['recurrence_type'] = !empty($data['recurrence_type']) && in_array($data['recurrence_type'], array('daily', 'weekly', 'monthly')) ? $data['recurrence_type'] : null;
        }
        
        if (isset($data['recurrence_interval'])) {
            $update_data['recurrence_interval'] = !empty($data['recurrence_interval']) ? absint($data['recurrence_interval']) : 1;
        }
        
        if (isset($data['next_recurrence_date'])) {
            $update_data['next_recurrence_date'] = !empty($data['next_recurrence_date']) ? sanitize_text_field($data['next_recurrence_date']) : null;
        }
        
        if (empty($update_data)) {
            return new \WP_Error('no_data', __('Nessun dato da aggiornare', 'fp-task-agenda'));
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => absint($id)),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante l\'aggiornamento del task', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Elimina un task
     */
    public static function delete_task($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Verifica che il task esista e appartenga all'utente corrente
        $task = self::get_task($id);
        if (!$task || $task->user_id != get_current_user_id()) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'));
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => absint($id)),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante l\'eliminazione del task', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Ottiene un singolo task
     */
    public static function get_task($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            absint($id),
            get_current_user_id()
        ));
        
        return $task;
    }
    
    /**
     * Ottiene i task con filtri
     */
    public static function get_tasks($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'status' => 'all', // all, pending, in_progress, completed
            'priority' => 'all', // all, low, normal, high, urgent
            'client_id' => 'all', // all o ID cliente
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("user_id = " . get_current_user_id());
        
        if ($args['status'] !== 'all') {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if ($args['priority'] !== 'all') {
            $where[] = $wpdb->prepare("priority = %s", $args['priority']);
        }
        
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare("(title LIKE %s OR description LIKE %s)", $search_term, $search_term);
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Gestisci ordinamento con validazione
        $allowed_orderby = array('id', 'title', 'priority', 'status', 'due_date', 'created_at', 'client_id');
        $orderby_field = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order_direction = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Per priorità, usa un CASE per ordinare logicamente invece che alfabeticamente
        // Task completati vanno sempre in fondo
        if ($orderby_field === 'priority') {
            // Ordine logico: urgent=4, high=3, normal=2, low=1
            // Status: in_progress=0, pending=1, completed=2 (in fondo)
            $orderby = "CASE 
                WHEN status = 'in_progress' THEN 0
                WHEN status = 'completed' THEN 2
                ELSE 1 
            END ASC, CASE priority 
                WHEN 'urgent' THEN 4 
                WHEN 'high' THEN 3 
                WHEN 'normal' THEN 2 
                WHEN 'low' THEN 1 
                ELSE 0 
            END " . $order_direction;
        } elseif ($orderby_field === 'status') {
            // Per status, ordine logico: in_progress=0 (prima), pending=1, completed=2 (in fondo)
            $orderby = "CASE status 
                WHEN 'in_progress' THEN 0
                WHEN 'pending' THEN 1 
                WHEN 'completed' THEN 2 
                ELSE 1 
            END ASC";
        } elseif ($orderby_field === 'due_date') {
            // Per due_date: in_progress in cima, completed in fondo, poi ordina per data
            // I NULL alla fine in ASC, all'inizio in DESC
            if ($order_direction === 'ASC') {
                $orderby = "CASE 
                    WHEN status = 'in_progress' THEN 0
                    WHEN status = 'completed' THEN 2
                    ELSE 1 
                END ASC, CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC";
            } else {
                $orderby = "CASE 
                    WHEN status = 'in_progress' THEN 0
                    WHEN status = 'completed' THEN 2
                    ELSE 1 
                END ASC, CASE WHEN due_date IS NULL THEN 0 ELSE 1 END DESC, due_date DESC";
            }
        } elseif ($orderby_field === 'created_at') {
            // Status: in_progress=0, pending=1, completed=2 (in fondo), poi ordina per data creazione
            $orderby = "CASE 
                WHEN status = 'in_progress' THEN 0
                WHEN status = 'completed' THEN 2
                ELSE 1 
            END ASC, created_at " . $order_direction;
        } elseif ($orderby_field === 'title') {
            // Status: in_progress=0, pending=1, completed=2 (in fondo), poi ordina per titolo
            $orderby = "CASE 
                WHEN status = 'in_progress' THEN 0
                WHEN status = 'completed' THEN 2
                ELSE 1 
            END ASC, title " . $order_direction;
        } elseif ($orderby_field === 'client_id') {
            // Status: in_progress=0, pending=1, completed=2 (in fondo), poi ordina per cliente
            $orderby = "CASE 
                WHEN status = 'in_progress' THEN 0
                WHEN status = 'completed' THEN 2
                ELSE 1 
            END ASC, client_id " . $order_direction;
        } else {
            // Status: in_progress=0, pending=1, completed=2 (in fondo), poi ordina per campo specificato
            $orderby = "CASE 
                WHEN status = 'in_progress' THEN 0
                WHEN status = 'completed' THEN 2
                ELSE 1 
            END ASC, " . $orderby_field . ' ' . $order_direction;
        }
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = absint($args['per_page']);
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby LIMIT $limit OFFSET $offset";
        
        $tasks = $wpdb->get_results($query);
        
        return $tasks;
    }
    
    /**
     * Conta i task con filtri
     */
    public static function count_tasks($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'status' => 'all',
            'priority' => 'all',
            'client_id' => 'all',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("user_id = " . get_current_user_id());
        
        if ($args['status'] !== 'all') {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if ($args['priority'] !== 'all') {
            $where[] = $wpdb->prepare("priority = %s", $args['priority']);
        }
        
        if ($args['client_id'] !== 'all' && !empty($args['client_id'])) {
            $where[] = $wpdb->prepare("client_id = %d", absint($args['client_id']));
        }
        
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare("(title LIKE %s OR description LIKE %s)", $search_term, $search_term);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where_clause");
        
        return absint($count);
    }
    
    /**
     * Pulisce i task completati più vecchi di X giorni
     */
    public static function cleanup_old_completed($days = 30) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE status = 'completed' AND completed_at < %s AND user_id = %d",
            $cutoff_date,
            get_current_user_id()
        ));
        
        return $result;
    }
}
