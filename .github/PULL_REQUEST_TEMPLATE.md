## Che cosa cambia

<!-- Una o due frasi. Che cosa fa questa modifica, dal punto di vista di chi userà la Factory. -->

## Perché

<!-- Il problema che risolve. Il "cosa" si legge dal diff; il "perché" no, e fra un anno è l'unica
     informazione che serve davvero. -->

## Ambito

- [ ] Documentazione (`docs/`, `architecture/`)
- [ ] Regole (`rules/`, `checklists/`)
- [ ] Agenti e prompt (`agents/`, `prompts/`)
- [ ] Codice riutilizzabile (`foundation/`, `templates/`, `modules/`)
- [ ] Processo (`workflows/`)
- [ ] Infrastruttura (`deployment/`, `tooling/`, `.github/`)

## Decisioni

<!-- Se la modifica tocca il contratto pubblico della Foundation, cambia lo stack o introduce un
     pattern nuovo, serve una ADR. Indicarne il numero, o dichiarare perché non serve. -->

- ADR: <!-- numero, oppure «non necessaria perché …» -->

## Assunzioni e domande aperte

<!-- Le assunzioni fatte, e le domande che restano senza risposta. Una domanda aperta dichiarata
     costa una riga; scoperta a valle costa una riscrittura. -->

## Verifiche eseguite

- [ ] `php tooling/scripts/check-docs.php --all` verde
- [ ] `php tooling/scripts/check-secrets.php` verde
- [ ] `composer qa` verde in `foundation/` (se toccata)
- [ ] Ogni nuovo documento ha le sezioni obbligatorie ed è collegato da un indice
- [ ] Nessun link rotto introdotto
- [ ] `CHANGELOG.md` aggiornato, se la modifica è osservabile da chi usa la Factory

## Impatto sui progetti esistenti

<!-- Nessuno · adozione facoltativa · richiede intervento.
     Se richiede intervento: linkare la guida di migrazione in governance/migrations/. -->
