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
    <!-- Header con titolo e azioni principali -->
    <div class="fp-page-header">
        <div class="fp-header-left">
            <h1 class="wp-heading-inline" style="margin: 0;">
                <?php echo esc_html__('Task Agenda', 'fp-task-agenda'); ?>
            </h1>
        </div>
        <div class="fp-header-actions">
            <button type="button" class="fp-btn fp-btn-primary" id="fp-add-task-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php echo esc_html__('Aggiungi Task', 'fp-task-agenda'); ?>
            </button>
            
            <?php 
            $templates = \FP\TaskAgenda\Template::get_all();
            $template_btn_class = empty($templates) ? 'fp-btn fp-btn-secondary fp-btn-disabled' : 'fp-btn fp-btn-secondary';
            ?>
            <button type="button" class="<?php echo esc_attr($template_btn_class); ?>" id="fp-create-from-template-btn" <?php echo empty($templates) ? 'title="' . esc_attr__('Crea prima un template nella pagina Template', 'fp-task-agenda') . '"' : ''; ?>>
                <span class="dashicons dashicons-media-document"></span>
                <?php echo esc_html__('Crea da Template', 'fp-task-agenda'); ?>
            </button>
        </div>
    </div>
    
    <hr class="wp-header-end">
    
    <!-- Avviso task archiviati -->
    <?php 
    $archived_count = isset($archived_count) ? $archived_count : \FP\TaskAgenda\Database::count_archived_tasks();
    if ($archived_count > 0): 
    ?>
    <div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left: 4px solid #2271b1; background: #f0f6fc;">
        <p style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <span class="dashicons dashicons-archive" style="color: #2271b1;"></span>
            <strong><?php echo esc_html(sprintf(__('Hai %d task archiviati che possono essere ripristinati.', 'fp-task-agenda'), $archived_count)); ?></strong>
            <a href="<?php echo esc_url(admin_url('admin.php?page=fp-task-agenda-archived')); ?>" class="button button-primary" style="margin-left: auto;">
                <?php echo esc_html__('Vedi Task Archiviati', 'fp-task-agenda'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
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
    
    <!-- Toolbar con View Toggle -->
    <div class="fp-toolbar">
        <div class="fp-toolbar-right">
            <!-- Toggle Vista -->
            <div class="fp-view-toggle">
                <button type="button" class="page-title-action fp-view-btn fp-view-table active" data-view="table">
                    <?php echo esc_html__('Tabella', 'fp-task-agenda'); ?>
                </button>
                <button type="button" class="page-title-action fp-view-btn fp-view-kanban" data-view="kanban">
                    <?php echo esc_html__('Kanban', 'fp-task-agenda'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Statistiche -->
    <?php 
    // Calcola percentuale completamento
    $completion_percentage = $stats['all'] > 0 ? round(($stats['completed'] / $stats['all']) * 100) : 0;
    ?>
    <div class="fp-task-stats">
        <a href="<?php echo esc_url(add_query_arg(array('status' => 'all'), remove_query_arg('paged'))); ?>" class="fp-stat-card fp-stat-card-link <?php echo ($current_status === 'all' && $current_priority === 'all') ? 'fp-stat-active' : ''; ?>">
            <span class="fp-stat-label">
                <span class="dashicons dashicons-list-view"></span>
                <?php echo esc_html__('Totali', 'fp-task-agenda'); ?>
            </span>
            <span class="fp-stat-value"><?php echo esc_html($stats['all']); ?></span>
            <?php if ($stats['all'] > 0): ?>
            <div class="fp-stat-progress-bar">
                <div class="fp-stat-progress-fill" style="width: <?php echo esc_attr($completion_percentage); ?>%"></div>
            </div>
            <span class="fp-stat-percentage"><?php echo esc_html($completion_percentage); ?>% completato</span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(array('status' => 'pending'), remove_query_arg('paged'))); ?>" class="fp-stat-card fp-stat-pending fp-stat-card-link <?php echo $current_status === 'pending' ? 'fp-stat-active' : ''; ?>">
            <span class="fp-stat-label">
                <span class="dashicons dashicons-clock"></span>
                <?php echo esc_html__('Da fare', 'fp-task-agenda'); ?>
            </span>
            <span class="fp-stat-value"><?php echo esc_html($stats['pending']); ?></span>
        </a>
        <a href="<?php echo esc_url(add_query_arg(array('status' => 'in_progress'), remove_query_arg('paged'))); ?>" class="fp-stat-card fp-stat-progress fp-stat-card-link <?php echo $current_status === 'in_progress' ? 'fp-stat-active' : ''; ?>">
            <span class="fp-stat-label">
                <span class="dashicons dashicons-update"></span>
                <?php echo esc_html__('In corso', 'fp-task-agenda'); ?>
            </span>
            <span class="fp-stat-value"><?php echo esc_html($stats['in_progress']); ?></span>
        </a>
        <?php if ($stats['due_soon'] > 0): ?>
        <a href="<?php echo esc_url(add_query_arg(array('status' => 'all', 'orderby' => 'due_date', 'order' => 'ASC'), remove_query_arg('paged'))); ?>" class="fp-stat-card fp-stat-due-soon fp-stat-card-link">
            <span class="fp-stat-label">
                <span class="dashicons dashicons-warning"></span>
                <?php echo esc_html__('In scadenza', 'fp-task-agenda'); ?>
            </span>
            <span class="fp-stat-value"><?php echo esc_html($stats['due_soon']); ?></span>
        </a>
        <?php endif; ?>
        <a href="<?php echo esc_url(add_query_arg(array('status' => 'completed'), remove_query_arg('paged'))); ?>" class="fp-stat-card fp-stat-completed fp-stat-card-link <?php echo $current_status === 'completed' ? 'fp-stat-active' : ''; ?>">
            <span class="fp-stat-label">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html__('Completati', 'fp-task-agenda'); ?>
            </span>
            <span class="fp-stat-value"><?php echo esc_html($stats['completed']); ?></span>
        </a>
    </div>
    
    <!-- Filtri -->
    <div class="fp-task-filters">
        <div class="fp-filter-header">
            <h3 style="margin: 0; font-size: 14px; font-weight: 600; color: #495057;">
                <span class="dashicons dashicons-filter" style="vertical-align: middle;"></span> <?php echo esc_html__('Filtri', 'fp-task-agenda'); ?>
            </h3>
            <button type="button" class="button-link fp-filter-toggle" id="fp-filter-toggle" style="text-decoration: none; padding: 4px 8px;">
                <span class="dashicons dashicons-arrow-down-alt"></span>
            </button>
        </div>
        <form method="get" action="" class="fp-filter-form" id="fp-filter-form" style="display: flex;">
            <input type="hidden" name="page" value="fp-task-agenda">
            
            <div class="fp-filter-group">
                <label class="fp-filter-label"><?php echo esc_html__('Stato', 'fp-task-agenda'); ?></label>
                <select name="status" id="filter-status" class="fp-filter-select">
                    <option value="all" <?php selected($current_status, 'all'); ?>><?php echo esc_html__('Tutti', 'fp-task-agenda'); ?></option>
                    <option value="pending" <?php selected($current_status, 'pending'); ?>><?php echo esc_html__('Da fare', 'fp-task-agenda'); ?></option>
                    <option value="in_progress" <?php selected($current_status, 'in_progress'); ?>><?php echo esc_html__('In corso', 'fp-task-agenda'); ?></option>
                    <option value="completed" <?php selected($current_status, 'completed'); ?>><?php echo esc_html__('Completati', 'fp-task-agenda'); ?></option>
                </select>
                <?php if ($current_status !== 'all'): ?>
                    <span class="fp-filter-badge"><?php echo esc_html(ucfirst($current_status === 'in_progress' ? 'In corso' : $current_status)); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="fp-filter-group">
                <label class="fp-filter-label"><?php echo esc_html__('Priorità', 'fp-task-agenda'); ?></label>
                <select name="priority" id="filter-priority" class="fp-filter-select">
                    <option value="all" <?php selected($current_priority, 'all'); ?>><?php echo esc_html__('Tutte', 'fp-task-agenda'); ?></option>
                    <option value="low" <?php selected($current_priority, 'low'); ?>><?php echo esc_html__('Bassa', 'fp-task-agenda'); ?></option>
                    <option value="normal" <?php selected($current_priority, 'normal'); ?>><?php echo esc_html__('Normale', 'fp-task-agenda'); ?></option>
                    <option value="high" <?php selected($current_priority, 'high'); ?>><?php echo esc_html__('Alta', 'fp-task-agenda'); ?></option>
                    <option value="urgent" <?php selected($current_priority, 'urgent'); ?>><?php echo esc_html__('Urgente', 'fp-task-agenda'); ?></option>
                </select>
                <?php if ($current_priority !== 'all'): ?>
                    <span class="fp-filter-badge fp-priority-<?php echo esc_attr($current_priority); ?>"><?php echo esc_html(ucfirst($current_priority)); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="fp-filter-group">
                <label class="fp-filter-label"><?php echo esc_html__('Cliente', 'fp-task-agenda'); ?></label>
                <select name="client_id" id="filter-client" class="fp-filter-select">
                    <option value="all" <?php selected($current_client, 'all'); ?>><?php echo esc_html__('Tutti', 'fp-task-agenda'); ?></option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo esc_attr($client->id); ?>" <?php selected($current_client, $client->id); ?>>
                            <?php echo esc_html($client->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($current_client !== 'all'): ?>
                    <span class="fp-filter-badge"><?php echo esc_html(get_post_meta($current_client, 'name', true) ?: $current_client); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="fp-filter-group fp-filter-search">
                <label class="fp-filter-label"><?php echo esc_html__('Cerca', 'fp-task-agenda'); ?></label>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Cerca task...', 'fp-task-agenda'); ?>" class="fp-search-input">
            </div>
            
            <div class="fp-filter-actions">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Filtra', 'fp-task-agenda'); ?></button>
                
                <?php if (!empty($search) || $current_status !== 'all' || $current_priority !== 'all' || $current_client !== 'all'): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fp-task-agenda')); ?>" class="button"><?php echo esc_html__('Reset', 'fp-task-agenda'); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Lista Task - Vista Tabella -->
    <div class="fp-tasks-container fp-view-table-view">
        <?php if (empty($tasks)): ?>
            <div class="fp-empty-state">
                <div class="fp-empty-state-icon">
                    <span class="dashicons dashicons-clipboard"></span>
                </div>
                <h3 class="fp-empty-state-title"><?php echo esc_html__('Nessun task trovato', 'fp-task-agenda'); ?></h3>
                <p class="fp-empty-state-description">
                    <?php if (!empty($search) || $current_status !== 'all' || $current_priority !== 'all'): ?>
                        <?php echo esc_html__('Prova a modificare i filtri o la ricerca per trovare i task.', 'fp-task-agenda'); ?>
                    <?php else: ?>
                        <?php echo esc_html__('Inizia creando il tuo primo task per organizzare le attività.', 'fp-task-agenda'); ?>
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && $current_status === 'all' && $current_priority === 'all'): ?>
                    <button type="button" class="button button-primary button-hero fp-empty-state-cta" id="fp-add-task-btn-empty">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php echo esc_html__('Crea il primo task', 'fp-task-agenda'); ?>
                    </button>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fp-task-agenda')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php echo esc_html__('Rimuovi filtri', 'fp-task-agenda'); ?>
                    </a>
                <?php endif; ?>
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
                        <th style="width: 120px;">
                            <span><?php echo esc_html__('Ricorrenza', 'fp-task-agenda'); ?></span>
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
                        $is_overdue = \FP\TaskAgenda\Task::is_overdue($task->due_date);
                        $is_completed = $task->status === 'completed';
                        $is_in_progress = $task->status === 'in_progress';
                        ?>
                        <tr class="fp-task-row <?php echo esc_attr($priority_class); ?> <?php echo $is_completed ? 'fp-task-completed' : ''; ?> <?php echo $is_in_progress && !$is_overdue ? 'fp-task-in-progress' : ''; ?> <?php echo $is_due_soon && !$is_completed && !$is_overdue ? 'fp-task-due-soon' : ''; ?> <?php echo $is_overdue && !$is_completed ? 'fp-task-overdue' : ''; ?>" data-task-id="<?php echo esc_attr($task->id); ?>">
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
                                    <?php 
                                    $description_length = mb_strlen($task->description);
                                    $is_long = $description_length > 100;
                                    $preview = $is_long ? mb_substr($task->description, 0, 100) . '...' : $task->description;
                                    ?>
                                    <div class="fp-task-description-container">
                                        <small class="fp-task-description fp-task-description-preview"><?php echo esc_html($preview); ?></small>
                                        <?php if ($is_long): ?>
                                            <small class="fp-task-description fp-task-description-full" style="display: none;"><?php echo nl2br(esc_html($task->description)); ?></small>
                                            <button type="button" class="button-link fp-toggle-description" data-expanded="false">
                                                <span class="fp-show-more"><?php echo esc_html__('Mostra tutto', 'fp-task-agenda'); ?></span>
                                                <span class="fp-show-less" style="display: none;"><?php echo esc_html__('Nascondi', 'fp-task-agenda'); ?></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($task->due_date): ?>
                                    <span class="fp-due-date <?php echo $is_overdue ? 'fp-due-overdue' : ($is_due_soon && !$is_completed ? 'fp-due-soon' : ''); ?>">
                                        <?php echo esc_html($due_date_formatted); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="fp-no-due-date">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($task->recurrence_type)): ?>
                                    <?php
                                    $recurrence_labels = array(
                                        'daily' => __('Giornaliera', 'fp-task-agenda'),
                                        'weekly' => __('Settimanale', 'fp-task-agenda'),
                                        'monthly' => __('Mensile', 'fp-task-agenda')
                                    );
                                    $recurrence_label = isset($recurrence_labels[$task->recurrence_type]) 
                                        ? $recurrence_labels[$task->recurrence_type] 
                                        : ucfirst($task->recurrence_type);
                                    
                                    // Aggiungi dettaglio giorno se presente
                                    $day_detail = '';
                                    if (!empty($task->recurrence_day)) {
                                        if ($task->recurrence_type === 'monthly') {
                                            $day_detail = sprintf(__('(il %d)', 'fp-task-agenda'), $task->recurrence_day);
                                        } elseif ($task->recurrence_type === 'weekly') {
                                            $days_of_week = array(
                                                0 => __('Dom', 'fp-task-agenda'),
                                                1 => __('Lun', 'fp-task-agenda'),
                                                2 => __('Mar', 'fp-task-agenda'),
                                                3 => __('Mer', 'fp-task-agenda'),
                                                4 => __('Gio', 'fp-task-agenda'),
                                                5 => __('Ven', 'fp-task-agenda'),
                                                6 => __('Sab', 'fp-task-agenda')
                                            );
                                            $day_detail = '(' . ($days_of_week[$task->recurrence_day] ?? '') . ')';
                                        }
                                    }
                                    ?>
                                    <span class="fp-recurrence-badge" title="<?php echo esc_attr($recurrence_label . ' ' . $day_detail); ?>">
                                        <span class="dashicons dashicons-update"></span>
                                        <span class="fp-recurrence-text"><?php echo esc_html($recurrence_label); ?></span>
                                        <?php if ($day_detail): ?>
                                            <small class="fp-recurrence-day-info"><?php echo esc_html($day_detail); ?></small>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="fp-no-recurrence">—</span>
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
                            <p class="description"><?php echo esc_html__('Il task verrà ricreato automaticamente quando viene completato', 'fp-task-agenda'); ?></p>
                        </td>
                    </tr>
                    <tr id="fp-recurrence-day-row" style="display: none;">
                        <th scope="row">
                            <label for="fp-task-recurrence-day"><?php echo esc_html__('Giorno ricorrenza', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <!-- Select per ricorrenza mensile (giorno del mese 1-31) -->
                            <select id="fp-task-recurrence-day-monthly" name="recurrence_day" class="fp-recurrence-day-select" style="display: none;">
                                <option value=""><?php echo esc_html__('Stesso giorno della scadenza', 'fp-task-agenda'); ?></option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo sprintf(__('Il %d di ogni mese', 'fp-task-agenda'), $i); ?></option>
                                <?php endfor; ?>
                            </select>
                            
                            <!-- Select per ricorrenza settimanale (giorno della settimana 0-6) -->
                            <select id="fp-task-recurrence-day-weekly" name="recurrence_day" class="fp-recurrence-day-select" style="display: none;">
                                <option value=""><?php echo esc_html__('Stesso giorno della scadenza', 'fp-task-agenda'); ?></option>
                                <option value="1"><?php echo esc_html__('Ogni Lunedì', 'fp-task-agenda'); ?></option>
                                <option value="2"><?php echo esc_html__('Ogni Martedì', 'fp-task-agenda'); ?></option>
                                <option value="3"><?php echo esc_html__('Ogni Mercoledì', 'fp-task-agenda'); ?></option>
                                <option value="4"><?php echo esc_html__('Ogni Giovedì', 'fp-task-agenda'); ?></option>
                                <option value="5"><?php echo esc_html__('Ogni Venerdì', 'fp-task-agenda'); ?></option>
                                <option value="6"><?php echo esc_html__('Ogni Sabato', 'fp-task-agenda'); ?></option>
                                <option value="0"><?php echo esc_html__('Ogni Domenica', 'fp-task-agenda'); ?></option>
                            </select>
                            
                            <p class="description fp-recurrence-day-description" id="fp-recurrence-day-desc-monthly" style="display: none;">
                                <?php echo esc_html__('Seleziona il giorno del mese in cui la ricorrenza si riattiva', 'fp-task-agenda'); ?>
                            </p>
                            <p class="description fp-recurrence-day-description" id="fp-recurrence-day-desc-weekly" style="display: none;">
                                <?php echo esc_html__('Seleziona il giorno della settimana in cui la ricorrenza si riattiva', 'fp-task-agenda'); ?>
                            </p>
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
