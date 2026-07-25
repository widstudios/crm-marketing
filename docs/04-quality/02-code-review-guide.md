# Guida al code review

> Come si revisiona il codice altrui, come si riceve una revisione, e come si mantiene il processo
> utile invece che rituale.

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cosa cerca una revisione](#che-cosa-cerca-una-revisione)
3. [Ordine di lettura](#ordine-di-lettura)
4. [Bloccante e non bloccante](#bloccante-e-non-bloccante)
5. [Come si scrive un commento](#come-si-scrive-un-commento)
6. [Come si riceve una revisione](#come-si-riceve-una-revisione)
7. [Revisione del codice generato da agenti](#revisione-del-codice-generato-da-agenti)
8. [Tempi e dimensioni](#tempi-e-dimensioni)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Il code review ha tre scopi, in ordine di importanza:

1. **Trovare i difetti** che i test automatici non intercettano.
2. **Diffondere la conoscenza** del sistema tra le persone.
3. **Mantenere la coerenza** con gli standard.

Non ha lo scopo di verificare la formattazione (lo fa Pint), i tipi (PHPStan), o il funzionamento
(i test). Se una revisione si esaurisce in commenti su virgolette e spaziature, gli strumenti
automatici non sono configurati bene.

---

## Che cosa cerca una revisione

In ordine di gravità:

| Priorità | Categoria | Esempi |
|---|---|---|
| 1 | **Sicurezza e isolamento** | query senza contesto tenant, autorizzazione mancante, dato sensibile esposto |
| 2 | **Correttezza** | logica errata, caso limite non gestito, transazione mancante |
| 3 | **Violazione di regole** | logica nel controller, Action non `final`, `env()` fuori da `config/` |
| 4 | **Prestazioni** | N+1, query senza indice, operazione lenta in sincrono |
| 5 | **Manutenibilità** | responsabilità confuse, duplicazione, nomi fuorvianti |
| 6 | **Test** | casi non coperti, test che non possono fallire |
| 7 | **Documentazione** | contratto pubblico cambiato senza aggiornare la documentazione |

Le prime due sono sempre bloccanti. Le altre dipendono dal contesto e dalla gravità.

---

## Ordine di lettura

Leggere una PR dall'alto verso il basso porta a commentare dettagli prima di aver capito
l'insieme. L'ordine efficace è:

1. **La descrizione**: cosa cambia e perché. Se non è chiara, si chiede prima di leggere il codice.
2. **I test**: dicono cosa l'autore riteneva importante, e cosa ha dimenticato.
3. **Le migration**: sono la parte più difficile da correggere dopo.
4. **Il dominio**: entità, enum, value object.
5. **Le Action**: la logica vera.
6. **Gli ingressi**: controller, Filament, comandi.
7. **Il resto**: viste, traduzioni, configurazione.

---

## Bloccante e non bloccante

La distinzione va **dichiarata**, non lasciata intuire.

| Prefisso | Significato | Blocca? |
|---|---|---|
| `[bloccante]` | va corretto prima del merge | sì |
| `[domanda]` | non ho capito, spiegami | dipende dalla risposta |
| `[suggerimento]` | si può fare meglio, decidi tu | no |
| `[nota]` | osservazione per il futuro | no |

Senza questa distinzione, l'autore non sa cosa fare: alcuni correggono tutto (perdendo tempo su
preferenze altrui), altri ignorano tutto (perdendo le segnalazioni importanti).

---

## Come si scrive un commento

Un commento utile contiene tre elementi: **cosa**, **perché**, **cosa fare invece**.

```markdown
✗  Questo non va bene.

✗  Perché non usi un'Action?

✓  [bloccante] La transizione di stato è verificata qui nel controller, quindi non vale per
   l'importazione massiva e per l'API, che passano dallo stesso caso d'uso.

   Spostandola in `ArchiveSupplierAction` la regola vale per tutti i percorsi.
   Vedi rules/action-pattern.md.
```

Regole di forma:

- Si commenta il **codice**, mai la persona: «questo metodo fa due cose», non «hai scritto male».
- Si cita la regola quando se ne invoca una: rende la discussione oggettiva.
- Si riconosce ciò che è fatto bene: una revisione fatta solo di correzioni scoraggia.
- Se si commenta lo stesso punto tre volte, il problema è la regola o la sua documentazione.

---

## Come si riceve una revisione

- **Rispondere a tutto**, anche solo «fatto» o «ok, procedo così».
- **Non difendersi**: il codice è il prodotto del lavoro, non una parte di sé.
- **Argomentare quando non si è d'accordo**, citando regole o vincoli: il revisore può non
  conoscere un dettaglio.
- **Chiedere chiarimenti** se un commento non è chiaro, invece di indovinare.
- **Ringraziare per i difetti trovati**: quel difetto sarebbe finito in produzione.

Se una discussione supera i tre scambi, si passa a una conversazione diretta e si riporta la
conclusione nella PR.

---

## Revisione del codice generato da agenti

Il codice generato richiede la **stessa** revisione di quello scritto a mano, con alcune
attenzioni specifiche.

| Attenzione | Perché |
|---|---|
| Assunzioni non dichiarate | l'agente colma i vuoti del requisito senza segnalarlo sempre |
| Codice plausibile ma inutile | metodi mai chiamati, astrazioni non necessarie |
| Regole applicate alla lettera ma fuori contesto | conformità formale senza comprensione |
| Test che verificano l'implementazione | copertura alta, valore basso |
| Coerenza con il codice circostante | l'agente può ignorare convenzioni locali non scritte |
| Casi limite del dominio | l'agente conosce le regole tecniche, non il dominio |

Il rischio principale è la **fiducia per stanchezza**: il codice generato è quasi sempre
formalmente corretto, e questo abitua ad approvarlo senza leggerlo. La contromisura è il
Claude Reviewer come revisione indipendente, che però non sostituisce quella umana sui casi di
dominio.

Se lo stesso difetto compare in più generazioni, la correzione va nel prompt dell'agente.

---

## Tempi e dimensioni

| Metrica | Obiettivo | Motivo |
|---|---|---|
| Dimensione della PR | < 400 righe di diff | oltre, la revisione diventa superficiale |
| Tempo di prima risposta | < mezza giornata lavorativa | una PR ferma perde valore |
| Numero di revisori | 1 (2 per modifiche architetturali) | oltre, la responsabilità si diluisce |
| Cicli di revisione | ≤ 2 | oltre, serve una conversazione diretta |

Le PR grandi ricevono **meno** commenti delle PR piccole, non di più: è il segnale che non vengono
lette davvero.

---

## Esempi

### Esempio 1 — difetto che solo una persona può trovare

```php
$batch->update(['quantity' => $batch->quantity - $data->quantity]);
```

Il codice è formalmente corretto, i test passano. Il revisore nota che due scarichi simultanei
sullo stesso lotto producono una giacenza errata: manca il lock.

Nessun test automatico lo avrebbe rilevato senza un test di concorrenza specifico — che ora si
aggiunge.

### Esempio 2 — commento che risolve invece di bloccare

```markdown
[domanda] Vedo che `occurred_at` è nullable. Nel brief la tracciabilità richiede il momento
del movimento per tutti i casi: c'è uno scenario in cui non lo conosciamo?

Se non c'è, renderei la colonna obbligatoria: così il vincolo è nel database e non dipende
dal percorso di inserimento.
```

Fa una domanda reale, spiega il ragionamento, propone una soluzione.

---

## Best practice

- Leggere prima descrizione e test, poi il codice.
- Dichiarare sempre se un commento è bloccante.
- Citare la regola quando se ne invoca una.
- Commentare il codice, mai la persona.
- Riconoscere ciò che è fatto bene.
- Approvare quando il codice è corretto, non quando è come lo si sarebbe scritto.
- Rispondere entro mezza giornata.
- Al terzo commento identico, correggere la regola o la sua documentazione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Revisione limitata allo stile | I difetti reali passano | Automatizzare lo stile |
| Commenti senza priorità | L'autore non sa cosa fare | Prefissi bloccante/suggerimento |
| Approvare senza leggere | Il processo diventa un rito | PR piccole, tempo dedicato |
| Imporre preferenze personali | Attrito, sfiducia | Citare regole, non gusti |
| PR enormi | Revisione superficiale | Dividere |
| Revisioni tardive | Conflitti, lavoro fermo | Mezza giornata |
| Fidarsi del codice generato | Difetti sistematici replicati | Stessa revisione, più attenzione al dominio |

---

## Checklist

**Come revisore**
- [ ] Ho letto descrizione e test prima del codice.
- [ ] Ho verificato isolamento tenant e autorizzazione.
- [ ] Ho verificato le migration, che sono le più difficili da correggere dopo.
- [ ] Ho dichiarato quali commenti sono bloccanti.
- [ ] Ho citato la regola per ogni richiesta bloccante.
- [ ] Ho riconosciuto ciò che è fatto bene.

**Come autore**
- [ ] La descrizione dice cosa, perché, come verificarlo, quali rischi.
- [ ] `composer qa` verde prima di aprire la PR.
- [ ] Ho risposto a ogni commento.
- [ ] Ho dichiarato eventuali deviazioni dalle regole.

---

## Riferimenti

- [Checklist di code review](../../checklists/code-review-checklist.md)
- [Agente Reviewer](../../agents/11-reviewer-agent.md) · [Claude Reviewer](../../agents/12-claude-reviewer.md)
- [Workflow quotidiano](../01-getting-started/05-daily-workflow.md)
- [Regole Git](../../rules/git.md)
