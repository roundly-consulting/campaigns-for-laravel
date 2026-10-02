<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;

beforeEach(function (): void {
    $this->store = new DatabaseCampaignStore;
});

it('answers a non-uuid lookup with nothing, on every engine', function (): void {
    expect($this->store->find('not-a-uuid'))->toBeNull()
        ->and($this->store->findRecipient('not-a-uuid', '00000000-0000-4000-8000-00000000a001'))->toBeNull()
        ->and($this->store->findRecipient('00000000-0000-4000-8000-c00000000001', 'not-a-uuid'))->toBeNull()
        ->and($this->store->recipients('not-a-uuid'))->toHaveCount(0);
});

it('re-saves a soft-deleted campaign instead of inserting a duplicate uuid', function (): void {
    $record = CampaignRecord::factory()->create(['uuid' => '00000000-0000-4000-8000-c00000000001']);
    $record->delete();

    $this->store->save(new Campaign(
        uuid: '00000000-0000-4000-8000-c00000000001',
        subject: 'Again',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    ));

    expect(CampaignRecord::withTrashed()->where('uuid', '00000000-0000-4000-8000-c00000000001')->count())->toBe(1)
        ->and($this->store->find('00000000-0000-4000-8000-c00000000001'))->toBeNull();
});

it('re-saves a soft-deleted recipient instead of inserting a duplicate uuid', function (): void {
    $record = CampaignRecipientRecord::factory()->create([
        'uuid' => '00000000-0000-4000-8000-00000000a001',
        'campaign_uuid' => '00000000-0000-4000-8000-c00000000001',
    ]);
    $record->delete();

    $this->store->saveRecipients('00000000-0000-4000-8000-c00000000001', [
        new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld'),
    ]);

    expect(CampaignRecipientRecord::withTrashed()->where('uuid', '00000000-0000-4000-8000-00000000a001')->count())->toBe(1);
});

it('refuses to insert over a soft-deleted campaign', function (): void {
    CampaignRecord::factory()->create(['uuid' => '00000000-0000-4000-8000-c00000000001', 'subject' => 'Trashed'])->delete();

    expect($this->store->insert(new Campaign(
        uuid: '00000000-0000-4000-8000-c00000000001',
        subject: 'Again',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    )))->toBeFalse()
        ->and(CampaignRecord::withTrashed()->sole()->subject)->toBe('Trashed');
});

it('loses an insert race to the unique index without breaking the caller\'s transaction', function (): void {
    $campaign = new Campaign(
        uuid: '00000000-0000-4000-8000-c00000000001',
        subject: 'Mine',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    );

    // Another process inserts the same uuid right after our existence check, before our write.
    $raced = false;
    DB::listen(function (QueryExecuted $query) use (&$raced): void {
        if (! $raced && str_contains($query->sql, 'exists')) {
            $raced = true;
            CampaignRecord::factory()->create(['uuid' => '00000000-0000-4000-8000-c00000000001', 'subject' => 'Theirs']);
        }
    });

    $won = DB::transaction(function () use ($campaign): bool {
        $won = $this->store->insert($campaign);

        // The enclosing transaction is still usable after the lost race.
        CampaignRecord::factory()->create(['uuid' => '00000000-0000-4000-8000-c00000000002', 'subject' => 'After']);

        return $won;
    });

    expect($won)->toBeFalse()
        ->and(CampaignRecord::query()->orderBy('id')->pluck('subject')->all())->toBe(['Theirs', 'After']);
});
