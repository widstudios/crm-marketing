# Checklist — Database

> Verifica che lo schema sia corretto, reversibile e verificato su MySQL.

| | |
|---|---|
| **Fase** | 3 — Database |
| **Agente** | [Database Agent](../agents/04-database-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Verifica che lo schema sia corretto, reversibile e verificato su MySQL.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Collocazione

- [ ] Migration tenant in `database/migrations/tenant/`, landlord in `landlord/`.
- [ ] **Nessuna colonna `tenant_id` nelle tabelle tenant.**

### Tipi

- [ ] `decimal` per quantità e importi; **nessun `float`**.
- [ ] Nessun tipo `enum` di MySQL: `varchar` con cast a enum PHP.
- [ ] `utf8mb4`, `utf8mb4_unicode_ci`, InnoDB.
- [ ] Lunghezze `varchar` giustificate dal dominio.
- [ ] Date e momenti in UTC.

### Vincoli

- [ ] Ogni chiave esterna ha un vincolo con comportamento esplicito in cancellazione.
- [ ] Ogni chiave esterna è indicizzata.
- [ ] I vincoli di unicità sono nel database, non solo nella validazione.
- [ ] Le colonne obbligatorie sono `NOT NULL`.

### Indici

- [ ] Indice su ogni colonna usata nei filtri dichiarati nei casi d'uso.
- [ ] Indice su ogni colonna usata negli ordinamenti dichiarati.
- [ ] Ordine corretto negli indici compositi: uguaglianza, intervallo, ordinamento.
- [ ] **Ogni indice è giustificato da una query reale**, dichiarata nel rapporto.

### Migration

- [ ] `down()` implementato in ogni migration, e **provato**.
- [ ] Una modifica logica per migration.
- [ ] Nessuna trasformazione di dati.
- [ ] Nessun model applicativo usato.
- [ ] Funzionano su database vuoto e su database pieno.

### Seeder

- [ ] Divisi in `System/` e `Demo/`.
- [ ] I seeder di sistema sono **idempotenti**.
- [ ] Il seeder dei permessi assegna i nuovi permessi al ruolo amministratore.
- [ ] I seeder di prova non contengono dati di persone reali.

### Factory

- [ ] Ogni model ha una factory.
- [ ] Le factory producono entità **valide secondo il dominio**.
- [ ] Stati nominati per i casi limite dichiarati nei requisiti.

### Verifica

- [ ] Migration eseguite su **MySQL**, non solo su SQLite.
- [ ] Provisioning di un tenant **nuovo** riuscito.
- [ ] Rollback completo e riesecuzione riusciti.

---

## Comandi di verifica

```bash
php artisan migrate --database=landlord
php artisan tenants:migrate
php artisan tenant:create test-provisioning --domain=test.localhost
php artisan tenants:migrate:rollback --step=<n>
php artisan tenants:migrate
php artisan test --env=testing-mysql
php tooling/scripts/check-schema.php          # tenant_id, float, enum MySQL
```

Il provisioning di un tenant nuovo intercetta le migration che funzionano sull'aggiornamento e
falliscono da zero.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/database-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/database-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- <voce>: <cosa manca>. Correzione: <cosa fare>.

Richiedo rework su questi punti.
```

---

## Best practice

- Verificare durante il lavoro, non solo alla fine.
- Eseguire davvero i comandi indicati.
- Dichiarare le voci non applicabili con la motivazione.
- Un gate rosso è un'informazione utile, non un fallimento personale.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Colonna `tenant_id` | Fraintendimento del modello | Il tenant è il database |
| `float` per importi | Errori di arrotondamento nei totali | `decimal` |
| `down()` non provato | Rollback impossibile su N tenant | Provarlo |
| Trasformazione dati in migration | Deploy lentissimo, rollback impossibile | Comando dedicato |
| Indici preventivi | Scritture rallentate su N database | Solo per query dichiarate |
| Seeder non idempotente | Deploy fallito alla seconda esecuzione | `firstOrCreate` |
| Verifica solo su SQLite | Migration che falliscono in produzione | Esecuzione su MySQL |

---

## Checklist

- [ ] Ho verificato ogni voce eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 3](../workflows/04-phase-database.md) · [Database Agent](../agents/04-database-agent.md)
- [SQL](../rules/sql.md) · [Database](../rules/database.md)
- [Workflow del database](../docs/03-development/03-database-workflow.md)
