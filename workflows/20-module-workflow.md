# Workflow — aggiungere un modulo

> Il percorso per un nuovo ambito funzionale su un progetto esistente: completo, ma limitato al
> modulo.

| | |
|---|---|
| **Durata indicativa** | 1-3 giorni |
| **Prompt** | [`prompts/library/add-module.md`](../prompts/library/add-module.md) |

---

## Indice

1. [Quando si usa](#quando-si-usa) 2. [Prerequisiti](#prerequisiti) 3. [Sequenza](#sequenza)
4. [La verifica di indipendenza](#la-verifica-di-indipendenza) 5. [Quality gate](#quality-gate)
6. [Esempi](#esempi) 7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist) 10. [Riferimenti](#riferimenti)

---

## Quando si usa

| Situazione | Percorso |
|---|---|
| Nuovo ambito funzionale con entità proprie | **questo workflow** |
| Installazione di un modulo di catalogo | questo workflow, senza le fasi di costruzione |
| Operazione su entità esistenti | [`prompts/library/add-feature.md`](../prompts/library/add-feature.md) |
| Nuovo progetto | [master workflow](00-master-workflow.md) |

---

## Prerequisiti

- [ ] L'ambito è descritto: entità, casi d'uso, attori, regole di business.
- [ ] Il **catalogo** è stato verificato: l'ambito non è già coperto.
- [ ] `composer qa` verde prima di iniziare.
- [ ] Branch dedicato.

Il secondo prerequisito è quello che si salta più spesso, ed è quello che evita il costo maggiore:
costruire ciò che esiste già.

---

## Sequenza

```
1. Architect        confine, dipendenze, contratti, eventi, manifesto
2. Database         migration, seeder, factory del modulo
3. Backend          dominio, Action, Query, repository
4. Filament         resource, se amministrativo
   Frontend         componenti, se rivolto all'utente finale
5. Security         permessi, Policy, test di isolamento
6. Testing          suite del modulo
7. Documentation    README, overview, checklist del modulo
```

Rispetto al master workflow mancano: fondazione (esiste già), analisi generale (limitata all'ambito),
prestazioni e refactoring (si applicano al progetto intero, non al singolo modulo).

---

## La verifica di indipendenza

È il quality gate specifico di questo workflow, e non ha equivalenti nel master workflow.

```bash
php artisan module:disable <nome>
composer test        # la suite degli altri moduli deve restare verde
php artisan module:enable <nome>
composer test        # tutto verde
```

Se disabilitando il modulo la suite degli altri fallisce, il modulo **non è indipendente**: qualcuno
lo importa direttamente invece di passare da eventi o contratti.

La verifica va eseguita anche quando sembra superflua: l'accoppiamento si introduce senza
accorgersene.

---

## Quality gate

| Fase | Gate |
|---|---|
| 1 Architettura | [architecture-checklist](../checklists/architecture-checklist.md) |
| 2 Database | [database-checklist](../checklists/database-checklist.md) |
| 3 Backend | [backend-checklist](../checklists/backend-checklist.md) |
| 4 Interfaccia | [filament](../checklists/filament-checklist.md) o [frontend](../checklists/frontend-checklist.md) |
| 5 Sicurezza | [security-checklist](../checklists/security-checklist.md) |
| 6 Testing | [testing-checklist](../checklists/testing-checklist.md) |
| 7 Documentazione | [documentation-checklist](../checklists/documentation-checklist.md) |
| — | **verifica di indipendenza** |

---

## Esempi

### Esempio 1 — riuso invece di costruzione

Richiesta: «serve la gestione dei documenti allegati alle pratiche».

Verifica del catalogo: il modulo `documents` copre l'ambito.

```bash
composer require widstudios/module-documents
php artisan module:install documents
php artisan tenants:migrate
```

Configurazione dei tipi di documento e dei permessi. Tempo: ore invece di giorni.

### Esempio 2 — modulo di dominio costruito

Richiesta: «tracciabilità dei dispositivi con lettura RFID».

Nessun modulo di catalogo copre l'ambito: si costruisce `rfid-tracking` come modulo di dominio, con
manifesto, migration, dominio, resource, permessi, test e documentazione.

Nel rapporto si dichiara la valutazione fatta sul catalogo, così la decisione resta tracciata.

---

## Best practice

- Verificare il catalogo prima di tutto: è il passaggio con il ritorno maggiore.
- Ridurre `requires` al minimo: ogni voce riduce l'indipendenza.
- Integrare i moduli facoltativi con eventi, mai con import diretti.
- Eseguire la verifica di indipendenza anche quando sembra superflua.
- Se il modulo sarebbe utile in tre progetti, proporlo per il catalogo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Costruire invece di riusare | Duplicazione, manutenzione doppia | Verifica del catalogo |
| Import diretto di model di altri moduli | Modulo non disinstallabile | Eventi o contratti |
| Troppe dipendenze obbligatorie | Nessuna indipendenza reale | `optional` + eventi |
| Permessi non dichiarati nel manifesto | Non vengono seminati | Manifesto completo |
| Verifica di indipendenza saltata | Il modulo sembra indipendente e non lo è | Prova di disinstallazione |
| Migration non reversibili | Disinstallazione impossibile | `down()` sempre |

---

## Checklist

- [ ] Catalogo verificato; costruzione motivata se non si riusa.
- [ ] Manifesto completo, con dipendenze, permessi, eventi, impostazioni.
- [ ] Nessuna dipendenza circolare.
- [ ] Nessun import diretto di model di altri moduli.
- [ ] Migration reversibili.
- [ ] Permessi dichiarati e seminati.
- [ ] Test di isolamento per ogni entità nuova.
- [ ] Suite del modulo verde anche senza i moduli facoltativi.
- [ ] **Verifica di indipendenza superata.**
- [ ] README, overview e checklist del modulo presenti.

---

## Riferimenti

- [Workflow](README.md) · [Master workflow](00-master-workflow.md)
- [Prompt aggiungi modulo](../prompts/library/add-module.md)
- [Sistema modulare](../architecture/10-modular-system.md) · [Contratto di modulo](../architecture/11-module-contract.md)
- [Blueprint di modulo](../modules/_blueprint/README.md) · [Catalogo](../modules/README.md)
