# Fase 6 — Frontend

> Costruire la presenza pubblica e i portali: landing page, CMS, componenti Livewire, accessibilità.

| | |
|---|---|
| **Agente** | [Frontend Agent](../agents/05-frontend-agent.md) |
| **Gate** | [`checklists/frontend-checklist.md`](../checklists/frontend-checklist.md) |
| **Durata indicativa** | 2-6 ore |
| **Fase precedente** | [Fase 4 — Backend](05-phase-backend.md) |
| **Fase successiva** | [Fase 7 — Sicurezza](08-phase-security.md) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Costruire la presenza pubblica e i portali: landing page, CMS, componenti Livewire, accessibilità.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Requisiti e attori | fase 1 | sì |
| Architettura e moduli (incluso `cms`) | fase 2 | sì |
| Action e Query | fase 4 | sì |
| Materiale grafico e testi | committente | no |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Layout pubblico e layout di portale.
2. Componenti Blade riutilizzabili, con **modalità scura** da subito.
3. Landing page con blocchi di contenuto dal CMS.
4. Pagine di contenuto, multilingua.
5. SEO: meta tag, canonical, `hreflang`, sitemap, dati strutturati, `robots.txt`.
6. Moduli di contatto, con protezioni non interattive.
7. Registrazione del tenant, con verifica dell'indirizzo **prima** del provisioning.
8. Componenti Livewire per le interazioni con il server.
9. Interattività lato client con Alpine.
10. Traduzioni `it` ed `en`.
11. Verifica di accessibilità: strumento automatico e prova completa da tastiera.
12. Misurazione delle prestazioni.

---

## Output

Layout, pagine, componenti Blade e Livewire, asset, traduzioni, test.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/frontend-checklist.md`](../checklists/frontend-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Requisito che Livewire non copre | serve una ADR per un framework aggiuntivo |
| Contenuti o materiale grafico mancanti | serve il committente |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Frontend Agent](../agents/05-frontend-agent.md).

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
| Livewire per interazioni client | Richieste inutili | Alpine |
| Dati sensibili in proprietà pubbliche | Esposizione nel browser | Solo campi necessari |
| Metodo Livewire senza autorizzazione | Endpoint aperto | Autorizzazione esplicita |
| Contenuti scritti nelle viste | Ogni correzione richiede un rilascio | CMS |
| Asset da CDN | Dipendenza esterna, CSP violata | Asset locali |
| Modalità scura aggiunta dopo | Risultati incoerenti | Da subito |
| Accessibilità rimandata | Rifacimento dell'interfaccia | Verifiche durante |
| Provisioning senza verifica dell'indirizzo | Database creati da moduli automatici | Verifica obbligatoria |

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
- [Frontend Agent](../agents/05-frontend-agent.md) · [Checklist](../checklists/frontend-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
