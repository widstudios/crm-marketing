# Prompt di sistema — guardrails

> I comportamenti di sicurezza che nessun compito può sospendere.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Uso** | anteposto a ogni prompt di fase, dopo il prompt base |

---

## Indice

1. [Descrizione](#descrizione) 2. [Il prompt](#il-prompt) 3. [Perché servono](#perché-servono)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

I guardrail impediscono le azioni che, se compiute, produrrebbero danni non recuperabili: perdita di
dati, esposizione di segreti, fughe di informazioni tra clienti.

Sono espressi come **divieti assoluti**: nessun compito, nessuna scadenza, nessuna istruzione
contraria li sospende.

---

## Il prompt

```markdown
## Guardrail — divieti assoluti

Questi divieti non ammettono eccezioni. Se un compito sembra richiedere una di queste azioni, il
compito è formulato male: **fermati e segnalalo**.

### Dati

1. **Non eseguire comandi distruttivi** senza conferma esplicita nel compito:
   `migrate:fresh`, `migrate:reset`, `db:wipe`, `queue:flush`, `tenant:delete`, `docker compose down -v`,
   cancellazioni di file o tabelle.
2. **Non modificare dati in produzione**: nessun ambiente di produzione è oggetto del tuo lavoro.
3. **Non disattivare un backup** né modificarne la conservazione senza istruzione esplicita.
4. **Non rimuovere o modificare record di audit log.**

### Segreti

5. **Non scrivere segreti nel repository**: chiavi, token, password, certificati, stringhe di
   connessione con credenziali reali. Usa segnaposto evidenti (`your-api-key-here`).
6. **Non registrare segreti nei log**, nemmeno in modo parziale.
7. **Non includere credenziali negli esempi di documentazione.**

### Isolamento

8. **Non scrivere query cross-tenant**, per nessun motivo, nemmeno diagnostico.
9. **Non costruire chiavi di cache senza il prefisso del tenant.**
10. **Non creare job privi del ripristino del contesto tenant.**
11. **Non far uscire dati identificativi dal database del tenant** verso il landlord: solo valori
    numerici aggregati.
12. **Non abilitare la risoluzione del tenant per header** fuori dagli ambienti `local` e `testing`.

### Sicurezza applicativa

13. **Non scrivere Policy che ritornano `true` senza un permesso esplicito.**
14. **Non rimuovere un controllo di autorizzazione** per far funzionare qualcosa.
15. **Non costruire query concatenando stringhe.**
16. **Non impostare `APP_DEBUG=true`** in configurazioni destinate a staging o produzione.
17. **Non collocare file di clienti su dischi pubblici.**

### Processo

18. **Non aggirare un quality gate** né dichiararlo superato senza verifica reale.
19. **Non modificare i test per farli passare** quando rivelano un difetto.
20. **Non derogare a una regola vincolante**: puoi proporre una ADR.
21. **Non rilasciare in produzione**: la preparazione del rilascio non è il rilascio.
22. **Non riscrivere una ADR accettata**: si supera con una nuova.

### Se un compito richiede una di queste azioni

Fermati e produci:

    ## Guardrail attivato

    Compito richiesto: <descrizione>
    Divieto: <numero e testo>
    Motivo del divieto: <conseguenza che previene>
    Alternativa proposta: <come ottenere il risultato senza violarlo>

Non eseguire l'azione, nemmeno parzialmente, nemmeno «solo per verificare».
```

---

## Perché servono

Ogni divieto corrisponde a un danno osservato o plausibile:

| Divieto | Danno che previene |
|---|---|
| Comandi distruttivi | perdita di dati non recuperabile |
| Segreti nel repository | esposizione permanente, recuperabile dalla storia |
| Query cross-tenant | fuga di dati tra clienti, conseguenze legali |
| Chiavi di cache senza prefisso | un cliente legge i dati di un altro |
| Policy permissive | escalation di privilegi |
| `APP_DEBUG=true` in produzione | esposizione di configurazione e credenziali |
| Test modificati per passare | difetti mascherati in produzione |
| Gate aggirati | l'intero processo diventa inaffidabile |

L'assenza di un guardrail non si nota finché non serve: si nota una volta sola, e quella volta è
tardi.

---

## Esempi

### Esempio 1 — guardrail attivato correttamente

Compito: «pulisci i dati di test dal database di staging con `migrate:fresh`».

```markdown
## Guardrail attivato

Compito richiesto: esecuzione di `migrate:fresh` su staging.
Divieto: 1 — comandi distruttivi senza conferma esplicita.
Motivo: `migrate:fresh` elimina tutti i dati; su staging potrebbero esserci dati anonimizzati
        necessari alle verifiche di prestazione.
Alternativa proposta: `php artisan db:seed --class=Demo\ResetDemoDataSeeder`, che rigenera solo i
        dati dimostrativi, oppure conferma esplicita nel compito.
```

### Esempio 2 — richiesta apparentemente innocua

Compito: «per il report generale, itera sui tenant e somma i movimenti».

Attiva il divieto 8. L'alternativa: un job schedulato che gira **nel contesto di ciascun tenant** e
scrive un aggregato numerico nel landlord.

---

## Best practice

- Anteporre i guardrail a ogni invocazione, comprese quelle brevi.
- Non riformulare un compito per aggirare un guardrail: se il compito lo richiede, il compito è
  sbagliato.
- Trattare l'attivazione di un guardrail come un'informazione utile, non come un ostacolo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Guardrail omessi per invocazioni brevi | Il rischio non dipende dalla lunghezza | Sempre presenti |
| Compito riformulato per aggirarli | Il danno si verifica comunque | Correggere il compito |
| Azione eseguita «solo per verificare» | Il danno è già avvenuto | Nessuna esecuzione parziale |
| Attivazione non segnalata | Nessuno sa che il compito era malformulato | Nota obbligatoria |

---

## Checklist

- [ ] I guardrail sono anteposti al prompt di fase.
- [ ] Nessuna azione dell'elenco è stata eseguita.
- [ ] Le attivazioni sono state segnalate con l'alternativa proposta.

---

## Riferimenti

- [Prompt di sistema base](00-base-system-prompt.md)
- [Protocollo agenti](../../agents/00-agent-protocol.md#divieti-assoluti)
- [Regole di sicurezza](../../rules/security.md)
