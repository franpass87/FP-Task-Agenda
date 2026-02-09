<?php
/**
 * REST API - FP Task Agenda
 *
 * Endpoint per gestione task via REST
 */

namespace FP\TaskAgenda;

if (!defined('ABSPATH')) {
    exit;
}

class RestApi {

    const NAMESPACE = 'fp-task-agenda/v1';

    public static function register_routes() {
        register_rest_route(self::NAMESPACE, '/tasks', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_tasks'),
                'permission_callback' => function () { return current_user_can('read'); },
                'args' => self::get_tasks_collection_params()
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'create_task'),
                'permission_callback' => function () { return current_user_can('edit_posts'); },
                'args' => self::get_task_create_params()
            )
        ));

        register_rest_route(self::NAMESPACE, '/tasks/(?P<id>\d+)', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'get_task'),
                'permission_callback' => function () { return current_user_can('read'); },
                'args' => array('id' => array('validate_callback' => function ($p) { return is_numeric($p); }))
            ),
            array(
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => array(__CLASS__, 'update_task'),
                'permission_callback' => function () { return current_user_can('edit_posts'); },
                'args' => array_merge(
                    array('id' => array('validate_callback' => function ($p) { return is_numeric($p); })),
                    self::get_task_update_params()
                )
            ),
            array(
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => array(__CLASS__, 'delete_task'),
                'permission_callback' => function () { return current_user_can('edit_posts'); },
                'args' => array('id' => array('validate_callback' => function ($p) { return is_numeric($p); }))
            )
        ));
    }

    private static function get_tasks_collection_params() {
        return array(
            'status' => array('default' => 'all', 'sanitize_callback' => 'sanitize_text_field'),
            'priority' => array('default' => 'all', 'sanitize_callback' => 'sanitize_text_field'),
            'client_id' => array('default' => 'all', 'sanitize_callback' => 'sanitize_text_field'),
            'page' => array('default' => 1, 'sanitize_callback' => 'absint'),
            'per_page' => array('default' => 20, 'sanitize_callback' => function ($v) {
                return max(1, min(100, absint($v)));
            }),
            'search' => array('default' => '', 'sanitize_callback' => 'sanitize_text_field'),
            'orderby' => array('default' => 'created_at', 'sanitize_callback' => 'sanitize_text_field'),
            'order' => array('default' => 'DESC', 'sanitize_callback' => function ($v) {
                return strtoupper($v) === 'ASC' ? 'ASC' : 'DESC';
            })
        );
    }

    private static function get_task_create_params() {
        return array(
            'title' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            'description' => array('default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
            'priority' => array('default' => 'normal', 'sanitize_callback' => 'sanitize_text_field'),
            'status' => array('default' => 'pending', 'sanitize_callback' => 'sanitize_text_field'),
            'due_date' => array('default' => null, 'sanitize_callback' => function ($v) {
                return !empty($v) ? sanitize_text_field($v) : null;
            }),
            'client_id' => array('default' => null, 'sanitize_callback' => function ($v) {
                return !empty($v) ? absint($v) : null;
            })
        );
    }

    private static function get_task_update_params() {
        return array(
            'title' => array('sanitize_callback' => 'sanitize_text_field'),
            'description' => array('sanitize_callback' => 'sanitize_textarea_field'),
            'priority' => array('sanitize_callback' => 'sanitize_text_field'),
            'status' => array('sanitize_callback' => 'sanitize_text_field'),
            'due_date' => array('sanitize_callback' => function ($v) {
                return $v !== null && $v !== '' ? sanitize_text_field($v) : null;
            }),
            'client_id' => array('sanitize_callback' => function ($v) {
                return $v !== null && $v !== '' ? absint($v) : null;
            })
        );
    }

    public static function get_tasks(\WP_REST_Request $request) {
        $per_page = $request->get_param('per_page') ?: Plugin::get_items_per_page();
        $args = array(
            'status' => $request->get_param('status'),
            'priority' => $request->get_param('priority'),
            'client_id' => $request->get_param('client_id'),
            'page' => $request->get_param('page'),
            'per_page' => $per_page,
            'search' => $request->get_param('search'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'show_completed' => Plugin::get_show_completed()
        );
        $tasks = Database::get_tasks($args);
        $total = Database::count_tasks($args);
        foreach ($tasks as $task) {
            if (!empty($task->client_id)) {
                $client = Client::get($task->client_id);
                $task->client_name = $client ? $client->name : '';
            } else {
                $task->client_name = '';
            }
        }
        return new \WP_REST_Response(array(
            'tasks' => $tasks,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ), 200);
    }

    public static function get_task(\WP_REST_Request $request) {
        $id = (int) $request['id'];
        $task = Database::get_task($id);
        if (!$task) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'), array('status' => 404));
        }
        if (!current_user_can('manage_options') && (int) $task->user_id !== get_current_user_id()) {
            return new \WP_Error('forbidden', __('Accesso negato', 'fp-task-agenda'), array('status' => 403));
        }
        if (!empty($task->client_id)) {
            $client = Client::get($task->client_id);
            $task->client_name = $client ? $client->name : '';
        }
        return new \WP_REST_Response($task, 200);
    }

    public static function create_task(\WP_REST_Request $request) {
        $title = trim($request->get_param('title'));
        if (empty($title)) {
            return new \WP_Error('missing_title', __('Il titolo è obbligatorio', 'fp-task-agenda'), array('status' => 400));
        }
        $priority = $request->get_param('priority');
        if (!in_array($priority, array('low', 'normal', 'high', 'urgent'))) {
            $priority = 'normal';
        }
        $status = $request->get_param('status');
        if (!in_array($status, array('pending', 'in_progress', 'completed'))) {
            $status = 'pending';
        }
        $data = array(
            'title' => $title,
            'description' => $request->get_param('description'),
            'priority' => $priority,
            'status' => $status,
            'due_date' => $request->get_param('due_date'),
            'client_id' => $request->get_param('client_id')
        );
        $result = Database::insert_task($data);
        if (is_wp_error($result)) {
            return new \WP_Error('db_error', $result->get_error_message(), array('status' => 500));
        }
        $task = Database::get_task($result);
        return new \WP_REST_Response($task, 201);
    }

    public static function update_task(\WP_REST_Request $request) {
        $id = (int) $request['id'];
        $task = Database::get_task($id);
        if (!$task) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'), array('status' => 404));
        }
        if (!current_user_can('manage_options') && (int) $task->user_id !== get_current_user_id()) {
            return new \WP_Error('forbidden', __('Accesso negato', 'fp-task-agenda'), array('status' => 403));
        }
        $data = array();
        foreach (array('title', 'description', 'priority', 'status', 'due_date', 'client_id') as $key) {
            if ($request->has_param($key)) {
                $data[$key] = $request->get_param($key);
            }
        }
        if (empty($data)) {
            return new \WP_REST_Response($task, 200);
        }
        if (isset($data['title']) && trim($data['title']) === '') {
            return new \WP_Error('missing_title', __('Il titolo è obbligatorio', 'fp-task-agenda'), array('status' => 400));
        }
        $result = Database::update_task($id, $data);
        if (is_wp_error($result)) {
            return new \WP_Error('db_error', $result->get_error_message(), array('status' => 500));
        }
        $task = Database::get_task($id);
        return new \WP_REST_Response($task, 200);
    }

    public static function delete_task(\WP_REST_Request $request) {
        $id = (int) $request['id'];
        $task = Database::get_task($id);
        if (!$task) {
            return new \WP_Error('not_found', __('Task non trovato', 'fp-task-agenda'), array('status' => 404));
        }
        if (!current_user_can('manage_options') && (int) $task->user_id !== get_current_user_id()) {
            return new \WP_Error('forbidden', __('Accesso negato', 'fp-task-agenda'), array('status' => 403));
        }
        $result = Database::delete_task($id);
        if (is_wp_error($result)) {
            return new \WP_Error('db_error', $result->get_error_message(), array('status' => 500));
        }
        return new \WP_REST_Response(array('deleted' => true), 200);
    }
}
