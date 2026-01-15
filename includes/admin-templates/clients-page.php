<?php
/**
 * Template pagina gestione clienti
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap fp-task-agenda-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Gestione Clienti', 'fp-task-agenda'); ?>
    </h1>
    
    <button type="button" class="page-title-action" id="fp-add-client-btn">
        <?php echo esc_html__('Aggiungi Cliente', 'fp-task-agenda'); ?>
    </button>
    
    <button type="button" class="page-title-action" id="fp-sync-clients-btn" style="margin-left: 5px;">
        <?php echo esc_html__('Sincronizza da FP Publisher', 'fp-task-agenda'); ?>
    </button>
    
    <hr class="wp-header-end">
    
    <p class="description">
        <?php echo esc_html__('Gestisci i tuoi clienti. Puoi sincronizzarli automaticamente da FP Publisher o aggiungerli manualmente.', 'fp-task-agenda'); ?>
    </p>
    
    <!-- Lista Clienti -->
    <div class="fp-clients-container">
        <?php if (empty($clients)): ?>
            <div class="fp-no-clients">
                <p><?php echo esc_html__('Nessun cliente trovato.', 'fp-task-agenda'); ?></p>
                <p><?php echo esc_html__('Clicca su "Aggiungi Cliente" o "Sincronizza da FP Publisher" per iniziare.', 'fp-task-agenda'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped fp-clients-table">
                <thead>
                    <tr>
                        <th style="width: 200px;"><?php echo esc_html__('Nome', 'fp-task-agenda'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Fonte', 'fp-task-agenda'); ?></th>
                        <th><?php echo esc_html__('Azioni', 'fp-task-agenda'); ?></th>
                    </tr>
                </thead>
                <tbody id="fp-clients-list">
                    <?php foreach ($clients as $client): ?>
                        <tr class="fp-client-row" data-client-id="<?php echo esc_attr($client->id); ?>">
                            <td>
                                <strong class="fp-client-name"><?php echo esc_html($client->name); ?></strong>
                            </td>
                            <td>
                                <?php if ($client->source === 'fp_publisher'): ?>
                                    <span class="fp-client-source fp-source-publisher">
                                        <span class="dashicons dashicons-update"></span> <?php echo esc_html__('FP Publisher', 'fp-task-agenda'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="fp-client-source fp-source-manual">
                                        <span class="dashicons dashicons-edit"></span> <?php echo esc_html__('Manuale', 'fp-task-agenda'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fp-client-actions">
                                    <?php if ($client->source === 'manual'): ?>
                                        <button type="button" class="button-link fp-edit-client" data-client-id="<?php echo esc_attr($client->id); ?>">
                                            <?php echo esc_html__('Modifica', 'fp-task-agenda'); ?>
                                        </button>
                                        <span class="separator">|</span>
                                        <button type="button" class="button-link delete fp-delete-client" data-client-id="<?php echo esc_attr($client->id); ?>">
                                            <?php echo esc_html__('Elimina', 'fp-task-agenda'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="fp-readonly-note"><?php echo esc_html__('Gestito da FP Publisher', 'fp-task-agenda'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Aggiungi/Modifica Cliente -->
<div id="fp-client-modal" class="fp-modal" style="display: none;">
    <div class="fp-modal-content">
        <div class="fp-modal-header">
            <h2 id="fp-client-modal-title"><?php echo esc_html__('Aggiungi Cliente', 'fp-task-agenda'); ?></h2>
            <button type="button" class="fp-modal-close">&times;</button>
        </div>
        <div class="fp-modal-body">
            <form id="fp-client-form">
                <input type="hidden" id="fp-client-id" name="id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fp-client-name"><?php echo esc_html__('Nome Cliente', 'fp-task-agenda'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="fp-client-name" name="name" class="regular-text" required>
                            <p class="description"><?php echo esc_html__('Inserisci il nome del cliente', 'fp-task-agenda'); ?></p>
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

<div class="fp-modal-backdrop" id="fp-client-modal-backdrop" style="display: none;"></div>

<script>
jQuery(document).ready(function($) {
    // Gestione clienti
    $('#fp-add-client-btn').on('click', function() {
        $('#fp-client-modal-title').text('<?php echo esc_js(__('Aggiungi Cliente', 'fp-task-agenda')); ?>');
        $('#fp-client-form')[0].reset();
        $('#fp-client-id').val('');
        $('#fp-client-modal-backdrop, #fp-client-modal').fadeIn(200);
    });
    
    $('.fp-edit-client').on('click', function(e) {
        e.preventDefault();
        var clientId = $(this).data('client-id');
        var $row = $(this).closest('.fp-client-row');
        var name = $row.find('.fp-client-name').text();
        
        $('#fp-client-modal-title').text('<?php echo esc_js(__('Modifica Cliente', 'fp-task-agenda')); ?>');
        $('#fp-client-id').val(clientId);
        $('#fp-client-name').val(name);
        $('#fp-client-modal-backdrop, #fp-client-modal').fadeIn(200);
    });
    
    $('.fp-delete-client').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php echo esc_js(__('Sei sicuro di voler eliminare questo cliente?', 'fp-task-agenda')); ?>')) {
            return;
        }
        
        var clientId = $(this).data('client-id');
        var $row = $(this).closest('.fp-client-row');
        
        $.ajax({
            url: fpTaskAgenda.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fp_task_agenda_delete_client',
                nonce: fpTaskAgenda.nonce,
                id: clientId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    alert(response.data.message || '<?php echo esc_js(__('Cliente eliminato', 'fp-task-agenda')); ?>');
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Errore', 'fp-task-agenda')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Errore durante l\'eliminazione', 'fp-task-agenda')); ?>');
            }
        });
    });
    
    $('#fp-sync-clients-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Sincronizzazione in corso...', 'fp-task-agenda')); ?>');
        
        $.ajax({
            url: fpTaskAgenda.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fp_task_agenda_sync_clients',
                nonce: fpTaskAgenda.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || '<?php echo esc_js(__('Sincronizzazione completata', 'fp-task-agenda')); ?>');
                    window.location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Errore durante la sincronizzazione', 'fp-task-agenda')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Errore durante la sincronizzazione', 'fp-task-agenda')); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Sincronizza da FP Publisher', 'fp-task-agenda')); ?>');
            }
        });
    });
    
    $('.fp-modal-close, .fp-modal-cancel, #fp-client-modal-backdrop').on('click', function() {
        $('#fp-client-modal-backdrop, #fp-client-modal').fadeOut(200);
    });
    
    $('#fp-client-modal').on('click', function(e) {
        e.stopPropagation();
    });
    
    $('.fp-modal-save').on('click', function() {
        var clientId = $('#fp-client-id').val();
        var name = $('#fp-client-name').val().trim();
        
        if (!name) {
            alert('<?php echo esc_js(__('Il nome è obbligatorio', 'fp-task-agenda')); ?>');
            return;
        }
        
        var action = clientId ? 'fp_task_agenda_update_client' : 'fp_task_agenda_add_client';
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Salvataggio...', 'fp-task-agenda')); ?>');
        
        $.ajax({
            url: fpTaskAgenda.ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: fpTaskAgenda.nonce,
                id: clientId,
                name: name
            },
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
