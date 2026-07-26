# Regole — Code review

> Cosa si verifica, in quale ordine, e cosa blocca il merge.

---

## Indice

1. [Descrizione](#descrizione)
2. [Requisiti della pull request](#requisiti-della-pull-request)
3. [Ordine di verifica](#ordine-di-verifica)
4. [Cosa blocca](#cosa-blocca)
5. [Come si commenta](#come-si-commenta)
6. [Tempi e dimensioni](#tempi-e-dimensioni)
7. [Codice generato da agenti](#codice-generato-da-agenti)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Il code review serve a trovare i difetti che i test non intercettano, a diffondere la conoscenza del
sistema e a mantenere la coerenza. **Non** serve a verificare la formattazione (Pint), i tipi
(PHPStan) o il funzionamento (i test).

Se una revisione si esaurisce in commenti sullo stile, gli strumenti automatici non sono configurati
bene.

---

## Requisiti della pull request

**R1.** La descrizione contiene quattro sezioni: cosa cambia, perché, come verificarlo, quali rischi.
*Motivo:* senza «come verificarlo», ogni revisore ricostruisce il procedimento da solo.
*Verifica:* template di pull request. *Livello: vincolante.*

**R2.** La pipeline è verde prima della richiesta di revisione.
*Verifica:* protezione del branch.

**R3.** Le deviazioni dalle regole sono dichiarate nella descrizione, con motivazione.
*Verifica:* revisione.

**R4.** Il diff resta sotto le **400 righe**, o la divisione è motivata.
*Motivo:* oltre, la revisione diventa superficiale: le PR grandi ricevono meno commenti, non di più.
*Verifica:* metriche.

---

## Ordine di verifica

1. **Descrizione** — se non è chiara, si chiede prima di leggere il codice.
2. **Test** — dicono cosa l'autore riteneva importante, e cosa ha dimenticato.
3. **Migration** — la parte più difficile da correggere dopo.
4. **Dominio** — entità, enum, value object.
5. **Action** — la logica vera.
6. **Punti di ingresso** — controller, Filament, comandi.
7. **Resto** — viste, traduzioni, configurazione.

**R5.** Si leggono i test prima del codice.
*Motivo:* rivelano l'intenzione e le omissioni. *Verifica:* prassi.

---

## Cosa blocca

In ordine di gravità. Le prime due sono **sempre** bloccanti.

| # | Categoria | Esempi |
|---|---|---|
| 1 | **Sicurezza e isolamento** | query senza contesto tenant, autorizzazione mancante, dato sensibile esposto, chiave di cache senza prefisso |
| 2 | **Correttezza** | logica errata, caso limite non gestito, transazione mancante, condizione di corsa |
| 3 | Violazione di regola vincolante | logica nel controller, Action non `final`, `env()` fuori da `config/` |
| 4 | Prestazioni | N+1, query senza indice, operazione lenta in sincrono |
| 5 | Test insufficienti | manca il test di autorizzazione negata o di isolamento |
| 6 | Manutenibilità | responsabilità confuse, duplicazione, nomi fuorvianti |
| 7 | Documentazione | contratto pubblico cambiato senza aggiornare la documentazione |

**R6.** Le categorie 1 e 2 bloccano sempre il merge.
*Verifica:* prassi. *Livello: vincolante.*

**R7.** Le categorie 3-5 bloccano salvo deroga dichiarata e approvata.
*Verifica:* prassi.

---

## Come si commenta

**R8.** Ogni commento dichiara se è bloccante.

| Prefisso | Significato | Blocca |
|---|---|---|
| `[bloccante]` | va corretto prima del merge | sì |
| `[domanda]` | non ho capito, spiegami | dipende |
| `[suggerimento]` | si può fare meglio, decidi tu | no |
| `[nota]` | osservazione per il futuro | no |

*Motivo:* senza distinzione, l'autore corregge tutto (perdendo tempo su preferenze) o ignora tutto
(perdendo le segnalazioni importanti). *Verifica:* prassi. *Livello: vincolante.*

**R9.** Un commento bloccante cita la **regola** violata, con il numero.
*Motivo:* rende la discussione oggettiva. *Verifica:* prassi.

**R10.** Il commento contiene cosa, perché e cosa fare invece.

```
[bloccante] La transizione di stato è verificata qui nel controller, quindi non vale per
l'importazione massiva e per l'API, che passano dallo stesso caso d'uso.

Spostandola in ArchiveSupplierAction la regola vale per tutti i percorsi.
Vedi rules/action-pattern.md R1.
```

*Verifica:* prassi.

**R11.** Si commenta il **codice**, mai la persona.
*Verifica:* prassi.

**R12.** Chi riceve la revisione risponde a **ogni** commento, anche solo con «fatto».
*Verifica:* conversazioni risolte prima del merge.

**R13.** Se lo stesso punto viene commentato tre volte su PR diverse, il problema è la regola o la
sua documentazione: si corregge quella.
*Verifica:* metriche sui commenti ricorrenti.

---

## Tempi e dimensioni

| Metrica | Obiettivo |
|---|---|
| Tempo di prima risposta | < mezza giornata lavorativa |
| Dimensione del diff | < 400 righe |
| Numero di revisori | 1 (2 per modifiche architetturali) |
| Cicli di revisione | ≤ 2 |

**R14.** Oltre due cicli di revisione si passa a una conversazione diretta e si riporta la
conclusione nella PR.
*Verifica:* prassi.

---

## Codice generato da agenti

**R15.** Il codice generato riceve la **stessa** revisione di quello scritto a mano.
*Verifica:* prassi. *Livello: vincolante.*

Attenzioni aggiuntive:

| Attenzione | Perché |
|---|---|
| Assunzioni non dichiarate | l'agente colma i vuoti del requisito |
| Codice plausibile ma inutile | metodi mai chiamati, astrazioni non necessarie |
| Conformità formale senza comprensione | regole applicate alla lettera fuori contesto |
| Test che verificano l'implementazione | copertura alta, valore basso |
| Casi limite del dominio | l'agente conosce le regole tecniche, non il dominio |
| Coerenza con il codice circostante | convenzioni locali non scritte |

**R16.** Se lo stesso difetto compare in più generazioni, la correzione va nel **prompt**
dell'agente, non solo nell'output.
*Verifica:* contributi ai prompt.

---

## Esempi

### Esempio 1 — commento efficace

```
[domanda] Vedo che occurred_at è nullable. Nel brief la tracciabilità richiede il momento del
movimento per tutti i casi: c'è uno scenario in cui non lo conosciamo?

Se non c'è, renderei la colonna obbligatoria: così il vincolo è nel database e non dipende dal
percorso di inserimento.
```

Fa una domanda reale, spiega il ragionamento, propone una soluzione.

### Esempio 2 — commenti da evitare

```
✗  Questo non va bene.
✗  Perché non usi un'Action?
✗  Avrei fatto diversamente.
✗  Hai scritto male questo metodo.
```

Nessuno dice cosa fare, e l'ultimo commenta la persona invece del codice.

---

## Best practice

- Leggere descrizione e test prima del codice.
- Dichiarare sempre se un commento è bloccante.
- Citare la regola con il numero.
- Riconoscere ciò che è fatto bene: una revisione di sole correzioni scoraggia.
- Approvare quando il codice è **corretto**, non quando è come lo si sarebbe scritto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Revisione limitata allo stile | I difetti reali passano | Automatizzare lo stile |
| Commenti senza priorità | L'autore non sa cosa fare | Prefissi espliciti |
| Approvare senza leggere | Il processo diventa un rito | PR piccole, tempo dedicato |
| Imporre preferenze personali | Attrito, sfiducia | Citare regole |
| PR enormi | Revisione superficiale | Dividere |
| Revisioni tardive | Lavoro fermo, conflitti | Mezza giornata |
| Fidarsi del codice generato | Difetti sistematici replicati | Stessa revisione, più attenzione al dominio |
| Correggere sempre l'output invece del prompt | Costo ripetuto ad ogni progetto | Correggere il prompt |

---

## Checklist

**Come autore**
- [ ] Descrizione con cosa, perché, come verificarlo, rischi.
- [ ] Pipeline verde.
- [ ] Diff sotto 400 righe o divisione motivata.
- [ ] Deviazioni dichiarate.
- [ ] Risposta a ogni commento.

**Come revisore**
- [ ] Letti descrizione e test prima del codice.
- [ ] Verificati isolamento tenant e autorizzazione.
- [ ] Verificate le migration.
- [ ] Dichiarato quali commenti sono bloccanti.
- [ ] Citata la regola per ogni richiesta bloccante.
- [ ] Riconosciuto ciò che è fatto bene.

---

## Riferimenti

- [Guida al code review](../docs/04-quality/02-code-review-guide.md)
- [Checklist di code review](../checklists/code-review-checklist.md)
- [Git](git.md) · [Commit](commit.md)
- [Agente Reviewer](../agents/11-reviewer-agent.md) · [Claude Reviewer](../agents/12-claude-reviewer.md)
