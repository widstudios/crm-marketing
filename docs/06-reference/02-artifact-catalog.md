# Catalogo degli artefatti

> Tutti gli artefatti producibili dalla Factory: chi li produce, da quale template, con quale
> regola e con quale verifica.

---

## Indice

1. [Descrizione](#descrizione)
2. [Artefatti di dominio](#artefatti-di-dominio)
3. [Artefatti applicativi](#artefatti-applicativi)
4. [Artefatti di infrastruttura](#artefatti-di-infrastruttura)
5. [Artefatti di presentazione](#artefatti-di-presentazione)
6. [Artefatti di persistenza](#artefatti-di-persistenza)
7. [Artefatti di test](#artefatti-di-test)
8. [Artefatti documentali](#artefatti-documentali)
9. [Artefatti di rilascio](#artefatti-di-rilascio)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Questo catalogo è la tabella di riferimento incrociata della Factory: per ogni tipo di artefatto
indica **chi lo produce**, **da quale template**, **quale regola lo governa** e **come si verifica**.

Serve a due scopi: un agente lo consulta per sapere cosa deve produrre in una fase; un revisore
lo consulta per sapere cosa deve essere presente.

---

## Artefatti di dominio

| Artefatto | Suffisso | Agente | Template | Regola | Verifica |
|---|---|---|---|---|---|
| Entità di dominio | — | Backend | [`Model.php.stub`](../../templates/domain/Model.php.stub) | [laravel](../../rules/laravel.md) | test unitari |
| Enum di stato | — | Backend | [`Enum.php.stub`](../../templates/domain/Enum.php.stub) | [php](../../rules/php.md) | 100% copertura |
| Value object | — | Backend | [`ValueObject.php.stub`](../../templates/domain/ValueObject.php.stub) | [php](../../rules/php.md) | 100% copertura |
| Evento di dominio | `Event` | Backend | [`Event.php.stub`](../../templates/domain/Event.php.stub) | [events](../../rules/events.md) | test di emissione |
| Eccezione di dominio | `Exception` | Backend | [`Exception.php.stub`](../../templates/domain/Exception.php.stub) | [error-handling](../../rules/error-handling.md) | test di sollevamento |
| Contratto | — | Architect | [`Contract.php.stub`](../../templates/domain/Contract.php.stub) | [dependency-injection](../../rules/dependency-injection.md) | test di architettura |

---

## Artefatti applicativi

| Artefatto | Suffisso | Agente | Template | Regola | Verifica |
|---|---|---|---|---|---|
| Action | `Action` | Backend | [`Action.php.stub`](../../templates/backend/Action.php.stub) | [action-pattern](../../rules/action-pattern.md) | **100% copertura** |
| Query object | `Query` | Backend | [`Query.php.stub`](../../templates/backend/Query.php.stub) | [repository-pattern](../../rules/repository-pattern.md) | test di feature |
| DTO | `Data` | Backend | [`Data.php.stub`](../../templates/backend/Data.php.stub) | [dto](../../rules/dto.md) | test unitari |
| Service | `Service` | Backend | [`Service.php.stub`](../../templates/backend/Service.php.stub) | [service-layer](../../rules/service-layer.md) | test di feature |

---

## Artefatti di infrastruttura

| Artefatto | Suffisso | Agente | Template | Regola | Verifica |
|---|---|---|---|---|---|
| Repository | `Repository` | Backend | [`Repository.php.stub`](../../templates/backend/Repository.php.stub) | [repository-pattern](../../rules/repository-pattern.md) | test di feature |
| Job | `Job` | Backend | [`Job.php.stub`](../../templates/infrastructure/Job.php.stub) | [queue](../../rules/queue.md) | test con `Queue::fake()` |
| Listener | `Listener` | Backend | [`Listener.php.stub`](../../templates/infrastructure/Listener.php.stub) | [events](../../rules/events.md) | test di reazione |
| Notification | `Notification` | Backend | [`Notification.php.stub`](../../templates/infrastructure/Notification.php.stub) | [laravel](../../rules/laravel.md) | test con `Notification::fake()` |
| Mailable | `Mail` | Backend | [`Mail.php.stub`](../../templates/infrastructure/Mail.php.stub) | [laravel](../../rules/laravel.md) | test con `Mail::fake()` |
| Comando Artisan | `Command` | Backend | [`Command.php.stub`](../../templates/infrastructure/Command.php.stub) | [laravel](../../rules/laravel.md) | test del comando |
| Middleware | `Middleware` | Backend | [`Middleware.php.stub`](../../templates/infrastructure/Middleware.php.stub) | [middleware](../../rules/middleware.md) | test di feature |
| Service provider | `ServiceProvider` | Foundation | [`ServiceProvider.php.stub`](../../templates/infrastructure/ServiceProvider.php.stub) | [laravel](../../rules/laravel.md) | test di binding |

---

## Artefatti di presentazione

| Artefatto | Suffisso | Agente | Template | Regola | Verifica |
|---|---|---|---|---|---|
| Controller web | `Controller` | Backend | [`Controller.php.stub`](../../templates/backend/Controller.php.stub) | [laravel](../../rules/laravel.md) | test di feature |
| Controller API | `Controller` | Backend | [`ApiController.php.stub`](../../templates/api/ApiController.php.stub) | [rest-api](../../rules/rest-api.md) | test di feature |
| Form Request | `Request` | Backend | [`FormRequest.php.stub`](../../templates/api/FormRequest.php.stub) | [validation](../../rules/validation.md) | test di validazione |
| API Resource | `Resource` | Backend | [`ApiResource.php.stub`](../../templates/api/ApiResource.php.stub) | [rest-api](../../rules/rest-api.md) | test di struttura |
| Policy | `Policy` | Security | [`Policy.php.stub`](../../templates/backend/Policy.php.stub) | [policies](../../rules/policies.md) | **100% copertura** |
| Filament Resource | `Resource` | Filament | [`FilamentResource.php.stub`](../../templates/filament/FilamentResource.php.stub) | [filament](../../rules/filament.md) | test delle azioni |
| Filament Widget | `Widget` | Filament | [`FilamentWidget.php.stub`](../../templates/filament/FilamentWidget.php.stub) | [filament](../../rules/filament.md) | test di rendering |
| Pagina Filament | `Page` | Filament | [`FilamentPage.php.stub`](../../templates/filament/FilamentPage.php.stub) | [filament](../../rules/filament.md) | test di feature |
| Componente Livewire | — | Frontend | [`LivewireComponent.php.stub`](../../templates/frontend/LivewireComponent.php.stub) | [livewire](../../rules/livewire.md) | test Livewire |
| Componente Blade | — | Frontend | [`BladeComponent.php.stub`](../../templates/frontend/BladeComponent.php.stub) | [frontend](../../rules/frontend.md) | test di rendering |

---

## Artefatti di persistenza

| Artefatto | Agente | Template | Regola | Verifica |
|---|---|---|---|---|
| Migration landlord | Database | [`MigrationLandlord.php.stub`](../../templates/backend/MigrationLandlord.php.stub) | [sql](../../rules/sql.md) | migrate + rollback |
| Migration tenant | Database | [`MigrationTenant.php.stub`](../../templates/backend/MigrationTenant.php.stub) | [sql](../../rules/sql.md) | migrate + rollback su 2 tenant |
| Seeder di sistema | Database | [`Seeder.php.stub`](../../templates/backend/Seeder.php.stub) | [database](../../rules/database.md) | idempotenza |
| Factory | Database | [`Factory.php.stub`](../../templates/backend/Factory.php.stub) | [testing](../../rules/testing.md) | entità valide |

---

## Artefatti di test

| Artefatto | Agente | Template | Regola | Verifica |
|---|---|---|---|---|
| Test unitario | Testing | [`UnitTest.php.stub`](../../templates/testing/UnitTest.php.stub) | [testing](../../rules/testing.md) | verde, senza database |
| Test di feature | Testing | [`FeatureTest.php.stub`](../../templates/testing/FeatureTest.php.stub) | [testing](../../rules/testing.md) | verde |
| Test di isolamento | Testing | [`TenantIsolationTest.php.stub`](../../templates/testing/TenantIsolationTest.php.stub) | [testing](../../rules/testing.md) | **obbligatorio per entità** |
| Test di architettura | Testing | [`ArchitectureTest.php.stub`](../../templates/testing/ArchitectureTest.php.stub) | [testing](../../rules/testing.md) | verde |

---

## Artefatti documentali

| Artefatto | Agente | Regola | Verifica |
|---|---|---|---|
| README di progetto | Documentation | [documentation](../../rules/documentation.md) | sezioni obbligatorie |
| README di modulo | Documentation | [documentation](../../rules/documentation.md) | sezioni obbligatorie |
| ADR | Architect | [documentation](../../rules/documentation.md) | alternative e conseguenze |
| Documentazione API (OpenAPI) | Documentation | [rest-api](../../rules/rest-api.md) | validazione dello schema |
| Manuale utente | Documentation | [documentation](../../rules/documentation.md) | revisione |
| Changelog | Documentation | [commit](../../rules/commit.md) | linguaggio dell'utente |

---

## Artefatti di rilascio

| Artefatto | Agente | Template | Verifica |
|---|---|---|---|
| Dockerfile | Deploy | [`Dockerfile.stub`](../../templates/infrastructure/Dockerfile.stub) | build riuscita |
| Compose | Deploy | [`compose.yaml.stub`](../../templates/infrastructure/compose.yaml.stub) | avvio dei servizi |
| Pipeline CI | Deploy | [`ci.yaml.stub`](../../templates/infrastructure/ci.yaml.stub) | esecuzione verde |
| Configurazione ambiente | Deploy | [`env.example.stub`](../../templates/infrastructure/env.example.stub) | completezza |
| Runbook | Deploy | — | prova pratica |

---

## Esempi

### Esempio 1 — uso da parte di un agente

Il Backend Agent riceve il compito «implementa la registrazione di un movimento». Consulta il
catalogo e determina l'insieme minimo da produrre:

`MovementType` (enum) → `MovementData` (DTO) → `RegisterMovementAction` → `MovementRegistered`
(evento) → `InsufficientStock` (eccezione) → `StockMovementRepository` → test unitari e di feature.

### Esempio 2 — uso da parte di un revisore

Una PR aggiunge un'operazione con Action, controller e Filament, ma senza test di isolamento
tenant sulla nuova entità. Il catalogo lo segnala come **obbligatorio**: la richiesta è bloccante.

---

## Best practice

- Consultare il catalogo prima di iniziare, non dopo.
- Partire sempre dal template: contiene le parti che si dimenticano.
- Verificare la copertura richiesta: Action e Policy al 100%.
- Aggiornare il catalogo quando si introduce un nuovo tipo di artefatto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Produrre codice senza il template | Parti obbligatorie dimenticate | Partire dallo stub |
| Saltare il test di isolamento | Rischio di data leak | Obbligatorio per entità |
| Suffissi non conformi | Test di architettura rossi | Rispettare la convenzione |
| Nuovo tipo di artefatto non catalogato | Nessuno sa come verificarlo | Aggiornare il catalogo |

---

## Checklist

- [ ] Ho identificato tutti gli artefatti richiesti dal compito.
- [ ] Ho usato il template corrispondente per ciascuno.
- [ ] I suffissi rispettano le convenzioni.
- [ ] Le verifiche indicate sono state eseguite.
- [ ] Action e Policy hanno copertura al 100%.
- [ ] L'entità nuova ha il test di isolamento tenant.

---

## Riferimenti

- [Template](../../templates/README.md) · [Regole](../../rules/README.md)
- [Agenti](../../agents/README.md) · [Master workflow](../../workflows/00-master-workflow.md)
- [Struttura di progetto](../02-conventions/02-project-layout.md)
