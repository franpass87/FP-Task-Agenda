<?php
/**
 * Template pagina gestione template task
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap fp-task-agenda-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Template Task', 'fp-task-agenda'); ?>
    </h1>
    
    <button type="button" class="page-title-action" id="fp-add-template-btn">
        <?php echo esc_html__('Aggiungi Template', 'fp-task-agenda'); ?>
    </button>
    
    <hr class="wp-header-end">
    
    <p class="description" style="margin-bottom: 24px;">
        <?php echo esc_html__('Crea template riutilizzabili per creare task rapidamente. I template possono includere priorità, cliente, ricorrenza e offset data di scadenza.', 'fp-task-agenda'); ?>
    </p>
    
    <!-- Lista Template -->
    <div class="fp-tasks-container">
        <?php if (empty($templates)): ?>
            <div class="fp-no-templates">
                <p><?php echo esc_html__('Nessun template trovato.', 'fp-task-agenda'); ?></p>
                <p><?php echo esc_html__('Clicca su "Aggiungi Template" per creare il tuo primo template.', 'fp-task-agenda'); ?></p>
            </div>
        <?php else: ?>
            <table class="fp-tasks-table">
                <thead>
                    <tr>
                        <th style="width: 200px;"><?php echo esc_html__('Nome Template', 'fp-task-agenda'); ?></th>
                        <th><?php echo esc_html__('Titolo Task', 'fp-task-agenda'); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Priorità', 'fp-task-agenda'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Cliente', 'fp-task-agenda'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Offset Scadenza', 'fp-task-agenda'); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Ricorrenza', 'fp-task-agenda'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Azioni', 'fp-task-agenda'); ?></th>
                    </tr>
                </thead>
                <tbody id="fp-templates-list">
                    <?php foreach ($templates as $template): ?>
                        <?php
                        $priority_class = \FP\TaskAgenda\Task::get_priority_class($template->priority);
                        $priorities = \FP\TaskAgenda\Task::get_priorities();
                        $recurrence_labels = array(
                            'daily' => __('Giornaliera', 'fp-task-agenda'),
                            'weekly' => __('Settimanale', 'fp-task-agenda'),
                            'monthly' => __('Mensile', 'fp-task-agenda')
                        );
                        ?>
                        <tr class="fp-task-row <?php echo esc_attr($priority_class); ?>" data-template-id="<?php echo esc_attr($template->id); ?>">
                            <td>
                                <strong class="fp-template-name"><?php echo esc_html($template->name); ?></strong>
                            </td>
                            <td>
                                <span class="fp-template-title"><?php echo esc_html($template->title); ?></span>
                            </td>
                            <td>
                                <span class="fp-priority-badge fp-priority-<?php echo esc_attr($template->priority); ?>">
                                    <?php echo esc_html($priorities[$template->priority] ?? $template->priority); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($template->client_id) {
                                    $client = \FP\TaskAgenda\Client::get($template->client_id);
                                    echo $client ? esc_html($client->name) : '-';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($template->due_date_offset == 0) {
                                    echo esc_html__('Nessuno', 'fp-task-agenda');
                                } else {
                                    echo esc_html(sprintf(__('%d giorni', 'fp-task-agenda'), $template->due_date_offset));
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($template->recurrence_type) {
                                    echo esc_html($recurrence_labels[$template->recurrence_type] ?? $template->recurrence_type);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="fp-template-actions">
                                    <button type="button" class="button-link fp-use-template" data-template-id="<?php echo esc_attr($template->id); ?>" title="<?php echo esc_attr__('Crea task da questo template', 'fp-task-agenda'); ?>">
                                        <span class="dashicons dashicons-plus-alt"></span> <?php echo esc_html__('Usa', 'fp-task-agenda'); ?>
                                    </button>
                                    <span class="separator">|</span>
                                    <button type="button" class="button-link fp-edit-template" data-template-id="<?php echo esc_attr($template->id); ?>">
                                        <span class="dashicons dashicons-edit"></span> <?php echo esc_html__('Modifica', 'fp-task-agenda'); ?>
                                    </button>
                                    <span class="separator">|</span>
                                    <button type="button" class="button-link delete fp-delete-template" data-template-id="<?php echo esc_attr($template->id); ?>">
                                        <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Elimina', 'fp-task-agenda'); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Aggiungi/Modifica Template -->
<div id="fp-template-modal" class="fp-modal" style="display: none;">
    <div class="fp-modal-content">
        <div class="fp-modal-header">
            <h2 id="fp-template-modal-title"><?php echo esc_html__('Aggiungi Template', 'fp-task-agenda'); ?></h2>
            <button type="button" class="fp-modal-close">&times;</button>
        </div>
        <div class="fp-modal-body">
            <form id="fp-template-form">
                <input type="hidden" id="fp-template-id" name="id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fp-template-name"><?php echo esc_html__('Nome Template', 'fp-task-agenda'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="fp-template-name" name="name" class="regular-text" required>
                            <p class="description"><?php echo esc_html__('Nome descrittivo per identificare questo template', 'fp-task-agenda'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-template-task-title"><?php echo esc_html__('Titolo Task', 'fp-task-agenda'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="fp-template-task-title" name="title" class="regular-text" required>
                            <p class="description"><?php echo esc_html__('Il titolo che avrà il task creato da questo template', 'fp-task-agenda'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-template-description"><?php echo esc_html__('Descrizione', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <textarea id="fp-template-description" name="description" rows="4" class="large-text"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-template-priority"><?php echo esc_html__('Priorità', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <select id="fp-template-priority" name="priority">
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
                            <label for="fp-template-client"><?php echo esc_html__('Cliente', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <select id="fp-template-client" name="client_id" class="regular-text">
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
                            <label for="fp-template-due-date-offset"><?php echo esc_html__('Offset Data Scadenza', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="fp-template-due-date-offset" name="due_date_offset" value="0" class="small-text">
                            <span class="description"><?php echo esc_html__('Giorni da aggiungere alla data corrente (0 = nessuna scadenza)', 'fp-task-agenda'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fp-template-recurrence"><?php echo esc_html__('Ricorrenza', 'fp-task-agenda'); ?></label>
                        </th>
                        <td>
                            <select id="fp-template-recurrence" name="recurrence_type">
                                <option value=""><?php echo esc_html__('Nessuna ricorrenza', 'fp-task-agenda'); ?></option>
                                <option value="daily"><?php echo esc_html__('Giornaliera', 'fp-task-agenda'); ?></option>
                                <option value="weekly"><?php echo esc_html__('Settimanale', 'fp-task-agenda'); ?></option>
                                <option value="monthly"><?php echo esc_html__('Mensile', 'fp-task-agenda'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('I task creati da questo template avranno questa ricorrenza', 'fp-task-agenda'); ?></p>
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

<div class="fp-modal-backdrop" id="fp-template-modal-backdrop" style="display: none;"></div>

<script>
// Definisci le variabili se non sono già state caricate da admin.js
(function() {
    if (typeof fpTaskAgenda === 'undefined') {
        window.fpTaskAgenda = {
            ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_js(wp_create_nonce('fp_task_agenda_nonce')); ?>'
        };
    }
})();

jQuery(document).ready(function($) {
    // Gestione template
    $('#fp-add-template-btn').on('click', function() {
        $('#fp-template-modal-title').text('<?php echo esc_js(__('Aggiungi Template', 'fp-task-agenda')); ?>');
        $('#fp-template-form')[0].reset();
        $('#fp-template-id').val('');
        $('#fp-template-modal-backdrop, #fp-template-modal').fadeIn(200);
    });
    
    $('.fp-edit-template').on('click', function(e) {
        e.preventDefault();
        var templateId = $(this).data('template-id');
        
        $.ajax({
            url: fpTaskAgenda.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fp_task_agenda_get_template',
                nonce: fpTaskAgenda.nonce,
                id: templateId
            },
            success: function(response) {
                if (response.success && response.data.template) {
                    var template = response.data.template;
                    $('#fp-template-modal-title').text('<?php echo esc_js(__('Modifica Template', 'fp-task-agenda')); ?>');
                    $('#fp-template-id').val(template.id);
                    $('#fp-template-name').val(template.name);
                    $('#fp-template-task-title').val(template.title);
                    $('#fp-template-description').val(template.description || '');
                    $('#fp-template-priority').val(template.priority);
                    $('#fp-template-client').val(template.client_id || '');
                    $('#fp-template-due-date-offset').val(template.due_date_offset || 0);
                    $('#fp-template-recurrence').val(template.recurrence_type || '');
                    $('#fp-template-modal-backdrop, #fp-template-modal').fadeIn(200);
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Errore', 'fp-task-agenda')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Errore durante il caricamento', 'fp-task-agenda')); ?>');
            }
        });
    });
    
    $('.fp-delete-template').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php echo esc_js(__('Sei sicuro di voler eliminare questo template?', 'fp-task-agenda')); ?>')) {
            return;
        }
        
        var templateId = $(this).data('template-id');
        var $row = $(this).closest('.fp-template-row');
        
        $.ajax({
            url: fpTaskAgenda.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fp_task_agenda_delete_template',
                nonce: fpTaskAgenda.nonce,
                id: templateId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    alert(response.data.message || '<?php echo esc_js(__('Template eliminato', 'fp-task-agenda')); ?>');
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Errore', 'fp-task-agenda')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Errore durante l\'eliminazione', 'fp-task-agenda')); ?>');
            }
        });
    });
    
    $('.fp-use-template').on('click', function(e) {
        e.preventDefault();
        var templateId = $(this).data('template-id');
        
        if (!confirm('<?php echo esc_js(__('Vuoi creare un task da questo template?', 'fp-task-agenda')); ?>')) {
            return;
        }
        
        $.ajax({
            url: fpTaskAgenda.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fp_task_agenda_create_task_from_template',
                nonce: fpTaskAgenda.nonce,
                template_id: templateId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || '<?php echo esc_js(__('Task creato con successo', 'fp-task-agenda')); ?>');
                    // Reindirizza alla pagina principale
                    window.location.href = '<?php echo esc_js(admin_url('admin.php?page=fp-task-agenda')); ?>';
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Errore', 'fp-task-agenda')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Errore durante la creazione del task', 'fp-task-agenda')); ?>');
            }
        });
    });
    
    $('.fp-modal-close, .fp-modal-cancel, #fp-template-modal-backdrop').on('click', function() {
        $('#fp-template-modal-backdrop, #fp-template-modal').fadeOut(200);
    });
    
    $('#fp-template-modal').on('click', function(e) {
        e.stopPropagation();
    });
    
    $('.fp-modal-save').on('click', function() {
        var templateId = $('#fp-template-id').val();
        var name = $('#fp-template-name').val().trim();
        var title = $('#fp-template-task-title').val().trim();
        
        if (!name) {
            alert('<?php echo esc_js(__('Il nome del template è obbligatorio', 'fp-task-agenda')); ?>');
            return;
        }
        
        if (!title) {
            alert('<?php echo esc_js(__('Il titolo del task è obbligatorio', 'fp-task-agenda')); ?>');
            return;
        }
        
        var action = templateId ? 'fp_task_agenda_update_template' : 'fp_task_agenda_add_template';
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Salvataggio...', 'fp-task-agenda')); ?>');
        
        var data = {
            action: action,
            nonce: fpTaskAgenda.nonce,
            name: name,
            title: title,
            description: $('#fp-template-description').val(),
            priority: $('#fp-template-priority').val(),
            client_id: $('#fp-template-client').val() || '',
            due_date_offset: parseInt($('#fp-template-due-date-offset').val()) || 0,
            recurrence_type: $('#fp-template-recurrence').val() || '',
            recurrence_interval: 1
        };
        
        if (templateId) {
            data.id = templateId;
        }
        
        $.ajax({
            url: fpTaskAgenda.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || '<?php echo esc_js(__('Operazione completata', 'fp-task-agenda')); ?>');
                    window.location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Errore', 'fp-task-agenda')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Salva', 'fp-task-agenda')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Errore durante il salvataggio', 'fp-task-agenda')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Salva', 'fp-task-agenda')); ?>');
            }
        });
    });
});
</script>
