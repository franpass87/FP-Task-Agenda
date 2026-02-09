<?php
/**
 * Template pagina Impostazioni
 *
 * @var array $settings
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap fp-task-agenda-wrap">
    <div class="fp-page-header">
        <div class="fp-header-left">
            <h1 class="wp-heading-inline" style="margin: 0;">
                <?php echo esc_html__('Impostazioni', 'fp-task-agenda'); ?>
            </h1>
        </div>
    </div>
    
    <hr class="wp-header-end">
    
    <form method="post" action="" id="fp-settings-form">
        <?php wp_nonce_field('fp_task_agenda_save_settings', 'fp_task_agenda_settings_nonce'); ?>
        
        <input type="hidden" name="fp_task_agenda_settings[submit]" value="1">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="items_per_page"><?php echo esc_html__('Task per pagina', 'fp-task-agenda'); ?></label>
                </th>
                <td>
                    <input type="number" id="items_per_page" name="fp_task_agenda_settings[items_per_page]" 
                           value="<?php echo esc_attr($settings['items_per_page']); ?>" 
                           min="10" max="100" class="small-text">
                    <p class="description"><?php echo esc_html__('Numero di task visualizzati per pagina (10-100)', 'fp-task-agenda'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="auto_cleanup_days"><?php echo esc_html__('Giorni prima di eliminare archiviati', 'fp-task-agenda'); ?></label>
                </th>
                <td>
                    <input type="number" id="auto_cleanup_days" name="fp_task_agenda_settings[auto_cleanup_days]" 
                           value="<?php echo esc_attr($settings['auto_cleanup_days']); ?>" 
                           min="7" max="90" class="small-text">
                    <p class="description"><?php echo esc_html__('I task archiviati vengono eliminati definitivamente dopo questo numero di giorni (7-90)', 'fp-task-agenda'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php echo esc_html__('FP Publisher', 'fp-task-agenda'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="fp_task_agenda_settings[publisher_sync_enabled]" value="1" 
                               <?php checked(!empty($settings['publisher_sync_enabled'])); ?>>
                        <?php echo esc_html__('Abilita sincronizzazione clienti e verifica post da FP Publisher', 'fp-task-agenda'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php echo esc_html__('Visualizzazione', 'fp-task-agenda'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="fp_task_agenda_settings[show_completed]" value="1" 
                               <?php checked(!empty($settings['show_completed'])); ?>>
                        <?php echo esc_html__('Mostra task completati nelle liste', 'fp-task-agenda'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Salva impostazioni', 'fp-task-agenda')); ?>
    </form>
</div>
