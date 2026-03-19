# Changelog

All notable changes to FP Task Agenda will be documented in this file.

## [1.1.6] - 2026-03-19
### Fixed
- Admin: `h1` screen reader come primo heading nel `.wrap` e titolo visibile in `h2` nel page header (compat notice iniettate con `.wrap h1`); `margin-top` sul `.wrap` sotto le notice native.

## [1.1.5] - 2026-03-09
### Changed
- Docs README

## [1.1.4] - 2026-03-07
### Fixed
- Icone priorità via `Task::get_priorities()` invece di CSS `::before` (non funziona su select)

## [1.1.3] - 2026-03-07
### Added
- Icone badge priorità
- Modal a 2 colonne
- Titolo dinamico nel modal
- Badge Kanban con priorità
- Spinner durante salvataggio

### Changed
- Ordinamento task: in corso → scaduti → urgenti → alta → normale

## [1.1.1] - 2026-03-02
### Fixed
- Paginazione: re-render link dopo caricamento AJAX

## [1.1.0] - 2026-03-02
### Changed
- Versione stabile, rimosso codice temporaneo cleanup

## [1.0.1] - 2026-03-02
### Fixed
- Task ricorrenti duplicati: calcolo data e protezione anti-duplicato

## [1.0.0] - 2026-02-23
### Fixed
- 7 bug corretti: filtro clienti, `recurrence_day=0`, SQL prepare, timezone, REST API

## [0.9.x] - 2026-01-27
### Added
- Integrazione FP Publisher: creazione automatica task per post mancanti
- Task ricorrenti mensili per articoli blog
- Pulsante verifica manuale post FP Publisher
- Sistema versioning database per prevenire perdita dati durante aggiornamenti

### Fixed
- Ordinamento task: scaduti sempre in testa, poi in corso, da fare, completati
- Sincronizzazione Publisher e permessi eliminazione task

## [0.8.x] - 2026-01-23
### Added
- Task scadute con ordinamento prioritario e cornice rossa

## [0.7.x] - 2026-01-16
### Added
- Vista Kanban con drag & drop
- Task ricorrenti con cron job
- Template task riutilizzabili
- Favicon SVG personalizzata per le pagine admin
- Colonna Ricorrenza nella tabella task
- Filtri avanzati collassabili con badge attivi e quick filters
- Statistiche clickabili per filtrare (inclusa stat "In scadenza")
- Priorità modificabile con click sul badge (dropdown)
- Colori di sfondo righe in base alla priorità
- Pagina Clienti con stile grafico unificato
- Pagina Template con stile grafico unificato

### Changed
- Ordinamento priorità logico: Urgente > Alta > Normale > Bassa
- Task completati sempre in fondo alla tabella

## [0.1.0] - 2026-01-15
### Added
- Release iniziale: agenda task per consulenti di digital marketing
- Tabella task con stati (da fare, in corso, completato)
- Gestione clienti
- Integrazione con FP Updater
