# Legacy — prototipo CRM Marketing (congelato)

> Archivio storico. Questo codice **non** è il riferimento per nuovi sviluppi e non riceve
> manutenzione evolutiva. È conservato per tracciabilità e come termine di paragone.

---

## Indice

1. [Che cos'è](#che-cosè)
2. [Perché è stato archiviato](#perché-è-stato-archiviato)
3. [Contenuto](#contenuto)
4. [Regole d'uso](#regole-duso)
5. [Percorso di migrazione](#percorso-di-migrazione)
6. [Documentazione originale](#documentazione-originale)
7. [Riferimenti](#riferimenti)

---

## Che cos'è

Un prototipo di CRM marketing scritto in PHP vanilla (senza framework), con SQLite e un router
fatto in casa. Cinque moduli: `contacts`, `campaigns`, `email`, `analytics`, `automation`.

---

## Perché è stato archiviato

Il prototipo dimostra il concetto di modularità ma non soddisfa gli standard della Factory:

| Aspetto | Prototipo | Standard Factory |
|---|---|---|
| Multitenancy | assente | landlord + database tenant separati |
| Framework | router artigianale | Laravel 12 LTS |
| Autorizzazione | assente | Policy deny-by-default, ruoli e permessi |
| Persistenza | SQL diretto | Repository + Eloquent, migration versionate |
| Test | dichiarati, non presenti | Pest obbligatorio su ogni artefatto |
| Analisi statica | assente | PHPStan livello 8 |
| Audit / Activity log | assente | obbligatori |
| Deploy | server PHP integrato | Docker + pipeline CI/CD |

Il CRM come prodotto verrà rigenerato dalla Factory come progetto a sé, riusando i moduli del
catalogo. Vedi [`modules/README.md`](../modules/README.md).

---

## Contenuto

```
legacy/
├── core/            Kernel, Router, Request, Response, Database, ModuleManager, install
├── modules/         contacts, campaigns, email, analytics, automation
├── database/        schema.php
├── config/          config.php
├── public/          index.php
├── composer.json    dipendenze del prototipo
├── .env.example
└── QUICKSTART.md    istruzioni originali di avvio
```

---

## Regole d'uso

- **Non** aggiungere nuove funzionalità qui.
- **Non** importare questo codice nella Foundation: non rispetta gli standard.
- Sono ammesse solo modifiche di documentazione o correzioni di sicurezza, se l'istanza è ancora
  esposta da qualche parte.
- Se serve una funzionalità presente qui, la si **riprogetta** secondo la Factory: si legge il
  comportamento, non si copia il codice.

---

## Percorso di migrazione

Per rigenerare il CRM come progetto Factory:

1. Estrarre i requisiti funzionali dai moduli esistenti (fase Business Analyst).
2. Compilare il Project Brief: [`docs/06-reference/01-project-brief-template.md`](../docs/06-reference/01-project-brief-template.md).
3. Eseguire il workflow completo: [`workflows/00-master-workflow.md`](../workflows/00-master-workflow.md).
4. Mappare i moduli storici su quelli di catalogo:

   | Modulo legacy | Modulo Factory previsto |
   |---|---|
   | contacts | `contacts` (catalogo) + entità di dominio |
   | campaigns | dominio verticale CRM |
   | email | `notifications` + template di posta |
   | analytics | `reporting` |
   | automation | `workflow-automation` |

---

## Documentazione originale

<details>
<summary>README originale del prototipo</summary>

**CRM Marketing - Modular Architecture**

Piattaforma CRM marketing modulare in PHP 8.0 con SQLite, progettata per scalare facilmente.

Caratteristiche dichiarate: architettura modulare (5 moduli indipendenti), PHP 8.0, SQLite,
RESTful API, unit test, comandi CLI, webhook, supporto Docker.

Moduli: Contacts (contatti e lead), Campaigns (campagne marketing), Email (invio e tracciamento),
Analytics (reportistica), Automation (workflow).

Installazione rapida:

```bash
composer install
php core/install.php
php -S localhost:8000 -t public
```

Endpoint API:

```
GET    /api/v1/contacts
POST   /api/v1/contacts
PUT    /api/v1/contacts/:id
DELETE /api/v1/contacts/:id

GET    /api/v1/campaigns
POST   /api/v1/campaigns
PUT    /api/v1/campaigns/:id

GET    /api/v1/emails
POST   /api/v1/emails
PUT    /api/v1/emails/:id/status

GET    /api/v1/analytics/dashboard
GET    /api/v1/analytics/campaigns/:id

GET    /api/v1/automations
POST   /api/v1/automations
PUT    /api/v1/automations/:id
```

Licenza MIT. Istruzioni di avvio originali in [`QUICKSTART.md`](QUICKSTART.md).

</details>

---

## Riferimenti

- [README della Factory](../README.md)
- [Architettura di riferimento](../architecture/README.md)
- [Catalogo moduli](../modules/README.md)
