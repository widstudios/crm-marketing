# Templates — Backend

> Gli stub del livello applicativo e dell'infrastruttura dati: Action, DTO, Query, Repository,
> Service, Policy, Controller, migration, seeder, factory.

---

## Indice

1. [Descrizione](#descrizione) 2. [Gli stub](#gli-stub) 3. [Dove vanno i file](#dove-vanno-i-file)
4. [Il percorso di una scrittura](#il-percorso-di-una-scrittura) 5. [Esempi](#esempi)
6. [Best practice](#best-practice) 7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

È il livello che **orchestra**: prende una richiesta, la traduce in un'operazione del dominio, la
esegue in transazione e ne propaga gli effetti.

Il rischio specifico di questo livello è l'accumulo. Un'Action che cresce, un repository che si
riempie di metodi, un controller che «tanto è solo un `if`»: sono tutte forme dello stesso
fenomeno, cioè logica che si deposita dove è più comodo scriverla invece che dove appartiene. Gli
stub di questa cartella sono costruiti per rendere quel deposito scomodo.

---

## Gli stub

| Stub | Quando | Vincolo che conta |
|---|---|---|
| [Action.php.stub](Action.php.stub) | ogni mutazione | `final`, un solo `execute()`, riceve un DTO |
| [Data.php.stub](Data.php.stub) | input di ogni Action | `final readonly`, costruttori nominati |
| [Query.php.stub](Query.php.stub) | ogni elenco o esportazione | colonne esplicite, ordinamenti in lista bianca |
| [Repository.php.stub](Repository.php.stub) | accesso alle entità | un metodo per caso d'uso |
| [Service.php.stub](Service.php.stub) | capacità trasversale | non è un'Action, non è una cartella |
| [Policy.php.stub](Policy.php.stub) | ogni model | deny by default, test di rifiuto |
| [Controller.php.stub](Controller.php.stub) | punto di ingresso web | autorizza, traduce, invoca, risponde |
| [MigrationTenant.php.stub](MigrationTenant.php.stub) | schema di dominio | `down()`, nessun dato, nessun model |
| [MigrationLandlord.php.stub](MigrationLandlord.php.stub) | schema di piattaforma | connessione esplicita |
| [Seeder.php.stub](Seeder.php.stub) | dati obbligatori | idempotente, permessi al ruolo admin |
| [Factory.php.stub](Factory.php.stub) | dati di prova | valida secondo il dominio, stati nominati |

---

## Dove vanno i file

```
app/
├── Application/{{ Module }}/
│   ├── Actions/{{ Class }}Action.php        Action.php.stub
│   ├── Data/{{ Class }}Data.php             Data.php.stub
│   └── Queries/{{ Class }}Query.php         Query.php.stub
├── Infrastructure/{{ Module }}/
│   └── {{ Entity }}Repository.php           Repository.php.stub
├── Domain/{{ Module }}/Services/            Service.php.stub
├── Policies/{{ Entity }}Policy.php          Policy.php.stub
└── Http/Controllers/{{ Entity }}Controller.php   Controller.php.stub

database/
├── migrations/tenant/                       MigrationTenant.php.stub
├── migrations/landlord/                     MigrationLandlord.php.stub
├── seeders/System/                          Seeder.php.stub
└── factories/                               Factory.php.stub
```

---

## Il percorso di una scrittura

```
Richiesta
   │
   ▼
Controller ──── authorize()            l'autorizzazione è qui, non nell'Action
   │
   ├── Form Request                    formato, presenza, tipo
   │
   ▼
Data::fromRequest()                    traduzione in tipi di dominio
   │
   ▼
Action::execute()
   │
   ├── precondizioni di dominio        esiste, è nello stato giusto
   │
   ├── transaction()
   │      ├── findForUpdateOrFail()    lock, dove c'è contesa
   │      ├── $entity->…()             le regole sono qui
   │      ├── repository->save()
   │      └── audit()
   │
   └── afterCommit()                   eventi e job
```

Ogni freccia che salta un passaggio è un difetto: un controller che invoca il repository, un'Action
che riceve una `Request`, un evento emesso dentro la transazione.

---

## Esempi

### Lo stesso caso d'uso da tre ingressi diversi

```php
// Controller
$action->execute(RegisterMovementData::fromRequest($request));

// Importazione
$action->execute(RegisterMovementData::fromImportRow($row));

// Comando di manutenzione
$action->execute(new RegisterMovementData(
    batchId: $batchId,
    type: MovementType::Outbound,
    quantity: new Quantity('1.000'),
    reason: 'Rettifica inventariale',
));
```

Se l'Action avesse ricevuto una `Request`, gli ultimi due sarebbero stati impossibili senza
costruirne una finta — che è il segnale inequivocabile che il progetto ha preso la strada sbagliata.

---

## Best practice

- Un'Action per operazione, con il nome dell'operazione nel linguaggio del committente.
- Lock pessimistico ogni volta che si legge, si decide e si scrive.
- Eventi e job sempre dopo il commit.
- Colonne esplicite nei Query object, lista bianca sugli ordinamenti.
- Migration di sola espansione quando possibile: rendono il ritorno una questione di solo codice.
- Stati nominati nelle factory per ogni caso limite del dominio.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Action che riceve `Request` | Inutilizzabile da CLI, coda, importazioni | DTO |
| Autorizzazione dentro l'Action | I processi di sistema non possono eseguirla | Nel controller |
| Regole di dominio nell'Action | Duplicate appena l'entità serve altrove | Nell'entità |
| Evento dentro la transazione | Il listener non trova i dati | `afterCommit()` |
| Nessun lock su risorsa contesa | Giacenze negative sotto concorrenza | `findForUpdateOrFail()` |
| Migration senza `down()` | Rollback impossibile su N tenant | Sempre reversibile |
| Trasformazione di dati in migration | Deploy lentissimo, rollback impossibile | Comando Artisan |
| Seeder non idempotente | Deploy fallito alla seconda esecuzione | `firstOrCreate` |
| Permessi non assegnati al ruolo admin | Funzionalità invisibile a tutti | `syncPermissions` |
| Factory con dati non validi | Test che verificano l'impossibile | Conforme al dominio |

---

## Checklist

- [ ] Ogni mutazione ha la sua Action, `final`, con un solo `execute()`.
- [ ] Ogni Action riceve un DTO; nessuna autorizzazione al suo interno.
- [ ] Le proiezioni sono Query object con colonne esplicite.
- [ ] Ogni model ha una Policy deny by default, con test di rifiuto.
- [ ] Le migration implementano `down()` e non trasformano dati.
- [ ] I seeder di sistema sono idempotenti e assegnano i permessi al ruolo admin.
- [ ] Ogni model ha una factory con stati per i casi limite.

---

## Riferimenti

- [Livello applicativo](../../architecture/13-application-layer.md) · [Infrastruttura](../../architecture/14-infrastructure-layer.md)
- [Action Pattern](../../rules/action-pattern.md) · [DTO](../../rules/dto.md) · [Repository Pattern](../../rules/repository-pattern.md)
- [Policies](../../rules/policies.md) · [SQL](../../rules/sql.md) · [Migration](../../rules/database.md)
- [Foundation — Action e DTO](../../foundation/docs/03-action-e-dto.md)
