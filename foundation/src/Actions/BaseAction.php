<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Actions;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

/**
 * Base per le Action: una operazione del dominio, invocabile da qualunque punto
 * di ingresso.
 *
 * Un'Action e' final, ha un solo metodo pubblico execute(), e riceve un DTO —
 * mai una Request. Il vincolo non e' formale: e' cio' che rende la stessa
 * operazione utilizzabile da un controller, da un comando, da un job e da
 * un'importazione, senza duplicarla.
 *
 * Che cosa NON fa un'Action (rules/action-pattern.md):
 *
 *   - non autorizza: l'autorizzazione appartiene al punto di ingresso, perche'
 *     un processo di sistema deve poter eseguire l'operazione senza un utente;
 *   - non valida il formato dell'input: lo fa la Form Request; l'Action
 *     verifica le precondizioni di dominio, che sono un'altra cosa;
 *   - non contiene le regole: le regole stanno nelle entita' e nei value
 *     object, altrimenti si duplicano appena l'entita' serve altrove.
 *
 * Che cosa fa: orchestra. Verifica le precondizioni, apre la transazione,
 * invoca il dominio, emette gli eventi dopo il commit.
 *
 * Questa classe base fornisce solo cio' che serve a farlo bene; non impone una
 * firma a execute(), perche' ogni operazione ha il proprio DTO e il proprio
 * tipo di ritorno.
 */
abstract class BaseAction
{
    public function __construct(
        protected readonly DatabaseManager $database,
    ) {}

    /**
     * Esegue il callback in transazione.
     *
     * Nulla che dipenda da un sistema esterno va qui dentro: una chiamata HTTP
     * lenta tiene aperta la transazione e i lock che la accompagnano, e una
     * chiamata fallita lascia il sistema esterno in uno stato che il rollback
     * non annulla (rules/database.md R24).
     *
     * @template TReturn
     *
     * @param  Closure(ConnectionInterface): TReturn  $callback
     * @return TReturn
     */
    protected function transaction(Closure $callback): mixed
    {
        $connection = $this->database->connection();

        return $connection->transaction(static fn (): mixed => $callback($connection));
    }

    /**
     * Registra un effetto da eseguire dopo il commit.
     *
     * Serve per gli eventi e per i job: emessi dentro la transazione, possono
     * partire prima che i dati siano visibili, e il listener non trova cio' che
     * cerca. Il difetto e' intermittente e dipende dal carico, quindi non si
     * riproduce in sviluppo.
     *
     * @param  Closure(): void  $callback
     */
    protected function afterCommit(Closure $callback): void
    {
        $this->database->connection()->afterCommit($callback);
    }
}
