<?php
/**
 * Integrazione con FP Publisher
 * 
 * Verifica i post mancanti in FP Publisher e crea automaticamente task in FP Task Agenda
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class PublisherIntegration {
    
    /**
     * Verifica se la tabella FP Publisher esiste
     */
    private static function publisher_table_exists() {
        global $wpdb;
        
        $publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
        $table_name_safe = esc_sql($publisher_table);
        
        // Metodo 1: SHOW TABLES
        $table_check = $wpdb->get_var("SHOW TABLES LIKE '{$table_name_safe}'");
        $table_exists = ($table_check === $publisher_table);
        
        // Metodo 2: information_schema (alternativa)
        if (!$table_exists && defined('DB_NAME')) {
            $table_exists = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $publisher_table
            ));
        }
        
        // Metodo 3: prova query diretta (come fallback)
        if (!$table_exists) {
            $wpdb->suppress_errors(true);
            $test_query = $wpdb->get_var("SELECT COUNT(*) FROM {$publisher_table} LIMIT 1");
            $wpdb->suppress_errors(false);
            if ($wpdb->last_error === '') {
                $table_exists = true;
            }
        }
        
        return $table_exists;
    }
    
    /**
     * Ottiene tutti i workspace/clienti da FP Publisher
     */
    private static function get_publisher_workspaces() {
        global $wpdb;
        
        if (!self::publisher_table_exists()) {
            return array();
        }
        
        $publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
        $table_name_safe = esc_sql($publisher_table);
        
        // Ottieni tutti i remote sites
        $workspaces = $wpdb->get_results(
            "SELECT id, name FROM {$table_name_safe} WHERE name IS NOT NULL AND name != ''"
        );
        
        // Se non trova nulla, prova a vedere quali colonne esistono
        if (empty($workspaces)) {
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name_safe}");
            $name_column = null;
            foreach ($columns as $col) {
                if (in_array(strtolower($col->Field), array('name', 'title', 'client_name', 'site_name'))) {
                    $name_column = $col->Field;
                    break;
                }
            }
            
            if ($name_column && $name_column !== 'name') {
                $name_column_safe = esc_sql($name_column);
                $workspaces = $wpdb->get_results(
                    "SELECT id, `{$name_column_safe}` as name FROM {$table_name_safe} WHERE `{$name_column_safe}` IS NOT NULL AND `{$name_column_safe}` != ''"
                );
            }
        }
        
        return $workspaces ? $workspaces : array();
    }
    
    /**
     * Trova il nome della colonna nel database (ricerca flessibile)
     */
    private static function find_column_name($table_name, $possible_names) {
        global $wpdb;
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        if (empty($columns)) {
            return null;
        }
        
        $possible_names_lower = array_map('strtolower', $possible_names);
        
        foreach ($columns as $col) {
            $col_name_lower = strtolower($col->Field);
            // Cerca corrispondenza esatta o parziale
            foreach ($possible_names_lower as $possible_name) {
                if ($col_name_lower === $possible_name || 
                    strpos($col_name_lower, $possible_name) !== false ||
                    strpos($possible_name, $col_name_lower) !== false) {
                    return $col->Field;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Ottiene l'ID di un utente admin di default per le task automatiche
     */
    private static function get_default_user_id() {
        // Se c'è un utente corrente, usalo
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0) {
            return $current_user_id;
        }
        
        // Altrimenti, trova il primo amministratore disponibile
        $admins = get_users(array(
            'role' => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ));
        
        if (!empty($admins)) {
            return $admins[0]->ID;
        }
        
        // Fallback: usa l'ID 1 (solitamente il primo admin)
        return 1;
    }
    
    /**
     * Verifica se esiste già una task per questo cliente e tipo
     * 
     * @param int $client_id ID del cliente
     * @param string $task_type Tipo di task (parte del titolo)
     * @return bool True se esiste una task pending/in_progress, false altrimenti
     */
    private static function task_exists($client_id, $task_type) {
        if (empty($client_id)) {
            return false;
        }
        
        global $wpdb;
        $table_name = Database::get_table_name();
        
        // Cerca task pendenti o in corso per questo cliente con questo tipo
        // Escludi task ricorrenti completate (quelle vengono rigenerate automaticamente)
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name 
            WHERE client_id = %d 
            AND status IN ('pending', 'in_progress')
            AND title LIKE %s
            AND deleted_at IS NULL
            LIMIT 1",
            absint($client_id),
            '%' . $wpdb->esc_like($task_type) . '%'
        ));
        
        $exists = !empty($task);
        
        if (defined('WP_DEBUG') && WP_DEBUG && $exists) {
            error_log("FP Task Agenda - task_exists: trovata task ID {$task->id} per cliente {$client_id}, tipo: {$task_type}");
        }
        
        return $exists;
    }
    
    /**
     * Ottiene o crea il cliente da un workspace FP Publisher
     */
    private static function get_or_create_client($workspace_id, $workspace_name) {
        global $wpdb;
        
        $clients_table = Client::get_table_name();
        
        // Cerca cliente esistente con questo source_id
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $clients_table WHERE source = 'fp_publisher' AND source_id = %d",
            absint($workspace_id)
        ));
        
        if ($client) {
            return $client->id;
        }
        
        // Cerca per nome
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $clients_table WHERE name = %s",
            sanitize_text_field($workspace_name)
        ));
        
        if ($client) {
            // Aggiorna con source_id se non ce l'ha
            $wpdb->update(
                $clients_table,
                array('source' => 'fp_publisher', 'source_id' => absint($workspace_id)),
                array('id' => $client->id),
                array('%s', '%d'),
                array('%d')
            );
            return $client->id;
        }
        
        // Crea nuovo cliente
        $result = Client::create($workspace_name, 'fp_publisher', $workspace_id);
        
        if (is_wp_error($result)) {
            return null;
        }
        
        return $result;
    }
    
    /**
     * Crea task automaticamente per un cliente
     * 
     * @param int $client_id ID del cliente
     * @param string $task_type Tipo di task (titolo)
     * @param string $workspace_name Nome del workspace
     * @param string $description Descrizione della task
     * @param array $recurrence_opts Opzioni ricorrenza: ['type' => 'monthly', 'interval' => 1, 'day' => null]
     * @return bool|int ID task se creata, false se errore o già esistente
     */
    private static function create_task_for_client($client_id, $task_type, $workspace_name, $description = '', $recurrence_opts = null) {
        if (empty($client_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - create_task_for_client: client_id vuoto');
            }
            return false;
        }
        
        // Verifica se esiste già una task simile ATTIVA (pending/in_progress)
        // Se la task è completata, possiamo crearne una nuova se necessario
        if (self::task_exists($client_id, $task_type)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - Task già esistente (pending/in_progress) per cliente {$client_id}, tipo: {$task_type}");
            }
            return false; // Task già esistente e attiva, non creare duplicato
        }
        
        // Per task ricorrenti, verifica anche se esiste una task ricorrente attiva
        // Se esiste una task ricorrente completata, il sistema la rigenererà automaticamente
        if (!empty($recurrence_opts)) {
            global $wpdb;
            $table_name = Database::get_table_name();
            $existing_recurring = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name 
                WHERE client_id = %d 
                AND status IN ('pending', 'in_progress')
                AND title LIKE %s
                AND recurrence_type IS NOT NULL
                AND deleted_at IS NULL
                LIMIT 1",
                absint($client_id),
                '%' . $wpdb->esc_like($task_type) . '%'
            ));
            
            if ($existing_recurring) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - Task ricorrente già esistente (pending/in_progress) per cliente {$client_id}, tipo: {$task_type}");
                }
                return false; // Task ricorrente già esistente e attiva
            }
            
            // Se esiste una task ricorrente completata, verifica se deve essere rigenerata
            // (questo viene gestito dal cron job, ma possiamo comunque creare una nuova se necessario)
            $completed_recurring = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name 
                WHERE client_id = %d 
                AND status = 'completed'
                AND title LIKE %s
                AND recurrence_type = %s
                AND deleted_at IS NULL
                AND (next_recurrence_date IS NULL OR next_recurrence_date <= %s)
                ORDER BY completed_at DESC
                LIMIT 1",
                absint($client_id),
                '%' . $wpdb->esc_like($task_type) . '%',
                $recurrence_opts['type'],
                current_time('mysql')
            ));
            
            if ($completed_recurring) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - Task ricorrente completata trovata per cliente {$client_id}, tipo: {$task_type}. Verrà rigenerata dal cron job.");
                }
                // Non creiamo una nuova task, il cron job la rigenererà automaticamente
                return false;
            }
        }
        
        // Calcola la data di scadenza (per task ricorrenti, fine mese corrente)
        $due_date = null;
        $next_recurrence_date = null;
        
        if (!empty($recurrence_opts) && $recurrence_opts['type'] === 'monthly') {
            // Per task mensili, imposta scadenza a fine mese corrente
            $due_date = date('Y-m-t 23:59:59'); // Ultimo giorno del mese
            // Prossima ricorrenza: fine mese successivo
            $next_recurrence_date = date('Y-m-t 23:59:59', strtotime('+1 month'));
        }
        
        $task_data = array(
            'title' => $task_type . ' - ' . $workspace_name,
            'description' => !empty($description) ? $description : sprintf(
                __('Task creata automaticamente per %s. Verifica i post mancanti in FP Publisher.', 'fp-task-agenda'),
                $workspace_name
            ),
            'priority' => 'normal',
            'status' => 'pending',
            'client_id' => $client_id,
            'user_id' => self::get_default_user_id(), // Usa admin di default per task automatiche
            'due_date' => $due_date
        );
        
        // Aggiungi ricorrenza se specificata
        if (!empty($recurrence_opts)) {
            $task_data['recurrence_type'] = $recurrence_opts['type'];
            $task_data['recurrence_interval'] = !empty($recurrence_opts['interval']) ? absint($recurrence_opts['interval']) : 1;
            $task_data['recurrence_day'] = !empty($recurrence_opts['day']) ? absint($recurrence_opts['day']) : null;
            $task_data['next_recurrence_date'] = $next_recurrence_date;
        }
        
        $result = Database::insert_task($task_data);
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Errore creazione task: ' . $result->get_error_message());
            }
            return false;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Task Agenda - Task creata con successo: ID {$result}, Cliente {$client_id}, Tipo: {$task_type}");
        }
        
        return $result;
    }
    
    /**
     * Verifica post mancanti per social (ultimo post programmato)
     */
    public static function check_social_posts($workspace_id, $workspace_name) {
        global $wpdb;
        
        if (!self::publisher_table_exists()) {
            return false;
        }
        
        $publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
        $table_name_safe = esc_sql($publisher_table);
        
        // Trova la colonna "Ultimo Post Programmato" (ricerca flessibile)
        $column_name = self::find_column_name($table_name_safe, array(
            'ultimo_post_programmato',
            'ultimo_post',
            'last_post',
            'last_post_scheduled',
            'ultimo_post_social',
            'last_social_post',
            'last_post_date',
            'ultima_pubblicazione',
            'last_publication'
        ));
        
        if (!$column_name) {
            // Colonna non trovata, non possiamo verificare
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Colonna "Ultimo Post Programmato" non trovata nella tabella FP Publisher');
            }
            return false;
        }
        
        // Ottieni l'ultimo post programmato per questo workspace
        // Usa esc_sql per sicurezza anche se la colonna è già validata
        $column_name_safe = esc_sql($column_name);
        
        // Cerca anche colonne per stato/prossimo post/avanzamento
        $status_column = self::find_column_name($table_name_safe, array(
            'status', 'stato', 'ultimo_post_status', 'last_post_status'
        ));
        $next_post_column = self::find_column_name($table_name_safe, array(
            'prossimo_post', 'next_post', 'prossimo_post_programmato', 'next_post_scheduled'
        ));
        $progress_column = self::find_column_name($table_name_safe, array(
            'avanzamento', 'progress', 'monthly_progress'
        ));
        
        // Costruisci query per ottenere tutte le colonne disponibili
        $select_columns = array("id", "name", "`{$column_name_safe}`");
        if ($status_column) {
            $status_column_safe = esc_sql($status_column);
            $select_columns[] = "`{$status_column_safe}`";
        }
        if ($next_post_column) {
            $next_post_column_safe = esc_sql($next_post_column);
            $select_columns[] = "`{$next_post_column_safe}`";
        }
        if ($progress_column) {
            $progress_column_safe = esc_sql($progress_column);
            $select_columns[] = "`{$progress_column_safe}`";
        }
        
        $workspace = $wpdb->get_row($wpdb->prepare(
            "SELECT " . implode(", ", $select_columns) . " FROM {$table_name_safe} WHERE id = %d",
            absint($workspace_id)
        ));
        
        // Verifica se il workspace esiste e ha un valore per la colonna
        if (!$workspace) {
            return false;
        }
        
        // Accedi alla colonna dinamicamente usando il nome trovato
        $column_value = isset($workspace->$column_name) ? $workspace->$column_name : null;
        
        // Verifica se c'è uno stato "Attenzione" o problemi
        $has_attention = false;
        if ($status_column && isset($workspace->$status_column)) {
            $status_value = strtolower($workspace->$status_column);
            if (strpos($status_value, 'attenzione') !== false || 
                strpos($status_value, 'attention') !== false ||
                strpos($status_value, 'warning') !== false ||
                strpos($status_value, 'problema') !== false) {
                $has_attention = true;
            }
        }
        
        // Verifica se ci sono progressi 0/1 per Reel o Art.
        $has_missing_content = false;
        $missing_content_types = array();
        if ($progress_column && isset($workspace->$progress_column)) {
            $progress_value = (string) $workspace->$progress_column;
            // Cerca pattern tipo "Reel 0/1" o "Art. 0/1"
            if (preg_match('/Reel\s*0\s*\/\s*\d+/i', $progress_value, $matches)) {
                $has_missing_content = true;
                $missing_content_types[] = 'Reel';
            }
            if (preg_match('/Art\.?\s*0\s*\/\s*\d+/i', $progress_value, $matches)) {
                $has_missing_content = true;
                $missing_content_types[] = 'Articolo';
            }
        }
        
        if (empty($column_value) && $column_value !== '0' && $column_value !== 0) {
            // Nessun post programmato, potrebbe essere un problema
            $client_id = self::get_or_create_client($workspace_id, $workspace_name);
            
            if ($client_id) {
                return self::create_task_for_client(
                    $client_id,
                    __('Post Social Mancanti', 'fp-task-agenda'),
                    $workspace_name,
                    __('Nessun post social programmato trovato per questo cliente.', 'fp-task-agenda')
                );
            }
            return false;
        }
        
        // Verifica se l'ultimo post è troppo vecchio
        $last_post_date = $column_value;
        $days_threshold = get_option('fp_task_agenda_social_days_threshold', 7);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Task Agenda - check_social_posts: Workspace {$workspace_name} (ID: {$workspace_id}), Ultimo post: {$last_post_date}, Soglia: {$days_threshold} giorni");
        }
        
        // Converti la data in timestamp
        $last_post_timestamp = strtotime($last_post_date);
        if (!$last_post_timestamp) {
            // Se non riesce a convertire la data, potrebbe essere un formato non standard
            // Prova a loggare per debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Impossibile convertire data ultimo post: ' . $last_post_date . ' per workspace ' . $workspace_name);
            }
            return false;
        }
        
        $days_ago = floor((time() - $last_post_timestamp) / (60 * 60 * 24));
        
        // Se la data è nel futuro (post programmato), considera giorni_ago = 0
        if ($days_ago < 0) {
            $days_ago = 0;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Task Agenda - check_social_posts: Giorni trascorsi: {$days_ago}, Soglia: {$days_threshold}");
        }
        
        // Crea task SOLO se c'è stato "Attenzione"
        // Il sistema FP Publisher gestisce già la logica per determinare quando impostare "Attenzione"
        // (post vecchi, progressi 0/1, ecc.) quindi usiamo solo quello come criterio
        $should_create_task = false;
        $task_description = '';
        $task_title = __('Post Social - Attenzione', 'fp-task-agenda');
        
        if ($has_attention) {
            $should_create_task = true;
            $task_description = __('Stato "Attenzione" rilevato per l\'ultimo post programmato. Verifica necessaria.', 'fp-task-agenda');
            
            // Aggiungi informazioni aggiuntive se disponibili (solo per contesto, non come trigger)
            $additional_info = array();
            
            if ($days_ago >= $days_threshold) {
                $additional_info[] = sprintf(
                    __('Ultimo post pubblicato %d giorni fa (soglia: %d giorni)', 'fp-task-agenda'),
                    $days_ago,
                    $days_threshold
                );
            }
            
            if ($has_missing_content && !empty($missing_content_types)) {
                $missing_types_str = implode(' e ', $missing_content_types);
                $additional_info[] = sprintf(
                    __('Contenuti mancanti: %s (0/1)', 'fp-task-agenda'),
                    $missing_types_str
                );
            }
            
            if (!empty($additional_info)) {
                $task_description .= ' ' . __('Dettagli:', 'fp-task-agenda') . ' ' . implode(', ', $additional_info) . '.';
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_social_posts: Stato 'Attenzione' rilevato per {$workspace_name}");
            }
        } else {
            // Nessuna attenzione, non creare task
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_social_posts: Nessuno stato 'Attenzione' per {$workspace_name}, nessuna task necessaria");
            }
        }
        
        if ($should_create_task) {
            $client_id = self::get_or_create_client($workspace_id, $workspace_name);
            
            if ($client_id) {
                $result = self::create_task_for_client(
                    $client_id,
                    $task_title,
                    $workspace_name,
                    $task_description
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - check_social_posts: Risultato creazione task: " . ($result ? "OK (ID: {$result})" : "FALLITA"));
                }
                
                return $result;
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - check_social_posts: Impossibile ottenere/creare cliente per workspace {$workspace_name}");
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_social_posts: Post ancora recente ({$days_ago} giorni < {$days_threshold}), nessuna task necessaria");
            }
        }
        
        return false;
    }
    
    /**
     * Verifica post mancanti per WordPress (avanzamento mensile)
     */
    public static function check_wordpress_posts($workspace_id, $workspace_name) {
        global $wpdb;
        
        if (!self::publisher_table_exists()) {
            return false;
        }
        
        $publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
        $table_name_safe = esc_sql($publisher_table);
        
        // Trova la colonna "Avanzamento" (ricerca flessibile)
        $column_name = self::find_column_name($table_name_safe, array(
            'avanzamento',
            'progress',
            'monthly_progress',
            'articoli_mensili',
            'monthly_articles',
            'wp_progress',
            'article_progress',
            'articoli_progress'
        ));
        
        if (!$column_name) {
            // Colonna non trovata, non possiamo verificare
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Colonna "Avanzamento" non trovata nella tabella FP Publisher');
            }
            return false;
        }
        
        // Ottieni l'avanzamento per questo workspace
        // Usa esc_sql per sicurezza anche se la colonna è già validata
        $column_name_safe = esc_sql($column_name);
        
        // Cerca anche colonna per stato "Attenzione" (potrebbe essere la stessa colonna status dei post social)
        $status_column = self::find_column_name($table_name_safe, array(
            'status', 'stato', 'ultimo_post_status', 'last_post_status', 'avanzamento_status'
        ));
        
        // Costruisci query per ottenere tutte le colonne disponibili
        $select_columns = array("id", "name", "`{$column_name_safe}`");
        if ($status_column && $status_column !== $column_name) {
            $status_column_safe = esc_sql($status_column);
            $select_columns[] = "`{$status_column_safe}`";
        }
        
        $workspace = $wpdb->get_row($wpdb->prepare(
            "SELECT " . implode(", ", $select_columns) . " FROM {$table_name_safe} WHERE id = %d",
            absint($workspace_id)
        ));
        
        if (!$workspace) {
            return false;
        }
        
        // Accedi alla colonna dinamicamente usando il nome trovato
        $avanzamento = isset($workspace->$column_name) ? $workspace->$column_name : null;
        
        // Verifica se c'è uno stato "Attenzione" per WordPress
        $has_attention = false;
        if ($status_column && isset($workspace->$status_column)) {
            $status_value = strtolower($workspace->$status_column);
            if (strpos($status_value, 'attenzione') !== false || 
                strpos($status_value, 'attention') !== false ||
                strpos($status_value, 'warning') !== false ||
                strpos($status_value, 'problema') !== false) {
                $has_attention = true;
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Task Agenda - check_wordpress_posts: Workspace {$workspace_name} (ID: {$workspace_id}), Avanzamento: " . var_export($avanzamento, true));
        }
        
        // Se l'avanzamento è null o vuoto, considera che mancano articoli
        if (empty($avanzamento) && $avanzamento !== '0' && $avanzamento !== 0) {
            $needs_article = true;
            $description = __('Nessun dato di avanzamento disponibile. Verifica gli articoli WordPress per questo mese.', 'fp-task-agenda');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_wordpress_posts: Avanzamento vuoto/null, creazione task per {$workspace_name}");
            }
        } else {
            // Interpreta l'avanzamento
            // Potrebbe essere un numero (es. 2/4 articoli), una percentuale, o un testo
            // Verifica se indica mancanza di articoli per il mese corrente
            $needs_article = false;
            $description = '';
            
            if (is_numeric($avanzamento)) {
                // Se è un numero, verifica se è inferiore a una soglia
                // Assumiamo che 0 o valori molto bassi indichino mancanza
                if ($avanzamento == 0 || $avanzamento < 1) {
                    $needs_article = true;
                    $description = __('Nessun articolo WordPress pubblicato questo mese.', 'fp-task-agenda');
                }
            } else {
                // Se è una stringa, cerca pattern come "0/4", "0%", "nessun articolo", etc.
                $avanzamento_str = (string) $avanzamento;
                $avanzamento_lower = strtolower($avanzamento_str);
                
                // Pattern comuni che indicano mancanza
                $missing_patterns = array(
                    '0/',
                    '/0',
                    '0%',
                    'nessun',
                    'no article',
                    'mancante',
                    'missing',
                    'da fare',
                    'to do'
                );
                
                foreach ($missing_patterns as $pattern) {
                    if (strpos($avanzamento_lower, $pattern) !== false) {
                        $needs_article = true;
                        $description = sprintf(
                            __('Avanzamento articoli WordPress: %s. È necessario pubblicare articoli per il mese corrente.', 'fp-task-agenda'),
                            $avanzamento
                        );
                        break;
                    }
                }
                
                // Se contiene pattern tipo "X/Y" dove X è 0 o molto basso rispetto a Y
                if (preg_match('/^(\d+)\s*\/\s*(\d+)/i', $avanzamento_str, $matches)) {
                    $current = (int) $matches[1];
                    $target = (int) $matches[2];
                    
                    // Se non ci sono articoli pubblicati (0/Y) o se siamo sotto il 50% del target
                    if ($current == 0 || ($target > 0 && ($current / $target) < 0.5)) {
                        $needs_article = true;
                        $description = sprintf(
                            __('Avanzamento articoli WordPress: %s (pubblicati %d su %d previsti). È necessario pubblicare più articoli per il mese corrente.', 'fp-task-agenda'),
                            $avanzamento,
                            $current,
                            $target
                        );
                    }
                } elseif (preg_match('/^0\s*\/\s*\d+/i', $avanzamento_str)) {
                    // Pattern alternativo per "0/Y"
                    $needs_article = true;
                    $description = sprintf(
                        __('Avanzamento articoli WordPress: %s. È necessario pubblicare articoli per il mese corrente.', 'fp-task-agenda'),
                        $avanzamento
                    );
                }
                
                // Verifica anche pattern tipo "Art. 0/1" o "Art 0/1" nell'avanzamento
                if (preg_match('/Art\.?\s*0\s*\/\s*\d+/i', $avanzamento_str, $matches)) {
                    $needs_article = true;
                    if (empty($description)) {
                        $description = sprintf(
                            __('Avanzamento articoli WordPress: %s. Articoli mancanti rilevati (0/1). È necessario pubblicare articoli per il mese corrente.', 'fp-task-agenda'),
                            $avanzamento
                        );
                    } else {
                        $description .= ' ' . __('Articoli mancanti rilevati (0/1).', 'fp-task-agenda');
                    }
                }
            }
        }
        
        // Crea task SOLO se c'è stato "Attenzione" OPPURE se l'avanzamento indica chiaramente un problema critico
        // (avanzamento vuoto/null o pattern espliciti di mancanza)
        $should_create_task = false;
        
        if ($has_attention) {
            // Se c'è stato "Attenzione", crea sempre task
            $should_create_task = true;
            if (empty($description)) {
                $description = __('Stato "Attenzione" rilevato per gli articoli WordPress. Verifica necessaria.', 'fp-task-agenda');
            } else {
                $description = __('Stato "Attenzione" rilevato. ', 'fp-task-agenda') . $description;
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_wordpress_posts: Stato 'Attenzione' rilevato per {$workspace_name}");
            }
        } elseif ($needs_article) {
            // Se non c'è "Attenzione" ma l'avanzamento indica problemi, crea task solo se è un caso critico
            // (avanzamento vuoto/null o pattern espliciti come "0/", "nessun", ecc.)
            $avanzamento_str = (string) $avanzamento;
            $avanzamento_lower = strtolower($avanzamento_str);
            
            // Casi critici che giustificano una task anche senza "Attenzione" esplicita
            $critical_patterns = array(
                'nessun',
                'no article',
                'mancante',
                'missing',
                'piano non configurato'
            );
            
            $is_critical = false;
            foreach ($critical_patterns as $pattern) {
                if (strpos($avanzamento_lower, $pattern) !== false) {
                    $is_critical = true;
                    break;
                }
            }
            
            // Anche avanzamento vuoto/null è critico
            if (empty($avanzamento) && $avanzamento !== '0' && $avanzamento !== 0) {
                $is_critical = true;
            }
            
            if ($is_critical) {
                $should_create_task = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - check_wordpress_posts: Caso critico rilevato per {$workspace_name}, creazione task");
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - check_wordpress_posts: Avanzamento insufficiente ma nessuno stato 'Attenzione' per {$workspace_name}, nessuna task necessaria");
                }
            }
        }
        
        if ($should_create_task) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_wordpress_posts: Creazione task ricorrente mensile per {$workspace_name}");
            }
            
            $client_id = self::get_or_create_client($workspace_id, $workspace_name);
            
            if ($client_id) {
                // Crea task ricorrente mensile per articoli blog
                $result = self::create_task_for_client(
                    $client_id,
                    __('Articolo WordPress Mancante', 'fp-task-agenda'),
                    $workspace_name,
                    $description,
                    array(
                        'type' => 'monthly',
                        'interval' => 1,
                        'day' => null // Fine mese automatico
                    )
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - check_wordpress_posts: Risultato creazione task: " . ($result ? "OK (ID: {$result})" : "FALLITA"));
                }
                
                return $result;
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - check_wordpress_posts: Impossibile ottenere/creare cliente per workspace {$workspace_name}");
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_wordpress_posts: Avanzamento sufficiente o nessuno stato 'Attenzione' per {$workspace_name}, nessuna task necessaria");
            }
        }
        
        return false;
    }
    
    /**
     * Metodo principale: verifica tutti i clienti e crea task per post mancanti
     */
    public static function check_missing_posts() {
        // Verifica se l'integrazione è abilitata
        $enabled = get_option('fp_task_agenda_publisher_enabled', true);
        if (!$enabled) {
            return array(
                'success' => false,
                'message' => __('Integrazione FP Publisher disabilitata', 'fp-task-agenda'),
                'tasks_created' => 0
            );
        }
        
        // Sincronizza i clienti se necessario
        $auto_sync = get_option('fp_task_agenda_auto_sync_clients', true);
        if ($auto_sync) {
            Client::sync_from_publisher();
        }
        
        if (!self::publisher_table_exists()) {
            return array(
                'success' => false,
                'message' => __('Tabella FP Publisher non trovata', 'fp-task-agenda'),
                'tasks_created' => 0
            );
        }
        
        $workspaces = self::get_publisher_workspaces();
        
        if (empty($workspaces)) {
            return array(
                'success' => true,
                'message' => __('Nessun workspace trovato in FP Publisher', 'fp-task-agenda'),
                'tasks_created' => 0
            );
        }
        
        $tasks_created = 0;
        $errors = array();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FP Task Agenda - Inizio verifica post mancanti. Workspace trovati: ' . count($workspaces));
        }
        
        foreach ($workspaces as $workspace) {
            try {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - Verifica workspace: ID {$workspace->id}, Nome: {$workspace->name}");
                }
                
                // Verifica post social
                $social_result = self::check_social_posts($workspace->id, $workspace->name);
                if ($social_result) {
                    $tasks_created++;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Task social creata per workspace {$workspace->name}");
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Nessuna task social creata per workspace {$workspace->name} (già esistente o non necessaria)");
                    }
                }
                
                // Verifica articoli WordPress
                $wp_result = self::check_wordpress_posts($workspace->id, $workspace->name);
                if ($wp_result) {
                    $tasks_created++;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Task WordPress creata per workspace {$workspace->name} (ricorrente mensile)");
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Nessuna task WordPress creata per workspace {$workspace->name} (già esistente o non necessaria)");
                    }
                }
            } catch (\Exception $e) {
                $errors[] = sprintf(
                    __('Errore per workspace %s: %s', 'fp-task-agenda'),
                    $workspace->name,
                    $e->getMessage()
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('FP Task Agenda - Errore verifica workspace: ' . $e->getMessage());
                    error_log('FP Task Agenda - Stack trace: ' . $e->getTraceAsString());
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Task Agenda - Verifica completata. Task create: {$tasks_created}");
        }
        
        $message = sprintf(
            __('Verifica completata. Create %d nuove task.', 'fp-task-agenda'),
            $tasks_created
        );
        
        if (!empty($errors)) {
            $message .= ' ' . __('Alcuni errori si sono verificati durante la verifica.', 'fp-task-agenda');
        }
        
        return array(
            'success' => true,
            'message' => $message,
            'tasks_created' => $tasks_created,
            'errors' => $errors
        );
    }
}
