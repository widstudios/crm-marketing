<?php

declare(strict_types=1);

use WidStudios\Foundation\Tenancy\TenantStatus;

it('consente il traffico solo ai tenant attivi', function (TenantStatus $status, bool $expected): void {
    expect($status->allowsAccess())->toBe($expected);
})->with([
    [TenantStatus::Provisioning, false],
    [TenantStatus::Active, true],
    [TenantStatus::Suspended, false],
    [TenantStatus::Archived, false],
]);

it('elabora anche i tenant sospesi', function (): void {
    // La sospensione riguarda l'accesso, non i dati: un tenant sospeso che non
    // riceve le migration non potrebbe piu' essere riattivato.
    expect(TenantStatus::Suspended->allowsProcessing())->toBeTrue()
        ->and(TenantStatus::Active->allowsProcessing())->toBeTrue()
        ->and(TenantStatus::Provisioning->allowsProcessing())->toBeFalse()
        ->and(TenantStatus::Archived->allowsProcessing())->toBeFalse();
});

it('ammette le transizioni previste', function (): void {
    expect(TenantStatus::Provisioning->canTransitionTo(TenantStatus::Active))->toBeTrue()
        ->and(TenantStatus::Active->canTransitionTo(TenantStatus::Suspended))->toBeTrue()
        ->and(TenantStatus::Suspended->canTransitionTo(TenantStatus::Active))->toBeTrue();
});

it('nega le transizioni non previste', function (): void {
    expect(TenantStatus::Provisioning->canTransitionTo(TenantStatus::Suspended))->toBeFalse()
        ->and(TenantStatus::Active->canTransitionTo(TenantStatus::Provisioning))->toBeFalse();
});

it('non ammette alcuna transizione da archiviato', function (): void {
    // Lo stato terminale e' terminale: riattivare un tenant archiviato e' un
    // provisioning nuovo, non una transizione.
    expect(TenantStatus::Archived->allowedTransitions())->toBe([]);

    foreach (TenantStatus::cases() as $target) {
        expect(TenantStatus::Archived->canTransitionTo($target))->toBeFalse();
    }
});

it("ha un'etichetta per ogni stato", function (TenantStatus $status): void {
    expect($status->label())->not->toBe('');
})->with(TenantStatus::cases());
