# Snippet — verifica del quality gate

> Come si verifica un gate, e cosa significa dichiararlo superato.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Usato da** | tutti gli agenti |

---

## Indice

1. [Descrizione](#descrizione) 2. [Lo snippet](#lo-snippet) 3. [Esempi](#esempi)
4. [Best practice](#best-practice) 5. [Errori comuni](#errori-comuni) 6. [Checklist](#checklist)
7. [Riferimenti](#riferimenti)

---

## Descrizione

Il quality gate è ciò che impedisce a un difetto di propagarsi alle fasi successive, dove costa un
ordine di grandezza in più. Un gate dichiarato superato senza verifica reale rende inutile l'intero
processo: non solo non intercetta il difetto, ma fa credere che sia stato cercato.

---

## Lo snippet

```markdown
## Verifica del quality gate

Al termine del tuo lavoro, verifica la checklist della tua fase **voce per voce**.

### Come si verifica

Per ogni voce:

1. **Esegui** la verifica indicata: un comando, un'ispezione del codice, una prova funzionale.
2. **Riporta l'esito reale**, non quello atteso.
3. Se la voce non è applicabile, dichiara **perché**.

Non esiste una terza possibilità tra «verificata» e «non verificata». «Presumibilmente a posto» è
«non verificata».

### Formato dell'esito

    ### Quality gate
    - checklists/<nome>.md: 16/18 soddisfatte.

    Voci non soddisfatte:
    - voce 7 (down() implementato): la migration `create_movements_table` non lo implementa.
      Correzione necessaria.
    - voce 11 (indice sui filtri): manca l'indice su `movements.batch_id`, usato dal filtro
      principale dichiarato nel caso d'uso CU-03.

    Voci non applicabili:
    - voce 15 (seeder di prova): questa fase non produce seeder di prova.

### Se il gate fallisce

**Dichiaralo.** Non correggere in silenzio e non riportare un esito positivo.

Un gate fallito è un'informazione utile: dice all'orchestratore che serve un rework, e su cosa.
Un gate falsamente positivo produce un difetto che emerge tre fasi dopo, quando la correzione costa
dieci volte tanto.

### Verifiche eseguibili

Quando la checklist indica un comando, eseguilo e riporta l'output rilevante:

    ### Verifiche eseguite
    - `composer qa`: verde (Pint ok, PHPStan 0 errori, 84 test passati)
    - `php artisan tenants:migrate`: 12 migration, 4,2 s
    - `php artisan test --coverage`: 87% complessivo, Action 100%, Policy 100%

Non riportare l'output completo: riporta ciò che dimostra l'esito.
```

---

## Esempi

### Esempio 1 — gate verificato correttamente

```markdown
### Quality gate
- checklists/backend-checklist.md: 14/14 soddisfatte.
- Voci non soddisfatte: nessuna.

### Verifiche eseguite
- `composer qa`: verde
- `composer test:coverage`: 84% complessivo; App\Application 100%; App\Domain 96%
- Ispezione: tutte le 11 Action sono `final` con il solo metodo `execute()`
```

### Esempio 2 — gate fallito, dichiarato

```markdown
### Quality gate
- checklists/backend-checklist.md: 12/14 soddisfatte.

Voci non soddisfatte:
- voce 8 (eventi dopo il commit): in `RegisterMovementAction` l'evento è emesso dentro
  `DB::transaction()`. Correzione: spostare l'emissione dopo il blocco.
- voce 12 (copertura Action al 100%): `ArchiveSupplierAction` è al 78%; manca il test del
  percorso con movimenti recenti.

Richiedo rework su questi due punti.
```

L'agente ha trovato i propri difetti e li ha dichiarati: è il comportamento corretto.

---

## Best practice

- Verificare mentre si lavora, non solo alla fine: le correzioni costano meno.
- Eseguire davvero i comandi indicati: la verifica «a memoria» non è una verifica.
- Dichiarare le voci non applicabili con la motivazione.
- Considerare un gate fallito un esito utile, non un fallimento personale.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Gate spuntato senza verifica | Il processo diventa inaffidabile | Verifica voce per voce |
| Esito atteso riportato al posto di quello reale | Difetti che emergono a valle | Esito reale |
| Correzione silenziosa del gate fallito | L'orchestratore non sa cosa è successo | Dichiararlo |
| Voci non applicabili omesse | Non si distingue da «non verificata» | Dichiarare la motivazione |
| Output completo dei comandi | Rumore | Solo ciò che dimostra l'esito |

---

## Checklist

- [ ] Ho verificato ogni voce del gate.
- [ ] Ho eseguito i comandi indicati e riportato l'esito reale.
- [ ] Ho dichiarato le voci non soddisfatte, con la correzione necessaria.
- [ ] Ho dichiarato le voci non applicabili, con la motivazione.

---

## Riferimenti

- [Checklist](../../checklists/README.md)
- [Protocollo agenti](../../agents/00-agent-protocol.md#quality-gate)
- [Master workflow](../../workflows/00-master-workflow.md)
