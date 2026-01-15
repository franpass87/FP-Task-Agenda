<?php
/**
 * Template pagina principale admin
 * 
 * @var array $tasks
 * @var int $total_tasks
 * @var int $total_pages
 * @var array $stats
 * @var string $current_status
 * @var string $current_priority
 * @var string $search
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap fp-task-agenda-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Task Agenda', 'fp-task-agenda'); ?>
    </h1>
    
    <button type="button" class="page-title-action" id="fp-add-task-btn">
        <?php echo esc_html__('Aggiungi Task', 'fp-task-agenda'); ?>
    </button>
    
    <?php 
    $templates = \FP\TaskAgenda\Template::get_all();
    ?>
    <button type="button" class="page-title-action" id="fp-create-from-template-btn" <?php echo empty($templates) ? 'style="opacity: 0.6; cursor: not-allowed;" title="' . esc_attr__('Crea prima un template nella pagina Template', 'fp-task-agenda') . '"' : ''; ?>>
        <?php echo esc_html__('Crea da Template', 'fp-task-agenda'); ?>
    </button>
    
    <hr class="wp-header-end">
    
    <!-- Modal Seleziona Template -->
    <?php if (!empty($templates)): ?>
    <div id="fp-template-select-modal" class="fp-modal" style="display: none;">
        <div class="fp-modal-content" style="max-width: 600px;">
            <div class="fp-modal-header">
                <h2><?php echo esc_html__('Seleziona Template', 'fp-task-agenda'); ?></h2>
                <button type="button" class="fp-modal-close">&times;</button>
            </div>
            <div class="fp-modal-body">
                <p class="description" style="margin-bottom: 20px;">
                    <?php echo esc_html__('Scegli un template per creare rapidamente un nuovo task:', 'fp-task-agenda'); ?>
                </p>
                <div class="fp-templates-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($templates as $template): ?>
                        <?php
                        $priority_class = \FP\TaskAgenda\Task::get_priority_class($template->priority);
                        $priorities = \FP\TaskAgenda\Task::get_priorities();
                        ?>
                        <div class="fp-template-card" data-template-id="<?php echo esc_attr($template->id); ?>" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; cursor: pointer; transition: all 0.2s ease; background: white;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <strong style="font-size: 14px; color: #212529;"><?php echo esc_html($template->name); ?></strong>
                                <span class="fp-priority-badge fp-priority-<?php echo esc_attr($template->priority); ?>" style="font-size: 10px; padding: 3px 8px;">
                                    <?php echo esc_html($priorities[$template->priority] ?? $template->priority); ?>
                                </span>
                            </div>
                            <div style="font-size: 13px; color: #6c757d; margin-bottom: 8px;">
                                <?php echo esc_html($template->title); ?>
                            </div>
                            <?php if ($template->client_id): 
                                $client = \FP\TaskAgenda\Client::get($template->client_id);
                            ?>
                                <div style="font-size: 12px; color: #868e96;">
                                    <span class="dashicons dashicons-businessman" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                    <?php echo $client ? esc_html($client->name) : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="fp-modal-footer">
                <button type="button" class="button fp-modal-cancel"><?php echo esc_html__('Annulla', 'fp-task-agenda'); ?></button>
            </div>
        </div>
    </div>
    <div class="fp-modal-backdrop" id="fp-template-select-modal-backdrop" style="display: none;"></div>
    <?php endif; ?>
    
    <!-- Statistiche -->
    <div class="fp-task-stats">
        <div class="fp-stat-card">
            <span class="fp-stat-label"><?php echo esc_html__('Totali', 'fp-task-agenda'); ?></span>
            <span class="fp-stat-value"><?php echo esc_html($stats['all']); ?></span>
        </div>
        <div class="fp-stat-card fp-stat-pending">
            <span class="fp-stat-label"><?php echo esc_html__('Da fare', 'fp-task-agenda'); ?></span>
            <span class="fp-stat-value"><?php echo esc_html($stats['pending']); ?></span>
        </div>
        <div class="fp-stat-card fp-stat-progress">
            <span class="fp-stat-label"><?php echo esc_html__('In corso', 'fp-task-agenda'); ?></span>
            <span class="fp-stat-value"><?php echo esc_html($stats['in_progress']); ?></span>
        </div>
        <div class="fp-stat-card fp-stat-completed">
            <span class="fp-stat-label"><?php echo esc_html__('Completati', 'fp-task-agenda'); ?></span>
            <span class="fp-stat-value"><?php echo esc_html($stats['completed']); ?></span>
        </div>
    </div>
    
    <!-- Filtri -->
    <div class="fp-task-filters">
        <form method="get" action="" class="fp-filter-form">
            <input type="hidden" name="page" value="fp-task-agenda">
            
            <select name="status" id="filter-status">
                <option value="all" <?php selected($current_status, 'all'); ?>><?php echo esc_html__('Tutti gli stati', 'fp-task-agenda'); ?></option>
                <option value="pending" <?php selected($current_status, 'pending'); ?>><?php echo esc_html__('Da fare', 'fp-task-agenda'); ?></option>
                <option value="in_progress" <?php selected($current_status, 'in_progress'); ?>><?php echo esc_html__('In corso', 'fp-task-agenda'); ?></option>
                <option value="completed" <?php selected($current_status, 'completed'); ?>><?php echo esc_html__('Completati', 'fp-task-agenda'); ?></option>
            </select>
            
            <select name="priority" id="filter-priority">
                <option value="all" <?php selected($current_priority, 'all'); ?>><?php echo esc_html__('Tutte le priorità', 'fp-task-agenda'); ?></option>
                <option value="low" <?php selected($current_priority, 'low'); ?>><?php echo esc_html__('Bassa', 'fp-task-agenda'); ?></option>
                <option value="normal" <?php selected($current_priority, 'normal'); ?>><?php echo esc_html__('Normale', 'fp-task-agenda'); ?></option>
                <option value="high" <?php selected($current_priority, 'high'); ?>><?php echo esc_html__('Alta', 'fp-task-agenda'); ?></option>
                <option value="urgent" <?php selected($current_priority, 'urgent'); ?>><?php echo esc_html__('Urgente', 'fp-task-agenda'); ?></option>
            </select>
            
            <select name="client_id" id="filter-client">
                <option value="all" <?php selected($current_client, 'all'); ?>><?php echo esc_html__('Tutti i clienti', 'fp-task-agenda'); ?></option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo esc_attr($client->id); ?>" <?php selected($current_client, $client->id); ?>>
                        <?php echo esc_html($client->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Cerca task...', 'fp-task-agenda'); ?>" class="fp-search-input">
            
            <button type="submit" class="button"><?php echo esc_html__('Filtra', 'fp-task-agenda'); ?></button>
            
            <?php if (!empty($search) || $current_status !== 'all' || $current_priority !== 'all' || $current_client !== 'all'): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=fp-task-agenda')); ?>" class="button"><?php echo esc_html__('Reset', 'fp-task-agenda'); ?></a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Toggle Vista -->
    <div class="fp-view-toggle" style="margin: 15px 0; text-align: right;">
        <button type="button" class="page-title-action fp-view-btn fp-view-table active" data-view="table">
            <?php echo esc_html__('Tabella', 'fp-task-agenda'); ?>
        </button>
        <button type="button" class="page-title-action fp-view-btn fp-view-kanban" data-view="kanban">
            <?php echo esc_html__('Kanban', 'fp-task-agenda'); ?>
        </button>
    </div>
    
    <!-- Lista Task - Vista Tabella -->
    <div class="fp-tasks-container fp-view-table-view">
        <?php if (empty($tasks)): ?>
            <div class="fp-no-tasks">
                <p><?php echo esc_html__('Nessun task trovato.', 'fp-task-agenda'); ?></p>
            </div>
        <?php else: ?>
            <!-- Bulk Actions -->
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php echo esc_html__('Seleziona azione di massa', 'fp-task-agenda'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php echo esc_html__('Azioni di massa', 'fp-task-agenda'); ?></option>
                        <option value="complete"><?php echo esc_html__('Marca come completati', 'fp-task-agenda'); ?></option>
                        <option value="pending"><?php echo esc_html__('Marca come da fare', 'fp-task-agenda'); ?></option>
                        <option value="delete"><?php echo esc_html__('Elimina', 'fp-task-agenda'); ?></option>
                    </select>
                    <button type="button" class="button action" id="doaction"><?php echo esc_html__('Applica', 'fp-task-agenda'); ?></button>
                </div>
                <div class="alignleft actions">
                    <?php echo sprintf(__('%d task trovati', 'fp-task-agenda'), $total_tasks); ?>
                </div>
                <br class="clear">
            </div>
            
            <table class="wp-list-table widefat fixed striped fp-tasks-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column" style="width: 40px;">
                            <input id="cb-select-all" type="checkbox">
                        </td>
                        <th style="width: 120px;" class="sortable <?php echo ($orderby === 'priority') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <?php
                            $order_priority = ($orderby === 'priority' && $order === 'ASC') ? 'DESC' : 'ASC';
                            $url_priority = add_query_arg(array('orderby' => 'priority', 'order' => $order_priority), remove_query_arg(array('paged')));
                            ?>
                            <a href="<?php echo esc_url($url_priority); ?>">
                                <span><?php echo esc_html__('Priorità', 'fp-task-agenda'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th style="width: 150px;" class="sortable <?php echo ($orderby === 'client_id') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <?php
                            $order_client = ($orderby === 'client_id' && $order === 'ASC') ? 'DESC' : 'ASC';
                            $url_client = add_query_arg(array('orderby' => 'client_id', 'order' => $order_client), remove_query_arg(array('paged')));
                            ?>
                            <a href="<?php echo esc_url($url_client); ?>">
                                <span><?php echo esc_html__('Cliente', 'fp-task-agenda'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="sortable <?php echo ($orderby === 'title') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <?php
                            $order_title = ($orderby === 'title' && $order === 'ASC') ? 'DESC' : 'ASC';
                            $url_title = add_query_arg(array('orderby' => 'title', 'order' => $order_title), remove_query_arg(array('paged')));
                            ?>
                            <a href="<?php echo esc_url($url_title); ?>">
                                <span><?php echo esc_html__('Titolo', 'fp-task-agenda'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th style="width: 150px;" class="sortable <?php echo ($orderby === 'due_date') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <?php
                            $order_due = ($orderby === 'due_date' && $order === 'ASC') ? 'DESC' : 'ASC';
                            $url_due = add_query_arg(array('orderby' => 'due_date', 'order' => $order_due), remove_query_arg(array('paged')));
                            ?>
                            <a href="<?php echo esc_url($url_due); ?>">
                                <span><?php echo esc_html__('Scadenza', 'fp-task-agenda'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th style="width: 120px;" class="sortable <?php echo ($orderby === 'status') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <?php
                            $order_status = ($orderby === 'status' && $order === 'ASC') ? 'DESC' : 'ASC';
                            $url_status = add_query_arg(array('orderby' => 'status', 'order' => $order_status), remove_query_arg(array('paged')));
                            ?>
                            <a href="<?php echo esc_url($url_status); ?>">
                                <span><?php echo esc_html__('Stato', 'fp-task-agenda'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th style="width: 140px;" class="sortable <?php echo ($orderby === 'created_at') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <?php
                            $order_created = ($orderby === 'created_at' && $order === 'ASC') ? 'DESC' : 'ASC';
                            $url_created = add_query_arg(array('orderby' => 'created_at', 'order' => $order_created), remove_query_arg(array('paged')));
                            ?>
                            <a href="<?php echo esc_url($url_created); ?>">
                                <span><?php echo esc_html__('Creato', 'fp-task-agenda'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th style="width: 150px;"><?php echo esc_html__('Azioni', 'fp-task-agenda'); ?></th>
                    </tr>
                </thead>
                <tbody id="fp-tasks-list">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $priority_class = \FP\TaskAgenda\Task::get_priority_class($task->priority);
                        $priority_icon = \FP\TaskAgenda\Task::get_priority_icon($task->priority);
                        $due_date_formatted = \FP\TaskAgenda\Task::format_due_date($task->due_date);
                        $is_due_soon = \FP\TaskAgenda\Task::is_due_soon($task->due_date);
                        $is_completed = $task->status === 'completed';
                        ?>
                        <tr class="fp-task-row <?php echo esc_attr($priority_class); ?> <?php echo $is_completed ? 'fp-task-completed' : ''; ?> <?php echo $is_due_soon && !$is_completed ? 'fp-task-due-soon' : ''; ?>" data-task-id="<?php echo esc_attr($task->id); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="fp-task-checkbox" name="task[]" value="<?php echo esc_attr($task->id); ?>" <?php checked($is_completed); ?> data-task-id="<?php echo esc_attr($task->id); ?>">
                            </th>
                            <td>
                                <select class="fp-priority-quick-change fp-priority-badge fp-priority-<?php echo esc_attr($task->priority); ?>" data-task-id="<?php echo esc_attr($task->id); ?>" data-current-priority="<?php echo esc_attr($task->priority); ?>">
                                    <?php 
                                    $priorities = \FP\TaskAgenda\Task::get_priorities();
                                    foreach ($priorities as $priority_key => $priority_label):
                                        $priority_class = \FP\TaskAgenda\Task::get_priority_class($priority_key);
                                        $priority_icon = \FP\TaskAgenda\Task::get_priority_icon($priority_key);
                                        $selected = ($priority_key === $task->priority) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo esc_attr($priority_key); ?>" <?php echo $selected; ?> data-class="<?php echo esc_attr($priority_class); ?>" data-icon="<?php echo esc_attr($priority_icon); ?>">
                                            <?php echo esc_html($priority_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php if (!empty($task->client_id)): ?>
                                    <span class="fp-client-name">
                                        <?php echo esc_html(\FP\TaskAgenda\Client::get_name_for_task($task->client_id)); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="fp-no-client">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="fp-task-title"><?php echo esc_html($task->title); ?></strong>
                                <?php if (!empty($task->description)): ?>
                                    <br><small class="fp-task-description"><?php echo esc_html(wp_trim_words($task->description, 15)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($task->due_date): ?>
                                    <span class="fp-due-date <?php echo $is_due_soon && !$is_completed ? 'fp-due-soon' : ''; ?>">
                                        <?php echo esc_html($due_date_formatted); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="fp-no-due-date">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select class="fp-status-quick-change" data-task-id="<?php echo esc_attr($task->id); ?>" style="font-size: 12px; padding: 3px 5px;">
                                    <?php 
                                    $statuses = \FP\TaskAgenda\Task::get_statuses();
                                    foreach ($statuses as $key => $label):
                                    ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($task->status, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <small class="fp-task-date">
                                    <?php echo esc_html(human_time_diff(strtotime($task->created_at), current_time('timestamp'))) . ' fa'; ?>
                                </small>
                            </td>
                            <td>
                                <div class="fp-task-actions">
                                    <button type="button" class="button-link fp-edit-task" data-task-id="<?php echo esc_attr($task->id); ?>" title="<?php echo esc_attr__('Modifica', 'fp-task-agenda'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button-link delete fp-delete-task" data-task-id="<?php echo esc_attr($task->id); ?>" title="<?php echo esc_attr__('Elimina', 'fp-task-agenda'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        );
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Aggiungi/Modifica Task -->
<div id="fp-task-modal" class="fp-modal" style="display: none;">
    <div class="fp-modal-content">
        <div class="fp-modal-header">
            <h2 id="fp-modal-title"><?php echo esc_html__('Aggiungi Task', 'fp-task-agenda'); ?></h2>
            <button type="button" class="fp-modal-close">&times;</button>
        </div>
        <div class="fp-modal-body">
            <form id="fp-task-form">
                <input type="hidden" id="fp-task-id" name="id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fp-task-title"><?php echo esc_html__('Titolo', 'fp-task-agenda'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="fp-task-title" name="title" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-task-description"><?php echo esc_html__('Descrizione', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <textarea id="fp-task-description" name="description" rows="4" class="large-text"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-task-priority"><?php echo esc_html__('Priorità', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <select id="fp-task-priority" name="priority">
                                <?php
                                $priorities = \FP\TaskAgenda\Task::get_priorities();
                                foreach ($priorities as $key => $label):
                                ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-task-client"><?php echo esc_html__('Cliente', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <select id="fp-task-client" name="client_id" class="regular-text">
                                <option value=""><?php echo esc_html__('Nessun cliente', 'fp-task-agenda'); ?></option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo esc_attr($client->id); ?>">
                                        <?php echo esc_html($client->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-task-due-date"><?php echo esc_html__('Data di scadenza', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="fp-task-due-date" name="due_date" class="regular-text">
                        </td>
                    </tr>
                    <tr id="fp-task-status-row" style="display: none;">
                        <th scope="row">
                            <label for="fp-task-status"><?php echo esc_html__('Stato', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <select id="fp-task-status" name="status">
                                <?php
                                $statuses = \FP\TaskAgenda\Task::get_statuses();
                                foreach ($statuses as $key => $label):
                                ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-task-recurrence"><?php echo esc_html__('Ricorrenza', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <select id="fp-task-recurrence" name="recurrence_type">
                                <option value=""><?php echo esc_html__('Nessuna ricorrenza', 'fp-task-agenda'); ?></option>
                                <option value="daily"><?php echo esc_html__('Giornaliera', 'fp-task-agenda'); ?></option>
                                <option value="weekly"><?php echo esc_html__('Settimanale', 'fp-task-agenda'); ?></option>
                                <option value="monthly"><?php echo esc_html__('Mensile', 'fp-task-agenda'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Il task verrà creato automaticamente in base alla ricorrenza selezionata', 'fp-task-agenda'); ?></p>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="fp-modal-footer">
            <button type="button" class="button fp-modal-cancel"><?php echo esc_html__('Annulla', 'fp-task-agenda'); ?></button>
            <button type="button" class="button button-primary fp-modal-save"><?php echo esc_html__('Salva', 'fp-task-agenda'); ?></button>
        </div>
    </div>
</div>

<div class="fp-modal-backdrop" id="fp-modal-backdrop" style="display: none;"></div>

<!-- Vista Kanban -->
<div class="fp-kanban-container fp-view-kanban-view" style="display: none;">
    <div class="fp-kanban-board">
        <div class="fp-kanban-column fp-column-pending" data-status="pending">
            <div class="fp-kanban-header">
                <h3><?php echo esc_html__('Da fare', 'fp-task-agenda'); ?></h3>
                <span class="fp-kanban-count" id="count-pending">0</span>
            </div>
            <div class="fp-kanban-cards" id="kanban-pending"></div>
        </div>
        <div class="fp-kanban-column fp-column-in-progress" data-status="in_progress">
            <div class="fp-kanban-header">
                <h3><?php echo esc_html__('In corso', 'fp-task-agenda'); ?></h3>
                <span class="fp-kanban-count" id="count-in-progress">0</span>
            </div>
            <div class="fp-kanban-cards" id="kanban-in-progress"></div>
        </div>
        <div class="fp-kanban-column fp-column-completed" data-status="completed">
            <div class="fp-kanban-header">
                <h3><?php echo esc_html__('Completati', 'fp-task-agenda'); ?></h3>
                <span class="fp-kanban-count" id="count-completed">0</span>
            </div>
            <div class="fp-kanban-cards" id="kanban-completed"></div>
        </div>
    </div>
</div>
