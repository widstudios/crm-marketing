# Checklist — Backend

> Verifica che ogni operazione dei casi d'uso sia invocabile, testata e con le regole nel posto giusto.

| | |
|---|---|
| **Fase** | 4 — Backend |
| **Agente** | [Backend Agent](../agents/06-backend-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Verifica che ogni operazione dei casi d'uso sia invocabile, testata e con le regole nel posto giusto.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Dominio

- [ ] Ogni regola di business della fase 1 è implementata.
- [ ] Le regole stanno nel **dominio**, non nei punti di ingresso.
- [ ] Ogni stato ha un enum con le transizioni ammesse (`canTransitionTo`).
- [ ] Ogni dato con vincoli ha un value object auto-validante.
- [ ] Le eccezioni di dominio hanno costruttori nominati.
- [ ] Gli eventi hanno nome al passato, sono `readonly` e trasportano identificatori.

### Applicazione

- [ ] Ogni mutazione dei casi d'uso ha la sua **Action**.
- [ ] Ogni Action è `final`, con **un solo** metodo pubblico `execute()`.
- [ ] Ogni Action riceve un **DTO**, mai una `Request`.
- [ ] Le precondizioni sono verificate **prima** della transazione.
- [ ] Nessuna chiamata esterna dentro una transazione.
- [ ] Gli eventi sono emessi **dopo** il commit; i job usano `afterCommit()`.
- [ ] Lock pessimistico sulle risorse contese.
- [ ] Le letture complesse sono Query object.
- [ ] I DTO sono `readonly` con proprietà tipizzate.

### Infrastruttura

- [ ] I contratti stanno nel dominio, le implementazioni nell'infrastruttura.
- [ ] Ogni contratto ha un binding esplicito.
- [ ] Nessuna regola di business nei repository.
- [ ] Gli errori tecnici sono tradotti in eccezioni di dominio.

### Job

- [ ] Ogni job usa `TenantAware`.
- [ ] Ogni job dichiara `tries`, `backoff`, `timeout`.
- [ ] Ogni job è **idempotente**.
- [ ] Ogni job implementa `failed()` con il contesto.

### Presentazione

- [ ] I controller autorizzano esplicitamente.
- [ ] Il corpo dei metodi resta sotto le 10 righe.
- [ ] Le risposte passano da API Resource con campi espliciti.
- [ ] Nessun `auth()` o `request()` fuori dalla presentazione.
- [ ] **Nessuna autorizzazione dentro le Action.**

### Test

- [ ] Copertura **100%** su Action, value object ed enum di dominio.
- [ ] Test unitari senza database sul dominio.
- [ ] Test di feature per ogni operazione.
- [ ] `composer qa` verde.

---

## Comandi di verifica

```bash
composer qa
composer test:coverage
composer test:arch
```

Riportare la copertura **per namespace**: la media complessiva può nascondere un buco sulle Action.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/backend-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/backend-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- <voce>: <cosa manca>. Correzione: <cosa fare>.

Richiedo rework su questi punti.
```

---

## Best practice

- Verificare durante il lavoro, non solo alla fine.
- Eseguire davvero i comandi indicati.
- Dichiarare le voci non applicabili con la motivazione.
- Un gate rosso è un'informazione utile, non un fallimento personale.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Action che riceve `Request` | Inutilizzabile da CLI, coda, importazioni | DTO |
| Regole nelle Action invece che nel dominio | Duplicate quando l'entità serve altrove | Regole nel dominio |
| Autorizzazione dentro l'Action | Non invocabile da processi di sistema | Nel punto di ingresso |
| Evento dentro la transazione | Listener che non trova i dati | Dopo il commit |
| Job senza `TenantAware` | Scrittura nel database sbagliato | Trait obbligatorio |
| Job non idempotente | Dati errati sui ritentativi | Ricalcolo dalla sorgente |
| Nessun lock su risorsa contesa | Giacenze negative | `lockForUpdate()` |

---

## Checklist

- [ ] Ho verificato ogni voce eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 4](../workflows/05-phase-backend.md) · [Backend Agent](../agents/06-backend-agent.md)
- [Action Pattern](../rules/action-pattern.md) · [DTO](../rules/dto.md) · [Queue](../rules/queue.md)
- [Livello applicativo](../architecture/13-application-layer.md)
