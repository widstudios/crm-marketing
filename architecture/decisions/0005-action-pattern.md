# ADR-0005 — Action Pattern per le mutazioni

> Ogni modifica di stato passa da una classe Action con un solo metodo pubblico, che riceve un DTO.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Architecture Owner |
| **Impatto** | livello applicativo di tutti i progetti |
| **Reversibilità** | reversibile con costo medio |

---

## Indice

1. [Contesto](#contesto)
2. [Decisione](#decisione)
3. [Alternative valutate](#alternative-valutate)
4. [Conseguenze](#conseguenze)
5. [Soglia di rivalutazione](#soglia-di-rivalutazione)
6. [Verifica](#verifica)
7. [Riferimenti](#riferimenti)

---

## Contesto

Nei progetti precedenti la stessa operazione veniva invocata da più punti — interfaccia web, API,
comando di importazione, azione amministrativa — e la logica veniva duplicata o parzialmente
riutilizzata. Tre conseguenze osservate:

1. **Regole applicate in modo diverso.** La verifica «non si preleva da un lotto scaduto» esisteva
   nel form web ma non nell'importazione massiva.
2. **Nessun inventario delle operazioni.** Per sapere cosa il sistema poteva modificare, bisognava
   leggere tutti i controller, i comandi e gli observer.
3. **Test difficili.** Verificare un'operazione richiedeva di simulare una richiesta HTTP.

Serviva un punto unico per ogni operazione, invocabile da qualunque contesto.

---

## Decisione

Ogni mutazione di stato è una classe **Action**:

```php
final readonly class RegisterMovementAction
{
    public function __construct(private BatchRepository $batches) {}

    public function execute(MovementData $data): StockMovement { /* … */ }
}
```

Vincoli:

| Vincolo | Motivo |
|---|---|
| `final` | l'ereditarietà rende il comportamento non deducibile dal nome |
| Un solo metodo pubblico, `execute()` | un'operazione per classe |
| Riceve un **DTO**, mai una `Request` | invocabile da HTTP, CLI, coda, Filament |
| Nome imperativo: `<Verbo><Oggetto>Action` | dice cosa fa |
| Precondizioni verificate prima della transazione | transazioni brevi |
| Eventi emessi dopo il commit | i listener trovano i dati |
| Copertura di test al **100%** | è la superficie funzionale del sistema |

Le letture complesse **non** sono Action: sono Query object. Le Action mutano, le Query leggono.

---

## Alternative valutate

### Alternativa A — Service con più metodi

Un `SupplierService` con `create()`, `update()`, `archive()`, `suspend()`.

**Scartata** per tre ragioni osservate nella pratica: le dipendenze del costruttore diventano
l'unione di quelle di tutti i metodi (iniezione di ciò che non serve); la classe cresce senza limite
naturale; il nome non dice cosa contiene, quindi nessuno sa dove cercare.

### Alternativa B — logica nei model

Metodi di dominio direttamente sui model Eloquent (`$supplier->archive()`).

**Parzialmente adottata**: i metodi che esprimono regole del dominio *stanno* sui model o sulle
entità. Ciò che non vi appartiene è l'**orchestrazione**: transazioni, verifica di precondizioni che
coinvolgono altri aggregati, emissione di eventi, coordinamento di repository.

`$supplier->canBeArchived()` è dominio. La transazione che archivia, registra l'audit ed emette
l'evento è un'Action.

### Alternativa C — command bus

Comandi e gestori, con dispatcher centrale.

**Scartata** per il livello di indirezione aggiuntivo: il dispatcher rende meno tracciabile il
percorso dell'esecuzione, e i benefici (middleware sui comandi, accodamento trasparente) sono
ottenibili con gli strumenti del framework quando servono.

### Confronto

| Asse | Action (adottata) | Service | Command bus |
|---|---|---|---|
| Inventario delle operazioni | **esplicito** | nascosto | esplicito |
| Dipendenze | **minime per operazione** | unione di tutte | minime |
| Riuso tra punti di ingresso | **totale** | totale | totale |
| Tracciabilità dell'esecuzione | **diretta** | diretta | indiretta |
| Numero di classi | **alto** | basso | alto |
| Curva di apprendimento | bassa | bassa | media |

---

## Conseguenze

### Positive

- L'elenco delle Action è l'inventario delle operazioni del sistema.
- Ogni operazione è invocabile da qualunque punto di ingresso, con lo stesso comportamento.
- Le dipendenze sono minime e visibili nel costruttore.
- I test sono diretti: si costruisce un DTO e si invoca `execute()`.
- Le regole non possono essere aggirate da un percorso alternativo.
- Il nome della classe descrive esattamente l'operazione.

### Negative (accettate consapevolmente)

- **Molte classi piccole.** Un'entità con cinque operazioni produce cinque Action.
- **Percepito come eccessivo sulle operazioni banali.** Anche `MarkNotificationAsReadAction` è una
  classe: la coerenza vale più della brevità.
- **Serve un DTO per ogni Action.** Codice aggiuntivo di trasporto.
- **Coordinamento di più Action richiede un Service.** Un livello in più nei casi composti.

### Impatto operativo

| Area | Effetto |
|---|---|
| Sviluppo | un'Action per operazione, dal template |
| Test | copertura al 100% richiesta |
| Revisione | verifica di `final`, metodo unico, DTO in ingresso |
| Documentazione | l'elenco delle Action documenta le capacità del sistema |

---

## Soglia di rivalutazione

1. Se il numero di Action in un progetto supera le **300** e diventa difficile orientarsi: valutare
   un raggruppamento per contesto, non l'abbandono del pattern.
2. Se le Action vengono sistematicamente aggirate (logica nei controller nonostante i test di
   architettura): il pattern è troppo oneroso o mal insegnato.
3. Se emergesse un'esigenza reale di middleware trasversali su tutte le mutazioni: valutare un
   command bus.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| Le classi in `Actions/` sono `final` | Pest Arch | sì |
| Hanno un solo metodo pubblico `execute()` | Pest Arch | sì |
| Non ricevono `Request` | Pest Arch | sì |
| Copertura al 100% | pipeline | sì |
| Nessuna mutazione fuori dalle Action | revisione | no |
| Eventi emessi dopo il commit | revisione | no |

---

## Riferimenti

- [Livello applicativo](../13-application-layer.md)
- [Regole Action Pattern](../../rules/action-pattern.md) · [DTO](../../rules/dto.md) · [Service Layer](../../rules/service-layer.md)
- [Template Action](../../templates/backend/README.md)
- [ADR-0003 — Architettura a livelli](0003-layered-architecture.md)
