# Fase 1 — Analisi

> Tradurre il dominio in requisiti espliciti e verificabili, senza progettare la soluzione.

| | |
|---|---|
| **Agente** | [Business Analyst Agent](../agents/02-business-analyst-agent.md) |
| **Gate** | [`checklists/analysis-checklist.md`](../checklists/analysis-checklist.md) |
| **Durata indicativa** | 2-4 ore |
| **Fase precedente** | [Fase 0 — Fondazione](01-phase-foundation.md) |
| **Fase successiva** | [Fase 2 — Architettura](03-phase-architecture.md) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Tradurre il dominio in requisiti espliciti e verificabili, senza progettare la soluzione.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Project Brief | committente | sì |
| Scheletro del progetto | fase 0 | sì |
| Documentazione di dominio | committente | no |
| Software esistente da sostituire | committente | no |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Lettura integrale del brief e del materiale di dominio.
2. Risalita dal requisito al **problema**: una richiesta arriva quasi sempre già tradotta in soluzione.
3. Estrazione delle entità, con attributi e **ciclo di vita**.
4. Estrazione degli attori, con i **divieti** espliciti.
5. Scrittura dei casi d'uso, con flusso, esito e casi di errore.
6. Estrazione delle regole di business, numerate, con la conseguenza della violazione.
7. Individuazione dei vincoli normativi e del loro impatto tecnico.
8. Raccolta dei volumi attesi, riferiti al **tenant più grande**.
9. Costruzione del glossario, con mappatura dei termini del cliente.
10. Delimitazione dell'ambito escluso.
11. Raccolta delle domande aperte, con opzioni e conseguenze.

---

## Output

Documenti dei requisiti in `docs/requirements/`, glossario, elenco delle domande aperte.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/analysis-checklist.md`](../checklists/analysis-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Brief incompleto | mancano sezioni bloccanti |
| Regola di business non specificata | il comportamento cambia schema o Policy |
| Termine di dominio ambiguo | due interpretazioni producono modelli diversi |
| Requisito normativo dubbio | la responsabilità è legale, non tecnica |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Business Analyst Agent](../agents/02-business-analyst-agent.md).

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
| Accettare la soluzione proposta | Si costruisce la cosa sbagliata | Risalire al problema |
| Requisiti senza criteri di accettazione | Impossibile verificare il risultato | Renderli verificabili |
| Entità senza ciclo di vita | Gli enum mancano, gli stati finiscono sparsi | Stati e transizioni |
| Attori senza divieti | Policy permissive | Colonna «cosa NON deve poter fare» |
| Regole di business inventate | Software plausibile e sbagliato | Domanda aperta |
| Volumi come media | Dimensionamento errato | Tenant più grande |
| Ambito escluso omesso | Moduli generati inutilmente | Sezione obbligatoria |

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
- [Business Analyst Agent](../agents/02-business-analyst-agent.md) · [Checklist](../checklists/analysis-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
