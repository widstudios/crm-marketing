# Checklist — Fondazione

> Verifica che il progetto sia funzionante, conforme e **vuoto**: nessuna logica di dominio.

| | |
|---|---|
| **Fase** | 0 — Fondazione |
| **Agente** | [Foundation Agent](../agents/01-foundation-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Verifica che il progetto sia funzionante, conforme e **vuoto**: nessuna logica di dominio.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Struttura

- [ ] `app/` organizzata per livello: `Domain/`, `Application/`, `Infrastructure/`, `Http/`, `Filament/`, `Console/`.
- [ ] Le cartelle previste esistono anche se vuote.
- [ ] Nessuna cartella `Helpers/`, `Utils/`, `Common/`.
- [ ] `database/migrations/` divisa in `landlord/` e `tenant/`.
- [ ] `database/seeders/` divisa in `System/` e `Demo/`.
- [ ] Quattro file di rotte: `web`, `tenant`, `api`, `api-tenant`.
- [ ] `tests/` con `Unit/`, `Feature/`, `Architecture/`, `Tenant/`.

### Configurazione

- [ ] Connessioni `landlord` e `tenant` configurate.
- [ ] `config/tenancy.php` con resolver, bootstrapper e domini centrali.
- [ ] Nessun `env()` fuori da `config/`.
- [ ] `.env.example` completo, con segnaposto evidenti, senza segreti reali.

### Ambiente

- [ ] `docker compose up -d` avvia tutti i servizi senza errori.
- [ ] I servizi PHP (app, queue, scheduler) usano la **stessa immagine**.
- [ ] Mailpit e MinIO raggiungibili.

### Qualità

- [ ] `composer qa` verde.
- [ ] PHPStan livello 8, **senza baseline**.
- [ ] Test di architettura di base presenti e verdi.

### Multitenancy

- [ ] Migration landlord eseguite.
- [ ] **Due** tenant di sviluppo creati e raggiungibili.
- [ ] Pannello Super Admin risponde su `/super-admin`.
- [ ] Pannello Tenant Admin risponde sul dominio del tenant.

### Documentazione

- [ ] `CLAUDE.md` di progetto con versione della Factory dichiarata.
- [ ] `README.md` con procedura di avvio **verificata da zero**.

### Ambito

- [ ] Nessuna entità di dominio.
- [ ] Nessuna migration di dominio.
- [ ] Nessuna Filament Resource di dominio.

### Pipeline

- [ ] CI verde sul primo commit.

---

## Comandi di verifica

```bash
docker compose down -v && docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --database=landlord --seed
docker compose exec app php artisan tenant:create acme --domain=acme.localhost
docker compose exec app php artisan tenant:create globex --domain=globex.localhost
docker compose exec app composer qa
```

La sequenza parte da `down -v`: verifica che la procedura documentata funzioni su un ambiente pulito.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/foundation-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/foundation-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- <voce>: <cosa manca>. Correzione: <cosa fare>.

Richiedo rework su questi punti.
```

---

## Best practice

- Verificare durante il lavoro, non solo alla fine.
- Eseguire davvero i comandi: la verifica «a memoria» non è una verifica.
- Dichiarare le voci non applicabili con la motivazione.
- Un gate rosso è un'informazione utile, non un fallimento personale.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Un solo tenant creato | I difetti di isolamento non emergono nelle fasi successive | Due tenant |
| Baseline PHPStan generata | Debito dichiarato dal primo giorno | Nessuna baseline |
| README non provato da zero | La procedura non funziona su una macchina nuova | `down -v` e ricostruzione |
| Cartelle vuote omesse | Le fasi successive collocano male gli artefatti | Struttura completa |
| Logica di dominio anticipata | Sovrapposizione con le fasi 1-4 | Restare nell'ambito |

---

## Checklist

- [ ] Ho verificato ogni voce eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 0](../workflows/01-phase-foundation.md) · [Foundation Agent](../agents/01-foundation-agent.md)
- [Laravel](../rules/laravel.md) · [Configurazione](../rules/configuration.md)
- [Struttura di progetto](../docs/02-conventions/02-project-layout.md)
