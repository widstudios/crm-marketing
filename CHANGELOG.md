# Changelog

> Registro delle modifiche alla WidStudios AI Factory.
> Formato ispirato a [Keep a Changelog](https://keepachangelog.com/it/1.1.0/);
> versionamento secondo [`governance/versioning.md`](governance/versioning.md).

---

## Indice

- [Unreleased](#unreleased)
- [0.1.0](#010---2026-07-25)
- [Come si scrive una voce](#come-si-scrive-una-voce)

---

## Unreleased

### Added
- Struttura completa del repository della Factory: tutte e quattordici le aree sono presenti e
  collegate ([`governance/roadmap.md`](governance/roadmap.md)).
- `README.md` di piattaforma, `CLAUDE.md` per gli agenti AI, `CONTRIBUTING.md`.
- Documentazione trasversale in [`docs/`](docs/README.md): introduzione, avvio, convenzioni,
  sviluppo, qualità, esercizio, riferimento.
- Architettura di riferimento e otto ADR in [`architecture/`](architecture/README.md).
- Standard vincolanti in [`rules/`](rules/README.md), con criterio di verifica per ogni regola.
- I sedici agenti con prompt completi in [`agents/`](agents/README.md), il protocollo comune e il
  contratto di [`loop crea`](prompts/loop-crea.md).
- Il pacchetto `widstudios/foundation` ([`foundation/`](foundation/README.md)): tenancy con i
  quattro bootstrapper, classi base per Action, DTO, repository e Query object, registro dei
  moduli, contratto di audit, aiuti per i test di isolamento.
- Quaranta stub in [`templates/`](templates/README.md), uno per artefatto.
- Blueprint di modulo e sette schede di catalogo in [`modules/`](modules/README.md).
- Le quattordici fasi e i tre workflow speciali in [`workflows/`](workflows/README.md).
- I diciassette quality gate in [`checklists/`](checklists/README.md).
- Immagini, pipeline, ambienti e cinque runbook in [`deployment/`](deployment/README.md).
- Sedici script di verifica e le configurazioni condivise in [`tooling/`](tooling/README.md).
- Walkthrough end-to-end e tre frammenti di codice in [`examples/`](examples/README.md).
- Pipeline della Factory e modello di pull request in `.github/`.

### Changed
- Il prototipo CRM in PHP vanilla è stato spostato in `legacy/` senza modifiche al codice.
- [`rules/documentation.md`](rules/documentation.md) R3 ammette ora tre profili di documento
  (`adr`, `agent`, `repository`) con un'ossatura propria, oltre a quella predefinita. L'elenco è
  chiuso e il profilo si deduce dalla posizione del file.
- I quattordici workflow di fase hanno «Descrizione» al posto di «Obiettivo», per allinearsi alla
  struttura obbligatoria dei documenti.

### Deprecated
- L'architettura del prototipo `legacy/` non è più il riferimento per nuovi sviluppi.

---

## 0.1.0 - 2026-07-25

Prima base della Factory. Vedi `Unreleased` fino alla chiusura della versione.

---

## Come si scrive una voce

- Una voce per modifica **osservabile** da chi usa la Factory.
- Categorie ammesse: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.
- Ogni voce cita il documento o il componente toccato, con link relativo.
- Le modifiche che richiedono azione da parte dei progetti esistenti vanno marcate
  **`[BREAKING]`** e devono linkare la guida di migrazione in `governance/migrations/`.

Esempio:

```markdown
### Changed
- **[BREAKING]** `TenantAwareRepository` ora richiede il `TenantContext` nel costruttore
  ([`foundation/src/Repositories`](foundation/src/Repositories)).
  Migrazione: [`governance/migrations/0002-tenant-context.md`](governance/migrations/0002-tenant-context.md).
```

---

## Riferimenti

- [Versionamento della Factory](governance/versioning.md)
- [Guida al contributo](CONTRIBUTING.md)
- [Roadmap](governance/roadmap.md)
