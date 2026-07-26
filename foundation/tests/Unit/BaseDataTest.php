<?php

declare(strict_types=1);

use WidStudios\Foundation\Data\BaseData;

enum Priority: string
{
    case Low = 'low';
    case High = 'high';
}

final readonly class SampleData extends BaseData
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public int $id,
        public string $name,
        public Priority $priority,
        public ?DateTimeImmutable $occurredAt = null,
        public array $tags = [],
    ) {}
}

it('riduce gli enum al loro valore scalare', function (): void {
    $data = new SampleData(id: 1, name: 'ACME', priority: Priority::High);

    expect($data->toArray()['priority'])->toBe('high');
});

it('serializza le date in formato ISO 8601', function (): void {
    $data = new SampleData(
        id: 1,
        name: 'ACME',
        priority: Priority::Low,
        occurredAt: new DateTimeImmutable('2026-07-26T10:30:00+00:00'),
    );

    expect($data->toArray()['occurredAt'])->toBe('2026-07-26T10:30:00+00:00');
});

it('normalizza ricorsivamente gli array', function (): void {
    $data = new SampleData(id: 1, name: 'ACME', priority: Priority::Low, tags: ['a', 'b']);

    expect($data->toArray()['tags'])->toBe(['a', 'b']);
});

it("espone le sole proprieta' pubbliche", function (): void {
    $data = new SampleData(id: 7, name: 'ACME', priority: Priority::Low);

    expect(array_keys($data->toArray()))
        ->toBe(['id', 'name', 'priority', 'occurredAt', 'tags']);
});

it('produce lo stesso risultato in toArray e jsonSerialize', function (): void {
    $data = new SampleData(id: 7, name: 'ACME', priority: Priority::Low);

    expect($data->jsonSerialize())->toBe($data->toArray());
});
