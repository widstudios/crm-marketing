# Regole — Service Layer

> Il Service coordina più Action o incapsula logica tecnica riutilizzabile. Non è il posto dove
> mettere ciò che non si sa dove mettere.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Quando serve un Service](#quando-serve-un-service)
4. [Quando NON serve](#quando-non-serve)
5. [Esempi](#esempi)
6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

Il «Service» è la classe più abusata di ogni applicazione Laravel: diventa il contenitore di tutto
ciò che non ha una collocazione evidente, cresce senza limite naturale, e dopo un anno nessuno sa
più cosa contenga.

Con le Action che coprono le mutazioni e i Query object le letture, al Service resta un ruolo
ristretto e preciso: **coordinare**.

---

## Regole

**R1.** Un Service vive in `Application/<Context>/Services/` con suffisso `Service`.
*Verifica:* test di architettura.

**R2.** Un Service ha **un solo scopo**, dichiarato nel nome.
`StockTransferService` (trasferimento tra magazzini) è ammesso; `InventoryService` no.
*Verifica:* revisione.

**R3.** Massimo **5 metodi pubblici**. Oltre, il Service sta accumulando responsabilità.
*Verifica:* revisione, metriche.

**R4.** Un Service **coordina** Action e Query: non contiene regole di dominio.
*Motivo:* le regole duplicate nel Service divergono da quelle del dominio.
*Verifica:* revisione.

**R5.** Non riceve `Request` né usa `auth()` o `request()`.
*Verifica:* test di architettura.

**R6.** Le dipendenze sono Action, Query, Repository e contratti, iniettati nel costruttore.
*Verifica:* revisione.

**R7.** Se il coordinamento deve essere atomico, il Service apre la transazione che racchiude le
Action.
*Motivo:* le singole Action non conoscono il contesto transazionale del chiamante.
*Verifica:* revisione.

**R8.** Copertura di test ≥ 90%.
*Verifica:* pipeline.

**R9.** Un Service che non coordina nulla va eliminato: il suo contenuto appartiene a un'Action, a
una Query o al dominio.
*Verifica:* revisione.

---

## Quando serve un Service

| Situazione | Esempio |
|---|---|
| Un'operazione richiede più Action in modo atomico | trasferimento: scarico + carico |
| Un processo ha passaggi con stato persistito | approvazione multilivello |
| Logica tecnica riutilizzabile senza mutazioni | calcolo di giacenza, generazione di numerazioni |
| Orchestrazione di integrazioni | sincronizzazione con un sistema esterno |
| Composizione di più letture per un caso d'uso | preparazione dei dati di una dashboard |

---

## Quando NON serve

| Situazione | Cosa usare |
|---|---|
| Una sola mutazione | Action |
| Una lettura, anche complessa | Query object |
| Regola su una sola entità | metodo di dominio |
| Trasformazione di dati | value object o funzione pura |
| Accesso alla persistenza | Repository |
| Raggruppare metodi non correlati | niente: separarli |

Test rapido: *se rimuovessi questo Service, dove andrebbe il suo contenuto?* Se la risposta è «in
tre Action diverse», il Service non serviva.

---

## Esempi

### Esempio 1 — Service legittimo

```php
final readonly class StockTransferService
{
    public function __construct(
        private RegisterMovementAction $registerMovement,
        private StockCalculator $calculator,
    ) {}

    public function transfer(TransferData $data): TransferResult
    {
        $available = $this->calculator->available($data->batchId);

        throw_if($available < $data->quantity, InsufficientStock::forBatch($data->batchId));

        // R7: atomicità del coordinamento
        return DB::transaction(function () use ($data): TransferResult {
            $outbound = $this->registerMovement->execute($data->toOutboundMovement());
            $inbound = $this->registerMovement->execute($data->toInboundMovement());

            return new TransferResult($outbound->id, $inbound->id);
        });
    }
}
```

Coordina due Action in modo atomico: nessuna delle due, da sola, può garantirlo.

### Esempio 2 — Service tuttofare da smontare

```php
// ✗ Dodici metodi pubblici, nessuno scopo unico
final class InventoryService
{
    public function createArticle(array $data): Article { /* … */ }
    public function updateArticle(int $id, array $data): Article { /* … */ }
    public function deleteArticle(int $id): void { /* … */ }
    public function registerMovement(array $data): StockMovement { /* … */ }
    public function calculateStock(int $batchId): float { /* … */ }
    public function exportMovements(): string { /* … */ }
    public function getExpiringBatches(int $days): Collection { /* … */ }
    public function sendExpiryReport(): void { /* … */ }
    // …
}
```

Smontaggio:

| Metodo | Destinazione |
|---|---|
| `createArticle`, `updateArticle`, `deleteArticle` | tre Action |
| `registerMovement` | `RegisterMovementAction` |
| `calculateStock` | `StockCalculator` (dominio) |
| `exportMovements` | `ExportMovementsAction` + Query |
| `getExpiringBatches` | `ExpiringBatchesQuery` |
| `sendExpiryReport` | job schedulato |

Il Service sparisce, e con lui l'ambiguità su dove cercare la logica.

---

## Best practice

- Partire dalle Action: il Service si aggiunge solo quando emerge un coordinamento reale.
- Nominare il Service con l'operazione composta che realizza.
- Se il Service supera i cinque metodi, smontarlo prima che diventi intoccabile.
- Tenere la transazione nel Service quando l'atomicità riguarda più Action.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Service come contenitore generico | Nessuno sa dove sta la logica | Smontare in Action e Query |
| Nome del tipo `<Entità>Service` | Attira tutto ciò che riguarda l'entità | Nome dell'operazione composta |
| Regole di dominio nel Service | Divergono da quelle del dominio | Regole nel dominio |
| Service che riceve `Request` | Non invocabile fuori da HTTP | DTO |
| Service con un solo metodo che invoca un'Action | Indirezione inutile | Invocare l'Action |
| Transazione nell'Action invece che nel Service | Atomicità del coordinamento non garantita | Transazione nel Service |

---

## Checklist

- [ ] Il Service ha un solo scopo, dichiarato nel nome.
- [ ] Massimo 5 metodi pubblici.
- [ ] Coordina Action e Query, senza regole di dominio.
- [ ] Non riceve `Request`, non usa `auth()` né `request()`.
- [ ] Apre la transazione quando il coordinamento deve essere atomico.
- [ ] Copertura ≥ 90%.
- [ ] Se non coordina nulla, è stato eliminato.

---

## Riferimenti

- [Action Pattern](action-pattern.md) · [Repository Pattern](repository-pattern.md) · [DTO](dto.md)
- [Livello applicativo](../architecture/13-application-layer.md)
- [Template Service](../templates/backend/README.md)
