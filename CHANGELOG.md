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
- Struttura iniziale del repository della Factory (`docs/`, `architecture/`, `rules/`, `agents/`,
  `prompts/`, `foundation/`, `templates/`, `modules/`, `workflows/`, `checklists/`,
  `deployment/`, `examples/`, `governance/`, `tooling/`).
- `README.md` di piattaforma, `CLAUDE.md` per gli agenti AI, `CONTRIBUTING.md`.

### Changed
- Il prototipo CRM in PHP vanilla è stato spostato in `legacy/` senza modifiche al codice.

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
