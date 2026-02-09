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
            // Apri modal aggiungi task (entrambi i pulsanti)
            $(document).on('click', '#fp-add-task-btn, #fp-add-task-btn-empty', this.openAddModal);
            
            // Apri modal selezione template
            $(document).on('click', '#fp-create-from-template-btn', this.openTemplateSelectModal);
            
            // Verifica post FP Publisher
            var self = this;
            $(document).on('click', '#fp-check-publisher-posts-btn', function(e) {
                self.checkPublisherPosts.call(self, e);
            });
            
            // Seleziona template dalla card
            $(document).on('click', '.fp-template-card', this.selectTemplate);
            
            // Chiudi modal
            $(document).on('click', '.fp-modal-close, .fp-modal-cancel, .fp-modal-backdrop', this.closeModal);
            
            // Gestione cambio tipo ricorrenza
            $(document).on('change', '#fp-task-recurrence', this.handleRecurrenceTypeChange);
            
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
            
            // Toggle filtri collassabili
            $(document).on('click', '#fp-filter-toggle', this.toggleFilters);
            
            // Filtri AJAX
            $(document).on('submit', '#fp-filter-form', this.onFilterFormSubmit);
            $(document).on('change', '#filter-status, #filter-priority, #filter-client', this.onFilterChange);
            $(document).on('input', '#fp-search-input', this.getDebouncedFilterChange());
            $(document).on('click', '.tablenav-pages a', this.onPaginationClick);
            
            // Toggle descrizione espansa
            $(document).on('click', '.fp-toggle-description', this.toggleDescription);
            
            // Ripristina task archiviato
            $(document).on('click', '.fp-restore-task', this.restoreTask);
            
            // Elimina definitivamente task
            $(document).on('click', '.fp-permanently-delete-task', this.permanentlyDeleteTask);
            
            // Pagina Clienti: gestione clienti
            $(document).on('click', '#fp-add-client-btn', this.openAddClientModal);
            $(document).on('click', '.fp-edit-client', this.openEditClientModal);
            $(document).on('click', '.fp-delete-client', this.deleteClient);
            $(document).on('click', '#fp-sync-clients-btn', this.syncClients);
            $(document).on('click', '#fp-client-modal .fp-modal-close, #fp-client-modal .fp-modal-cancel, #fp-client-modal-backdrop', this.closeClientModal);
            $(document).on('click', '#fp-client-modal', function(e) { e.stopPropagation(); });
            $(document).on('click', '#fp-client-modal .fp-modal-save', this.saveClient);
            
            // Previeni chiusura modal cliccando dentro
            $(document).on('click', '.fp-modal-content', function(e) {
                e.stopPropagation();
            });
        },
        
        toggleDescription: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $container = $btn.closest('.fp-task-description-container');
            var $preview = $container.find('.fp-task-description-preview');
            var $full = $container.find('.fp-task-description-full');
            var isExpanded = $btn.data('expanded') === true;
            
            if (isExpanded) {
                // Comprimi
                $preview.show();
                $full.hide();
                $btn.find('.fp-show-more').show();
                $btn.find('.fp-show-less').hide();
                $btn.data('expanded', false);
            } else {
                // Espandi
                $preview.hide();
                $full.show();
                $btn.find('.fp-show-more').hide();
                $btn.find('.fp-show-less').show();
                $btn.data('expanded', true);
            }
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
                
                // Gestione recurrence_day
                var recurrenceType = task.recurrence_type || '';
                if (recurrenceType === 'monthly') {
                    $('#fp-recurrence-day-row').show();
                    $('#fp-task-recurrence-day-monthly').show().prop('name', 'recurrence_day').val(task.recurrence_day || '');
                    $('#fp-task-recurrence-day-weekly').hide().prop('name', '');
                    $('#fp-recurrence-day-desc-monthly').show();
                    $('#fp-recurrence-day-desc-weekly').hide();
                } else if (recurrenceType === 'weekly') {
                    $('#fp-recurrence-day-row').show();
                    $('#fp-task-recurrence-day-weekly').show().prop('name', 'recurrence_day').val(task.recurrence_day || '');
                    $('#fp-task-recurrence-day-monthly').hide().prop('name', '');
                    $('#fp-recurrence-day-desc-weekly').show();
                    $('#fp-recurrence-day-desc-monthly').hide();
                } else {
                    $('#fp-recurrence-day-row').hide();
                    $('.fp-recurrence-day-select').hide().prop('name', '').val('');
                    $('.fp-recurrence-day-description').hide();
                }
                
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
            
            // Determina quale select recurrence_day usare
            var recurrenceType = $('#fp-task-recurrence').val() || '';
            var recurrenceDay = '';
            if (recurrenceType === 'monthly') {
                recurrenceDay = $('#fp-task-recurrence-day-monthly').val() || '';
            } else if (recurrenceType === 'weekly') {
                recurrenceDay = $('#fp-task-recurrence-day-weekly').val() || '';
            }
            
            var data = {
                action: isEdit ? 'fp_task_agenda_update_task' : 'fp_task_agenda_add_task',
                nonce: fpTaskAgenda.nonce,
                title: title,
                description: $('#fp-task-description').val(),
                priority: $('#fp-task-priority').val(),
                due_date: $('#fp-task-due-date').val(),
                client_id: $('#fp-task-client').val() || '',
                recurrence_type: recurrenceType,
                recurrence_day: recurrenceDay
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
            // Reset campi ricorrenza
            $('#fp-recurrence-day-row').hide();
            $('.fp-recurrence-day-select').hide().val('');
            $('.fp-recurrence-day-description').hide();
        },
        
        handleRecurrenceTypeChange: function() {
            var recurrenceType = $(this).val();
            var $dayRow = $('#fp-recurrence-day-row');
            var $monthlySelect = $('#fp-task-recurrence-day-monthly');
            var $weeklySelect = $('#fp-task-recurrence-day-weekly');
            var $monthlyDesc = $('#fp-recurrence-day-desc-monthly');
            var $weeklyDesc = $('#fp-recurrence-day-desc-weekly');
            
            // Nascondi tutti i select e descrizioni
            $('.fp-recurrence-day-select').hide().prop('name', '');
            $('.fp-recurrence-day-description').hide();
            
            if (recurrenceType === 'monthly') {
                $dayRow.show();
                $monthlySelect.show().prop('name', 'recurrence_day');
                $monthlyDesc.show();
            } else if (recurrenceType === 'weekly') {
                $dayRow.show();
                $weeklySelect.show().prop('name', 'recurrence_day');
                $weeklyDesc.show();
            } else {
                $dayRow.hide();
            }
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
                        TaskAgenda.showNotice(response.data?.message || fpTaskAgenda.strings.error || 'Errore', 'error');
                    }
                },
                error: function(xhr) {
                    var oldPriority = $select.data('current-priority');
                    $select.val(oldPriority);
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || fpTaskAgenda.strings.networkError || fpTaskAgenda.strings.error;
                    TaskAgenda.showNotice(msg, 'error');
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
            var $row = select.closest('.fp-task-row');
            
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
                        // Aggiorna le classi della riga in base al nuovo status
                        $row.removeClass('fp-task-completed fp-task-in-progress');
                        if (newStatus === 'completed') {
                            $row.addClass('fp-task-completed');
                        } else if (newStatus === 'in_progress') {
                            $row.addClass('fp-task-in-progress');
                        }
                        // Forza il reflow per aggiornare gli stili
                        $row[0].offsetHeight;
                        
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
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || fpTaskAgenda.strings.networkError || fpTaskAgenda.strings.error;
                    TaskAgenda.showNotice(msg, 'error');
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
            
            if (action === 'delete' && !confirm((fpTaskAgenda.strings.confirmBulkDelete || 'Sei sicuro di voler eliminare i task selezionati?') + ' (' + taskIds.length + ')')) {
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
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || fpTaskAgenda.strings.networkError || fpTaskAgenda.strings.error;
                    TaskAgenda.showNotice(msg, 'error');
                    $btn.prop('disabled', false).val('Applica');
                }
            });
        },
        
        getDebouncedFilterChange: function() {
            var timeout;
            return function() {
                clearTimeout(timeout);
                timeout = setTimeout(function() { TaskAgenda.onFilterChange(); }, 300);
            };
        },
        onFilterFormSubmit: function(e) {
            if ($('#fp-tasks-tbody-wrapper').length === 0) return;
            e.preventDefault();
            TaskAgenda.loadTasksAjax();
        },
        onFilterChange: function() {
            if ($('#fp-tasks-tbody-wrapper').length === 0) return;
            TaskAgenda.loadTasksAjax({ page: 1 });
        },
        onPaginationClick: function(e) {
            if ($('#fp-tasks-tbody-wrapper').length === 0) return;
            var $link = $(this);
            if (!$link.is('a')) return;
            var href = $link.attr('href');
            if (!href || href === '#') return;
            var match = href.match(/paged=(\d+)/);
            if (match) {
                e.preventDefault();
                TaskAgenda.loadTasksAjax({ page: parseInt(match[1], 10) });
            }
        },
        loadTasksAjax: function(overrides) {
            var $wrap = $('#fp-tasks-tbody-wrapper');
            if ($wrap.length === 0) return;
            var filters = {
                status: $('#filter-status').val() || 'all',
                priority: $('#filter-priority').val() || 'all',
                client_id: $('#filter-client').val() || 'all',
                search: ($('#fp-search-input').val() || '').trim(),
                page: 1,
                per_page: $wrap.data('per-page') || fpTaskAgenda.itemsPerPage || 20,
                orderby: $wrap.data('orderby') || 'created_at',
                order: $wrap.data('order') || 'DESC'
            };
            $.extend(filters, overrides || {});
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_get_tasks',
                    nonce: fpTaskAgenda.nonce,
                    status: filters.status,
                    priority: filters.priority,
                    client_id: filters.client_id,
                    search: filters.search,
                    page: filters.page,
                    per_page: filters.per_page,
                    orderby: filters.orderby,
                    order: filters.order
                },
                success: function(response) {
                    if (response.success && response.data) {
                        TaskAgenda.updateTasksUI(response.data, filters);
                    }
                }
            });
        },
        renderTaskRows: function(tasks, priorities, statuses) {
            var html = '';
            var prior = priorities || (fpTaskAgenda.priorities || {});
            var stat = statuses || (fpTaskAgenda.statuses || {});
            for (var i = 0; i < tasks.length; i++) {
                var t = tasks[i];
                var isCompl = t.status === 'completed';
                var isProg = t.status === 'in_progress';
                var isOverdue = t.is_overdue || false;
                var isDueSoon = t.is_due_soon || false;
                var rowClass = 'fp-task-row ' + (t.priority_class || 'priority-normal') + (isCompl ? ' fp-task-completed' : '') + (isProg && !isOverdue ? ' fp-task-in-progress' : '') + (isDueSoon && !isCompl && !isOverdue ? ' fp-task-due-soon' : '') + (isOverdue && !isCompl ? ' fp-task-overdue' : '');
                var priorOpts = '';
                for (var pk in prior) { priorOpts += '<option value="' + pk + '"' + (pk === t.priority ? ' selected' : '') + '>' + (prior[pk] || pk) + '</option>'; }
                var statOpts = '';
                for (var sk in stat) { statOpts += '<option value="' + sk + '"' + (sk === t.status ? ' selected' : '') + '>' + (stat[sk] || sk) + '</option>'; }
                var descPreview = (t.description && t.description.length > 100) ? t.description.substring(0, 100) + '...' : (t.description || '');
                var descFull = t.description ? t.description.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') : '';
                var hasLongDesc = t.description && t.description.length > 100;
                var dueClass = isOverdue ? 'fp-due-overdue' : (isDueSoon && !isCompl ? 'fp-due-soon' : '');
                var recurHtml = t.recurrence_label ? '<span class="fp-recurrence-badge" title="' + (t.recurrence_label + ' ' + (t.recurrence_day_detail || '')).replace(/"/g, '&quot;') + '"><span class="dashicons dashicons-update"></span><span class="fp-recurrence-text">' + t.recurrence_label + '</span>' + (t.recurrence_day_detail ? '<small class="fp-recurrence-day-info">' + t.recurrence_day_detail + '</small>' : '') + '</span>' : '<span class="fp-no-recurrence">—</span>';
                html += '<tr class="' + rowClass + '" data-task-id="' + t.id + '"><th scope="row" class="check-column"><input type="checkbox" class="fp-task-checkbox" name="task[]" value="' + t.id + '"' + (isCompl ? ' checked' : '') + ' data-task-id="' + t.id + '"></th>';
                html += '<td><select class="fp-priority-quick-change fp-priority-badge fp-priority-' + t.priority + '" data-task-id="' + t.id + '" data-current-priority="' + t.priority + '">' + priorOpts + '</select></td>';
                html += '<td>' + (t.client_name ? '<span class="fp-client-name">' + String(t.client_name).replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>' : '<span class="fp-no-client">—</span>') + '</td>';
                html += '<td><strong class="fp-task-title">' + (t.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong>';
                if (t.description) {
                    html += '<div class="fp-task-description-container"><small class="fp-task-description fp-task-description-preview">' + descPreview.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</small>';
                    if (hasLongDesc) {
                        html += '<small class="fp-task-description fp-task-description-full" style="display:none;">' + descFull + '</small><button type="button" class="button-link fp-toggle-description" data-expanded="false"><span class="fp-show-more">Mostra tutto</span><span class="fp-show-less" style="display:none;">Nascondi</span></button>';
                    }
                    html += '</div>';
                }
                html += '</td><td>' + (t.due_date ? '<span class="fp-due-date ' + dueClass + '">' + (t.due_date_formatted || '').replace(/</g, '&lt;') + '</span>' : '<span class="fp-no-due-date">—</span>') + '</td>';
                html += '<td>' + recurHtml + '</td>';
                html += '<td><select class="fp-status-quick-change" data-task-id="' + t.id + '" style="font-size:12px;padding:3px 5px;">' + statOpts + '</select></td>';
                html += '<td><small class="fp-task-date">' + (t.created_at_human || '').replace(/</g, '&lt;') + '</small></td>';
                html += '<td><div class="fp-task-actions"><button type="button" class="button-link fp-edit-task" data-task-id="' + t.id + '" title="Modifica"><span class="dashicons dashicons-edit"></span></button><button type="button" class="button-link delete fp-delete-task" data-task-id="' + t.id + '" title="Elimina"><span class="dashicons dashicons-trash"></span></button></div></td></tr>';
            }
            return html;
        },
        updateTasksUI: function(data, filters) {
            var $wrap = $('#fp-tasks-tbody-wrapper');
            var $tbody = $('#fp-tasks-list');
            if (!$tbody.length) return;
            if (data.tasks && data.tasks.length > 0) {
                $tbody.html(TaskAgenda.renderTaskRows(data.tasks, data.priorities, data.statuses));
            } else {
                $tbody.html('<tr><td colspan="8" style="text-align:center;padding:40px;"><div class="fp-empty-state-icon"><span class="dashicons dashicons-clipboard"></span></div><h3 class="fp-empty-state-title">Nessun task trovato</h3><p class="fp-empty-state-description">Prova a modificare i filtri.</p></td></tr>');
            }
            if (data.stats) {
                $('.fp-stat-card[data-stat]').each(function() {
                    var key = $(this).data('stat');
                    if (data.stats[key] !== undefined) {
                        $(this).find('.fp-stat-value').text(data.stats[key]);
                    }
                });
            }
            if (data.total !== undefined) $('.fp-tasks-count').text(data.total + (data.total === 1 ? ' task' : ' task'));
            var baseUrl = (window.location.pathname || '/wp-admin/admin.php') + '?page=fp-task-agenda';
            var params = { status: filters.status, priority: filters.priority, client_id: filters.client_id, s: filters.search, paged: filters.page };
            if (params.status === 'all') delete params.status;
            if (params.priority === 'all') delete params.priority;
            if (params.client_id === 'all') delete params.client_id;
            if (!params.s) delete params.s;
            if (params.paged === 1) delete params.paged;
            var qs = $.param(params);
            if (qs) baseUrl += '&' + qs;
            if (typeof history.replaceState === 'function') history.replaceState({}, '', baseUrl);
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            // Crea il container se non esiste
            if ($('.fp-toast-container').length === 0) {
                $('body').append('<div class="fp-toast-container"></div>');
            }
            
            // Mappa tipo a classe e icona
            var typeClass = 'fp-toast-' + (type === 'info' ? 'info' : type);
            var iconClass = type === 'error' ? 'dashicons-warning' : 
                           type === 'info' ? 'dashicons-info' : 
                           'dashicons-yes-alt';
            
            var toast = $(
                '<div class="fp-toast ' + typeClass + '">' +
                    '<div class="fp-toast-icon"><span class="dashicons ' + iconClass + '"></span></div>' +
                    '<div class="fp-toast-content"><p class="fp-toast-message">' + message + '</p></div>' +
                    '<button type="button" class="fp-toast-close"><span class="dashicons dashicons-no-alt"></span></button>' +
                '</div>'
            );
            
            $('.fp-toast-container').append(toast);
            
            // Gestione chiusura
            toast.find('.fp-toast-close').on('click', function() {
                toast.addClass('fp-toast-hiding');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            });
            
            // Auto-rimuovi dopo 4 secondi
            setTimeout(function() {
                if (toast.length && !toast.hasClass('fp-toast-hiding')) {
                    toast.addClass('fp-toast-hiding');
                    setTimeout(function() {
                        toast.remove();
                    }, 300);
                }
            }, 4000);
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
            // Reset colonne
            $('#kanban-pending, #kanban-in-progress, #kanban-completed').empty();
            $('#count-pending, #count-in-progress, #count-completed').text('0');
            
            // Carica tutti i task via AJAX (ignorando filtri tabella)
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_get_tasks',
                    nonce: fpTaskAgenda.nonce,
                    status: 'all',
                    priority: 'all',
                    client_id: 'all',
                    search: '',
                    page: 1,
                    per_page: fpTaskAgenda.kanbanPerPage || 500
                },
                success: function(response) {
                    if (response.success && response.data.tasks) {
                        var tasks = response.data.tasks;
                        var counts = {pending: 0, in_progress: 0, completed: 0};
                        
                        tasks.forEach(function(task) {
                            // Normalizza lo status (assicurati che sia uno dei valori validi)
                            var status = task.status || 'pending';
                            if (status !== 'pending' && status !== 'in_progress' && status !== 'completed') {
                                // Se lo status non è valido, default a pending
                                status = 'pending';
                            }
                            
                            var card = TaskAgenda.createKanbanCard({
                                id: task.id,
                                title: task.title,
                                priority: task.priority || 'normal',
                                status: status,
                                dueDate: task.due_date ? TaskAgenda.formatDueDate(task.due_date) : '',
                                client: task.client_name || ''
                            });
                            
                            // Aggiungi la card alla colonna corretta
                            var statusKey = status.replace(/_/g, '-');
                            var $column = $('#kanban-' + statusKey);
                            
                            if ($column.length > 0) {
                                $column.append(card);
                                // Incrementa il contatore solo se la chiave esiste
                                if (counts.hasOwnProperty(status)) {
                                    counts[status]++;
                                }
                            }
                        });
                        
                        // Aggiorna contatori
                        $('#count-pending').text(counts.pending || 0);
                        $('#count-in-progress').text(counts.in_progress || 0);
                        $('#count-completed').text(counts.completed || 0);
                    }
                },
                error: function() {
                    // Fallback: usa le righe della tabella se AJAX fallisce
                    var tasks = [];
                    $('.fp-task-row').each(function() {
                        var $row = $(this);
                        var statusValue = $row.find('.fp-status-quick-change').val();
                        if (!statusValue) {
                            // Prova a leggere dalla classe della riga
                            if ($row.hasClass('fp-task-completed')) {
                                statusValue = 'completed';
                            } else if ($row.hasClass('fp-task-in-progress')) {
                                statusValue = 'in_progress';
                            } else {
                                statusValue = 'pending';
                            }
                        }
                        var task = {
                            id: $row.data('task-id'),
                            title: $row.find('.fp-task-title').text(),
                            priority: $row.hasClass('priority-low') ? 'low' : 
                                      $row.hasClass('priority-normal') ? 'normal' :
                                      $row.hasClass('priority-high') ? 'high' : 'urgent',
                            status: statusValue,
                            dueDate: $row.find('.fp-due-date').text() || '',
                            client: $row.find('td:nth-child(3)').text().trim()
                        };
                        tasks.push(task);
                    });
                    
                    var counts = {pending: 0, in_progress: 0, completed: 0};
                    tasks.forEach(function(task) {
                        var card = TaskAgenda.createKanbanCard(task);
                        var statusKey = task.status.replace(/_/g, '-');
                        $('#kanban-' + statusKey).append(card);
                        counts[task.status]++;
                    });
                    
                    $('#count-pending').text(counts.pending || 0);
                    $('#count-in-progress').text(counts.in_progress || 0);
                    $('#count-completed').text(counts.completed || 0);
                }
            });
        },
        
        formatDueDate: function(dateString) {
            if (!dateString) return '';
            // Formatta la data in modo leggibile
            var date = new Date(dateString);
            var today = new Date();
            var diffTime = date - today;
            var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) {
                return 'Scaduto ' + Math.abs(diffDays) + ' giorni fa';
            } else if (diffDays === 0) {
                return 'Scade oggi';
            } else if (diffDays === 1) {
                return 'Scade domani';
            } else {
                return 'Scade tra ' + diffDays + ' giorni';
            }
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
            // Controlla se ci sono template
            if ($('#fp-template-select-modal').length === 0 || $('.fp-template-card').length === 0) {
                var confirmRedirect = confirm('Nessun template disponibile. Vuoi andare alla pagina Template per crearne uno?');
                if (confirmRedirect) {
                    window.location.href = fpTaskAgenda.templatesPageUrl || (window.location.href.split('?')[0] + '?page=fp-task-agenda-templates');
                }
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
        },
        
        toggleFilters: function() {
            var $form = $('#fp-filter-form');
            var $toggle = $('#fp-filter-toggle .dashicons');
            
            if ($form.is(':visible')) {
                $form.slideUp(200);
                $toggle.removeClass('dashicons-arrow-up-alt').addClass('dashicons-arrow-down-alt');
            } else {
                $form.slideDown(200);
                $toggle.removeClass('dashicons-arrow-down-alt').addClass('dashicons-arrow-up-alt');
            }
        },
        
        restoreTask: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var taskId = $btn.data('task-id');
            var $row = $btn.closest('.fp-archived-task-row');
            
            $btn.prop('disabled', true).text('Ripristino...');
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_restore_task',
                    nonce: fpTaskAgenda.nonce,
                    id: taskId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            // Aggiorna contatore se presente
                            var $counter = $('.fp-archived-count');
                            if ($counter.length) {
                                var count = parseInt($counter.text()) - 1;
                                $counter.text(count);
                            }
                        });
                        TaskAgenda.showNotice(response.data.message || 'Task ripristinato', 'success');
                    } else {
                        TaskAgenda.showNotice(response.data.message || 'Errore durante il ripristino', 'error');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo" style="vertical-align: middle;"></span> Ripristina');
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings?.networkError || 'Errore di connessione', 'error');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo" style="vertical-align: middle;"></span> Ripristina');
                }
            });
        },
        
        permanentlyDeleteTask: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var taskId = $btn.data('task-id');
            var $row = $btn.closest('.fp-archived-task-row');
            
            if (!confirm(fpTaskAgenda.strings.confirmPermanentlyDelete || 'Sei sicuro di voler eliminare definitivamente questo task? Questa azione è irreversibile.')) {
                return;
            }
            
            $btn.prop('disabled', true);
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_permanently_delete_task',
                    nonce: fpTaskAgenda.nonce,
                    id: taskId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        TaskAgenda.showNotice(response.data.message || 'Task eliminato definitivamente', 'success');
                    } else {
                        TaskAgenda.showNotice(response.data.message || 'Errore durante l\'eliminazione', 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    TaskAgenda.showNotice(fpTaskAgenda.strings?.networkError || 'Errore di connessione', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },
        
        checkPublisherPosts: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var originalText = $btn.html();
            
            // Disabilita il pulsante e mostra loading
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: fp-spin 1s linear infinite;"></span> Verifica in corso...');
            
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_task_agenda_check_publisher_posts',
                    nonce: fpTaskAgenda.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = response.data.message || 'Verifica completata';
                        var tasksCreated = response.data.tasks_created || 0;
                        
                        if (tasksCreated > 0) {
                            message += ' - ' + tasksCreated + ' task create';
                            TaskAgenda.showNotice(message, 'success');
                            // Ricarica la pagina dopo 1 secondo per mostrare le nuove task
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            message += ' - Nessuna nuova task creata';
                            TaskAgenda.showNotice(message, 'info');
                        }
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Errore durante la verifica';
                        TaskAgenda.showNotice(errorMsg, 'error');
                    }
                    
                    // Ripristina il pulsante
                    $btn.prop('disabled', false).html(originalText);
                },
                error: function(xhr, status, error) {
                    TaskAgenda.showNotice((fpTaskAgenda.strings?.networkError || 'Errore di connessione durante la verifica') + (error ? ': ' + error : ''), 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        /* === Pagina Clienti === */
        openAddClientModal: function() {
            if ($('#fp-client-modal').length === 0) return;
            $('#fp-client-modal-title').text(fpTaskAgenda.strings.addClient || 'Aggiungi Cliente');
            $('#fp-client-form')[0].reset();
            $('#fp-client-id').val('');
            $('#fp-client-modal-backdrop, #fp-client-modal').fadeIn(200);
        },
        openEditClientModal: function(e) {
            e.preventDefault();
            if ($('#fp-client-modal').length === 0) return;
            var clientId = $(this).data('client-id');
            var $row = $(this).closest('.fp-client-row');
            var name = $row.find('.fp-client-name').text();
            $('#fp-client-modal-title').text(fpTaskAgenda.strings.editClient || 'Modifica Cliente');
            $('#fp-client-id').val(clientId);
            $('#fp-client-name').val(name);
            $('#fp-client-modal-backdrop, #fp-client-modal').fadeIn(200);
        },
        deleteClient: function(e) {
            e.preventDefault();
            if ($('#fp-client-modal').length === 0) return;
            if (!confirm(fpTaskAgenda.strings.confirmDeleteClient || 'Sei sicuro di voler eliminare questo cliente?')) return;
            var clientId = $(this).data('client-id');
            var $row = $(this).closest('.fp-client-row');
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: { action: 'fp_task_agenda_delete_client', nonce: fpTaskAgenda.nonce, id: clientId },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() { $(this).remove(); });
                        TaskAgenda.showNotice(response.data.message || (fpTaskAgenda.strings.clientDeleted || 'Cliente eliminato'), 'success');
                    } else {
                        TaskAgenda.showNotice(response.data?.message || fpTaskAgenda.strings.error, 'error');
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || fpTaskAgenda.strings.networkError || fpTaskAgenda.strings.error;
                    TaskAgenda.showNotice(msg, 'error');
                }
            });
        },
        syncClients: function() {
            if ($('#fp-sync-clients-btn').length === 0) return;
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s linear infinite;"></span> ' + (fpTaskAgenda.strings.syncing || 'Sincronizzazione in corso...'));
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: { action: 'fp_task_agenda_sync_clients', nonce: fpTaskAgenda.nonce },
                success: function(response) {
                    if (response.success) {
                        var msg = response.data.message || (fpTaskAgenda.strings.syncComplete || 'Sincronizzazione completata');
                        if (response.data.synced === 0 && response.data.updated === 0 && response.data.skipped === 0) {
                            TaskAgenda.showNotice(fpTaskAgenda.strings.noClientsInPublisher || 'Nessun cliente trovato in FP Publisher', 'info');
                        } else {
                            TaskAgenda.showNotice(msg, 'success');
                        }
                        if (response.data.synced > 0 || response.data.updated > 0) {
                            window.location.reload();
                        }
                    } else {
                        TaskAgenda.showNotice(response.data?.message || fpTaskAgenda.strings.error, 'error');
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || fpTaskAgenda.strings.networkError || fpTaskAgenda.strings.error;
                    TaskAgenda.showNotice(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        closeClientModal: function() {
            $('#fp-client-modal-backdrop, #fp-client-modal').fadeOut(200);
        },
        saveClient: function() {
            if ($('#fp-client-modal').length === 0) return;
            var clientId = $('#fp-client-id').val();
            var name = $('#fp-client-name').val().trim();
            if (!name) {
                TaskAgenda.showNotice(fpTaskAgenda.strings.nameRequired || 'Il nome è obbligatorio', 'error');
                $('#fp-client-name').focus();
                return;
            }
            var action = clientId ? 'fp_task_agenda_update_client' : 'fp_task_agenda_add_client';
            var $btn = $(this);
            $btn.prop('disabled', true).text(fpTaskAgenda.strings.saving || 'Salvataggio...');
            $.ajax({
                url: fpTaskAgenda.ajaxUrl,
                type: 'POST',
                data: { action: action, nonce: fpTaskAgenda.nonce, id: clientId, name: name },
                success: function(response) {
                    if (response.success) {
                        TaskAgenda.showNotice(response.data.message || (fpTaskAgenda.strings.success || 'Operazione completata'), 'success');
                        window.location.reload();
                    } else {
                        TaskAgenda.showNotice(response.data?.message || fpTaskAgenda.strings.error, 'error');
                        $btn.prop('disabled', false).text(fpTaskAgenda.strings.save || 'Salva');
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || fpTaskAgenda.strings.networkError || fpTaskAgenda.strings.error;
                    TaskAgenda.showNotice(msg, 'error');
                    $btn.prop('disabled', false).text(fpTaskAgenda.strings.save || 'Salva');
                }
            });
        }
    };
    
    window.TaskAgenda = TaskAgenda;
    
    $(document).ready(function() {
        TaskAgenda.init();
    });
    
})(jQuery);
