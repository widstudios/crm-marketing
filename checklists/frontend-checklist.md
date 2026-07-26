# Checklist — Frontend

> Verifica accessibilità, prestazioni, isolamento dei dati nei componenti e assenza di asset esterni.

| | |
|---|---|
| **Fase** | 6 — Frontend |
| **Agente** | [Frontend Agent](../agents/05-frontend-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Verifica accessibilità, prestazioni, isolamento dei dati nei componenti e assenza di asset esterni.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Struttura

- [ ] Lo strumento scelto corrisponde al tipo di interazione (Blade / Livewire / Alpine).
- [ ] Nessun framework SPA introdotto.
- [ ] Riuso tramite componenti Blade, non classi CSS personalizzate.
- [ ] Ogni componente supporta la **modalità scura**.

### Livewire

- [ ] Nessuna logica di business nei componenti.
- [ ] **Ogni metodo pubblico autorizza.**
- [ ] **Nessun dato sensibile nelle proprietà pubbliche.**
- [ ] Proprietà pubbliche con tipi semplici; query in `render()`.
- [ ] `wire:key` su ogni ciclo; `wire:loading` sulle azioni lente.
- [ ] `wire:model.live` solo dove necessario.

### Contenuti

- [ ] Nessun testo scritto direttamente nelle viste: tutto da `__()`.
- [ ] Traduzioni `it` ed `en` complete.
- [ ] Contenuti delle pagine dal **CMS**, non dalle viste.

### Asset

- [ ] **Nessun asset da CDN esterni.**
- [ ] Inclusione con `@vite`, non con percorsi scritti a mano.
- [ ] Immagini ottimizzate, dimensionate, differite sotto la piega.

### SEO

- [ ] Meta tag e canonical per pagina.
- [ ] `hreflang` sulle pagine multilingua.
- [ ] Sitemap generata.
- [ ] `robots.txt` **esclude i domini dei tenant**.
- [ ] Dati strutturati presenti.

### Moduli e registrazione

- [ ] Moduli protetti da limite per indirizzo, campo trappola e tempo minimo.
- [ ] Registrazione con **verifica dell'indirizzo prima del provisioning**.
- [ ] Provisioning eseguito in coda.

### Accessibilità (WCAG 2.1 AA)

- [ ] Gerarchia dei titoli coerente, un solo `h1`.
- [ ] Elementi semantici nativi per azioni e navigazione.
- [ ] Contrasto ≥ 4.5:1, verificato in chiaro **e** in scuro.
- [ ] Nessuna informazione veicolata dal solo colore.
- [ ] Ogni campo con `<label>` associata.
- [ ] Navigazione completa da tastiera; focus sempre visibile.
- [ ] Modali con cattura e restituzione del focus; `Esc` chiude.
- [ ] Aggiornamenti asincroni annunciati con `aria-live`.

### Prestazioni

- [ ] LCP < 2,5 s.
- [ ] CLS < 0,1.
- [ ] Peso JS iniziale < 150 KB compressi.

---

## Comandi di verifica

```bash
npm run build
php tooling/scripts/check-translations.php     # chiavi mancanti
php tooling/scripts/check-external-assets.php  # riferimenti a CDN
```

Verifiche manuali obbligatorie: navigazione completa da **tastiera** su ogni schermata nuova;
contrasto in entrambe le modalità; metriche di prestazione su connessione lenta simulata.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/frontend-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/frontend-checklist.md: N-2/N soddisfatte.

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
| Dati sensibili in proprietà pubbliche | Esposizione nel browser | Solo campi necessari |
| Metodo Livewire senza autorizzazione | Endpoint aperto | Autorizzazione esplicita |
| Asset da CDN | Dipendenza esterna, CSP violata | Asset locali |
| Contenuti scritti nelle viste | Ogni correzione richiede un rilascio | CMS |
| Modalità scura aggiunta dopo | Risultati incoerenti | Da subito |
| Accessibilità rimandata | Rifacimento dell'interfaccia | Verifiche durante |
| Domini tenant indicizzati | Interfacce dei clienti nei motori di ricerca | `robots.txt` |

---

## Checklist

- [ ] Ho verificato ogni voce eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 6](../workflows/07-phase-frontend.md) · [Frontend Agent](../agents/05-frontend-agent.md)
- [Frontend](../rules/frontend.md) · [Livewire](../rules/livewire.md) · [Accessibilità](../rules/accessibility.md)
