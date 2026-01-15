# FP Task Agenda

Agenda semplice per gestire task e attività da fare - ideale per consulenti di digital marketing.

## 📋 Caratteristiche

- ✅ Gestione completa dei task (aggiungi, modifica, elimina)
- 📊 Dashboard con statistiche (totali, da fare, in corso, completati)
- 🔍 Filtri per stato, priorità e cliente
- 🔎 Ricerca testuale
- ⚡ Interfaccia moderna e responsive
- 🎯 Priorità configurabili (Bassa, Normale, Alta, Urgente)
- 📅 Date di scadenza con avvisi visivi
- ✅ Marca task come completati con un click
- 🔐 Ogni utente vede solo i propri task
- 👥 Gestione clienti con sincronizzazione da FP Publisher
- 📈 Ordinamento per colonne (priorità, titolo, cliente, scadenza, stato, creazione)
- ⚡ Azioni rapide (cambio stato da dropdown)
- 📦 Azioni di massa (bulk actions)

## 🚀 Installazione

1. Carica la cartella `FP-Task-Agenda` nella directory `wp-content/plugins/`
2. Attiva il plugin dalla pagina "Plugin" di WordPress
3. Il plugin creerà automaticamente le tabelle necessarie nel database

## 📖 Utilizzo

Dopo l'attivazione, troverai il menu "Task Agenda" nella sidebar di WordPress (icona lista).

### Aggiungere un Task

1. Clicca su "Aggiungi Task" nella pagina principale
2. Compila il form:
   - **Titolo** (obbligatorio)
   - **Descrizione** (opzionale)
   - **Priorità** (Bassa, Normale, Alta, Urgente)
   - **Data di scadenza** (opzionale)
   - **Cliente** (opzionale - può essere sincronizzato da FP Publisher o aggiunto manualmente)
3. Clicca su "Salva"

### Gestire i Task

- **Completare un task**: Seleziona la checkbox accanto al task oppure cambia lo stato dal dropdown
- **Modificare un task**: Clicca sull'icona matita nella riga del task
- **Eliminare un task**: Clicca sull'icona cestino nella riga del task (conferma richiesta)
- **Azioni di massa**: Seleziona più task e usa il menu "Azioni di massa" per completarli o eliminarli in blocco

### Filtrare i Task

Usa i filtri in alto per:
- **Stato**: Tutti, Da fare, In corso, Completati
- **Priorità**: Tutte, Bassa, Normale, Alta, Urgente
- **Cliente**: Tutti i clienti o un cliente specifico
- **Ricerca**: Cerca per titolo o descrizione

### Ordinare i Task

Clicca sulle intestazioni delle colonne per ordinare per:
- Priorità
- Titolo
- Cliente
- Scadenza
- Stato
- Data di creazione

### Gestire i Clienti

1. Vai alla pagina "Clienti" dal menu "Task Agenda"
2. **Sincronizza da FP Publisher**: Clicca su "Sincronizza da FP Publisher" per importare automaticamente i clienti
3. **Aggiungi manualmente**: Clicca su "Aggiungi Cliente" per aggiungere un cliente manualmente

## 🏗️ Architettura

Il plugin segue l'architettura modulare moderna degli altri plugin FP:

- **PSR-4 Autoload**: Gestione automatica delle classi via Composer
- **Namespace**: `FP\TaskAgenda\`
- **Struttura modulare**:
  - `Plugin.php` - Classe principale (singleton)
  - `Database.php` - Gestione database e CRUD
  - `Task.php` - Modello e helper
  - `Client.php` - Gestione clienti e sincronizzazione
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
│   ├── Client.php           # Modello clienti
│   ├── Admin.php            # Interfaccia admin
│   └── admin-templates/
│       ├── main-page.php    # Template pagina principale
│       └── clients-page.php # Template gestione clienti
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

- **Database**: Crea due tabelle:
  - `wp_fp_task_agenda` - Memorizza i task
  - `wp_fp_task_agenda_clients` - Memorizza i clienti
- **Sicurezza**: Tutte le operazioni sono validate e sanificate
- **Permessi**: Ogni utente può vedere e gestire solo i propri task
- **AJAX**: Operazioni asincrone per una migliore UX
- **Nonces**: Tutte le richieste AJAX sono protette con nonce
- **Sincronizzazione**: I clienti possono essere sincronizzati da FP Publisher mantenendo il riferimento tramite `source_id`

## 📄 Licenza

GPL v2 or later

## 👤 Autore

Francesco Passeri - https://www.francescopasseri.com
