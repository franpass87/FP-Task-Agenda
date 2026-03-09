# FP Task Agenda

Agenda task per consulenti di digital marketing. Gestione attività con vista tabella e Kanban, task ricorrenti, template e integrazione FP Publisher.

[![Version](https://img.shields.io/badge/version-1.1.4-blue.svg)](https://github.com/franpass87/FP-Task-Agenda)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)]()

---

## Per l'utente

### Cosa fa
FP Task Agenda è un'agenda task integrata nel pannello WordPress. Permette di gestire le attività per ogni cliente con priorità, scadenze, ricorrenze e vista Kanban.

### Funzionalità principali
- **Vista tabella** con ordinamento e filtri avanzati
- **Vista Kanban** con drag & drop
- **Priorità**: Urgente, Alta, Normale, Bassa (con badge colorati)
- **Task ricorrenti**: giornalieri, settimanali, mensili
- **Template task** riutilizzabili
- **Gestione clienti** con sincronizzazione
- **Integrazione FP Publisher**: crea automaticamente task per post mancanti
- **Statistiche**: contatori per stato (da fare, in corso, completati, in scadenza)
- **Favicon personalizzata** nelle pagine admin

### Ordinamento automatico
I task vengono ordinati automaticamente: **In corso → Scaduti → Urgenti → Alta priorità → Normale**

### Requisiti
- WordPress 6.0+
- PHP 8.0+

---

## Per lo sviluppatore

### Struttura
```
FP-Task-Agenda/
├── fp-task-agenda.php          # File principale
├── src/
│   ├── Core/Plugin.php         # Bootstrap
│   ├── Models/Task.php         # Modello task + DB
│   ├── Admin/
│   │   ├── TasksPage.php       # Pagina principale task
│   │   ├── KanbanPage.php      # Vista Kanban
│   │   ├── TemplatesPage.php   # Gestione template
│   │   └── ClientsPage.php     # Gestione clienti
│   ├── REST/
│   │   └── TaskEndpoints.php   # API REST per AJAX
│   ├── Cron/
│   │   └── RecurringTasks.php  # Cron job task ricorrenti
│   └── Integrations/
│       └── FPPublisher.php     # Integrazione FP Publisher
└── vendor/
```

### Database
Il plugin crea la tabella `{prefix}fp_tasks` con versioning automatico per migrazioni sicure.

| Colonna | Tipo | Descrizione |
|---------|------|-------------|
| `id` | INT | ID task |
| `title` | VARCHAR | Titolo task |
| `status` | ENUM | `todo`, `in_progress`, `done` |
| `priority` | ENUM | `urgent`, `high`, `normal`, `low` |
| `due_date` | DATE | Data scadenza |
| `client_id` | INT | ID cliente associato |
| `recurrence_type` | ENUM | `none`, `daily`, `weekly`, `monthly` |
| `recurrence_day` | INT | Giorno ricorrenza |
| `template_id` | INT | ID template origine |

### REST Endpoints (AJAX)
| Endpoint | Metodo | Descrizione |
|----------|--------|-------------|
| `/wp-json/fp-tasks/v1/tasks` | GET/POST | Lista e creazione task |
| `/wp-json/fp-tasks/v1/tasks/{id}` | PUT/DELETE | Modifica e cancellazione |
| `/wp-json/fp-tasks/v1/quick-status` | POST | Cambio stato rapido |

### Hooks disponibili
| Hook | Tipo | Descrizione |
|------|------|-------------|
| `fp_task_before_save` | filter | Modifica dati prima del salvataggio |
| `fp_task_after_save` | action | Dopo il salvataggio di un task |
| `fp_task_statuses` | filter | Personalizza stati disponibili |
| `fp_task_priorities` | filter | Personalizza priorità disponibili |

### Integrazione FP Publisher
Quando FP Publisher rileva post con stato "Attenzione" o avanzamento 0/1, crea automaticamente task ricorrenti mensili in FP Task Agenda.

---

## Changelog
Vedi [CHANGELOG.md](CHANGELOG.md)
---

## Autore

**Francesco Passeri**
- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)
