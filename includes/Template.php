<?php
/**
 * Gestione Template Task
 * 
 * Gestisce i template per creare task rapidamente
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class Template {
    
    /**
     * Ottiene il nome della tabella template
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'fp_task_agenda_templates';
    }
    
    /**
     * Ottiene tutti i template dell'utente corrente
     */
    public static function get_all() {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $templates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY name ASC",
            get_current_user_id()
        ));
        
        return $templates;
    }
    
    /**
     * Ottiene un singolo template
     */
    public static function get($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            absint($id),
            get_current_user_id()
        ));
        
        return $template;
    }
    
    /**
     * Crea un nuovo template
     */
    public static function create($data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'name' => '',
            'title' => '',
            'description' => '',
            'priority' => 'normal',
            'client_id' => null,
            'due_date_offset' => 0,
            'recurrence_type' => null,
            'recurrence_interval' => 1,
            'user_id' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validazione
        if (empty($data['name'])) {
            return new \WP_Error('missing_name', __('Il nome del template è obbligatorio', 'fp-task-agenda'));
        }
        
        if (empty($data['title'])) {
            return new \WP_Error('missing_title', __('Il titolo del task è obbligatorio', 'fp-task-agenda'));
        }
        
        // Sanitizzazione
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description']),
            'priority' => in_array($data['priority'], array('low', 'normal', 'high', 'urgent')) ? $data['priority'] : 'normal',
            'client_id' => !empty($data['client_id']) ? absint($data['client_id']) : null,
            'due_date_offset' => intval($data['due_date_offset']),
            'recurrence_type' => !empty($data['recurrence_type']) && in_array($data['recurrence_type'], array('daily', 'weekly', 'monthly')) ? $data['recurrence_type'] : null,
            'recurrence_interval' => !empty($data['recurrence_interval']) ? absint($data['recurrence_interval']) : 1,
            'user_id' => absint($data['user_id'])
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante il salvataggio del template', 'fp-task-agenda'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Aggiorna un template esistente
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Verifica che il template esista e appartenga all'utente corrente
        $template = self::get($id);
        if (!$template) {
            return new \WP_Error('not_found', __('Template non trovato', 'fp-task-agenda'));
        }
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['priority']) && in_array($data['priority'], array('low', 'normal', 'high', 'urgent'))) {
            $update_data['priority'] = $data['priority'];
        }
        
        if (isset($data['client_id'])) {
            $update_data['client_id'] = !empty($data['client_id']) ? absint($data['client_id']) : null;
        }
        
        if (isset($data['due_date_offset'])) {
            $update_data['due_date_offset'] = intval($data['due_date_offset']);
        }
        
        if (isset($data['recurrence_type'])) {
            $update_data['recurrence_type'] = !empty($data['recurrence_type']) && in_array($data['recurrence_type'], array('daily', 'weekly', 'monthly')) ? $data['recurrence_type'] : null;
        }
        
        if (isset($data['recurrence_interval'])) {
            $update_data['recurrence_interval'] = !empty($data['recurrence_interval']) ? absint($data['recurrence_interval']) : 1;
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
            return new \WP_Error('db_error', __('Errore durante l\'aggiornamento del template', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Elimina un template
     */
    public static function delete($id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Verifica che il template esista e appartenga all'utente corrente
        $template = self::get($id);
        if (!$template) {
            return new \WP_Error('not_found', __('Template non trovato', 'fp-task-agenda'));
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => absint($id)),
            array('%d')
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Errore durante l\'eliminazione del template', 'fp-task-agenda'));
        }
        
        return true;
    }
    
    /**
     * Crea un task da un template
     */
    public static function create_task_from_template($template_id, $custom_due_date = null) {
        $template = self::get($template_id);
        if (!$template) {
            return new \WP_Error('not_found', __('Template non trovato', 'fp-task-agenda'));
        }
        
        // Calcola la data di scadenza
        $due_date = null;
        if ($custom_due_date) {
            $due_date = $custom_due_date;
        } elseif ($template->due_date_offset != 0) {
            $date = new \DateTime();
            $date->modify("{$template->due_date_offset} days");
            $due_date = $date->format('Y-m-d H:i:s');
        }
        
        // Calcola next_recurrence_date se c'è ricorrenza
        $next_recurrence_date = null;
        if (!empty($template->recurrence_type) && $due_date) {
            $next_recurrence_date = Plugin::calculate_next_recurrence_date_static(
                $due_date,
                $template->recurrence_type,
                $template->recurrence_interval
            );
        }
        
        // Crea il task
        $task_data = array(
            'title' => $template->title,
            'description' => $template->description,
            'priority' => $template->priority,
            'status' => 'pending',
            'due_date' => $due_date,
            'client_id' => $template->client_id,
            'recurrence_type' => $template->recurrence_type,
            'recurrence_interval' => $template->recurrence_interval,
            'next_recurrence_date' => $next_recurrence_date,
            'user_id' => get_current_user_id()
        );
        
        return Database::insert_task($task_data);
    }
}
