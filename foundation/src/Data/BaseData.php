<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base per i DTO.
 *
 * Un DTO trasporta dati gia' validati attraverso i confini dei livelli. E'
 * readonly perche' un oggetto che cambia mentre attraversa tre livelli non
 * trasporta piu' nulla di affidabile.
 *
 * I costruttori nominati (fromRequest, fromArray, fromImportRow) sono il modo
 * in cui un DTO viene creato: mettono in un punto solo la traduzione da una
 * forma esterna alla forma interna, e permettono di aggiungere una sorgente
 * senza toccare le altre.
 *
 *     final readonly class RegisterMovementData extends BaseData
 *     {
 *         public function __construct(
 *             public int $batchId,
 *             public MovementType $type,
 *             public Quantity $quantity,
 *         ) {}
 *
 *         public static function fromRequest(RegisterMovementRequest $request): self
 *         {
 *             return new self(
 *                 batchId: $request->integer('batch_id'),
 *                 type: MovementType::from($request->string('type')->toString()),
 *                 quantity: new Quantity($request->string('quantity')->toString()),
 *             );
 *         }
 *     }
 *
 * @implements Arrayable<string, mixed>
 */
abstract readonly class BaseData implements Arrayable, JsonSerializable
{
    /**
     * Rappresentazione ad array delle proprieta' pubbliche.
     *
     * I value object e gli enum vengono ridotti al loro valore scalare: un DTO
     * serializzato deve essere leggibile da chi non conosce le classi del
     * dominio.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $properties = (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC);

        $result = [];

        foreach ($properties as $property) {
            $result[$property->getName()] = $this->normalize($property->getValue($this));
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof \Stringable => (string) $value,
            is_array($value) => array_map($this->normalize(...), $value),
            default => $value,
        };
    }
}
