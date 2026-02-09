<?php
/**
 * Pannello di Amministrazione
 * 
 * Gestisce l'interfaccia admin del plugin
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_head', array($this, 'add_custom_favicon'));
        
        // AJAX handlers
        add_action('wp_ajax_fp_task_agenda_add_task', array($this, 'ajax_add_task'));
        add_action('wp_ajax_fp_task_agenda_update_task', array($this, 'ajax_update_task'));
        add_action('wp_ajax_fp_task_agenda_delete_task', array($this, 'ajax_delete_task'));
        add_action('wp_ajax_fp_task_agenda_toggle_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_fp_task_agenda_get_tasks', array($this, 'ajax_get_tasks'));
        add_action('wp_ajax_fp_task_agenda_get_task', array($this, 'ajax_get_task'));
        
        // AJAX handlers per azioni rapide e bulk
        add_action('wp_ajax_fp_task_agenda_quick_change_status', array($this, 'ajax_quick_change_status'));
        add_action('wp_ajax_fp_task_agenda_quick_change_priority', array($this, 'ajax_quick_change_priority'));
        add_action('wp_ajax_fp_task_agenda_bulk_action', array($this, 'ajax_bulk_action'));
        
        // AJAX handlers per clienti
        add_action('wp_ajax_fp_task_agenda_get_clients', array($this, 'ajax_get_clients'));
        add_action('wp_ajax_fp_task_agenda_add_client', array($this, 'ajax_add_client'));
        add_action('wp_ajax_fp_task_agenda_update_client', array($this, 'ajax_update_client'));
        add_action('wp_ajax_fp_task_agenda_delete_client', array($this, 'ajax_delete_client'));
        add_action('wp_ajax_fp_task_agenda_sync_clients', array($this, 'ajax_sync_clients'));
        
        // AJAX handler per verifica post mancanti FP Publisher
        add_action('wp_ajax_fp_task_agenda_check_publisher_posts', array($this, 'ajax_check_publisher_posts'));
        
        // AJAX handlers per template
        add_action('wp_ajax_fp_task_agenda_get_templates', array($this, 'ajax_get_templates'));
        add_action('wp_ajax_fp_task_agenda_add_template', array($this, 'ajax_add_template'));
        add_action('wp_ajax_fp_task_agenda_update_template', array($this, 'ajax_update_template'));
        add_action('wp_ajax_fp_task_agenda_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_fp_task_agenda_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_fp_task_agenda_create_task_from_template', array($this, 'ajax_create_task_from_template'));
        
        // AJAX handlers per task archiviati
        add_action('wp_ajax_fp_task_agenda_restore_task', array($this, 'ajax_restore_task'));
        add_action('wp_ajax_fp_task_agenda_permanently_delete_task', array($this, 'ajax_permanently_delete_task'));
    }
    
    /**
     * Invia risposta AJAX di errore standardizzata (message + code)
     */
    private static function send_ajax_error($message, $code = 'error', $http_status = 400) {
        status_header($http_status);
        wp_send_json_error(array('message' => $message, 'code' => $code));
    }
    
    /**
     * Ottiene messaggio user-friendly da WP_Error
     */
    private static function get_wp_error_message(\WP_Error $error) {
        $code = $error->get_error_code();
        $message = $error->get_error_message();
        $map = array(
            'db_error' => __('Errore del database. Riprova più tardi.', 'fp-task-agenda'),
            'missing_title' => __('Il titolo è obbligatorio', 'fp-task-agenda'),
            'invalid_id' => __('ID non valido', 'fp-task-agenda')
        );
        return isset($map[$code]) ? $map[$code] : $message;
    }
    
    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu() {
        // Conta i task pending
        $pending_count = Database::count_tasks(array('status' => 'pending'));
        
        $menu_title = __('Task Agenda', 'fp-task-agenda');
        if ($pending_count > 0) {
            $menu_title .= ' <span class="awaiting-mod">' . $pending_count . '</span>';
        }
        
        add_menu_page(
            __('Task Agenda', 'fp-task-agenda'),
            $menu_title,
            'read',
            'fp-task-agenda',
            array($this, 'render_main_page'),
            'dashicons-list-view',
            26
        );
        
        add_submenu_page(
            'fp-task-agenda',
            __('Clienti', 'fp-task-agenda'),
            __('Clienti', 'fp-task-agenda'),
            'read',
            'fp-task-agenda-clients',
            array($this, 'render_clients_page')
        );
        
        add_submenu_page(
            'fp-task-agenda',
            __('Template', 'fp-task-agenda'),
            __('Template', 'fp-task-agenda'),
            'read',
            'fp-task-agenda-templates',
            array($this, 'render_templates_page')
        );
        
        add_submenu_page(
            'fp-task-agenda',
            __('Impostazioni', 'fp-task-agenda'),
            __('Impostazioni', 'fp-task-agenda'),
            'manage_options',
            'fp-task-agenda-settings',
            array(Settings::get_instance(), 'render_settings_page')
        );
        
        // Conta task archiviati per il badge
        $archived_count = Database::count_archived_tasks();
        $archived_title = __('Archiviati', 'fp-task-agenda');
        if ($archived_count > 0) {
            $archived_title .= ' <span class="awaiting-mod">' . $archived_count . '</span>';
        }
        
        add_submenu_page(
            'fp-task-agenda',
            __('Task Archiviati', 'fp-task-agenda'),
            $archived_title,
            'read',
            'fp-task-agenda-archived',
            array($this, 'render_archived_page')
        );
    }
    
    /**
     * Aggiungi favicon personalizzato nelle pagine del plugin
     */
    public function add_custom_favicon() {
        $screen = get_current_screen();
        if ($screen === null) {
            return;
        }
        
        // Verifica se siamo in una pagina del plugin (controllo più flessibile)
        $screen_id = (string) $screen->id;
        if (strpos($screen_id, 'fp-task-agenda') === false && $screen_id !== 'toplevel_page_fp-task-agenda') {
            return;
        }
        
        // SVG favicon inline - Checklist/Task icon con gradient arancione-ambra
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            . '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#f59e0b"/><stop offset="100%" stop-color="#ea580c"/>'
            . '</linearGradient></defs>'
            . '<rect width="32" height="32" rx="6" fill="url(#g)"/>'
            . '<path d="M8 10l3 3 5-5" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<rect x="18" y="9" width="8" height="2" rx="1" fill="#fff"/>'
            . '<path d="M8 18l3 3 5-5" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<rect x="18" y="17" width="8" height="2" rx="1" fill="#fff"/>'
            . '<rect x="8" y="25" width="16" height="2" rx="1" fill="#fff" opacity=".5"/>'
            . '</svg>';
        $favicon_data = 'data:image/svg+xml;base64,' . base64_encode($svg);
        
        echo '<link rel="icon" type="image/svg+xml" href="' . esc_attr($favicon_data) . '" />' . "\n";
    }
    
    /**
     * Carica script e stili admin
     */
    public function enqueue_admin_assets($hook) {
        // Verifica se siamo in una delle pagine del plugin (main, clienti, template, archiviati)
        $is_plugin_page = (strpos($hook, 'fp-task-agenda') !== false);
        
        if (!$is_plugin_page) {
            return;
        }
        
        wp_enqueue_style(
            'fp-task-agenda-admin',
            FP_TASK_AGENDA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FP_TASK_AGENDA_VERSION
        );
        
        // Carica JavaScript per tutte le pagine del plugin
        if ($is_plugin_page) {
            wp_enqueue_script(
                'fp-task-agenda-admin',
                FP_TASK_AGENDA_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                FP_TASK_AGENDA_VERSION,
                true
            );
            
            wp_localize_script('fp-task-agenda-admin', 'fpTaskAgenda', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fp_task_agenda_nonce'),
                'templatesPageUrl' => admin_url('admin.php?page=fp-task-agenda-templates'),
                'itemsPerPage' => Plugin::get_items_per_page(),
                'kanbanPerPage' => min(500, Plugin::get_items_per_page() * 25),
                'priorities' => \FP\TaskAgenda\Task::get_priorities(),
                'statuses' => \FP\TaskAgenda\Task::get_statuses(),
                'strings' => array(
                    'addTask' => __('Aggiungi Task', 'fp-task-agenda'),
                    'editTask' => __('Modifica Task', 'fp-task-agenda'),
                    'confirmDelete' => __('Sei sicuro di voler eliminare questo task?', 'fp-task-agenda'),
                    'confirmPermanentlyDelete' => __('Sei sicuro di voler eliminare definitivamente questo task? Questa azione è irreversibile.', 'fp-task-agenda'),
                    'confirmBulkDelete' => __('Sei sicuro di voler eliminare i task selezionati?', 'fp-task-agenda'),
                    'error' => __('Si è verificato un errore', 'fp-task-agenda'),
                    'success' => __('Operazione completata con successo', 'fp-task-agenda'),
                    'titleRequired' => __('Il titolo è obbligatorio', 'fp-task-agenda'),
                    'saving' => __('Salvataggio...', 'fp-task-agenda'),
                    'save' => __('Salva', 'fp-task-agenda'),
                    'taskDeleted' => __('Task eliminato', 'fp-task-agenda'),
                    'networkError' => __('Impossibile contattare il server. Verifica la connessione.', 'fp-task-agenda'),
                    'addClient' => __('Aggiungi Cliente', 'fp-task-agenda'),
                    'editClient' => __('Modifica Cliente', 'fp-task-agenda'),
                    'confirmDeleteClient' => __('Sei sicuro di voler eliminare questo cliente?', 'fp-task-agenda'),
                    'clientDeleted' => __('Cliente eliminato', 'fp-task-agenda'),
                    'syncing' => __('Sincronizzazione in corso...', 'fp-task-agenda'),
                    'syncComplete' => __('Sincronizzazione completata', 'fp-task-agenda'),
                    'noClientsInPublisher' => __('Nessun cliente trovato in FP Publisher', 'fp-task-agenda'),
                    'nameRequired' => __('Il nome è obbligatorio', 'fp-task-agenda')
                )
            ));
        }
    }
    
    /**
     * Renderizza la pagina principale
     */
    public function render_main_page() {
        // Gestisci azioni non-AJAX (form submit)
        if (isset($_POST['fp_task_action']) && check_admin_referer('fp_task_agenda_action', 'fp_task_nonce')) {
            $this->handle_form_action();
        }
        
        // Ottieni filtri
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $current_priority = isset($_GET['priority']) ? sanitize_text_field($_GET['priority']) : 'all';
        $current_client = isset($_GET['client_id']) ? sanitize_text_field($_GET['client_id']) : 'all';
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Ordinamento
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $per_page = Plugin::get_items_per_page();
        
        // Ottieni task
        $args = array(
            'status' => $current_status,
            'priority' => $current_priority,
            'client_id' => $current_client,
            'search' => $search,
            'per_page' => $per_page,
            'page' => $current_page,
            'orderby' => $orderby,
            'order' => $order,
            'show_completed' => Plugin::get_show_completed()
        );
        
        // Ottieni tutti i clienti per il filtro
        $clients = Client::get_all();
        
        $tasks = Database::get_tasks($args);
        $total_tasks = Database::count_tasks($args);
        $total_pages = ceil($total_tasks / $per_page);
        
        // Statistiche (rispetta show_completed per conteggio 'all')
        $stats = array(
            'all' => Database::count_tasks(array('status' => 'all', 'show_completed' => Plugin::get_show_completed())),
            'pending' => Database::count_tasks(array('status' => 'pending')),
            'in_progress' => Database::count_tasks(array('status' => 'in_progress')),
            'completed' => Database::count_tasks(array('status' => 'completed')),
            'due_soon' => Database::count_tasks_due_soon()
        );
        
        // Passa variabili per ordinamento al template
        $orderby = $args['orderby'];
        $order = $args['order'];
        
        // Passa anche il conteggio dei task archiviati
        $archived_count = Database::count_archived_tasks();
        
        include FP_TASK_AGENDA_PLUGIN_DIR . 'includes/admin-templates/main-page.php';
    }
    
    /**
     * Gestisce le azioni del form
     */
    private function handle_form_action() {
        $action = sanitize_text_field($_POST['fp_task_action']);
        
        switch ($action) {
            case 'add':
                $result = Database::insert_task(array(
                    'title' => isset($_POST['title']) ? $_POST['title'] : '',
                    'description' => isset($_POST['description']) ? $_POST['description'] : '',
                    'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'normal',
                    'due_date' => isset($_POST['due_date']) && !empty($_POST['due_date']) ? $_POST['due_date'] : null
                ));
                
                if (is_wp_error($result)) {
                    add_action('admin_notices', function() use ($result) {
                        echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('Task aggiunto con successo', 'fp-task-agenda') . '</p></div>';
                    });
                }
                break;
        }
    }
    
    /**
     * AJAX: Aggiungi task
     */
    public function ajax_add_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        // Title è sempre richiesto
        $title = isset($_POST['title']) ? trim(sanitize_text_field($_POST['title'])) : '';
        if (empty($title)) {
            self::send_ajax_error(__('Il titolo è obbligatorio', 'fp-task-agenda'), 'missing_title');
        }
        
        // Description può essere vuota
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        // Priority
        $priority = isset($_POST['priority']) && in_array($_POST['priority'], array('low', 'normal', 'high', 'urgent')) 
            ? sanitize_text_field($_POST['priority']) 
            : 'normal';
        
        // Due date
        $due_date = isset($_POST['due_date']) && !empty(trim($_POST['due_date'])) 
            ? sanitize_text_field($_POST['due_date']) 
            : null;
        
        // Client ID
        $client_id = null;
        if (isset($_POST['client_id'])) {
            $client_id_raw = trim($_POST['client_id']);
            $client_id = !empty($client_id_raw) ? absint($client_id_raw) : null;
        }
        
        // Recurrence type
        $recurrence_type = null;
        $recurrence_interval = 1;
        $recurrence_day = null;
        $next_recurrence_date = null;
        
        if (isset($_POST['recurrence_type'])) {
            $recurrence_type_raw = trim($_POST['recurrence_type']);
            if (!empty($recurrence_type_raw) && in_array($recurrence_type_raw, array('daily', 'weekly', 'monthly'))) {
                $recurrence_type = $recurrence_type_raw;
                
                // Recurrence day - giorno specifico per mensile/settimanale
                if (isset($_POST['recurrence_day']) && !empty($_POST['recurrence_day'])) {
                    $recurrence_day = absint($_POST['recurrence_day']);
                    // Valida in base al tipo di ricorrenza
                    if ($recurrence_type === 'monthly') {
                        $recurrence_day = max(1, min(31, $recurrence_day)); // 1-31
                    } elseif ($recurrence_type === 'weekly') {
                        $recurrence_day = max(0, min(6, $recurrence_day)); // 0-6 (0=domenica)
                    } else {
                        $recurrence_day = null; // Non applicabile per daily
                    }
                }
                
                // Calcola next_recurrence_date se c'è una ricorrenza e una due_date
                if (!empty($due_date)) {
                    $next_recurrence_date = \FP\TaskAgenda\Plugin::calculate_next_recurrence_date_static(
                        $due_date,
                        $recurrence_type,
                        $recurrence_interval,
                        $recurrence_day
                    );
                }
            }
        }
        
        $result = Database::insert_task(array(
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'due_date' => $due_date,
            'client_id' => $client_id,
            'recurrence_type' => $recurrence_type,
            'recurrence_interval' => $recurrence_interval,
            'recurrence_day' => $recurrence_day,
            'next_recurrence_date' => $next_recurrence_date
        ));
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Task Agenda - Errore inserimento task: ' . $result->get_error_message());
            }
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        if (empty($result)) {
            self::send_ajax_error(__('Errore: nessun ID restituito dopo l\'inserimento', 'fp-task-agenda'), 'db_error');
        }
        
        $task = Database::get_task($result);
        if (!$task) {
            self::send_ajax_error(__('Task creato ma non trovato nel database', 'fp-task-agenda'), 'not_found');
        }
        
        wp_send_json_success(array('task' => $task, 'message' => __('Task aggiunto con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Aggiorna task
     */
    public function ajax_update_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $data = array();
        
        // Title è sempre richiesto
        if (isset($_POST['title']) && !empty(trim($_POST['title']))) {
            $data['title'] = sanitize_text_field($_POST['title']);
        }
        
        // Description può essere vuota
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_textarea_field($_POST['description']);
        }
        
        // Priority
        if (isset($_POST['priority']) && in_array($_POST['priority'], array('low', 'normal', 'high', 'urgent'))) {
            $data['priority'] = sanitize_text_field($_POST['priority']);
        }
        
        // Status (solo in edit mode)
        if (isset($_POST['status']) && in_array($_POST['status'], array('pending', 'in_progress', 'completed'))) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }
        
        // Due date
        if (isset($_POST['due_date'])) {
            $data['due_date'] = !empty(trim($_POST['due_date'])) ? sanitize_text_field($_POST['due_date']) : null;
        }
        
        // Client ID
        if (isset($_POST['client_id'])) {
            $client_id = trim($_POST['client_id']);
            $data['client_id'] = !empty($client_id) ? absint($client_id) : null;
        }
        
        // Recurrence type
        if (isset($_POST['recurrence_type'])) {
            $recurrence_type = trim($_POST['recurrence_type']);
            if (!empty($recurrence_type) && in_array($recurrence_type, array('daily', 'weekly', 'monthly'))) {
                $data['recurrence_type'] = $recurrence_type;
                
                // Recurrence day - giorno specifico per mensile/settimanale
                $recurrence_day = null;
                if (isset($_POST['recurrence_day']) && !empty($_POST['recurrence_day'])) {
                    $recurrence_day = absint($_POST['recurrence_day']);
                    // Valida in base al tipo di ricorrenza
                    if ($recurrence_type === 'monthly') {
                        $recurrence_day = max(1, min(31, $recurrence_day)); // 1-31
                    } elseif ($recurrence_type === 'weekly') {
                        $recurrence_day = max(0, min(6, $recurrence_day)); // 0-6 (0=domenica)
                    } else {
                        $recurrence_day = null; // Non applicabile per daily
                    }
                }
                $data['recurrence_day'] = $recurrence_day;
                
                // Calcola next_recurrence_date se c'è una ricorrenza
                if (isset($_POST['due_date']) && !empty(trim($_POST['due_date']))) {
                    $due_date = sanitize_text_field($_POST['due_date']);
                    $data['next_recurrence_date'] = \FP\TaskAgenda\Plugin::calculate_next_recurrence_date_static(
                        $due_date,
                        $recurrence_type,
                        1,
                        $recurrence_day
                    );
                } else {
                    $data['next_recurrence_date'] = null;
                }
            } else {
                // Se recurrence_type è vuoto o non valido, rimuovi la ricorrenza
                $data['recurrence_type'] = null;
                $data['recurrence_day'] = null;
                $data['next_recurrence_date'] = null;
            }
        }
        
        // Verifica che ci sia almeno un campo da aggiornare
        if (empty($data)) {
            self::send_ajax_error(__('Nessun dato da aggiornare', 'fp-task-agenda'), 'missing_title');
        }
        
        $result = Database::update_task($id, $data);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }

        $task = Database::get_task($id);
        wp_send_json_success(array('task' => $task, 'message' => __('Task aggiornato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Elimina task
     */
    public function ajax_delete_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $result = Database::delete_task($id);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        wp_send_json_success(array('message' => __('Task eliminato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Toggle status (pending <-> completed)
     */
    public function ajax_toggle_status() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $task = Database::get_task($id);
        if (!$task) {
            self::send_ajax_error(__('Task non trovato', 'fp-task-agenda'), 'not_found');
        }
        
        $new_status = $task->status === 'completed' ? 'pending' : 'completed';
        
        $result = Database::update_task($id, array('status' => $new_status));
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $updated_task = Database::get_task($id);
        wp_send_json_success(array('task' => $updated_task, 'message' => __('Stato aggiornato', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Ottieni task (per filtro/paginazione)
     */
    public function ajax_get_tasks() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
        $priority = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : 'all';
        $client_id = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : 'all';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : Plugin::get_items_per_page();
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'created_at';
        $order = isset($_POST['order']) && strtoupper($_POST['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $args = array(
            'status' => $status,
            'priority' => $priority,
            'client_id' => $client_id,
            'search' => $search,
            'per_page' => $per_page,
            'page' => $page,
            'orderby' => $orderby,
            'order' => $order,
            'show_completed' => Plugin::get_show_completed()
        );
        
        $tasks = Database::get_tasks($args);
        $total = Database::count_tasks($args);
        $total_pages = ceil($total / $per_page);
        
        // Aggiungi dati formattati per ogni task (per render AJAX)
        $priorities = \FP\TaskAgenda\Task::get_priorities();
        $statuses = \FP\TaskAgenda\Task::get_statuses();
        foreach ($tasks as $task) {
            if (!empty($task->client_id)) {
                $client = Client::get($task->client_id);
                $task->client_name = $client ? $client->name : '';
            } else {
                $task->client_name = '';
            }
            $task->due_date_formatted = \FP\TaskAgenda\Task::format_due_date($task->due_date);
            $task->is_due_soon = \FP\TaskAgenda\Task::is_due_soon($task->due_date);
            $task->is_overdue = \FP\TaskAgenda\Task::is_overdue($task->due_date);
            $task->priority_class = \FP\TaskAgenda\Task::get_priority_class($task->priority);
            $task->priority_icon = \FP\TaskAgenda\Task::get_priority_icon($task->priority);
            $task->status_label = isset($statuses[$task->status]) ? $statuses[$task->status] : $task->status;
            $task->priority_label = isset($priorities[$task->priority]) ? $priorities[$task->priority] : $task->priority;
            $rec_labels = array('daily' => __('Giornaliera', 'fp-task-agenda'), 'weekly' => __('Settimanale', 'fp-task-agenda'), 'monthly' => __('Mensile', 'fp-task-agenda'));
            $task->recurrence_label = !empty($task->recurrence_type) && isset($rec_labels[$task->recurrence_type]) ? $rec_labels[$task->recurrence_type] : '';
            $task->recurrence_day_detail = '';
            if (!empty($task->recurrence_day) && !empty($task->recurrence_type)) {
                if ($task->recurrence_type === 'monthly') {
                    $task->recurrence_day_detail = sprintf(__('(il %d)', 'fp-task-agenda'), $task->recurrence_day);
                } elseif ($task->recurrence_type === 'weekly') {
                    $days = array(0 => __('Dom', 'fp-task-agenda'), 1 => __('Lun', 'fp-task-agenda'), 2 => __('Mar', 'fp-task-agenda'), 3 => __('Mer', 'fp-task-agenda'), 4 => __('Gio', 'fp-task-agenda'), 5 => __('Ven', 'fp-task-agenda'), 6 => __('Sab', 'fp-task-agenda'));
                    $task->recurrence_day_detail = '(' . (isset($days[$task->recurrence_day]) ? $days[$task->recurrence_day] : '') . ')';
                }
            }
            $task->created_at_human = human_time_diff(strtotime($task->created_at), current_time('timestamp')) . ' fa';
        }
        
        // Stats per aggiornamento AJAX
        $stats_args = array('show_completed' => Plugin::get_show_completed());
        $stats = array(
            'all' => Database::count_tasks(array_merge($stats_args, array('status' => 'all'))),
            'pending' => Database::count_tasks(array('status' => 'pending')),
            'in_progress' => Database::count_tasks(array('status' => 'in_progress')),
            'completed' => Database::count_tasks(array('status' => 'completed')),
            'due_soon' => Database::count_tasks_due_soon()
        );
        
        wp_send_json_success(array(
            'tasks' => $tasks,
            'total' => $total,
            'pages' => $total_pages,
            'current_page' => $page,
            'stats' => $stats,
            'priorities' => $priorities,
            'statuses' => $statuses
        ));
    }
    
    /**
     * AJAX: Ottieni un singolo task
     */
    public function ajax_get_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $task = Database::get_task($id);
        if (!$task) {
            self::send_ajax_error(__('Task non trovato', 'fp-task-agenda'), 'not_found');
        }
        
        wp_send_json_success(array('task' => $task));
    }
    
    /**
     * AJAX: Ottieni tutti i clienti
     */
    public function ajax_get_clients() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $clients = Client::get_all();
        wp_send_json_success(array('clients' => $clients));
    }
    
    /**
     * AJAX: Aggiungi cliente
     */
    public function ajax_add_client() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        
        if (empty($name)) {
            self::send_ajax_error(__('Il nome è obbligatorio', 'fp-task-agenda'), 'missing_title');
        }
        
        $result = Client::create($name);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $client = Client::get($result);
        wp_send_json_success(array('client' => $client, 'message' => __('Cliente aggiunto con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Aggiorna cliente
     */
    public function ajax_update_client() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        
        if (!$id) {
            self::send_ajax_error(__('ID cliente non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        if (empty($name)) {
            self::send_ajax_error(__('Il nome è obbligatorio', 'fp-task-agenda'), 'missing_title');
        }
        
        $result = Client::update($id, $name);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $client = Client::get($id);
        wp_send_json_success(array('client' => $client, 'message' => __('Cliente aggiornato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Elimina cliente
     */
    public function ajax_delete_client() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        
        if (!$id) {
            self::send_ajax_error(__('ID cliente non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $result = Client::delete($id);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        wp_send_json_success(array('message' => __('Cliente eliminato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Sincronizza clienti da FP Publisher
     */
    public function ajax_sync_clients() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        if (!Plugin::get_publisher_sync_enabled()) {
            self::send_ajax_error(__('Sincronizzazione FP Publisher disabilitata nelle impostazioni.', 'fp-task-agenda'), 'publisher_disabled');
        }
        
        $result = Client::sync_from_publisher();
        
        if (!$result['success']) {
            self::send_ajax_error($result['message'], 'sync_error');
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Verifica post mancanti in FP Publisher e crea task
     */
    public function ajax_check_publisher_posts() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            self::send_ajax_error(__('Permessi insufficienti', 'fp-task-agenda'), 'forbidden', 403);
        }
        
        if (!Plugin::get_publisher_sync_enabled()) {
            self::send_ajax_error(__('Sincronizzazione FP Publisher disabilitata nelle impostazioni.', 'fp-task-agenda'), 'publisher_disabled');
        }
        
        $result = PublisherIntegration::check_missing_posts();
        
        if (!$result['success']) {
            self::send_ajax_error($result['message'], 'check_publisher_error');
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Ottieni tutti i template
     */
    public function ajax_get_templates() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $templates = \FP\TaskAgenda\Template::get_all();
        wp_send_json_success(array('templates' => $templates));
    }
    
    /**
     * AJAX: Ottieni un template
     */
    public function ajax_get_template() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID template non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $template = \FP\TaskAgenda\Template::get($id);
        if (!$template) {
            self::send_ajax_error(__('Template non trovato', 'fp-task-agenda'), 'not_found');
        }
        
        wp_send_json_success(array('template' => $template));
    }
    
    /**
     * AJAX: Aggiungi template
     */
    public function ajax_add_template() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        
        if (empty($name)) {
            self::send_ajax_error(__('Il nome del template è obbligatorio', 'fp-task-agenda'), 'missing_title');
        }
        
        if (empty($title)) {
            self::send_ajax_error(__('Il titolo del task è obbligatorio', 'fp-task-agenda'), 'missing_title');
        }
        
        $data = array(
            'name' => $name,
            'title' => $title,
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
            'priority' => isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : 'normal',
            'client_id' => isset($_POST['client_id']) && !empty($_POST['client_id']) ? absint($_POST['client_id']) : null,
            'due_date_offset' => isset($_POST['due_date_offset']) ? intval($_POST['due_date_offset']) : 0,
            'recurrence_type' => isset($_POST['recurrence_type']) && !empty($_POST['recurrence_type']) ? sanitize_text_field($_POST['recurrence_type']) : null,
            'recurrence_interval' => isset($_POST['recurrence_interval']) ? absint($_POST['recurrence_interval']) : 1
        );
        
        $result = \FP\TaskAgenda\Template::create($data);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $template = \FP\TaskAgenda\Template::get($result);
        wp_send_json_success(array('template' => $template, 'message' => __('Template aggiunto con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Aggiorna template
     */
    public function ajax_update_template() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID template non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $data = array();
        if (isset($_POST['name'])) {
            $data['name'] = sanitize_text_field($_POST['name']);
        }
        if (isset($_POST['title'])) {
            $data['title'] = sanitize_text_field($_POST['title']);
        }
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_textarea_field($_POST['description']);
        }
        if (isset($_POST['priority'])) {
            $data['priority'] = sanitize_text_field($_POST['priority']);
        }
        if (isset($_POST['client_id'])) {
            $data['client_id'] = !empty($_POST['client_id']) ? absint($_POST['client_id']) : null;
        }
        if (isset($_POST['due_date_offset'])) {
            $data['due_date_offset'] = intval($_POST['due_date_offset']);
        }
        if (isset($_POST['recurrence_type'])) {
            $data['recurrence_type'] = !empty($_POST['recurrence_type']) ? sanitize_text_field($_POST['recurrence_type']) : null;
        }
        if (isset($_POST['recurrence_interval'])) {
            $data['recurrence_interval'] = absint($_POST['recurrence_interval']);
        }
        
        $result = \FP\TaskAgenda\Template::update($id, $data);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $template = \FP\TaskAgenda\Template::get($id);
        wp_send_json_success(array('template' => $template, 'message' => __('Template aggiornato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Elimina template
     */
    public function ajax_delete_template() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID template non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $result = \FP\TaskAgenda\Template::delete($id);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        wp_send_json_success(array('message' => __('Template eliminato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Crea task da template
     */
    public function ajax_create_task_from_template() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        if (!$template_id) {
            self::send_ajax_error(__('ID template non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $custom_due_date = isset($_POST['due_date']) && !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;
        
        $result = \FP\TaskAgenda\Template::create_task_from_template($template_id, $custom_due_date);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $task = Database::get_task($result);
        wp_send_json_success(array('task' => $task, 'message' => __('Task creato dal template con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Cambio stato rapido da dropdown
     */
    public function ajax_quick_change_status() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        if (!in_array($status, array('pending', 'in_progress', 'completed'))) {
            self::send_ajax_error(__('Stato non valido', 'fp-task-agenda'), 'invalid_status');
        }
        
        $result = Database::update_task($id, array('status' => $status));
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $task = Database::get_task($id);
        wp_send_json_success(array('task' => $task, 'message' => __('Stato aggiornato', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Cambio priorità rapido da dropdown
     */
    public function ajax_quick_change_priority() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $priority = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : '';
        
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        if (!in_array($priority, array('low', 'normal', 'high', 'urgent'))) {
            self::send_ajax_error(__('Priorità non valida', 'fp-task-agenda'), 'invalid_priority');
        }
        
        $result = Database::update_task($id, array('priority' => $priority));
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        $task = Database::get_task($id);
        $priority_class = \FP\TaskAgenda\Task::get_priority_class($priority);
        $priority_icon = \FP\TaskAgenda\Task::get_priority_icon($priority);
        $priorities = \FP\TaskAgenda\Task::get_priorities();
        
        wp_send_json_success(array(
            'task' => $task,
            'priority_class' => $priority_class,
            'priority_icon' => $priority_icon,
            'priority_label' => $priorities[$priority] ?? $priority,
            'message' => __('Priorità aggiornata', 'fp-task-agenda')
        ));
    }
    
    /**
     * AJAX: Bulk actions
     */
    public function ajax_bulk_action() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $task_ids = isset($_POST['task_ids']) ? array_map('absint', (array)$_POST['task_ids']) : array();
        
        if (empty($task_ids)) {
            self::send_ajax_error(__('Nessun task selezionato', 'fp-task-agenda'), 'no_tasks_selected');
        }
        
        $processed = 0;
        $errors = 0;
        
        foreach ($task_ids as $task_id) {
            switch ($action) {
                case 'complete':
                    $result = Database::update_task($task_id, array('status' => 'completed'));
                    if (!is_wp_error($result)) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                    break;
                    
                case 'pending':
                    $result = Database::update_task($task_id, array('status' => 'pending'));
                    if (!is_wp_error($result)) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                    break;
                    
                case 'delete':
                    $result = Database::delete_task($task_id);
                    if (!is_wp_error($result)) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                    break;
            }
        }
        
        wp_send_json_success(array(
            'processed' => $processed,
            'errors' => $errors,
            'message' => sprintf(__('%d task processati', 'fp-task-agenda'), $processed)
        ));
    }
    
    /**
     * Renderizza la pagina gestione template
     */
    public function render_templates_page() {
        // Ottieni tutti i template
        $templates = \FP\TaskAgenda\Template::get_all();
        $clients = \FP\TaskAgenda\Client::get_all();
        
        include FP_TASK_AGENDA_PLUGIN_DIR . 'includes/admin-templates/templates-page.php';
    }
    
    /**
     * Renderizza la pagina gestione clienti
     */
    public function render_clients_page() {
        $clients = Client::get_all();
        
        include FP_TASK_AGENDA_PLUGIN_DIR . 'includes/admin-templates/clients-page.php';
    }
    
    /**
     * Renderizza la pagina task archiviati
     */
    public function render_archived_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $per_page = Plugin::get_items_per_page();
        
        $args = array(
            'per_page' => $per_page,
            'page' => $current_page,
            'search' => $search
        );
        
        $archived_tasks = Database::get_archived_tasks($args);
        $total_archived = Database::count_archived_tasks($args);
        $total_pages = ceil($total_archived / $per_page);
        
        include FP_TASK_AGENDA_PLUGIN_DIR . 'includes/admin-templates/archived-page.php';
    }
    
    /**
     * AJAX: Ripristina task archiviato
     */
    public function ajax_restore_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $result = Database::restore_task($id);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        wp_send_json_success(array('message' => __('Task ripristinato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Elimina definitivamente task archiviato
     */
    public function ajax_permanently_delete_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            self::send_ajax_error(__('ID task non valido', 'fp-task-agenda'), 'invalid_id');
        }
        
        $result = Database::permanently_delete_task($id);
        
        if (is_wp_error($result)) {
            self::send_ajax_error(self::get_wp_error_message($result), $result->get_error_code());
        }
        
        wp_send_json_success(array('message' => __('Task eliminato definitivamente', 'fp-task-agenda')));
    }
}
