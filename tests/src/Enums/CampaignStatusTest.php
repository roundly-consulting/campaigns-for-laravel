<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

it('reports terminal statuses', function (): void {
    expect(CampaignStatus::Completed->isTerminal())->toBeTrue()
        ->and(CampaignStatus::Failed->isTerminal())->toBeTrue()
        ->and(CampaignStatus::Canceled->isTerminal())->toBeTrue();
});

it('reports non-terminal statuses', function (): void {
    expect(CampaignStatus::Created->isTerminal())->toBeFalse()
        ->and(CampaignStatus::Pending->isTerminal())->toBeFalse()
        ->and(CampaignStatus::Processing->isTerminal())->toBeFalse();
});

it('returns a translated label', function (): void {
    expect(CampaignStatus::Processing->label())->toBe('Processing');
});

it('exposes the enums helper values and names', function (): void {
    expect(CampaignStatus::values()->all())
        ->toBe(['Created', 'Pending', 'Processing', 'Completed', 'Failed', 'Canceled'])
        ->and(CampaignStatus::names()->all())
        ->toBe(['Created', 'Pending', 'Processing', 'Completed', 'Failed', 'Canceled']);
});

it('exposes readable labels for every case', function (): void {
    expect(CampaignStatus::labels()->all())
        ->toBe(['Created', 'Pending', 'Processing', 'Completed', 'Failed', 'Canceled']);

    foreach (CampaignStatus::cases() as $case) {
        expect($case->readable())->toBe($case->label());
    }
});

it('builds select options and a value map', function (): void {
    expect(CampaignStatus::toOptions()->all())->toBe([
        'Created' => 'Created',
        'Pending' => 'Pending',
        'Processing' => 'Processing',
        'Completed' => 'Completed',
        'Failed' => 'Failed',
        'Canceled' => 'Canceled',
    ]);

    $options = CampaignStatus::options();

    expect($options)->toHaveCount(6)
        ->and($options->first()->value)->toBe('Created')
        ->and($options->first()->label)->toBe('Created')
        ->and($options->first()->name)->toBe('Created');
});

it('builds a validation rule from every value', function (): void {
    expect(CampaignStatus::validationRule())
        ->toBe('in:Created,Pending,Processing,Completed,Failed,Canceled');
});

it('resolves cases by name and value', function (): void {
    expect(CampaignStatus::tryFromName('Completed'))->toBe(CampaignStatus::Completed)
        ->and(CampaignStatus::tryFromName('nope'))->toBeNull()
        ->and(CampaignStatus::hasValue('Pending'))->toBeTrue()
        ->and(CampaignStatus::hasValue('nope'))->toBeFalse();
});

it('renders labels without any campaigns translation seam', function (): void {
    // The hand-rolled campaigns::campaigns.status lang file was removed; the
    // trait must still produce a human label from the case value alone.
    app('translator')->setLoaded([]);

    expect(CampaignStatus::Canceled->label())->toBe('Canceled');
});
