# Frammenti di codice

> Tre frammenti che mostrano un pattern in un contesto reale. **Autorità: nulla.**

---

## Indice

1. [Descrizione](#descrizione) 2. [I frammenti](#i-frammenti) 3. [Come sono scritti](#come-sono-scritti)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Le regole dicono che cosa fare. Gli esempi dentro le regole mostrano la forma corretta accanto a
quella sbagliata, il che basta quasi sempre.

Questi frammenti coprono i casi in cui non basta: quando la differenza tra due scelte non sta in una
riga, ma in **dove** una riga viene messa, e le conseguenze si vedono solo guardando il sistema
intero.

---

## I frammenti

| Frammento | Mostra | Regole coinvolte |
|---|---|---|
| [01 — Dal requisito all'entità](01-dal-requisito-allentita.md) | come una frase del committente diventa dominio, e le tre collocazioni possibili | [action-pattern](../../rules/action-pattern.md), [laravel](../../rules/laravel.md) |
| [02 — Un'operazione da tre ingressi](02-operazione-tre-ingressi.md) | perché un'Action riceve un DTO e non una `Request` | [action-pattern](../../rules/action-pattern.md), [dto](../../rules/dto.md) |
| [03 — I cinque punti dell'isolamento](03-cinque-punti-isolamento.md) | come si rompe l'isolamento, e come si dimostra che regge | [security](../../rules/security.md), [testing](../../rules/testing.md) |

---

## Come sono scritti

| Requisito | Perché |
|---|---|
| Codice **completo**, nessun `// …` al posto della logica | un esempio incompleto genera codice incompleto |
| La versione sbagliata **accanto** a quella corretta | la differenza si vede confrontando, non descrivendo |
| La conseguenza dichiarata, non «è meglio» | «è meglio» perde contro una scadenza; «produce giacenze negative» no |
| Nomi del verticale sanitario | sono presi dal walkthrough, e non vanno copiati altrove |

---

## Esempi

### Che cosa rende utile un frammento

```php
// ✗
if ($batch->expiry_date < now()) { … }

// ✓
$batch->assertUsable($clock);
```

Da solo, il confronto non insegna nulla: si vede che la seconda forma è più ordinata, non perché la
prima sia un difetto.

Il frammento [01](01-dal-requisito-allentita.md) aggiunge ciò che manca: la prima forma sta in
un'Action, l'importazione massiva non la applica, e il risultato è che i movimenti su lotti scaduti
entrano dal percorso di importazione mentre vengono rifiutati da quello interattivo. Con quella
informazione, la scelta non è più di stile.

---

## Best practice

- Leggere la regola prima del frammento.
- Guardare la conseguenza dichiarata, non la forma.
- Copiare il criterio, non i nomi del dominio.
- Segnalare come difetto ogni divergenza tra un frammento e una regola.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Frammento letto senza la regola | Si impara la forma, non il motivo | Prima la regola |
| Nomi del dominio copiati | Entità di un altro settore | Copiare il criterio |
| Frammento non aggiornato | Sembra autorevole e insegna il falso | Stesso commit della modifica |
| `// …` al posto della logica | Genera codice incompleto | Frammento completo o assente |

---

## Checklist

- [ ] Ho letto la regola pertinente.
- [ ] Ho capito la conseguenza, non solo la forma.
- [ ] Sto copiando il criterio, non il dominio.

---

## Riferimenti

- [Examples](../README.md) · [Walkthrough](../walkthroughs/README.md)
- [Indice delle regole](../../rules/README.md) · [Foundation](../../foundation/README.md)
