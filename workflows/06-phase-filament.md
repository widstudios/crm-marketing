# Fase 5 — Amministrazione

> Costruire i pannelli Super Admin e Tenant Admin, delegando ogni operazione alle Action esistenti.

| | |
|---|---|
| **Agente** | [Filament Agent](../agents/07-filament-agent.md) |
| **Gate** | [`checklists/filament-checklist.md`](../checklists/filament-checklist.md) |
| **Durata indicativa** | 2-4 ore |
| **Fase precedente** | [Fase 4 — Backend](05-phase-backend.md) |
| **Fase successiva** | [Fase 7 — Sicurezza](08-phase-security.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Costruire i pannelli Super Admin e Tenant Admin, delegando ogni operazione alle Action esistenti.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Casi d'uso e attori | fase 1 | sì |
| Moduli e permessi previsti | fase 2 | sì |
| Action, Query, DTO, enum | fase 4 | sì |
| Schema e indici | fase 3 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Configurazione dei due pannelli, con guardie e middleware corretti.
2. Resource del Super Admin: tenant, piani, domini, monitoraggio.
3. Resource di dominio nel Tenant Admin.
4. Form: campi, sezioni, validazione allineata al dominio, condizioni sullo stato.
5. Tabelle: colonne, filtri e ricerca **sui campi dichiarati** nei requisiti.
6. Eager loading in `getEloquentQuery()`.
7. Azioni: delega alle Action, `->authorize()` esplicito, conferma sulle distruttive.
8. Widget: query aggregate, cache **tenant-scoped**.
9. Traduzioni `it` ed `en`.
10. Test di feature sulle azioni personalizzate.
11. Verifica del numero di query con dati realistici.

---

## Output

Pannelli, resource, form, tabelle, azioni, widget, traduzioni e test.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/filament-checklist.md`](../checklists/filament-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Action mancante per un'operazione richiesta | compete alla fase 4 |
| Filtro richiesto su colonna non indicizzata | compete alla fase 3 |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Filament Agent](../agents/07-filament-agent.md).

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
| Logica di business nella Resource | Non vale per API e importazioni | Delegare all'Action |
| Azione senza `authorize()` | Aperta a chiunque veda la pagina | Autorizzazione esplicita |
| Widget senza cache tenant-scoped | Dashboard lenta, o dati di altri tenant | Cache con prefisso |
| Accessor con query nelle colonne | N+1 per riga | `counts()`, `with()` |
| Filtro su colonna non indicizzata | Elenco lentissimo | Segnalare, non aggiungere |
| Etichette scritte direttamente | Seconda lingua impossibile | `__()` |
| Pannello unico con menu condizionali | Elementi visibili a chi non deve | Pannelli separati |

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
- [Filament Agent](../agents/07-filament-agent.md) · [Checklist](../checklists/filament-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
