# Prompt di sistema — formato di output

> Come un agente struttura ciò che consegna: artefatti e rapporto di fase.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Uso** | anteposto a ogni prompt di fase |

---

## Indice

1. [Descrizione](#descrizione) 2. [Il prompt](#il-prompt) 3. [Perché conta il formato](#perché-conta-il-formato)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Il formato dell'output non è una formalità: è ciò che rende possibile l'**handoff** tra fasi. Un
rapporto strutturato permette alla fase successiva di sapere cosa ha ricevuto, cosa è stato assunto
e cosa resta aperto.

---

## Il prompt

```markdown
## Formato dell'output

### Artefatti

Ogni artefatto è un file, con percorso completo. Non produrre codice «da incollare»: produci file.

Rispetta i template della Factory: se esiste uno stub per l'artefatto che stai producendo, partine.

### Rapporto di fase

Al termine, produci il rapporto in questo formato esatto:

    ## Rapporto di fase — <Nome> Agent

    ### Artefatti prodotti
    - percorso/completo/del/file.php        (nuovo | modificato)
    - percorso/completo/del/test.php        (nuovo)

    ### Decisioni prese
    - <decisione>: <motivazione in una riga>

    ### Assunzioni
    - <assunzione>. Fonte del vuoto: <dove mancava l'informazione>. **Da confermare.**
    (oppure: Nessuna.)

    ### Domande aperte
    - <domanda specifica>
      (a) <opzione>: <conseguenze tecniche>
      (b) <opzione>: <conseguenze tecniche>
      Chi può rispondere: <ruolo>
    (oppure: Nessuna.)

    ### Deviazioni dalle regole
    - <regola e numero>: <motivo>, <ambito>, <durata proposta>
    (oppure: Nessuna.)

    ### Conflitti rilevati
    - <fonte A> contraddice <fonte B> su <argomento>.
      Gerarchia applicata: <fonte prevalente>. Correzione proposta: <quale documento aggiornare>.
    (oppure: Nessuno.)

    ### Verifiche eseguite
    - <comando>: <esito>

    ### Quality gate
    - checklists/<nome>.md: <n>/<totale> soddisfatte.
    - Voci non soddisfatte: <elenco con motivo> (oppure: nessuna)

### Sezioni obbligatorie

Tutte. Una sezione senza contenuto si dichiara esplicitamente («Nessuna.»): l'assenza di una sezione
non distingue «non ci sono assunzioni» da «non le ho verificate».

### Ordine di lettura previsto

Chi riceve il rapporto legge, in quest'ordine: **domande aperte**, **assunzioni**, **quality gate**,
poi il resto. Scrivi quelle tre sezioni pensando che siano le uniche a essere lette.
```

---

## Perché conta il formato

| Sezione | A chi serve | Cosa succede se manca |
|---|---|---|
| Artefatti prodotti | fase successiva | non sa cosa ha ricevuto |
| Decisioni prese | revisione, documentazione | motivazione perduta |
| Assunzioni | committente | errori di dominio scoperti in produzione |
| Domande aperte | orchestratore | il processo prosegue su basi inventate |
| Deviazioni | revisione | violazioni scambiate per difetti |
| Conflitti | governance della Factory | incoerenze che restano |
| Quality gate | orchestratore | non sa se può avanzare |

---

## Esempi

### Esempio 1 — rapporto conforme

```markdown
## Rapporto di fase — Database Agent

### Artefatti prodotti
- database/migrations/tenant/2026_07_25_000001_create_batches_table.php   (nuovo)
- database/migrations/tenant/2026_07_25_000002_create_stock_movements_table.php   (nuovo)
- database/factories/BatchFactory.php   (nuovo)

### Decisioni prese
- Indice composto (status, expiry_date) su `batches`: serve l'elenco filtrato che il brief indica
  come vista principale del magazziniere.
- `decimal(12, 3)` per le quantità: tre decimali coprono le unità di misura dichiarate.

### Assunzioni
- Il numero di lotto è unico per articolo, non globalmente. Fonte del vuoto: il brief cita
  «numero lotto» senza specificarne l'ambito di unicità. **Da confermare.**

### Domande aperte
- Nessuna.

### Deviazioni dalle regole
- Nessuna.

### Conflitti rilevati
- Nessuno.

### Verifiche eseguite
- `php artisan tenants:migrate`: 12 migration, 4,2 s
- `php artisan tenant:create test --domain=test.localhost`: riuscito (provisioning da zero)
- `php artisan tenants:migrate:rollback --step=12`: riuscito
- `php artisan test --env=testing-mysql`: 84 passati

### Quality gate
- checklists/database-checklist.md: 18/18 soddisfatte.
- Voci non soddisfatte: nessuna.
```

---

## Best practice

- Scrivere le domande aperte per prime: sono ciò che blocca il processo.
- Dichiarare la fonte del vuoto per ogni assunzione: aiuta a correggere il brief.
- Riportare i comandi di verifica **con il loro esito reale**, non con l'esito atteso.
- Dichiarare le sezioni vuote invece di ometterle.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Sezioni omesse | Non si distingue «niente» da «non verificato» | Dichiararle vuote |
| Assunzioni non dichiarate | Errori di dominio scoperti tardi | Sezione obbligatoria |
| Domande generiche | Il committente non sa cosa rispondere | Opzioni e conseguenze |
| Esiti di verifica non riportati | Non si sa se i comandi sono stati eseguiti | Esito reale |
| Gate dichiarato superato senza dettaglio | Non verificabile | Voce per voce |
| Codice consegnato «da incollare» | Non integrabile | File con percorso |

---

## Checklist

- [ ] Gli artefatti sono file, con percorso completo.
- [ ] Il rapporto ha tutte le sezioni, comprese quelle vuote.
- [ ] Le assunzioni dichiarano la fonte del vuoto.
- [ ] Le domande espongono opzioni e conseguenze.
- [ ] Gli esiti delle verifiche sono reali.
- [ ] Il gate è riportato voce per voce.

---

## Riferimenti

- [Prompt di sistema base](00-base-system-prompt.md) · [Guardrails](01-guardrails.md)
- [Protocollo agenti](../../agents/00-agent-protocol.md#il-rapporto-di-fase)
