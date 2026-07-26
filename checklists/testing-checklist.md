# Checklist — Testing

> Verifica che la suite dimostri il comportamento, copra ciò che conta e resti eseguibile ogni
> giorno.

| | |
|---|---|
| **Fase** | 8 — Testing |
| **Agente** | [Testing Agent](../agents/13-testing-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Una suite verde non dimostra nulla se non si sa **che cosa** verifica. Questa checklist verifica la
composizione della suite, non solo il suo esito: le categorie previste, le soglie doppie di
copertura, e i tre test che ogni operazione deve avere.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Struttura

- [ ] Le quattro categorie esistono: `tests/Unit/`, `tests/Feature/`, `tests/Tenant/`,
      `tests/Architecture/`.
- [ ] I test unitari non toccano il database né avviano il framework (R12).
- [ ] Nessun test dipende dall'ordine di esecuzione o dallo stato lasciato da un altro (R7).
- [ ] Nessun `sleep()`: il tempo si manipola con `travel()` (R8).
- [ ] Le dipendenze esterne sono simulate: `Http::fake()`, `Mail::fake()`, `Queue::fake()`,
      `Storage::fake()` (R9).
- [ ] Nessuna chiamata di rete reale durante la suite.

### Copertura — soglia doppia

- [ ] Copertura complessiva ≥ **80%**.
- [ ] Copertura **100%** su `Application\*Action`.
- [ ] Copertura **100%** sulle Policy.
- [ ] Copertura **100%** su value object ed enum di dominio.
- [ ] Copertura ≥ **70%** sui controller.
- [ ] La copertura è riportata **per namespace**, non solo come media.

### Copertura del comportamento

- [ ] Ogni operazione ha almeno **tre** test: percorso corretto, violazione di una regola di
      dominio, autorizzazione negata (R4).
- [ ] Ogni regola di business del brief ha un test che la verifica, identificabile per nome.
- [ ] Ogni endpoint API ha test per: risposta corretta, validazione fallita, autorizzazione negata,
      risorsa inesistente (R16).
- [ ] Ogni azione Filament personalizzata ha un test (R17).
- [ ] Gli effetti collaterali sono verificati: evento emesso, job accodato, audit scritto (R18).
- [ ] Ogni job idempotente ha un test che lo esegue **due volte** e verifica lo stesso risultato.
- [ ] Ogni transizione di stato non ammessa ha un test che la rifiuta.

### Isolamento tenant

- [ ] Ogni entità di dominio ha un test di isolamento (R19).
- [ ] Il test crea **almeno due** tenant reali e verifica l'invisibilità reciproca (R20).
- [ ] Esiste un test di isolamento della **cache** (R21).
- [ ] Esiste un test sul ripristino del contesto tenant nei job (R22).
- [ ] L'accesso via API a una risorsa di un altro tenant ritorna `404` (R23).

### Architettura

- [ ] I test di architettura esistono e sono verdi (R24).
- [ ] Insieme minimo presente: dominio indipendente dal framework, Action `final` con `execute()`,
      DTO `readonly`, controller che non usano l'infrastruttura, nessun helper di debug residuo,
      `strict_types` ovunque.
- [ ] Ogni regola strutturale introdotta nella fase 2 ha il suo test di architettura.

### Qualità dei test

- [ ] Il nome del test descrive il **comportamento**, in italiano (R5).
- [ ] I test verificano il comportamento osservabile, non l'implementazione (R6).
- [ ] Nessun test verifica il funzionamento del framework o del linguaggio.
- [ ] I casi limite dello stesso comportamento usano `with()` invece di essere duplicati (R14).
- [ ] Le factory producono entità **valide secondo il dominio**, con stati nominati per i casi
      limite.

### Esecuzione

- [ ] La suite gira su **SQLite e MySQL** in pipeline (R10).
- [ ] La suite completa resta sotto i **3 minuti**; quella unitaria sotto i 10 secondi (R11).
- [ ] La suite è verde in ordine casuale.
- [ ] Nessun test marcato come saltato senza motivazione scritta.

---

## Comandi di verifica

```bash
composer test                                   # suite completa
composer test:coverage                          # copertura con soglie
composer test:arch                              # solo test di architettura
php artisan test --testsuite=Tenant             # isolamento
php artisan test --order-by=random              # indipendenza dall'ordine
php artisan test --env=testing-mysql            # seconda base dati
php tooling/scripts/check-tests.php             # entità senza test di isolamento
```

La copertura si riporta **per namespace**: una media dell'88% può nascondere un'Action al 40%.

Una prova utile e sottovalutata: **falsificare** un test. Rompere deliberatamente il codice che
dovrebbe far fallire un test; se il test resta verde, non verifica ciò che dichiara.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/testing-checklist.md: N/N soddisfatte.
- Copertura: complessiva 87%; Actions 100%; Policies 100%; VO ed enum 100%; Controller 78%.
- Durata: suite completa 1m48s, unitaria 4s. Verde su SQLite e MySQL.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/testing-checklist.md: N-3/N soddisfatte.
- Copertura: complessiva 84%; Actions **92%**; Policies 100%.

Voci non soddisfatte:
- Copertura 100% sulle Action: `RegisterMovementAction` non ha test sul ramo di lotto scaduto.
  Correzione: aggiungere il test della violazione di dominio.
- Tre test per operazione: `ArchiveDocumentAction` non ha il test di autorizzazione negata.
- Test di isolamento per entità: manca su `DocumentTemplate`.

Richiedo rework su questi punti.
```

---

## Best practice

- Scrivere per primo il test del percorso di errore: è quello che si dimentica.
- Falsificare periodicamente i test: un test che non fallisce mai non protegge da nulla.
- Usare helper condivisi (`userWithPermission`, `createTenant`) invece di ripetere l'impianto.
- Misurare la durata della suite ad ogni fase: la lentezza si accumula in silenzio.
- Verificare gli effetti collaterali, non solo il valore di ritorno.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Solo copertura complessiva | Un buco esattamente dove conta | Soglia doppia, per namespace |
| Nessun test di autorizzazione negata | I difetti più gravi passano | Tre test per operazione |
| Nessun test di isolamento tenant | Rischio di fuga di dati | Obbligatorio per entità |
| Test sull'implementazione | Rotti ad ogni refactoring | Comportamento osservabile |
| Test che verificano il framework | Copertura alta, valore nullo | Rimuoverli |
| Job idempotente non provato due volte | L'idempotenza è dichiarata, non verificata | Test a doppia esecuzione |
| `sleep()` nei test | Suite lenta e instabile | `travel()` |
| Chiamate esterne reali | Fallimenti dipendenti dalla rete | `Http::fake()` |
| Test saltati senza motivazione | Copertura apparente | Motivare o rimuovere |
| Suite provata solo su SQLite | Difetti che emergono in produzione | Anche MySQL |

---

## Checklist

- [ ] Ho eseguito la suite completa, su entrambe le basi dati.
- [ ] Ho riportato la copertura per namespace, non come media.
- [ ] Ho verificato i tre test per ogni operazione dei casi d'uso.
- [ ] Ho verificato la presenza dei test di isolamento e di architettura.
- [ ] Ho riportato la durata reale della suite.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 8](../workflows/09-phase-testing.md) · [Testing Agent](../agents/13-testing-agent.md)
- [Regole di testing](../rules/testing.md) · [ADR-0007](../architecture/decisions/0007-testing-strategy.md)
- [Strategia di testing](../docs/04-quality/01-testing-strategy.md)
- [Template di test](../templates/testing/README.md)
