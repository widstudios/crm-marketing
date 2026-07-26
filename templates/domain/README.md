# Templates — Dominio

> Gli stub del livello che contiene le regole: entità, stati, valori, eventi, eccezioni, contratti.

---

## Indice

1. [Descrizione](#descrizione) 2. [Gli stub](#gli-stub) 3. [Dove vanno i file](#dove-vanno-i-file)
4. [Come si sceglie](#come-si-sceglie) 5. [Esempi](#esempi) 6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

Il dominio è il livello che non dipende da nulla: né dal framework, né dal database, né dal modo in
cui i dati arrivano. È anche il livello in cui un errore costa di più, perché si propaga a tutto
ciò che vi si appoggia.

Gli stub di questa cartella hanno una caratteristica in comune: sono più commentati degli altri, e
i commenti dicono soprattutto **cosa non mettere dentro**. È l'informazione che serve davvero,
perché la pressione a mettere le regole altrove — nell'Action, nel controller, nel Resource — è
costante e sempre giustificata dalla fretta.

---

## Gli stub

| Stub | Quando | Vincoli |
|---|---|---|
| [Model.php.stub](Model.php.stub) | ogni entità persistita | `final`, `$fillable` esplicito, nessuna query di presentazione |
| [Enum.php.stub](Enum.php.stub) | ogni insieme chiuso di stati | tipo `string`, `canTransitionTo()`, `match` esaustivo |
| [ValueObject.php.stub](ValueObject.php.stub) | ogni dato con vincoli propri | `final readonly`, validazione nel costruttore |
| [Event.php.stub](Event.php.stub) | ogni fatto rilevante | nome al passato, `readonly`, solo identificatori |
| [Exception.php.stub](Exception.php.stub) | ogni regola che può essere violata | costruttori nominati |
| [Contract.php.stub](Contract.php.stub) | ogni dipendenza verso l'esterno | linguaggio del dominio, non della tecnologia |

---

## Dove vanno i file

```
app/Domain/{{ Module }}/
├── {{ Entity }}.php                 Model.php.stub
├── Enums/{{ Entity }}Status.php     Enum.php.stub
├── ValueObjects/Quantity.php        ValueObject.php.stub
├── Events/{{ Entity }}Created.php   Event.php.stub
├── Exceptions/…                     Exception.php.stub
└── Contracts/…                      Contract.php.stub
```

---

## Come si sceglie

| Domanda | Artefatto |
|---|---|
| Ha un'identità che sopravvive ai cambiamenti dei suoi valori? | Model |
| È un insieme chiuso di valori con transizioni? | Enum |
| È identificato dal proprio valore, e ha vincoli? | Value object |
| È un fatto avvenuto a cui altri devono poter reagire? | Evento |
| È una regola che può essere violata? | Eccezione |
| È una capacità che il dominio richiede e non fornisce? | Contratto |

Il caso di confine più frequente è **value object o colonna semplice**. Il criterio: se il valore ha
vincoli che qualcuno potrebbe dimenticare di verificare, è un value object. Una partita IVA lo è;
una nota libera no.

---

## Esempi

### Un enum con la macchina a stati

```php
enum BatchStatus: string
{
    case Draft = 'draft';
    case Available = 'available';
    case Quarantined = 'quarantined';
    case Expired = 'expired';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Available],
            self::Available => [self::Quarantined, self::Expired],
            self::Quarantined => [self::Available, self::Expired],
            self::Expired => [],
        };
    }
}
```

La transizione è dichiarata in un punto solo. Senza, la sequenza si ricostruisce leggendo tutti gli
`if` sparsi nel codice, e nessuno sa dire con certezza quali passaggi siano ammessi.

### Una regola nel posto giusto

```php
// ✗ Nell'Action: l'importazione massiva non la applicherà
if ($batch->expiry_date < now()) {
    throw new BatchExpired();
}

// ✓ Nell'entità: chiunque la usi la applica
$batch->assertUsable();
```

---

## Best practice

- Scrivere per primo l'enum degli stati: chiarisce il ciclo di vita prima dello schema.
- Un value object per ogni dato con vincoli, anche quando sembra eccessivo.
- Nomi presi dal glossario del committente, senza sinonimi introdotti.
- Copertura al 100% su enum, value object ed eccezioni: sono piccoli, e il test costa poco.
- Nessun `now()` nel dominio: ricevere il `Clock` rende verificabile ciò che dipende dalla data.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Regole nell'Action invece che nell'entità | Duplicate appena l'entità serve altrove | Metodi di dominio |
| `$guarded = []` | Assegnazione massiva di colonne di stato | `$fillable` esplicito |
| Enum di MySQL invece di enum PHP | `ALTER TABLE` su N tenant per un valore nuovo | `varchar` + cast |
| Transizioni sparse in `if` | Nessuno sa quali siano ammesse | `canTransitionTo()` |
| Value object mutabile | Cambia il valore che lo identifica | `readonly` |
| Evento con un model nel payload | Payload grande, relazioni sparite | Identificatori |
| `new DomainException('…')` | Lo stesso errore formulato in tre modi | Costruttori nominati |
| Contratto scritto sulla tecnologia | Cambiare implementazione cambia il dominio | Linguaggio del dominio |
| Observer sul model | Azioni invisibili a ogni salvataggio | Action |

---

## Checklist

- [ ] Ogni entità ha `$fillable` esplicito e i cast dichiarati.
- [ ] Ogni stato è un enum con `canTransitionTo()`.
- [ ] Ogni dato con vincoli è un value object auto-validante.
- [ ] Gli eventi hanno nome al passato e trasportano identificatori.
- [ ] Le eccezioni hanno costruttori nominati.
- [ ] Nessuna query di presentazione nei model.
- [ ] Nessun Observer.
- [ ] Copertura al 100% su enum, value object ed enum di dominio.

---

## Riferimenti

- [Livello di dominio](../../architecture/12-domain-layer.md)
- [Laravel](../../rules/laravel.md) · [PHP](../../rules/php.md) · [Naming](../../rules/naming.md) · [Events](../../rules/events.md)
- [Catalogo degli artefatti](../../docs/06-reference/02-artifact-catalog.md)
