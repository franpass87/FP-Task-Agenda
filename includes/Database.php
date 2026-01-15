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
            'user_id' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validazione
        if (empty($data['title'])) {
            return new \WP_Error('missing_title', __('Il titolo è obbligatorio', 'fp-task-agenda'));
        }
        
        // Sanitizzazione
        $insert_data = array(
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description']),
            'priority' => in_array($data['priority'], array('low', 'normal', 'high', 'urgent')) ? $data['priority'] : 'normal',
            'status' => in_array($data['status'], array('pending', 'in_progress', 'completed')) ? $data['status'] : 'pending',
            'due_date' => !empty($data['due_date']) ? sanitize_text_field($data['due_date']) : null,
            'client_id' => !empty($data['client_id']) ? absint($data['client_id']) : null,
            'user_id' => absint($data['user_id'])
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante il salvataggio del task', 'fp-task-agenda'));
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
        if ($orderby_field === 'priority') {
            // Ordine logico: urgent=4, high=3, normal=2, low=1
            $orderby = "CASE priority 
                WHEN 'urgent' THEN 4 
                WHEN 'high' THEN 3 
                WHEN 'normal' THEN 2 
                WHEN 'low' THEN 1 
                ELSE 0 
            END " . $order_direction;
        } elseif ($orderby_field === 'status') {
            // Per status, ordine logico: pending=1, in_progress=2, completed=3
            $orderby = "CASE status 
                WHEN 'pending' THEN 1 
                WHEN 'in_progress' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 0 
            END " . $order_direction;
        } else {
            $orderby = $orderby_field . ' ' . $order_direction;
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
