<?php
/**
 * Gestione Clienti
 * 
 * Gestisce i clienti del plugin, con sincronizzazione da FP Publisher
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class Client {
    
    /**
     * Ottiene il nome della tabella clienti
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_task_agenda_clients';
    }
    
    /**
     * Ottiene tutti i clienti
     */
    public static function get_all() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $clients = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY name ASC"
        );
        
        return $clients;
    }
    
    /**
     * Ottiene un singolo cliente
     */
    public static function get($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            absint($id)
        ));
        
        return $client;
    }
    
    /**
     * Crea un nuovo cliente
     */
    public static function create($name, $source = 'manual', $source_id = null) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Verifica se esiste già un cliente con lo stesso nome
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE name = %s",
            sanitize_text_field($name)
        ));
        
        if ($existing) {
            return new \WP_Error('duplicate', __('Cliente già esistente', 'fp-task-agenda'));
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => sanitize_text_field($name),
                'source' => sanitize_text_field($source),
                'source_id' => $source_id ? absint($source_id) : null
            ),
            array('%s', '%s', '%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante il salvataggio del cliente', 'fp-task-agenda'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Aggiorna un cliente esistente
     */
    public static function update($id, $name) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Verifica se esiste
        $client = self::get($id);
        if (!$client) {
            return new \WP_Error('not_found', __('Cliente non trovato', 'fp-task-agenda'));
        }
        
        // Verifica duplicati (escludendo se stesso)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE name = %s AND id != %d",
            sanitize_text_field($name),
            absint($id)
        ));
        
        if ($existing) {
            return new \WP_Error('duplicate', __('Esiste già un cliente con questo nome', 'fp-task-agenda'));
        }
        
        $result = $wpdb->update(
            $table_name,
            array('name' => sanitize_text_field($name)),
            array('id' => absint($id)),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante l\'aggiornamento del cliente', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Elimina un cliente
     */
    public static function delete($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Verifica se esiste
        $client = self::get($id);
        if (!$client) {
            return new \WP_Error('not_found', __('Cliente non trovato', 'fp-task-agenda'));
        }
        
        // Verifica se ci sono task associati
        $task_count = Database::count_tasks(array('client_id' => $id));
        if ($task_count > 0) {
            return new \WP_Error('has_tasks', sprintf(__('Impossibile eliminare: ci sono %d task associati a questo cliente', 'fp-task-agenda'), $task_count));
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => absint($id)),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante l\'eliminazione del cliente', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Sincronizza i clienti da FP Publisher
     */
    public static function sync_from_publisher() {
        global $wpdb;
        
        // Assicurati che la tabella clienti esista
        Database::create_tables();
        
        $publisher_table = $wpdb->prefix . 'fp_pub_remote_sites';
        
        // Verifica se la tabella FP Publisher esiste
        // Metodo 1: SHOW TABLES (non può usare prepare per LIKE, ma il nome tabella è già sicuro dal prefix)
        $table_name_safe = esc_sql($publisher_table);
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
        
        if (!$table_exists) {
            return array(
                'success' => false,
                'message' => sprintf(__('Tabella FP Publisher (%s) non trovata. Verifica che il plugin FP Publisher sia installato e attivo.', 'fp-task-agenda'), $publisher_table),
                'synced' => 0
            );
        }
        
        // Ottieni tutti i remote sites da FP Publisher
        // Prova prima con il campo 'name', poi con altri campi comuni
        $remote_sites = $wpdb->get_results(
            "SELECT id, name FROM {$table_name_safe} WHERE name IS NOT NULL AND name != ''"
        );
        
        // Se non trova nulla, prova a vedere quali colonne esistono
        if (empty($remote_sites)) {
            // Prova con altri campi possibili
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name_safe}");
            $name_column = null;
            foreach ($columns as $col) {
                if (in_array(strtolower($col->Field), array('name', 'title', 'client_name', 'site_name'))) {
                    $name_column = $col->Field;
                    break;
                }
            }
            
            if ($name_column && $name_column !== 'name') {
                // Prova con il campo trovato
                $remote_sites = $wpdb->get_results(
                    "SELECT id, {$name_column} as name FROM {$table_name_safe} WHERE {$name_column} IS NOT NULL AND {$name_column} != ''"
                );
            }
            
            // Se ancora vuoto, prendi tutti i record e usa id come fallback
            if (empty($remote_sites)) {
                $all_records = $wpdb->get_results("SELECT id FROM {$table_name_safe} LIMIT 10");
                if (!empty($all_records)) {
                    // C'è qualcosa nella tabella ma senza nome - crea nomi generici
                    $remote_sites = array();
                    foreach ($all_records as $record) {
                        $remote_sites[] = (object) array(
                            'id' => $record->id,
                            'name' => sprintf(__('Cliente #%d', 'fp-task-agenda'), $record->id)
                        );
                    }
                }
            }
        }
        
        if (empty($remote_sites)) {
            return array(
                'success' => true,
                'message' => __('Nessun cliente trovato in FP Publisher', 'fp-task-agenda'),
                'synced' => 0
            );
        }
        
        $synced = 0;
        $skipped = 0;
        $table_name = self::get_table_name();
        
        foreach ($remote_sites as $site) {
            // Verifica se esiste già un cliente con questo source_id
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE source = 'fp_publisher' AND source_id = %d",
                absint($site->id)
            ));
            
            if ($existing) {
                // Aggiorna il nome se è cambiato
                $wpdb->update(
                    $table_name,
                    array('name' => sanitize_text_field($site->name)),
                    array('id' => $existing),
                    array('%s'),
                    array('%d')
                );
                $skipped++;
            } else {
                // Verifica se esiste già un cliente con lo stesso nome
                $existing_by_name = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE name = %s",
                    sanitize_text_field($site->name)
                ));
                
                if (!$existing_by_name) {
                    // Crea nuovo cliente
                    $wpdb->insert(
                        $table_name,
                        array(
                            'name' => sanitize_text_field($site->name),
                            'source' => 'fp_publisher',
                            'source_id' => absint($site->id)
                        ),
                        array('%s', '%s', '%d')
                    );
                    $synced++;
                } else {
                    $skipped++;
                }
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('Sincronizzati %d clienti da FP Publisher', 'fp-task-agenda'), $synced),
            'synced' => $synced,
            'skipped' => $skipped,
            'total' => count($remote_sites)
        );
    }
    
    /**
     * Ottiene il nome del cliente per un task
     */
    public static function get_name_for_task($client_id) {
        if (empty($client_id)) {
            return __('Nessun cliente', 'fp-task-agenda');
        }
        
        $client = self::get($client_id);
        return $client ? $client->name : __('Cliente sconosciuto', 'fp-task-agenda');
    }
}
