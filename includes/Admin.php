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
    }
    
    /**
     * Carica script e stili admin
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_fp-task-agenda' && $hook !== 'task-agenda_page_fp-task-agenda-clients') {
            return;
        }
        
        wp_enqueue_style(
            'fp-task-agenda-admin',
            FP_TASK_AGENDA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FP_TASK_AGENDA_VERSION
        );
        
        // Carica JavaScript per entrambe le pagine
        if ($hook === 'toplevel_page_fp-task-agenda' || $hook === 'task-agenda_page_fp-task-agenda-clients') {
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
                'strings' => array(
                    'addTask' => __('Aggiungi Task', 'fp-task-agenda'),
                    'editTask' => __('Modifica Task', 'fp-task-agenda'),
                    'confirmDelete' => __('Sei sicuro di voler eliminare questo task?', 'fp-task-agenda'),
                    'error' => __('Si è verificato un errore', 'fp-task-agenda'),
                    'success' => __('Operazione completata con successo', 'fp-task-agenda'),
                    'titleRequired' => __('Il titolo è obbligatorio', 'fp-task-agenda'),
                    'saving' => __('Salvataggio...', 'fp-task-agenda'),
                    'save' => __('Salva', 'fp-task-agenda'),
                    'taskDeleted' => __('Task eliminato', 'fp-task-agenda')
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
        
        // Ottieni task
        $args = array(
            'status' => $current_status,
            'priority' => $current_priority,
            'client_id' => $current_client,
            'search' => $search,
            'per_page' => 20,
            'page' => $current_page,
            'orderby' => $orderby,
            'order' => $order
        );
        
        // Ottieni tutti i clienti per il filtro
        $clients = Client::get_all();
        
        $tasks = Database::get_tasks($args);
        $total_tasks = Database::count_tasks($args);
        $total_pages = ceil($total_tasks / 20);
        
        // Statistiche
        $stats = array(
            'all' => Database::count_tasks(array('status' => 'all')),
            'pending' => Database::count_tasks(array('status' => 'pending')),
            'in_progress' => Database::count_tasks(array('status' => 'in_progress')),
            'completed' => Database::count_tasks(array('status' => 'completed'))
        );
        
        // Passa variabili per ordinamento al template
        $orderby = $args['orderby'];
        $order = $args['order'];
        
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
        
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $priority = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : 'normal';
        $due_date = isset($_POST['due_date']) && !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;
        $client_id = isset($_POST['client_id']) && !empty($_POST['client_id']) ? absint($_POST['client_id']) : null;
        
        if (empty($title)) {
            wp_send_json_error(array('message' => __('Il titolo è obbligatorio', 'fp-task-agenda')));
        }
        
        $result = Database::insert_task(array(
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'due_date' => $due_date,
            'client_id' => $client_id
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $task = Database::get_task($result);
        wp_send_json_success(array('task' => $task, 'message' => __('Task aggiunto con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Aggiorna task
     */
    public function ajax_update_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => __('ID task non valido', 'fp-task-agenda')));
        }
        
        $data = array();
        if (isset($_POST['title'])) {
            $data['title'] = sanitize_text_field($_POST['title']);
        }
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_textarea_field($_POST['description']);
        }
        if (isset($_POST['priority'])) {
            $data['priority'] = sanitize_text_field($_POST['priority']);
        }
        if (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['due_date'])) {
            $data['due_date'] = !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;
        }
        if (isset($_POST['client_id'])) {
            $data['client_id'] = !empty($_POST['client_id']) ? absint($_POST['client_id']) : null;
        }
        
        $result = Database::update_task($id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
            wp_send_json_error(array('message' => __('ID task non valido', 'fp-task-agenda')));
        }
        
        $result = Database::delete_task($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
            wp_send_json_error(array('message' => __('ID task non valido', 'fp-task-agenda')));
        }
        
        $task = Database::get_task($id);
        if (!$task) {
            wp_send_json_error(array('message' => __('Task non trovato', 'fp-task-agenda')));
        }
        
        $new_status = $task->status === 'completed' ? 'pending' : 'completed';
        
        $result = Database::update_task($id, array('status' => $new_status));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $args = array(
            'status' => $status,
            'priority' => $priority,
            'client_id' => $client_id,
            'search' => $search,
            'per_page' => 20,
            'page' => $page,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $tasks = Database::get_tasks($args);
        $total = Database::count_tasks($args);
        
        wp_send_json_success(array(
            'tasks' => $tasks,
            'total' => $total,
            'pages' => ceil($total / 20)
        ));
    }
    
    /**
     * AJAX: Ottieni un singolo task
     */
    public function ajax_get_task() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(array('message' => __('ID task non valido', 'fp-task-agenda')));
        }
        
        $task = Database::get_task($id);
        if (!$task) {
            wp_send_json_error(array('message' => __('Task non trovato', 'fp-task-agenda')));
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
            wp_send_json_error(array('message' => __('Il nome è obbligatorio', 'fp-task-agenda')));
        }
        
        $result = Client::create($name);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
            wp_send_json_error(array('message' => __('ID cliente non valido', 'fp-task-agenda')));
        }
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Il nome è obbligatorio', 'fp-task-agenda')));
        }
        
        $result = Client::update($id, $name);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
            wp_send_json_error(array('message' => __('ID cliente non valido', 'fp-task-agenda')));
        }
        
        $result = Client::delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('Cliente eliminato con successo', 'fp-task-agenda')));
    }
    
    /**
     * AJAX: Sincronizza clienti da FP Publisher
     */
    public function ajax_sync_clients() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $result = Client::sync_from_publisher();
        
        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['message']));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Cambio stato rapido da dropdown
     */
    public function ajax_quick_change_status() {
        check_ajax_referer('fp_task_agenda_nonce', 'nonce');
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$id) {
            wp_send_json_error(array('message' => __('ID task non valido', 'fp-task-agenda')));
        }
        
        if (!in_array($status, array('pending', 'in_progress', 'completed'))) {
            wp_send_json_error(array('message' => __('Stato non valido', 'fp-task-agenda')));
        }
        
        $result = Database::update_task($id, array('status' => $status));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
            wp_send_json_error(array('message' => __('ID task non valido', 'fp-task-agenda')));
        }
        
        if (!in_array($priority, array('low', 'normal', 'high', 'urgent'))) {
            wp_send_json_error(array('message' => __('Priorità non valida', 'fp-task-agenda')));
        }
        
        $result = Database::update_task($id, array('priority' => $priority));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
            wp_send_json_error(array('message' => __('Nessun task selezionato', 'fp-task-agenda')));
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
     * Renderizza la pagina gestione clienti
     */
    public function render_clients_page() {
        $clients = Client::get_all();
        
        include FP_TASK_AGENDA_PLUGIN_DIR . 'includes/admin-templates/clients-page.php';
    }
}
