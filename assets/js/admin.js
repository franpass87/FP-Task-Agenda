/**
 * JavaScript Admin - FP Task Agenda
 */

(function($) {
    'use strict';
    
    var TaskAgenda = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Apri modal aggiungi task
            $(document).on('click', '#fp-add-task-btn', this.openAddModal);
            
            // Chiudi modal
            $(document).on('click', '.fp-modal-close, .fp-modal-cancel, .fp-modal-backdrop', this.closeModal);
            
            // Salva task
            $(document).on('click', '.fp-modal-save', this.saveTask);
            
            // Modifica task
            $(document).on('click', '.fp-edit-task', this.openEditModal);
            
            // Elimina task
            $(document).on('click', '.fp-delete-task', this.deleteTask);
            
            // Toggle completato
            $(document).on('change', '.fp-task-checkbox', this.toggleStatus);
            
            // Cambio stato rapido
            $(document).on('change', '.fp-status-quick-change', this.quickChangeStatus);
            
            // Cambio priorità rapido
            $(document).on('change', '.fp-priority-quick-change', this.quickChangePriority);
            
            // Select all checkbox
            $(document).on('change', '#cb-select-all', this.selectAllTasks);
            
            // Bulk actions
            $(document).on('click', '#doaction', this.bulkAction);
            
            // Previeni chiusura modal cliccando dentro
            $(document).on('click', '.fp-modal-content', function(e) {
                e.stopPropagation();
            });
        },
        
        openAddModal: function() {
            TaskAgenda.resetForm();
            $('#fp-modal-title').text(fpTaskAgenda.strings.addTask || 'Aggiungi Task');
            $('#fp-task-status-row').hide();
            TaskAgenda.showModal();
        },
        
        openEditModal: function(e) {
            e.preventDefault();
            var $button = $(this);
            var taskId = $button.data('task-id');
            var $row = $button.closest('.fp-task-row');
            
            // Estrai i dati dalla riga corrente per velocità
            var title = $row.find('.fp-task-title').text();
            var description = $row.find('.fp-task-description').text();
            var priority = $row.hasClass('priority-low') ? 'low' : 
                          $row.hasClass('priority-normal') ? 'normal' :
                          $row.hasClass('priority-high') ? 'high' : 'urgent';
            
            // Popola il form con i dati disponibili
            $('#fp-task-id').val(taskId);
            $('#fp-task-title').val(title);
            $('#fp-task-description').val(description || '');
            $('#fp-task-priority').val(priority);
            
            // Carica i dati completi via AJAX per ottenere status, due_date e client_id
            TaskAgenda.loadTaskData(taskId, function(task) {
                $('#fp-task-status').val(task.status || 'pending');
                $('#fp-task-due-date').val(task.due_date ? task.due_date.split(' ')[0] : '');
                $('#fp-task-client').val(task.client_id || '');
                
                $('#fp-modal-title').text(fpTaskAgenda.strings.editTask || 'Modifica Task');
                $('#fp-task-status-row').show();
                TaskAgenda.showModal();
            });
        },
        
        loadTaskData: function(taskId, callback) {
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_get_task',
                    nonce: fpTaskAgenda.nonce,
                    id: taskId
                },
                success: function(response) {
                    if (response.success && response.data.task) {
                        if (callback) {
                            callback(response.data.task);
                        }
                    } else {
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.error, 'error');
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings.error, 'error');
                }
            });
        },
        
        saveTask: function() {
            var form = $('#fp-task-form');
            var taskId = $('#fp-task-id').val();
            var isEdit = taskId !== '';
            
            // Validazione
            var title = $('#fp-task-title').val().trim();
            if (!title) {
                alert(fpTaskAgenda.strings.titleRequired || 'Il titolo è obbligatorio');
                $('#fp-task-title').focus();
                return;
            }
            
            var data = {
                action: isEdit ? 'fp_task_agenda_update_task' : 'fp_task_agenda_add_task',
                nonce: fpTaskAgenda.nonce,
                title: title,
                description: $('#fp-task-description').val(),
                priority: $('#fp-task-priority').val(),
                due_date: $('#fp-task-due-date').val(),
                client_id: $('#fp-task-client').val() || ''
            };
            
            if (isEdit) {
                data.id = taskId;
                data.status = $('#fp-task-status').val();
            }
            
            // Disabilita pulsante durante il salvataggio
            var $saveBtn = $('.fp-modal-save');
            $saveBtn.prop('disabled', true).text(fpTaskAgenda.strings.saving || 'Salvataggio...');
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.success, 'success');
                        TaskAgenda.closeModal();
                        // Ricarica la pagina per aggiornare la lista
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.error, 'error');
                        $saveBtn.prop('disabled', false).text(fpTaskAgenda.strings.save || 'Salva');
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings.error, 'error');
                    $saveBtn.prop('disabled', false).text(fpTaskAgenda.strings.save || 'Salva');
                }
            });
        },
        
        deleteTask: function(e) {
            e.preventDefault();
            
            if (!confirm(fpTaskAgenda.strings.confirmDelete)) {
                return;
            }
            
            var taskId = $(this).data('task-id');
            var $row = $(this).closest('.fp-task-row');
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_delete_task',
                    nonce: fpTaskAgenda.nonce,
                    id: taskId
                },
                success: function(response) {
                    if (response.success) {
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.taskDeleted || 'Task eliminato', 'success');
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.error, 'error');
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings.error, 'error');
                }
            });
        },
        
        toggleStatus: function() {
            var checkbox = $(this);
            var taskId = checkbox.data('task-id');
            var isChecked = checkbox.is(':checked');
            var newStatus = isChecked ? 'completed' : 'pending';
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_update_task',
                    nonce: fpTaskAgenda.nonce,
                    id: taskId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        var $row = checkbox.closest('.fp-task-row');
                        if (isChecked) {
                            $row.addClass('fp-task-completed');
                        } else {
                            $row.removeClass('fp-task-completed');
                        }
                        // Ricarica la pagina per aggiornare le statistiche
                        setTimeout(function() {
                            window.location.reload();
                        }, 300);
                    } else {
                        // Ripristina checkbox in caso di errore
                        checkbox.prop('checked', !isChecked);
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.error, 'error');
                    }
                },
                error: function() {
                    checkbox.prop('checked', !isChecked);
                    TaskAgenda.showNotice(fpTaskAgenda.strings.error, 'error');
                }
            });
        },
        
        resetForm: function() {
            $('#fp-task-form')[0].reset();
            $('#fp-task-id').val('');
            $('#fp-task-status-row').hide();
            $('#fp-task-client').val('');
        },
        
        showModal: function() {
            $('#fp-modal-backdrop').fadeIn(200);
            $('#fp-task-modal').fadeIn(200);
            $('#fp-task-title').focus();
        },
        
        closeModal: function() {
            $('#fp-task-modal').fadeOut(200);
            $('#fp-modal-backdrop').fadeOut(200);
            TaskAgenda.resetForm();
        },
        
        quickChangePriority: function(e) {
            var $select = $(this);
            var taskId = $select.data('task-id');
            var newPriority = $select.val();
            var $row = $select.closest('tr');
            
            // Disabilita durante la richiesta
            $select.prop('disabled', true);
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_quick_change_priority',
                    nonce: fpTaskAgenda.nonce,
                    id: taskId,
                    priority: newPriority
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;
                        
                        // Aggiorna le classi del select e della riga
                        var oldClasses = $select.attr('class').match(/fp-priority-\w+/g);
                        if (oldClasses) {
                            oldClasses.forEach(function(cls) {
                                $select.removeClass(cls);
                                $row.removeClass(cls);
                            });
                        }
                        
                        // Aggiungi nuove classi
                        var newPriorityClass = 'fp-priority-' + newPriority;
                        var rowPriorityClass = 'priority-' + newPriority;
                        $select.addClass(newPriorityClass);
                        $select.attr('data-current-priority', newPriority);
                        $row.addClass(rowPriorityClass);
                        
                        // Aggiorna l'opzione selezionata
                        $select.find('option').prop('selected', false);
                        $select.find('option[value="' + newPriority + '"]').prop('selected', true);
                        
                        // Feedback visivo opzionale
                        $select.css('opacity', '0.7');
                        setTimeout(function() {
                            $select.css('opacity', '1');
                        }, 200);
                    } else {
                        // Ripristina valore precedente in caso di errore
                        var oldPriority = $select.data('current-priority');
                        $select.val(oldPriority);
                        alert(response.data?.message || fpTaskAgenda.strings.error || 'Errore');
                    }
                },
                error: function() {
                    var oldPriority = $select.data('current-priority');
                    $select.val(oldPriority);
                    alert(fpTaskAgenda.strings.error || 'Errore durante l\'aggiornamento');
                },
                complete: function() {
                    $select.prop('disabled', false);
                }
            });
        },
        
        quickChangeStatus: function(e) {
            var select = $(this);
            var taskId = select.data('task-id');
            var newStatus = select.val();
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_quick_change_status',
                    nonce: fpTaskAgenda.nonce,
                    id: taskId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        // Aggiorna visivamente la riga
                        var $row = select.closest('.fp-task-row');
                        if (newStatus === 'completed') {
                            $row.addClass('fp-task-completed');
                        } else {
                            $row.removeClass('fp-task-completed');
                        }
                        
                        // Aggiorna checkbox
                        $row.find('.fp-task-checkbox').prop('checked', newStatus === 'completed');
                        
                        // Ricarica per aggiornare statistiche
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        // Ripristina valore precedente
                        var oldStatus = $row.data('old-status');
                        select.val(oldStatus);
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.error, 'error');
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings.error, 'error');
                }
            });
        },
        
        selectAllTasks: function() {
            var checked = $(this).prop('checked');
            $('.fp-task-checkbox').prop('checked', checked);
        },
        
        bulkAction: function(e) {
            e.preventDefault();
            
            var action = $('#bulk-action-selector-top').val();
            if (action === '-1') {
                alert('Seleziona un\'azione');
                return;
            }
            
            var checked = $('.fp-task-checkbox:checked');
            if (checked.length === 0) {
                alert('Seleziona almeno un task');
                return;
            }
            
            var taskIds = [];
            checked.each(function() {
                taskIds.push($(this).val());
            });
            
            if (action === 'delete' && !confirm('Sei sicuro di voler eliminare ' + taskIds.length + ' task selezionati?')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).val('Elaborazione...');
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_bulk_action',
                    nonce: fpTaskAgenda.nonce,
                    bulk_action: action,
                    task_ids: taskIds
                },
                success: function(response) {
                    if (response.success) {
                        TaskAgenda.showNotice(response.data.message || 'Operazione completata', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.error, 'error');
                        $btn.prop('disabled', false).val('Applica');
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings.error, 'error');
                    $btn.prop('disabled', false).val('Applica');
                }
            });
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap').first().prepend(notice);
            
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Inizializza quando il DOM è pronto
    $(document).ready(function() {
        TaskAgenda.init();
    });
    
})(jQuery);
