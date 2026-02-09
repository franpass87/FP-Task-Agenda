<?php
/**
 * Template pagina task archiviati
 * 
 * @var array $archived_tasks
 * @var int $total_archived
 * @var int $total_pages
 * @var int $current_page
 * @var string $search
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap fp-task-agenda-wrap">
    <!-- Header -->
    <div class="fp-page-header">
        <div class="fp-header-left">
            <h1 class="wp-heading-inline" style="margin: 0;">
                <span class="dashicons dashicons-archive" style="vertical-align: middle; margin-right: 8px;"></span>
                <?php echo esc_html__('Task Archiviati', 'fp-task-agenda'); ?>
            </h1>
        </div>
        <div class="fp-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=fp-task-agenda')); ?>" class="page-title-action">
                <span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></span>
                <?php echo esc_html__('Torna ai Task', 'fp-task-agenda'); ?>
            </a>
        </div>
    </div>
    
    <hr class="wp-header-end">
    
    <!-- Info box -->
    <div class="fp-archived-info" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px 16px; margin: 20px 0; display: flex; align-items: center; gap: 10px;">
        <span class="dashicons dashicons-info" style="color: #856404;"></span>
        <span style="color: #856404;">
            <?php echo esc_html__('I task archiviati vengono eliminati definitivamente dopo 30 giorni.', 'fp-task-agenda'); ?>
        </span>
    </div>
    
    <!-- Ricerca -->
    <?php if ($total_archived > 0 || !empty($search)): ?>
    <div class="fp-task-filters" style="margin-bottom: 20px;">
        <form method="get" action="" class="fp-filter-form" style="display: flex; gap: 10px; align-items: flex-end;">
            <input type="hidden" name="page" value="fp-task-agenda-archived">
            
            <div class="fp-filter-group fp-filter-search" style="flex: 1;">
                <label class="fp-filter-label"><?php echo esc_html__('Cerca', 'fp-task-agenda'); ?></label>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Cerca nei task archiviati...', 'fp-task-agenda'); ?>" class="fp-search-input">
            </div>
            
            <div class="fp-filter-actions">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Cerca', 'fp-task-agenda'); ?></button>
                <?php if (!empty($search)): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fp-task-agenda-archived')); ?>" class="button"><?php echo esc_html__('Reset', 'fp-task-agenda'); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Lista Task Archiviati -->
    <div class="fp-tasks-container">
        <?php if (empty($archived_tasks)): ?>
            <div class="fp-no-tasks" style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 12px; border: 1px solid #e9ecef;">
                <span class="dashicons dashicons-archive" style="font-size: 48px; width: 48px; height: 48px; color: #adb5bd; margin-bottom: 16px;"></span>
                <p style="font-size: 16px; color: #495057; margin: 0 0 8px;">
                    <?php echo esc_html__('Nessun task archiviato.', 'fp-task-agenda'); ?>
                </p>
                <p style="font-size: 14px; color: #6c757d; margin: 0;">
                    <?php echo esc_html__('I task che elimini finiranno qui e potrai ripristinarli.', 'fp-task-agenda'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="alignleft actions" style="margin-bottom: 10px;">
                <?php echo esc_html(sprintf(__('%d task archiviati', 'fp-task-agenda'), $total_archived)); ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped fp-tasks-table fp-archived-table">
                <thead>
                    <tr>
                        <th style="width: 120px;"><?php echo esc_html__('Priorità', 'fp-task-agenda'); ?></th>
                        <th><?php echo esc_html__('Titolo', 'fp-task-agenda'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Cliente', 'fp-task-agenda'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Stato', 'fp-task-agenda'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Archiviato', 'fp-task-agenda'); ?></th>
                        <th style="width: 180px;"><?php echo esc_html__('Azioni', 'fp-task-agenda'); ?></th>
                    </tr>
                </thead>
                <tbody id="fp-archived-tasks-list">
                    <?php foreach ($archived_tasks as $task): ?>
                        <?php
                        $priority_class = \FP\TaskAgenda\Task::get_priority_class($task->priority);
                        $priorities = \FP\TaskAgenda\Task::get_priorities();
                        $statuses = \FP\TaskAgenda\Task::get_statuses();
                        ?>
                        <tr class="fp-task-row fp-archived-task-row <?php echo esc_attr($priority_class); ?>" data-task-id="<?php echo esc_attr($task->id); ?>">
                            <td>
                                <span class="fp-priority-badge fp-priority-<?php echo esc_attr($task->priority); ?>">
                                    <?php echo esc_html($priorities[$task->priority] ?? $task->priority); ?>
                                </span>
                            </td>
                            <td>
                                <strong class="fp-task-title"><?php echo esc_html($task->title); ?></strong>
                                <?php if (!empty($task->description)): ?>
                                    <br><small class="fp-task-description" style="color: #6c757d;"><?php echo esc_html(wp_trim_words($task->description, 15)); ?></small>
                                <?php endif; ?>
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
                                <span class="fp-status-badge fp-status-<?php echo esc_attr($task->status); ?>">
                                    <?php echo esc_html($statuses[$task->status] ?? $task->status); ?>
                                </span>
                            </td>
                            <td>
                                <small class="fp-task-date">
                                    <?php echo esc_html(human_time_diff(strtotime($task->deleted_at), current_time('timestamp'))) . ' fa'; ?>
                                </small>
                                <br>
                                <small style="color: #868e96;">
                                    <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($task->deleted_at))); ?>
                                </small>
                            </td>
                            <td>
                                <div class="fp-task-actions" style="display: flex; gap: 8px;">
                                    <button type="button" class="button button-small fp-restore-task" data-task-id="<?php echo esc_attr($task->id); ?>" title="<?php echo esc_attr__('Ripristina', 'fp-task-agenda'); ?>">
                                        <span class="dashicons dashicons-undo" style="vertical-align: middle;"></span>
                                        <?php echo esc_html__('Ripristina', 'fp-task-agenda'); ?>
                                    </button>
                                    <button type="button" class="button button-small button-link-delete fp-permanently-delete-task" data-task-id="<?php echo esc_attr($task->id); ?>" title="<?php echo esc_attr__('Elimina definitivamente', 'fp-task-agenda'); ?>" style="color: #b32d2e;">
                                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
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
