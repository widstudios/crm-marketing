# Metriche di qualità

> Come si misura se la Factory sta funzionando davvero, invece di limitarsi a esistere.

---

## Indice

1. [Descrizione](#descrizione)
2. [Metriche della Factory](#metriche-della-factory)
3. [Metriche dei progetti generati](#metriche-dei-progetti-generati)
4. [Metriche degli agenti](#metriche-degli-agenti)
5. [Soglie e allarmi](#soglie-e-allarmi)
6. [Come si raccolgono](#come-si-raccolgono)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

La Factory ha successo se produce **software uguale a sé stesso** in meno tempo e con meno difetti.
Tutto il resto — numero di documenti, righe di codice, agenti definiti — è attività, non risultato.

Le metriche si dividono in tre famiglie:

1. **Salute della Factory** — la piattaforma è coerente e navigabile?
2. **Efficacia sui progetti** — i progetti generati sono migliori e più veloci?
3. **Comportamento degli agenti** — l'automazione funziona senza supervisione costante?

---

## Metriche della Factory

| Metrica | Definizione | Obiettivo | Come si misura |
|---|---|---|---|
| Copertura documentale | % di aree con `README.md` indice completo | 100% | `tooling/scripts/check-docs.php` |
| Link rotti | Numero di link relativi non risolvibili | 0 | script di verifica in CI |
| Documenti conformi | % di `.md` con tutte le sezioni obbligatorie | ≥ 95% | `check-docs.php --sections` |
| Regole verificabili | % di regole in `rules/` con criterio di verifica esplicito | ≥ 90% | revisione periodica |
| Regole automatizzate | % di regole controllate da pipeline (Pint, PHPStan, Pest Arch) | ≥ 50% | conteggio manuale |
| Età media dei documenti | Mesi dall'ultima revisione sostanziale | ≤ 12 | `git log` per file |
| ADR per major | Numero di decisioni strutturali registrate | ≥ 1 | conteggio |
| Aree orfane | Aree senza owner attivo | 0 | `CODEOWNERS` + roadmap |

---

## Metriche dei progetti generati

| Metrica | Definizione | Obiettivo | Nota |
|---|---|---|---|
| Time to first deploy | Giorni dall'avvio al primo deploy in staging | ≤ 3 | il vero indicatore di efficacia |
| Riuso della Foundation | % di classi infrastrutturali ereditate invece che riscritte | ≥ 70% | conteggio su `app/` |
| Codice duplicato tra progetti | Blocchi identici ≥ 30 righe presenti in 2+ progetti | 0 | segnale di mancata promozione |
| Copertura test | Linee coperte da Pest | ≥ 80% complessivo, 100% su Action e Policy | `--coverage` |
| Livello PHPStan | Livello raggiunto senza baseline | 8 | `composer analyse` |
| Violazioni di isolamento tenant | Query senza scope tenant rilevate dai test di architettura | 0 | test Pest Arch dedicati |
| Deviazioni dallo standard | Deroghe locali attive nel `CLAUDE.md` di progetto | ≤ 3, tutte con scadenza | revisione trimestrale |
| Difetti in produzione per release | Bug segnalati entro 14 giorni dal rilascio | ≤ 2 | tracker |
| Tempo di rilascio | Minuti dal merge al deploy completato | ≤ 20 | pipeline |

---

## Metriche degli agenti

| Metrica | Definizione | Obiettivo | Perché conta |
|---|---|---|---|
| Completamento di fase | % di fasi completate senza intervento umano | ≥ 70% | misura l'autonomia reale |
| Quality gate superati al primo tentativo | % di fasi che passano la checklist senza rework | ≥ 60% | misura la qualità dei prompt |
| Interventi correttivi | Numero medio di correzioni umane per progetto | ≤ 10 | tendenza più importante del valore assoluto |
| Violazioni di regola rilevate dal Reviewer | Numero per fase | tendenza decrescente | se cresce, il prompt a monte è debole |
| Costo per progetto | Token/tempo per portare un progetto a staging | tendenza decrescente | sostenibilità economica |
| Loop infiniti / stalli | Casi in cui l'agente non converge | 0 | richiede intervento sul workflow |

---

## Soglie e allarmi

| Condizione | Gravità | Azione |
|---|---|---|
| Link rotti > 0 in `main` | alta | blocco della pipeline documentale |
| Violazione di isolamento tenant in un progetto | **critica** | blocco del rilascio, analisi immediata |
| Copertura test < 70% | alta | blocco del merge |
| PHPStan sotto livello 8 o baseline in crescita | media | rientro entro la sprint |
| Codice duplicato tra 3 progetti | media | valutare promozione alla Foundation |
| Quality gate superati al primo tentativo < 40% | alta | revisione dei prompt dell'agente coinvolto |
| Documenti non revisionati da oltre 18 mesi | bassa | revisione pianificata |
| Deroga locale scaduta | media | rientro o nuova ADR |

---

## Come si raccolgono

- **Automatiche in CI**: link rotti, sezioni obbligatorie, copertura, PHPStan, test di architettura.
- **Automatiche a fine progetto**: time to first deploy, riuso della Foundation, costo.
- **Manuali trimestrali**: età dei documenti, deroghe attive, aree orfane, duplicazione tra progetti.

Il risultato confluisce in una scheda sintetica allegata alla revisione di versione. Non serve una
dashboard sofisticata: serve che i numeri vengano **guardati**.

---

## Esempi

### Esempio 1 — la metrica che rivela un problema reale

`Quality gate superati al primo tentativo` scende dal 65% al 30% sulla fase Database.

Diagnosi: il prompt del Database Agent non specificava le convenzioni sugli indici compositi,
introdotte due versioni prima in `rules/sql.md`. L'agente produceva schemi che il Reviewer
respingeva sistematicamente.

Rimedio: aggiornamento del prompt con riferimento esplicito alla regola. La metrica risale al 68%.

### Esempio 2 — la metrica che va letta con giudizio

`Copertura test = 92%` su un progetto, ma le Action critiche hanno copertura 55%: la media
nasconde il buco. Per questo l'obiettivo è **doppio**: complessivo ≥ 80% *e* 100% su Action e
Policy.

### Esempio 3 — segnale di promozione

Tre progetti diversi hanno un `ExportCsvService` quasi identico. La metrica «codice duplicato tra
progetti» lo intercetta: è il momento di promuoverlo nella Foundation come modulo `import-export`.

---

## Best practice

- Poche metriche, guardate davvero. Venti metriche ignorate valgono zero.
- Ogni metrica ha una **soglia** e un'**azione**: senza azione è solo un numero.
- Misurare le tendenze, non i valori assoluti: la direzione dice più del punto.
- Le metriche degli agenti servono a migliorare i **prompt**, non a giudicare l'output singolo.
- Automatizzare tutto ciò che è automatizzabile; il resto va calendarizzato.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Misurare l'attività (numero di documenti) | Si ottimizza la quantità, non il valore | Misurare l'esito sui progetti |
| Copertura test come unico indicatore | Test tanti e inutili | Affiancare copertura mirata su Action/Policy |
| Metriche senza soglia | Nessuno sa quando intervenire | Definire soglia e azione |
| Raccolta manuale di tutto | Si smette dopo due mesi | Automatizzare in CI |
| Usare le metriche per valutare le persone | Le metriche vengono manipolate | Usarle per migliorare il processo |

---

## Checklist

- [ ] Ogni metrica ha definizione, obiettivo e metodo di raccolta.
- [ ] Le metriche critiche sono automatizzate in CI.
- [ ] Ogni soglia ha un'azione associata.
- [ ] La scheda di qualità è stata compilata all'ultima versione.
- [ ] Le metriche in peggioramento hanno un intervento pianificato.

---

## Riferimenti

- [Governance](README.md) · [Roadmap](roadmap.md)
- [Regole di testing](../rules/testing.md)
- [Checklist di code review](../checklists/code-review-checklist.md)
- [Pipeline CI](../deployment/ci/README.md)
