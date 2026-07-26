# ADR-0006 — Modello dei permessi

> Permessi granulari come unità atomica, ruoli come raggruppamento modificabile dal cliente,
> Policy deny-by-default come punto di decisione.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Factory Owner |
| **Impatto** | sicurezza di tutti i progetti |
| **Reversibilità** | reversibile con costo alto (migrazione di assegnazioni) |

---

## Indice

1. [Contesto](#contesto)
2. [Decisione](#decisione)
3. [Alternative valutate](#alternative-valutate)
4. [Conseguenze](#conseguenze)
5. [Soglia di rivalutazione](#soglia-di-rivalutazione)
6. [Verifica](#verifica)
7. [Riferimenti](#riferimenti)

---

## Contesto

I nostri clienti hanno organizzazioni diverse tra loro e, nel tempo, cambiano la propria. Un
magazzino con tre operatori ha esigenze di autorizzazione diverse da uno con quaranta persone
divise in reparti.

Nei progetti precedenti, il controllo basato sui **ruoli nel codice**
(`if ($user->hasRole('admin'))`) ha prodotto due problemi ricorrenti:

1. **Rinominare un ruolo rompeva funzionalità.** Un cliente che chiamava «Responsabile Magazzino»
   il ruolo `warehouse_manager` non poteva farlo senza intervento sul codice.
2. **Nuovi ruoli richiedevano modifiche al codice.** Ogni cliente con una struttura organizzativa
   diversa produceva una richiesta di sviluppo.

Inoltre, le Policy scritte in modo permissivo (`return true` in coda al metodo) hanno prodotto due
casi reali di accesso non previsto, entrambi per dimenticanza di un controllo.

---

## Decisione

Modello a tre livelli:

```
PERMESSO   unità atomica, stabile: `supplier.archive`
   │
RUOLO      raggruppamento, modificabile dal cliente: `warehouse_manager`
   │
POLICY     decisione: permesso + stato della risorsa
```

Regole vincolanti:

| # | Regola |
|---|---|
| 1 | Il codice verifica **permessi**, mai nomi di ruolo |
| 2 | Ogni model ha una Policy |
| 3 | Ogni metodo di Policy **nega** in assenza di permesso esplicito |
| 4 | La Policy considera anche lo **stato** della risorsa |
| 5 | I ruoli sono modificabili dal tenant, i permessi no |
| 6 | Ogni funzionalità dichiara il proprio permesso nello stesso commit |
| 7 | I permessi si deprecano, non si rimuovono |
| 8 | `Gate::before` solo per il super admin di piattaforma, con audit |
| 9 | Copertura di test al **100%** sulle Policy |
| 10 | Test di autorizzazione negata per ogni operazione |

Convenzione di nome: `<risorsa>.<azione>`, in inglese.

---

## Alternative valutate

### Alternativa A — controllo sui ruoli

`if ($user->hasRole('warehouse_manager'))`.

**Scartata** per i due problemi descritti nel contesto: il codice diventa dipendente
dall'organizzazione del cliente, che cambia.

### Alternativa B — ACL per singola risorsa

Permessi assegnati istanza per istanza («questo utente può modificare questo fornitore»).

**Scartata** per il costo: richiede una tabella di assegnazioni che cresce con il prodotto tra
utenti e risorse, un'interfaccia di gestione complessa, e verifiche costose in query.

Nei nostri domini la granularità utile è per **tipo di risorsa**, non per istanza. Dove serve la
granularità per istanza (un documento condiviso con persone specifiche), si modella come dato di
dominio, non come permesso.

### Alternativa C — controllo basato su attributi

Regole valutate su attributi di utente, risorsa e contesto.

**Scartata** per sproporzione: potente, ma richiede un motore di regole, è difficile da spiegare
agli amministratori dei clienti, e la sua flessibilità non corrisponde a esigenze osservate.

### Confronto

| Asse | Permessi + Policy (adottata) | Ruoli nel codice | ACL per istanza | Attributi |
|---|---|---|---|---|
| Indipendenza dall'organizzazione del cliente | **sì** | no | sì | sì |
| Costo di gestione | basso | minimo | **alto** | alto |
| Comprensibilità per l'amministratore | **alta** | alta | media | **bassa** |
| Prestazioni | buone (cache) | ottime | scarse | medie |
| Granularità per istanza | no (nel dominio) | no | sì | sì |
| Verificabilità | **alta** | media | media | bassa |

---

## Conseguenze

### Positive

- Il cliente organizza i propri ruoli senza richiedere sviluppo.
- Rinominare un ruolo non rompe nulla.
- L'elenco dei permessi documenta le capacità del sistema.
- Deny-by-default rende la dimenticanza di un controllo un rifiuto, non un accesso.
- Le Policy sono testabili in isolamento, con copertura completa.

### Negative (accettate consapevolmente)

- **Configurazione iniziale più laboriosa.** Ogni progetto parte con decine di permessi da definire,
  seminare e assegnare.
- **Errori di autorizzazione durante lo sviluppo.** Deny-by-default significa che una funzionalità
  nuova è invisibile finché il permesso non è seminato e assegnato.
- **Nessuna granularità per istanza.** I casi che la richiedono vanno modellati nel dominio.
- **Il numero di permessi cresce.** Un progetto maturo ne ha oltre cento: serve un'interfaccia di
  gestione ordinata per gruppi.
- **Doppio controllo per le API.** Abilità del token *e* permessi dell'utente.

### Impatto operativo

| Area | Effetto |
|---|---|
| Sviluppo | ogni funzionalità porta permesso, Policy e test di rifiuto |
| Deploy | il seeder assegna i nuovi permessi al ruolo amministratore |
| Esercizio | il cliente gestisce i propri ruoli in autonomia |
| Assistenza | «non vedo la funzionalità» è quasi sempre un permesso non assegnato |

---

## Soglia di rivalutazione

1. **Oltre 300 permessi** in un progetto: la granularità è probabilmente troppo fine.
2. **Richieste ricorrenti di granularità per istanza** su più di un ambito: valutare un modello
   ibrido, con ACL per i soli ambiti che la richiedono.
3. **Prestazioni degradate** dalla verifica dei permessi nonostante la cache.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| Nessun controllo su nomi di ruolo nel codice | ricerca in CI | sì |
| Ogni model ha una Policy | script di verifica | sì |
| Copertura al 100% sulle Policy | pipeline | sì |
| Ogni operazione ha un test di autorizzazione negata | revisione + copertura | parziale |
| Ogni azione Filament ha `->authorize()` | script di verifica | sì |
| Permessi usati tutti dichiarati e seminati | script di verifica | sì |
| `Gate::before` registra gli usi | revisione | no |

---

## Riferimenti

- [Autorizzazione, ruoli e permessi](../09-authorization-roles-permissions.md)
- [Autenticazione](../08-authentication.md)
- [Regole Policies](../../rules/policies.md) · [Sicurezza](../../rules/security.md)
- [Guida alla sicurezza](../../docs/04-quality/05-security-guide.md)
