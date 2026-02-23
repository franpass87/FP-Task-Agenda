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
     * Versione corrente del database schema
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Crea le tabelle del plugin
     * 
     * IMPORTANTE: Questa funzione ora controlla la versione del database
     * e non ricrea le tabelle se già esistono e sono aggiornate.
     * Questo previene la perdita di dati durante gli aggiornamenti.
     */
    public static function create_tables() {
        global $wpdb;
        
        // Controlla la versione del database
        $current_db_version = get_option('fp_task_agenda_db_version', '0');
        
        // Se la versione è già aggiornata, non fare nulla (previene perdite dati)
        if (version_compare($current_db_version, self::DB_VERSION, '>=')) {
            // Verifica solo che le colonne essenziali esistano (migrazione sicura)
            self::maybe_add_missing_columns();
            return;
        }
        
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
            'recurrence_day' => "ALTER TABLE $table_name ADD COLUMN recurrence_day int(11) NULL AFTER recurrence_interval",
            'recurrence_parent_id' => "ALTER TABLE $table_name ADD COLUMN recurrence_parent_id bigint(20) NULL AFTER recurrence_day",
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
        
        // Aggiungi colonna deleted_at per soft delete
        $deleted_at_check = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'deleted_at'
        ));
        
        if (empty($deleted_at_check)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN deleted_at datetime NULL AFTER completed_at");
            $wpdb->query("ALTER TABLE $table_name ADD KEY deleted_at (deleted_at)");
        }
        
        // Aggiorna la versione del database SOLO dopo che tutte le modifiche sono state applicate con successo
        update_option('fp_task_agenda_db_version', self::DB_VERSION);
    }
    
    /**
     * Aggiunge colonne mancanti senza ricreare la tabella (migrazione sicura)
     * 
     * Questa funzione viene chiamata quando il database è già alla versione corrente
     * ma potrebbe mancare qualche colonna aggiunta in versioni successive.
     */
    public static function maybe_add_missing_columns() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_task_agenda';
        
        // Verifica se la tabella esiste
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if (!$table_exists) {
            // Se la tabella non esiste, ricreala completamente
            self::create_tables();
            return;
        }
        
        // Lista di tutte le colonne che dovrebbero esistere
        $required_columns = array(
            'client_id' => "ALTER TABLE $table_name ADD COLUMN client_id bigint(20) NULL AFTER due_date",
            'recurrence_type' => "ALTER TABLE $table_name ADD COLUMN recurrence_type varchar(20) NULL AFTER client_id",
            'recurrence_interval' => "ALTER TABLE $table_name ADD COLUMN recurrence_interval int(11) NULL DEFAULT 1 AFTER recurrence_type",
            'recurrence_day' => "ALTER TABLE $table_name ADD COLUMN recurrence_day int(11) NULL AFTER recurrence_interval",
            'recurrence_parent_id' => "ALTER TABLE $table_name ADD COLUMN recurrence_parent_id bigint(20) NULL AFTER recurrence_day",
            'next_recurrence_date' => "ALTER TABLE $table_name ADD COLUMN next_recurrence_date datetime NULL AFTER recurrence_parent_id",
            'deleted_at' => "ALTER TABLE $table_name ADD COLUMN deleted_at datetime NULL AFTER completed_at"
        );
        
        // Verifica e aggiungi colonne mancanti
        foreach ($required_columns as $column_name => $sql) {
            $column_check = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM $table_name LIKE %s",
                $column_name
            ));
            
            if (empty($column_check)) {
                // Aggiungi la colonna
                $wpdb->query($sql);
                
                // Aggiungi indici se necessario
                if ($column_name === 'client_id') {
                    $wpdb->query("ALTER TABLE $table_name ADD KEY client_id (client_id)");
                }
                if ($column_name === 'recurrence_parent_id') {
                    $wpdb->query("ALTER TABLE $table_name ADD KEY recurrence_parent_id (recurrence_parent_id)");
                }
                if ($column_name === 'next_recurrence_date') {
                    $wpdb->query("ALTER TABLE $table_name ADD KEY next_recurrence_date (next_recurrence_date)");
                }
                if ($column_name === 'deleted_at') {
                    $wpdb->query("ALTER TABLE $table_name ADD KEY deleted_at (deleted_at)");
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
            'recurrence_day' => null,
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
            
            // Recurrence day - giorno specifico per ricorrenza mensile (1-31) o settimanale (0-6)
            if (isset($data['recurrence_day']) && $data['recurrence_day'] !== null && $data['recurrence_day'] !== '') {
                $insert_data['recurrence_day'] = absint($data['recurrence_day']);
            } else {
                $insert_data['recurrence_day'] = null;
            }
            
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
            if (in_array($key, array('client_id', 'recurrence_interval', 'recurrence_day', 'recurrence_parent_id', 'user_id'))) {
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
        
        if (isset($data['recurrence_day'])) {
            $update_data['recurrence_day'] = ($data['recurrence_day'] !== null && $data['recurrence_day'] !== '') ? absint($data['recurrence_day']) : null;
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
     * Elimina un task (soft delete - imposta deleted_at)
     * Gli admin (manage_options) possono eliminare qualsiasi task, anche creato dalla sync Publisher.
     */
    public static function delete_task($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $task = self::get_task($id);
        if (!$task && current_user_can('manage_options')) {
            $task = self::get_task_by_id($id, false);
        }
        if (!$task) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'));
        }
        if ($task->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'));
        }
        
        // Soft delete: imposta deleted_at invece di eliminare fisicamente
        $result = $wpdb->update(
            $table_name,
            array('deleted_at' => current_time('mysql')),
            array('id' => absint($id)),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante l\'archiviazione del task', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Ripristina un task archiviato
     * Gli admin (manage_options) possono ripristinare qualsiasi task.
     */
    public static function restore_task($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $task = self::get_archived_task($id);
        if (!$task && current_user_can('manage_options')) {
            $task = self::get_task_by_id($id, true);
        }
        if (!$task) {
            return new \WP_Error('not_found', __('Task archiviato non trovato', 'fp-task-agenda'));
        }
        if ($task->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new \WP_Error('not_found', __('Task archiviato non trovato', 'fp-task-agenda'));
        }
        
        // Ripristina: imposta deleted_at a NULL
        $result = $wpdb->update(
            $table_name,
            array('deleted_at' => null),
            array('id' => absint($id)),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante il ripristino del task', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Elimina definitivamente un task (hard delete)
     * Gli admin (manage_options) possono eliminare definitivamente qualsiasi task.
     */
    public static function permanently_delete_task($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $task = self::get_archived_task($id);
        if (!$task && current_user_can('manage_options')) {
            $task = self::get_task_by_id($id, true);
        }
        if (!$task) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'));
        }
        if ($task->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'));
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => absint($id)),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante l\'eliminazione definitiva del task', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Ottiene un task archiviato (con deleted_at)
     */
    public static function get_archived_task($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND deleted_at IS NOT NULL",
            absint($id),
            get_current_user_id()
        ));
        
        return $task;
    }
    
    /**
     * Ottiene i task archiviati
     */
    public static function get_archived_tasks($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'deleted_at',
            'order' => 'DESC',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("deleted_at IS NOT NULL");
        if (!current_user_can('manage_options')) {
            $where[] = "user_id = " . absint(get_current_user_id());
        }
        
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare("(title LIKE %s OR description LIKE %s)", $search_term, $search_term);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $allowed_orderby = array('id', 'title', 'deleted_at', 'created_at');
        $orderby_field = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'deleted_at';
        $order_direction = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = absint($args['per_page']);
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby_field $order_direction LIMIT $limit OFFSET $offset";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Conta i task archiviati
     */
    public static function count_archived_tasks($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $where = array("deleted_at IS NOT NULL");
        if (!current_user_can('manage_options')) {
            $where[] = "user_id = " . absint(get_current_user_id());
        }
        
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare("(title LIKE %s OR description LIKE %s)", $search_term, $search_term);
        }
        
        $where_clause = implode(' AND ', $where);
        
        return absint($wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where_clause"));
    }
    
    /**
     * Pulisce i task archiviati più vecchi di X giorni (eliminazione definitiva)
     */
    public static function cleanup_archived_tasks($days = 30) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $cutoff_date = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE deleted_at IS NOT NULL AND deleted_at < %s AND user_id = %d",
            $cutoff_date,
            get_current_user_id()
        ));
        
        return $result;
    }
    
    /**
     * Pulisce TUTTI i task archiviati più vecchi di X giorni (per cron job)
     */
    public static function cleanup_all_archived_tasks($days = 30) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $cutoff_date = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE deleted_at IS NOT NULL AND deleted_at < %s",
            $cutoff_date
        ));
        
        return $result;
    }
    
    /**
     * Ottiene un singolo task (esclude archiviati). Per l'utente corrente; gli admin vedono qualsiasi task.
     */
    public static function get_task($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND deleted_at IS NULL",
            absint($id),
            get_current_user_id()
        ));
        if (!$task && current_user_can('manage_options')) {
            $task = self::get_task_by_id($id, false);
        }
        
        return $task;
    }
    
    /**
     * Ottiene un task per ID (senza filtro utente). Usato da admin per eliminare/ripristinare qualsiasi task.
     *
     * @param int  $id            ID task
     * @param bool $deleted_only  true = solo task archiviati (deleted_at IS NOT NULL)
     * @return object|null
     */
    public static function get_task_by_id($id, $deleted_only = false) {
        global $wpdb;
        $table_name = self::get_table_name();
        $deleted_cond = $deleted_only ? 'AND deleted_at IS NOT NULL' : 'AND deleted_at IS NULL';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d {$deleted_cond}",
            absint($id)
        ));
    }
    
    /**
     * Ottiene i task con filtri (esclude archiviati)
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
            'search' => '',
            'show_completed' => true // da impostazioni: false = nasconde task completati quando status=all
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Gli admin vedono tutte le task (anche quelle create dalla sync Publisher con altro user_id)
        $where = array("deleted_at IS NULL");
        if (!current_user_can('manage_options')) {
            $where[] = "user_id = " . absint(get_current_user_id());
        }
        
        if ($args['status'] !== 'all') {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        } elseif (isset($args['show_completed']) && !$args['show_completed']) {
            // Nascondi task completati quando show_completed è false
            $where[] = "status != 'completed'";
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
        
        // Gestisci ordinamento con validazione
        $allowed_orderby = array('id', 'title', 'priority', 'status', 'due_date', 'created_at', 'client_id');
        $orderby_field = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order_direction = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // SEMPRE applica questo ordinamento come primo criterio:
        // 1. Scaduti (overdue) = -1
        // 2. In corso (in_progress) = 0
        // 3. Da fare (pending) = 1
        // 4. Completati (completed) = 2
        $status_order = "CASE 
            WHEN status != 'completed' AND due_date IS NOT NULL AND DATE(due_date) < CURDATE() THEN -1
            WHEN status = 'in_progress' THEN 0
            WHEN status = 'pending' THEN 1
            WHEN status = 'completed' THEN 2
            ELSE 1 
        END ASC";
        
        // Poi applica l'ordinamento specifico richiesto
        if ($orderby_field === 'priority') {
            // Ordine logico: urgent=4, high=3, normal=2, low=1
            $orderby = $status_order . ", CASE priority 
                WHEN 'urgent' THEN 4 
                WHEN 'high' THEN 3 
                WHEN 'normal' THEN 2 
                WHEN 'low' THEN 1 
                ELSE 0 
            END " . $order_direction;
        } elseif ($orderby_field === 'status') {
            // Per status, usa solo l'ordinamento logico (già definito in $status_order)
            $orderby = $status_order;
        } elseif ($orderby_field === 'due_date') {
            // Per due_date: ordina per data scadenza, gestendo i NULL
            if ($order_direction === 'ASC') {
                $orderby = $status_order . ", CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC";
            } else {
                $orderby = $status_order . ", CASE WHEN due_date IS NULL THEN 0 ELSE 1 END DESC, due_date DESC";
            }
        } elseif ($orderby_field === 'created_at') {
            // Ordina per data creazione
            $orderby = $status_order . ", created_at " . $order_direction;
        } elseif ($orderby_field === 'title') {
            // Ordina per titolo
            $orderby = $status_order . ", title " . $order_direction;
        } elseif ($orderby_field === 'client_id') {
            // Ordina per cliente
            $orderby = $status_order . ", client_id " . $order_direction;
        } else {
            // Ordina per campo specificato
            $orderby = $status_order . ", " . $orderby_field . ' ' . $order_direction;
        }
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = absint($args['per_page']);
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby LIMIT $limit OFFSET $offset";
        
        $tasks = $wpdb->get_results($query);
        
        return $tasks;
    }
    
    /**
     * Conta i task in scadenza (entro 3 giorni, non completati, non archiviati)
     */
    public static function count_tasks_due_soon() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $today = current_time('Y-m-d');
        $three_days_later = date('Y-m-d', strtotime('+3 days', strtotime($today)));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d 
            AND status != 'completed' 
            AND due_date IS NOT NULL 
            AND DATE(due_date) >= %s 
            AND DATE(due_date) <= %s
            AND deleted_at IS NULL",
            get_current_user_id(),
            $today,
            $three_days_later
        ));
        
        return absint($count);
    }
    
    /**
     * Conta i task con filtri (esclude archiviati)
     */
    public static function count_tasks($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'status' => 'all',
            'priority' => 'all',
            'client_id' => 'all',
            'search' => '',
            'show_completed' => true
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("deleted_at IS NULL");
        if (!current_user_can('manage_options')) {
            $where[] = "user_id = " . absint(get_current_user_id());
        }
        
        if ($args['status'] !== 'all') {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        } elseif (isset($args['show_completed']) && !$args['show_completed']) {
            $where[] = "status != 'completed'";
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
        
        $cutoff_date = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE status = 'completed' AND completed_at < %s AND user_id = %d",
            $cutoff_date,
            get_current_user_id()
        ));
        
        return $result;
    }
}
