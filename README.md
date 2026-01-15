# FP Task Agenda

Agenda semplice per gestire task e attività da fare - ideale per consulenti di digital marketing.

## 📋 Caratteristiche

- ✅ Gestione completa dei task (aggiungi, modifica, elimina)
- 📊 Dashboard con statistiche (totali, da fare, in corso, completati)
- 🔍 Filtri per stato e priorità
- 🔎 Ricerca testuale
- ⚡ Interfaccia moderna e responsive
- 🎯 Priorità configurabili (Bassa, Normale, Alta, Urgente)
- 📅 Date di scadenza con avvisi visivi
- ✅ Marca task come completati con un click
- 🔐 Ogni utente vede solo i propri task

## 🚀 Installazione

1. Carica la cartella `FP-Task-Agenda` nella directory `wp-content/plugins/`
2. Attiva il plugin dalla pagina "Plugin" di WordPress
3. Il plugin creerà automaticamente la tabella necessaria nel database

## 📖 Utilizzo

Dopo l'attivazione, troverai il menu "Task Agenda" nella sidebar di WordPress (icona lista).

### Aggiungere un Task

1. Clicca su "Aggiungi Task" nella pagina principale
2. Compila il form:
   - **Titolo** (obbligatorio)
   - **Descrizione** (opzionale)
   - **Priorità** (Bassa, Normale, Alta, Urgente)
   - **Data di scadenza** (opzionale)
3. Clicca su "Salva"

### Gestire i Task

- **Completare un task**: Seleziona la checkbox accanto al task
- **Modificare un task**: Clicca su "Modifica" nella riga del task
- **Eliminare un task**: Clicca su "Elimina" nella riga del task (conferma richiesta)

### Filtrare i Task

Usa i filtri in alto per:
- **Stato**: Tutti, Da fare, In corso, Completati
- **Priorità**: Tutte, Bassa, Normale, Alta, Urgente
- **Ricerca**: Cerca per titolo o descrizione

## 🏗️ Architettura

Il plugin segue l'architettura modulare moderna degli altri plugin FP:

- **PSR-4 Autoload**: Gestione automatica delle classi via Composer
- **Namespace**: `FP\TaskAgenda\`
- **Struttura modulare**:
  - `Plugin.php` - Classe principale (singleton)
  - `Database.php` - Gestione database e CRUD
  - `Task.php` - Modello e helper
  - `Admin.php` - Interfaccia amministrazione
  - `admin-templates/` - Template PHP per la UI
  - `assets/` - CSS e JavaScript

## 🔧 Sviluppo

### Struttura File

```
FP-Task-Agenda/
├── fp-task-agenda.php       # Main file del plugin
├── composer.json             # Configurazione Composer/PSR-4
├── vendor/                   # Autoloader Composer (generato)
├── includes/
│   ├── Plugin.php           # Classe principale
│   ├── Database.php         # Gestione database
│   ├── Task.php             # Modello task
│   ├── Admin.php            # Interfaccia admin
│   └── admin-templates/
│       └── main-page.php    # Template pagina principale
└── assets/
    ├── css/
    │   └── admin.css        # Stili admin
    └── js/
        └── admin.js         # JavaScript admin
```

### Generare Autoload

Dopo modifiche alle classi, rigenera l'autoloader:

```bash
composer dump-autoload --optimize
```

## 📝 Note Tecniche

- **Database**: Crea una tabella `wp_fp_task_agenda` per memorizzare i task
- **Sicurezza**: Tutte le operazioni sono validate e sanificate
- **Permessi**: Ogni utente può vedere e gestire solo i propri task
- **AJAX**: Operazioni asincrone per una migliore UX
- **Nonces**: Tutte le richieste AJAX sono protette con nonce

## 📄 Licenza

GPL v2 or later

## 👤 Autore

Francesco Passeri - https://www.francescopasseri.com
