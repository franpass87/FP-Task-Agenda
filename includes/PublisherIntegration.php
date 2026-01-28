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
        
        // Ottieni tutti i remote sites - usa client_name invece di name
        $workspaces = $wpdb->get_results(
            "SELECT id, client_name as name FROM {$table_name_safe} WHERE client_name IS NOT NULL AND client_name != ''"
        );
        
        // Se non trova nulla con client_name, prova con name
        if (empty($workspaces)) {
            $workspaces = $wpdb->get_results(
                "SELECT id, name FROM {$table_name_safe} WHERE name IS NOT NULL AND name != ''"
            );
        }
        
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
            
            if ($name_column) {
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
        $existing_task = self::task_exists($client_id, $task_type);
        if ($existing_task) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - create_task_for_client: Task già esistente (pending/in_progress) per cliente {$client_id}, tipo: {$task_type}");
                // Log più dettagliato per capire quale task esiste
                global $wpdb;
                $table_name = Database::get_table_name();
                $existing_tasks = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, title, status FROM $table_name 
                    WHERE client_id = %d 
                    AND status IN ('pending', 'in_progress')
                    AND title LIKE %s
                    AND deleted_at IS NULL
                    LIMIT 5",
                    absint($client_id),
                    '%' . $wpdb->esc_like($task_type) . '%'
                ));
                if (!empty($existing_tasks)) {
                    foreach ($existing_tasks as $task) {
                        error_log("FP Task Agenda - create_task_for_client: Task esistente - ID: {$task->id}, Titolo: {$task->title}, Status: {$task->status}");
                    }
                }
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
     * Calcola i dati dalla tabella fp_pub_jobs invece di cercare colonne inesistenti
     */
    public static function check_social_posts($workspace_id, $workspace_name) {
        global $wpdb;
        
        if (!self::publisher_table_exists()) {
            return false;
        }
        
        // I dati vengono calcolati dinamicamente dalla tabella jobs
        $jobs_table = $wpdb->prefix . 'fp_pub_jobs';
        $jobs_table_safe = esc_sql($jobs_table);
        
        // Verifica se la tabella jobs esiste
        $jobs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$jobs_table_safe}'") === $jobs_table;
        if (!$jobs_table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Tabella jobs non trovata: ' . $jobs_table);
            }
            return false;
        }
        
        // Ottieni configurazione dal workspace
        $publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
        $workspace = $wpdb->get_row($wpdb->prepare(
            "SELECT content_config FROM {$publisher_table} WHERE id = %d",
            absint($workspace_id)
        ));
        
        if (!$workspace) {
            return false;
        }
        
        // Decodifica content_config per ottenere i target mensili
        $config = !empty($workspace->content_config) ? json_decode($workspace->content_config, true) : array();
        $target_posts = isset($config['posts_per_month']) ? (int)$config['posts_per_month'] : 0;
        $target_reels = isset($config['reels_per_month']) ? (int)$config['reels_per_month'] : 0;
        
        // Calcola statistiche per il mese corrente
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        
        // Conta post social pubblicati questo mese
        $posts_published = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$jobs_table_safe} 
            WHERE remote_site_id = %d 
            AND post_type = 'post'
            AND status IN ('published', 'completed')
            AND COALESCE(published_at, scheduled_at) >= %s
            AND COALESCE(published_at, scheduled_at) <= %s",
            absint($workspace_id),
            $current_month_start,
            $current_month_end
        ));
        
        // Conta reel pubblicati questo mese
        $reels_published = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$jobs_table_safe} 
            WHERE remote_site_id = %d 
            AND post_type = 'reel'
            AND status IN ('published', 'completed')
            AND COALESCE(published_at, scheduled_at) >= %s
            AND COALESCE(published_at, scheduled_at) <= %s",
            absint($workspace_id),
            $current_month_start,
            $current_month_end
        ));
        
        // Ottieni ultimo post social pubblicato
        $last_social_post = $wpdb->get_row($wpdb->prepare(
            "SELECT published_at, scheduled_at, status 
            FROM {$jobs_table_safe} 
            WHERE remote_site_id = %d 
            AND post_type IN ('post', 'reel', 'story')
            AND status IN ('published', 'completed')
            ORDER BY COALESCE(published_at, scheduled_at) DESC 
            LIMIT 1",
            absint($workspace_id)
        ));
        
        // Calcola se c'è bisogno di attenzione o urgenza basandosi sul prossimo post programmato
        // Come mostrato nell'interfaccia: "🟡 Attenzione" o "🔴 Urgente" nella colonna "Ultimo Post Programmato"
        $has_attention = false;
        $is_urgent = false;
        
        // Ottieni il prossimo post programmato
        $next_scheduled_post = $wpdb->get_row($wpdb->prepare(
            "SELECT scheduled_at 
            FROM {$jobs_table_safe} 
            WHERE remote_site_id = %d 
            AND post_type IN ('post', 'reel', 'story')
            AND status IN ('pending', 'scheduled')
            AND scheduled_at > NOW()
            ORDER BY scheduled_at ASC 
            LIMIT 1",
            absint($workspace_id)
        ));
        
        if ($next_scheduled_post && !empty($next_scheduled_post->scheduled_at)) {
            $next_post_timestamp = strtotime($next_scheduled_post->scheduled_at);
            $now_timestamp = time();
            $days_until_next = floor(($next_post_timestamp - $now_timestamp) / (60 * 60 * 24));
            
            // Se il target mensile è già raggiunto, stato OK su Publisher → non creare task
            $target_reached = true;
            if ($target_posts > 0 && $posts_published < $target_posts) {
                $target_reached = false;
            }
            if ($target_reels > 0 && $reels_published < $target_reels) {
                $target_reached = false;
            }
            if ($target_reached && ($target_posts > 0 || $target_reels > 0)) {
                $has_attention = false;
                $is_urgent = false;
            } else {
                // Logica basata su quanto visto nell'interfaccia:
                // - "🔴 Urgente" quando mancano pochi giorni (es. 3-4 giorni)
                // - "🟡 Attenzione" quando mancano più giorni ma comunque vicino (es. 7-14 giorni)
                if ($days_until_next <= 4) {
                    $is_urgent = true;
                    $has_attention = true;
                } elseif ($days_until_next <= 14) {
                    $has_attention = true;
                }
            }
        } else {
            // Nessun post programmato - verifica se l'ultimo post è troppo vecchio
            if ($last_social_post) {
                $last_post_date = !empty($last_social_post->published_at) 
                    ? $last_social_post->published_at 
                    : $last_social_post->scheduled_at;
                
                $last_post_timestamp = strtotime($last_post_date);
                if ($last_post_timestamp) {
                    $days_ago = floor((time() - $last_post_timestamp) / (60 * 60 * 24));
                    $days_threshold = get_option('fp_task_agenda_social_days_threshold', 7);
                    
                    // Se l'ultimo post è vecchio e non ci sono post programmati, è un problema
                    if ($days_ago >= $days_threshold) {
                        if ($days_ago <= 3) {
                            $is_urgent = true;
                        }
                        $has_attention = true;
                    }
                }
            } else {
                // Nessun post social trovato - potrebbe essere un problema se ci sono target configurati
                // Ma non creare task se il target mensile è già raggiunto (stato OK su Publisher)
                if ($target_posts > 0 || $target_reels > 0) {
                    $target_reached = ($target_posts <= 0 || $posts_published >= $target_posts)
                        && ($target_reels <= 0 || $reels_published >= $target_reels);
                    if (!$target_reached) {
                        $has_attention = true;
                    }
                }
            }
        }
        
        // Crea task SOLO se c'è "Attenzione" o "Urgente"
        if ($has_attention) {
            $client_id = self::get_or_create_client($workspace_id, $workspace_name);
            
            if ($client_id) {
                $status_text = $is_urgent ? __('Urgente', 'fp-task-agenda') : __('Attenzione', 'fp-task-agenda');
                $task_description = sprintf(
                    __('Stato "%s" rilevato per i post social.', 'fp-task-agenda'),
                    $status_text
                );
                
                return self::create_task_for_client(
                    $client_id,
                    sprintf(__('Nuovi post %s', 'fp-task-agenda'), $workspace_name),
                    $workspace_name,
                    $task_description
                );
            }
        }
        
        return false;
    }
    
    /**
     * Verifica post mancanti per WordPress (avanzamento mensile)
     * Calcola i dati dalla tabella fp_pub_jobs invece di cercare colonne inesistenti
     */
    public static function check_wordpress_posts($workspace_id, $workspace_name) {
        global $wpdb;
        
        if (!self::publisher_table_exists()) {
            return false;
        }
        
        // I dati vengono calcolati dinamicamente dalla tabella jobs
        $jobs_table = $wpdb->prefix . 'fp_pub_jobs';
        $jobs_table_safe = esc_sql($jobs_table);
        
        // Verifica se la tabella jobs esiste
        $jobs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$jobs_table_safe}'") === $jobs_table;
        if (!$jobs_table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Tabella jobs non trovata: ' . $jobs_table);
            }
            return false;
        }
        
        // Ottieni configurazione dal workspace
        $publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
        $workspace = $wpdb->get_row($wpdb->prepare(
            "SELECT content_config FROM {$publisher_table} WHERE id = %d",
            absint($workspace_id)
        ));
        
        if (!$workspace) {
            return false;
        }
        
        // Decodifica content_config per ottenere il target mensile
        $config = !empty($workspace->content_config) ? json_decode($workspace->content_config, true) : array();
        $target_articles = isset($config['articles_per_month']) ? (int)$config['articles_per_month'] : 0;
        
        // Se non c'è target configurato, non creare task
        if ($target_articles <= 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_wordpress_posts: Nessun target articoli configurato per {$workspace_name}");
            }
            return false;
        }
        
        // Calcola statistiche per il mese corrente
        $current_month_start = date('Y-m-01 00:00:00');
        $current_month_end = date('Y-m-t 23:59:59');
        
        // Conta articoli WordPress pubblicati questo mese
        $articles_published = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$jobs_table_safe} 
            WHERE remote_site_id = %d 
            AND post_type = 'article'
            AND status IN ('published', 'completed')
            AND COALESCE(published_at, scheduled_at) >= %s
            AND COALESCE(published_at, scheduled_at) <= %s",
            absint($workspace_id),
            $current_month_start,
            $current_month_end
        ));
        
        // Conta articoli già programmati per il mese (scheduled) - su Publisher = OK, non creare task
        $articles_scheduled_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$jobs_table_safe} 
            WHERE remote_site_id = %d 
            AND post_type = 'article'
            AND status IN ('pending', 'scheduled')
            AND scheduled_at >= %s
            AND scheduled_at <= %s",
            absint($workspace_id),
            $current_month_start,
            $current_month_end
        ));
        
        // Verifica se è "0/1" (caso specifico per WordPress)
        // Crea task SOLO se: target = 1, articoli pubblicati = 0 E nessun articolo già programmato per il mese
        $needs_article = false;
        $description = '';
        
        if ($target_articles == 1 && $articles_published == 0 && $articles_scheduled_this_month == 0) {
            $needs_article = true;
            $description = __('Avanzamento articoli WordPress: 0/1. È necessario pubblicare l\'articolo previsto per il mese corrente.', 'fp-task-agenda');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_wordpress_posts: Avanzamento 0/1 rilevato per {$workspace_name}, creazione task");
            }
        }
        
        // Crea task SOLO se è "0/1"
        $should_create_task = $needs_article;
        
        if ($should_create_task) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Task Agenda - check_wordpress_posts: Creazione task ricorrente mensile per {$workspace_name}");
            }
            
            $client_id = self::get_or_create_client($workspace_id, $workspace_name);
            
            if ($client_id) {
                // Crea task ricorrente mensile per articoli blog
                $result = self::create_task_for_client(
                    $client_id,
                    sprintf(__('Blog post %s', 'fp-task-agenda'), $workspace_name),
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
        $debug_info = array(); // Info di debug per capire cosa succede
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FP Task Agenda - Inizio verifica post mancanti. Workspace trovati: ' . count($workspaces));
        }
        
        foreach ($workspaces as $workspace) {
            try {
                $workspace_debug = array(
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'social_result' => false,
                    'wp_result' => false,
                    'social_reason' => '',
                    'wp_reason' => ''
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Task Agenda - Verifica workspace: ID {$workspace->id}, Nome: {$workspace->name}");
                }
                
                // Verifica post social
                $social_result = self::check_social_posts($workspace->id, $workspace->name);
                if ($social_result) {
                    $tasks_created++;
                    $workspace_debug['social_result'] = true;
                    $workspace_debug['social_reason'] = 'Task creata';
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Task social creata per workspace {$workspace->name}");
                    }
                } else {
                    $workspace_debug['social_reason'] = 'Nessuna task creata (già esistente o criteri non soddisfatti)';
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Nessuna task social creata per workspace {$workspace->name} (già esistente o non necessaria)");
                    }
                }
                
                // Verifica articoli WordPress
                $wp_result = self::check_wordpress_posts($workspace->id, $workspace->name);
                if ($wp_result) {
                    $tasks_created++;
                    $workspace_debug['wp_result'] = true;
                    $workspace_debug['wp_reason'] = 'Task creata';
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Task WordPress creata per workspace {$workspace->name} (ricorrente mensile)");
                    }
                } else {
                    $workspace_debug['wp_reason'] = 'Nessuna task creata (già esistente o criteri non soddisfatti)';
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Task Agenda - Nessuna task WordPress creata per workspace {$workspace->name} (già esistente o non necessaria)");
                    }
                }
                
                $debug_info[] = $workspace_debug;
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
            error_log("FP Task Agenda - Workspace verificati: " . count($workspaces));
            if ($tasks_created == 0 && !empty($workspaces)) {
                error_log("FP Task Agenda - ATTENZIONE: 0 task create ma ci sono " . count($workspaces) . " workspace. Verifica log sopra per dettagli.");
            }
        }
        
        $message = sprintf(
            __('Verifica completata. Create %d nuove task.', 'fp-task-agenda'),
            $tasks_created
        );
        
        if (!empty($errors)) {
            $message .= ' ' . __('Alcuni errori si sono verificati durante la verifica.', 'fp-task-agenda');
        }
        
        // Se non sono state create task ma ci sono workspace, aggiungi info di debug
        if ($tasks_created == 0 && !empty($workspaces)) {
            $message .= ' ' . __('Nessuna task creata. Verifica i criteri (Attenzione/Urgente o WordPress 0/1).', 'fp-task-agenda');
        }
        
        $result = array(
            'success' => true,
            'message' => $message,
            'tasks_created' => $tasks_created,
            'errors' => $errors
        );
        
        // Aggiungi info di debug se richiesto (solo in modalità debug)
        if (defined('WP_DEBUG') && WP_DEBUG && $tasks_created == 0 && !empty($debug_info)) {
            $result['debug_info'] = $debug_info;
        }
        
        return $result;
    }
}
