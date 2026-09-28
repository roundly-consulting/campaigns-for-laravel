<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Events\RecipientProcessed;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use Symfony\Component\Mailer\SentMessage;

beforeEach(function () {
    fakeBus();

    $this->campaign = Campaigns::prepare(
        new Campaign(
            uuid: 'd58284c9-4e49-4c07-a26c-d220ce62b5ec',
            subject: 'Test Campaign',
            content: 'Hello ! \n How are you?',
            fromName: 'Unit Testing',
            fromAddress: 'unit@testing.tld',
        ),
        [new CampaignRecipient(uuid: '629b33c5-8160-436b-bb33-02866471cfa6', name: 'Jane Doe', reachableAt: 'jane@doe.tld')],
    );

    $this->job = new SendCampaignEmail(
        campaign: $this->campaign,
        recipient: Campaigns::campaign($this->campaign)->recipient('629b33c5-8160-436b-bb33-02866471cfa6'),
    );
});

it('does not send email when campaign and its batch is canceled', function () {
    $this->job->withFakeBatch(
        cancelledAt: CarbonImmutable::now(),
    );

    Mail::fake();

    $this->job->handle(resolve(CampaignManager::class));

    Mail::assertNothingOutgoing();
});

it('sends email and marks recipient as processed', function () {
    $this->job->withFakeBatch();

    $this->job->handle(resolve(CampaignManager::class));

    $messages = Mail::getSymfonyTransport()->messages();

    /** @var SentMessage $message */
    $message = $messages->first();
    $envelope = $message->getEnvelope();

    $messageParts = iterator_to_array($message->toIterable());

    expect($messages)
        ->toHaveCount(1);

    expect($messageParts[3])->toBe('Hello ! \n How are you?');

    expect($envelope->getSender())
        ->getName()->toBe('Unit Testing')
        ->getAddress()->toBe('unit@testing.tld');

    expect($envelope->getRecipients())
        ->toHaveCount(1)
        ->and($envelope->getRecipients()[0])
        ->getName()->toBe('Jane Doe')
        ->getAddress()->toBe('jane@doe.tld');

    expect(Campaigns::campaign($this->campaign)->recipient('629b33c5-8160-436b-bb33-02866471cfa6')->hasBeenProcessed)->toBeTrue();
});

it('marks recipient as failed when sending mail fails', function () {
    $this->job->withFakeBatch();

    Mail::shouldReceive('html')->andThrow(Exception::class, 'Something happened');

    $this->job->handle(resolve(CampaignManager::class));

    expect(Campaigns::campaign($this->campaign)->recipient('629b33c5-8160-436b-bb33-02866471cfa6'))
        ->hasBeenProcessed->toBeFalse()
        ->errorOccured->toBeTrue()
        ->errorMessage->toBe('Something happened');
});

it('records the outcome in a worker whose in-memory store never saw the campaign', function () {
    Event::fake([RecipientProcessed::class]);

    $foreign = new Campaign(
        uuid: 'worker-only',
        subject: 'Elsewhere',
        content: 'Hi',
        fromName: 'Unit Testing',
        fromAddress: 'unit@testing.tld',
    );

    $job = new SendCampaignEmail(
        campaign: $foreign,
        recipient: new CampaignRecipient(uuid: 'worker-recipient', name: 'Jane', reachableAt: 'jane@doe.tld', campaignUuid: 'worker-only'),
    );
    $job->withFakeBatch();

    $job->handle(resolve(CampaignManager::class));

    expect($job->recipient->hasBeenProcessed)->toBeTrue();
    Event::assertDispatched(RecipientProcessed::class);
});

it('fails the job for a recipient of another campaign', function () {
    $job = new SendCampaignEmail(
        campaign: $this->campaign,
        recipient: new CampaignRecipient(uuid: 'stray', name: 'Stray', reachableAt: 'stray@doe.tld', campaignUuid: 'another-campaign'),
    );
    $job->withFakeBatch();

    expect(fn () => $job->handle(resolve(CampaignManager::class)))->toThrow(RecipientNotFound::class);
});
