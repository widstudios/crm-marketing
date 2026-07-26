# Fase 7 — Sicurezza

> Rendere l'applicazione sicura per costruzione e **verificare** che lo sia. È l'unica fase con autorità di blocco.

| | |
|---|---|
| **Agente** | [Security Agent](../agents/08-security-agent.md) |
| **Gate** | [`checklists/security-checklist.md`](../checklists/security-checklist.md) |
| **Durata indicativa** | 1-3 ore |
| **Fase precedente** | [Fase 5](06-phase-filament.md) e [Fase 6](07-phase-frontend.md) |
| **Fase successiva** | [Fase 8 — Testing](09-phase-testing.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Rendere l'applicazione sicura per costruzione e **verificare** che lo sia. È l'unica fase con autorità di blocco.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Attori, ruoli e divieti | fase 1 | sì |
| Vincoli normativi | fase 1 | sì |
| Permessi previsti | fase 2 | sì |
| Model, Action, enum | fase 4 | sì |
| Resource e azioni Filament | fase 5 | sì |
| Componenti Livewire e rotte pubbliche | fase 6 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Modello di minaccia specifico del progetto.
2. Permessi in configurazione, derivati dai casi d'uso e dai divieti.
3. Seeder idempotenti, che assegnano i nuovi permessi al ruolo amministratore.
4. Una Policy per model, **deny-by-default**, con verifica di stato.
5. Verifica dell'autorizzazione in **ogni** punto di ingresso.
6. Test di isolamento tenant per ogni entità.
7. Test di isolamento della cache e del contesto nei job.
8. Verifica dello storage: disco privato, accesso da controller autorizzato.
9. Audit sulle entità sensibili, con la conservazione richiesta.
10. Irrigidimento della configurazione di produzione.
11. Verifica delle liste bianche su input variabili.
12. Verifica che i log non contengano dati sensibili.

---

## Output

Policy, permessi, seeder, test di isolamento e autorizzazione, modello di minaccia, rapporto di
sicurezza.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/security-checklist.md`](../checklists/security-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Violazione di isolamento rilevata | **blocco**: il processo non avanza |
| Correzione che richiede modifiche alla logica | compete alla fase 4 |
| Vincolo normativo con impatto sulla conservazione non chiaro | serve il committente |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Security Agent](../agents/08-security-agent.md).

---

## Best practice

- Verificare gli input prima di iniziare.
- Fornire all'agente il contesto **pertinente**, non l'intero repository.
- Leggere per prime le sezioni «assunzioni» e «domande aperte» del rapporto.
- Verificare il gate voce per voce.
- Registrare tempo ed esito: servono alle metriche di processo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Policy con `return true` finale | Autorizzazione permissiva | Deny by default |
| Controllo su nomi di ruolo | Si rompe quando il cliente rinomina | Verificare il permesso |
| Azione Filament senza `authorize()` | Operazione aperta a tutti | Autorizzazione esplicita |
| Test di isolamento della cache omesso | La fuga più insidiosa non emerge | Test obbligatorio |
| Permessi non assegnati al ruolo | Funzionalità invisibile dopo il deploy | `syncPermissions` |
| Vulnerabilità non segnalata per non bloccare | Difetto in produzione | Il ruolo dell'agente è bloccare |

---

## Checklist

- [ ] Gli artefatti di input esistono.
- [ ] L'invocazione contiene identità, regole, contesto, compito e gate.
- [ ] Il rapporto di fase è completo, con tutte le sezioni.
- [ ] Assunzioni e domande aperte lette e registrate.
- [ ] Gate verificato voce per voce.
- [ ] Esito e durata registrati nel registro di esecuzione.

---

## Riferimenti

- [Master workflow](00-master-workflow.md) · [Workflow](README.md)
- [Security Agent](../agents/08-security-agent.md) · [Checklist](../checklists/security-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
