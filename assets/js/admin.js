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
            
            // Apri modal selezione template
            $(document).on('click', '#fp-create-from-template-btn', this.openTemplateSelectModal);
            
            // Seleziona template dalla card
            $(document).on('click', '.fp-template-card', this.selectTemplate);
            
            // Chiudi modal
            $(document).on('click', '.fp-modal-close, .fp-modal-cancel, .fp-modal-backdrop', this.closeModal);
            
            // Chiudi modal template select
            $(document).on('click', '#fp-template-select-modal-backdrop, #fp-template-select-modal .fp-modal-cancel, #fp-template-select-modal .fp-modal-close', function() {
                $('#fp-template-select-modal-backdrop, #fp-template-select-modal').fadeOut(200);
            });
            
            // Previeni chiusura modal template select cliccando dentro
            $(document).on('click', '#fp-template-select-modal', function(e) {
                e.stopPropagation();
            });
            
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
            
            // Toggle vista Kanban/Tabella
            $(document).on('click', '.fp-view-btn', this.toggleView);
            
            // Drag & drop Kanban
            this.initKanbanDragDrop();
            
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
                $('#fp-task-recurrence').val(task.recurrence_type || '');
                
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
                client_id: $('#fp-task-client').val() || '',
                recurrence_type: $('#fp-task-recurrence').val() || ''
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
            var $row = $select.closest('.fp-task-row');
            
            // Fallback: se non trova con closest, cerca per data-task-id
            if ($row.length === 0 || !$row.hasClass('fp-task-row')) {
                $row = $('.fp-task-row[data-task-id="' + taskId + '"]');
            }
            
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
                        
                        // Rimuovi TUTTE le classi di priorità dalla riga usando attr per essere sicuri
                        var currentRowClasses = $row.attr('class') || '';
                        var priorityClasses = ['priority-low', 'priority-normal', 'priority-high', 'priority-urgent'];
                        var fpPriorityClasses = ['fp-priority-low', 'fp-priority-normal', 'fp-priority-high', 'fp-priority-urgent'];
                        
                        // Rimuovi tutte le classi di priorità dalla riga
                        priorityClasses.forEach(function(cls) {
                            currentRowClasses = currentRowClasses.replace(new RegExp('\\s*' + cls + '\\s*', 'g'), ' ');
                        });
                        $row.attr('class', currentRowClasses.trim());
                        
                        // Rimuovi tutte le classi di priorità dal select
                        var currentSelectClasses = $select.attr('class') || '';
                        fpPriorityClasses.forEach(function(cls) {
                            currentSelectClasses = currentSelectClasses.replace(new RegExp('\\s*' + cls + '\\s*', 'g'), ' ');
                        });
                        $select.attr('class', currentSelectClasses.trim());
                        
                        // Forza un reflow per assicurarsi che le rimozioni vengano applicate
                        if ($row[0]) {
                            $row[0].offsetHeight;
                        }
                        
                        // Aggiungi nuove classi
                        var newPriorityClass = 'fp-priority-' + newPriority;
                        var rowPriorityClass = 'priority-' + newPriority;
                        $select.addClass(newPriorityClass);
                        $select.attr('data-current-priority', newPriority);
                        $row.addClass(rowPriorityClass);
                        
                        // Forza un altro reflow per assicurarsi che le nuove classi vengano applicate
                        if ($row[0]) {
                            $row[0].offsetHeight;
                        }
                        
                        // Aggiorna l'opzione selezionata
                        $select.find('option').prop('selected', false);
                        $select.find('option[value="' + newPriority + '"]').prop('selected', true);
                        
                        // Feedback visivo con animazione leggera
                        $row.css('transition', 'background 0.3s ease, border-left 0.3s ease');
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
        },
        
        toggleView: function() {
            var view = $(this).data('view');
            $('.fp-view-btn').removeClass('active');
            $(this).addClass('active');
            
            if (view === 'kanban') {
                $('.fp-view-table-view').hide();
                $('.fp-view-kanban-view').show();
                TaskAgenda.renderKanban();
            } else {
                $('.fp-view-kanban-view').hide();
                $('.fp-view-table-view').show();
            }
        },
        
        renderKanban: function() {
            var tasks = [];
            $('.fp-task-row').each(function() {
                var $row = $(this);
                var task = {
                    id: $row.data('task-id'),
                    title: $row.find('.fp-task-title').text(),
                    priority: $row.hasClass('priority-low') ? 'low' : 
                              $row.hasClass('priority-normal') ? 'normal' :
                              $row.hasClass('priority-high') ? 'high' : 'urgent',
                    status: $row.find('.fp-status-quick-change').val() || 'pending',
                    dueDate: $row.find('.fp-task-date').text(),
                    client: $row.find('td:nth-child(3)').text().trim()
                };
                tasks.push(task);
            });
            
            // Reset colonne
            $('#kanban-pending, #kanban-in-progress, #kanban-completed').empty();
            
            // Popola colonne
            var counts = {pending: 0, in_progress: 0, completed: 0};
            tasks.forEach(function(task) {
                var card = TaskAgenda.createKanbanCard(task);
                $('#kanban-' + task.status).append(card);
                counts[task.status]++;
            });
            
            // Aggiorna contatori
            $('#count-pending').text(counts.pending);
            $('#count-in-progress').text(counts.in_progress);
            $('#count-completed').text(counts.completed);
        },
        
        createKanbanCard: function(task) {
            var priorityClass = 'fp-priority-' + task.priority;
            var priorityLabel = task.priority === 'low' ? 'Bassa' : 
                               task.priority === 'normal' ? 'Normale' : 
                               task.priority === 'high' ? 'Alta' : 'Urgente';
            
            var card = $('<div class="fp-kanban-card" data-task-id="' + task.id + '" draggable="true">' +
                '<div class="fp-kanban-card-header">' +
                    '<span class="fp-priority-badge fp-priority-' + task.priority + '">' + priorityLabel + '</span>' +
                '</div>' +
                '<div class="fp-kanban-card-title">' + task.title + '</div>' +
                (task.client ? '<div class="fp-kanban-card-client">' + task.client + '</div>' : '') +
                (task.dueDate ? '<div class="fp-kanban-card-date">' + task.dueDate + '</div>' : '') +
                '<div class="fp-kanban-card-actions">' +
                    '<button class="button-link fp-edit-task" data-task-id="' + task.id + '"><span class="dashicons dashicons-edit"></span></button>' +
                    '<button class="button-link fp-delete-task" data-task-id="' + task.id + '"><span class="dashicons dashicons-trash"></span></button>' +
                '</div>' +
            '</div>');
            
            return card;
        },
        
        initKanbanDragDrop: function() {
            // Drag start
            $(document).on('dragstart', '.fp-kanban-card', function(e) {
                $(this).addClass('dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
            });
            
            // Drag end
            $(document).on('dragend', '.fp-kanban-card', function() {
                $(this).removeClass('dragging');
            });
            
            // Drag over
            $(document).on('dragover', '.fp-kanban-cards', function(e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('drag-over');
            });
            
            // Drag leave
            $(document).on('dragleave', '.fp-kanban-cards', function() {
                $(this).removeClass('drag-over');
            });
            
            // Drop
            $(document).on('drop', '.fp-kanban-cards', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                
                var $card = $('.dragging');
                var taskId = $card.data('task-id');
                var newStatus = $(this).closest('.fp-kanban-column').data('status');
                
                // Sposta la card
                $(this).append($card);
                $card.removeClass('dragging');
                
                // Aggiorna via AJAX
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
                            // Ricarica la vista
                            TaskAgenda.renderKanban();
                        }
                    }
                });
            });
        },
        
        openTemplateSelectModal: function() {
            var $btn = $(this);
            // Controlla se ci sono template
            if ($('#fp-template-select-modal').length === 0 || $('.fp-template-card').length === 0) {
                alert('Nessun template disponibile. Vai alla pagina Template per crearne uno.');
                return;
            }
            $('#fp-template-select-modal-backdrop, #fp-template-select-modal').fadeIn(200);
        },
        
        selectTemplate: function() {
            var templateId = $(this).data('template-id');
            
            // Feedback visivo
            $(this).css('transform', 'scale(0.98)');
            
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
                        // Chiudi modal
                        $('#fp-template-select-modal-backdrop, #fp-template-select-modal').fadeOut(200);
                        TaskAgenda.showNotice(response.data.message || 'Task creato con successo', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        TaskAgenda.showNotice(response.data.message || fpTaskAgenda.strings.error, 'error');
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings.error, 'error');
                },
                complete: function() {
                    $('.fp-template-card').css('transform', '');
                }
            });
        }
    };
    
    // Inizializza quando il DOM è pronto
    $(document).ready(function() {
        TaskAgenda.init();
    });
    
})(jQuery);
