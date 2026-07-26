# Checklist — Analisi

> Verifica che i requisiti siano espliciti, verificabili e privi di regole di business inventate.

| | |
|---|---|
| **Fase** | 1 — Analisi |
| **Agente** | [Business Analyst Agent](../agents/02-business-analyst-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Verifica che i requisiti siano espliciti, verificabili e privi di regole di business inventate.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Requisiti

- [ ] Ogni requisito ha **criteri di accettazione verificabili**.
- [ ] Per ogni soluzione proposta nel brief è stato individuato il **problema** sottostante.
- [ ] Nessun requisito è formulato in modo non verificabile.

### Entità

- [ ] Ogni entità ha attributi e descrizione.
- [ ] Ogni entità ha il **ciclo di vita**: stati e transizioni ammesse.
- [ ] Le entità usano i termini del glossario.

### Attori

- [ ] Ogni attore è descritto con ciò che fa.
- [ ] Ogni attore dichiara **cosa NON deve poter fare**.

### Casi d'uso

- [ ] Ogni caso d'uso primario ha attore, precondizioni, flusso, esito.
- [ ] Ogni caso d'uso elenca i **casi di errore**.
- [ ] I filtri e gli ordinamenti necessari sono dichiarati (serviranno agli indici).

### Regole di business

- [ ] Numerate.
- [ ] Ognuna dichiara la **conseguenza della violazione**.
- [ ] **Nessuna regola inventata**: i vuoti sono domande aperte.

### Vincoli normativi

- [ ] Elencati, con la fonte.
- [ ] Tradotti in **impatto tecnico**: audit, conservazione, cifratura, tracciabilità.
- [ ] Nessun vincolo normativo assunto.

### Volumi

- [ ] Riferiti al **tenant più grande**, non alla media.
- [ ] Proiezione a tre anni presente.

### Glossario e ambito

- [ ] Glossario con mappatura dei termini del cliente sui nomi tecnici.
- [ ] **Ambito escluso** elencato esplicitamente.

### Domande aperte

- [ ] Ognuna espone opzioni e conseguenze tecniche.
- [ ] Ognuna indica chi può rispondere.

---

## Comandi di verifica

Non ci sono comandi: la verifica è documentale.

Controllo utile: prendere tre requisiti a caso e chiedersi *come verificherei che è soddisfatto?*
Se non c'è risposta, il requisito non è verificabile.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/analysis-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/analysis-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- <voce>: <cosa manca>. Correzione: <cosa fare>.

Richiedo rework su questi punti.
```

---

## Best practice

- Verificare durante il lavoro, non solo alla fine.
- Eseguire davvero i comandi: la verifica «a memoria» non è una verifica.
- Dichiarare le voci non applicabili con la motivazione.
- Un gate rosso è un'informazione utile, non un fallimento personale.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Requisiti senza criteri di accettazione | Impossibile verificare il risultato | Renderli verificabili |
| Entità senza ciclo di vita | Gli enum mancano nelle fasi successive | Stati e transizioni |
| Attori senza divieti | Policy permissive alla fase 7 | Colonna «cosa NON deve poter fare» |
| Regole di business inventate | Software plausibile e sbagliato | Domanda aperta |
| Volumi come media | Dimensionamento errato | Tenant più grande |
| Ambito escluso omesso | Moduli generati inutilmente | Sezione obbligatoria |

---

## Checklist

- [ ] Ho verificato ogni voce eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 1](../workflows/02-phase-analysis.md) · [Business Analyst Agent](../agents/02-business-analyst-agent.md)
- [Project Brief](../docs/06-reference/01-project-brief-template.md)
- [Glossario](../docs/00-introduction/06-glossary.md)
