<?php
/**
 * Template pagina gestione clienti
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
                <?php echo esc_html__('Gestione Clienti', 'fp-task-agenda'); ?>
            </h1>
        </div>
        <div class="fp-header-actions">
            <button type="button" class="fp-btn fp-btn-primary" id="fp-add-client-btn">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php echo esc_html__('Aggiungi Cliente', 'fp-task-agenda'); ?>
            </button>
            <button type="button" class="fp-btn fp-btn-secondary" id="fp-sync-clients-btn">
                <span class="dashicons dashicons-update"></span>
                <?php echo esc_html__('Sincronizza da FP Publisher', 'fp-task-agenda'); ?>
            </button>
        </div>
    </div>
    
    <hr class="wp-header-end">
    
    <p class="description fp-clients-description">
        <?php echo esc_html__('Gestisci i tuoi clienti. Puoi sincronizzarli automaticamente da FP Publisher o aggiungerli manualmente.', 'fp-task-agenda'); ?>
    </p>
    
    <!-- Lista Clienti -->
    <div class="fp-tasks-container">
        <?php if (empty($clients)): ?>
            <div class="fp-empty-state">
                <div class="fp-empty-state-icon">
                    <span class="dashicons dashicons-businessman"></span>
                </div>
                <h3 class="fp-empty-state-title"><?php echo esc_html__('Nessun cliente trovato', 'fp-task-agenda'); ?></h3>
                <p class="fp-empty-state-description">
                    <?php echo esc_html__('Clicca su "Aggiungi Cliente" o "Sincronizza da FP Publisher" per iniziare.', 'fp-task-agenda'); ?>
                </p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped fp-tasks-table">
                <thead>
                    <tr>
                        <th scope="col" style="width: 200px;"><?php echo esc_html__('Nome', 'fp-task-agenda'); ?></th>
                        <th scope="col" style="width: 150px;"><?php echo esc_html__('Fonte', 'fp-task-agenda'); ?></th>
                        <th scope="col"><?php echo esc_html__('Azioni', 'fp-task-agenda'); ?></th>
                    </tr>
                </thead>
                <tbody id="fp-clients-list">
                    <?php foreach ($clients as $client): ?>
                        <tr class="fp-task-row fp-client-row" data-client-id="<?php echo esc_attr($client->id); ?>">
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
                                <div class="fp-template-actions">
                                    <?php if ($client->source === 'manual'): ?>
                                    <button type="button" class="button-link fp-edit-client" data-client-id="<?php echo esc_attr($client->id); ?>" aria-label="<?php echo esc_attr__('Modifica cliente', 'fp-task-agenda'); ?>">
                                        <span class="dashicons dashicons-edit"></span> <?php echo esc_html__('Modifica', 'fp-task-agenda'); ?>
                                    </button>
                                    <span class="separator">|</span>
                                    <button type="button" class="button-link delete fp-delete-client" data-client-id="<?php echo esc_attr($client->id); ?>" aria-label="<?php echo esc_attr__('Elimina cliente', 'fp-task-agenda'); ?>">
                                        <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Elimina', 'fp-task-agenda'); ?>
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
<div id="fp-client-modal" class="fp-modal" role="dialog" aria-modal="true" aria-labelledby="fp-client-modal-title" style="display: none;">
    <div class="fp-modal-content">
        <div class="fp-modal-header">
            <h2 id="fp-client-modal-title"><?php echo esc_html__('Aggiungi Cliente', 'fp-task-agenda'); ?></h2>
            <button type="button" class="fp-modal-close" aria-label="<?php echo esc_attr__('Chiudi', 'fp-task-agenda'); ?>">&times;</button>
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
