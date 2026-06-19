<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Managers\Manager;
use Symfony\Component\Mailer\SentMessage;

beforeEach(function () {
    $this->manager = resolve(Manager::class);

    $this->job = new SendCampaignEmail(
        campaign: new Campaign(
            uuid: 'd58284c9-4e49-4c07-a26c-d220ce62b5ec',
            subject: 'Test Campaign',
            content: 'Hello ! \n How are you?',
            fromName: 'Unit Testing',
            fromAddress: 'unit@testing.tld',
        ),
        recipient: new CampaignRecipient(
            uuid: '629b33c5-8160-436b-bb33-02866471cfa6',
            name: 'Jane Doe',
            reachableAt: 'jane@doe.tld',
        )
    );
});

it('does not send email when campaign and its batch is canceled', function () {
    $this->job->withFakeBatch(
        cancelledAt: CarbonImmutable::now(),
    );

    Mail::fake();

    $this->job->handle($this->manager);

    Mail::assertNothingOutgoing();
});

it('sends email and marks recipient as processed', function () {
    $this->job->withFakeBatch();

    $this->job->handle($this->manager);

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
});

it('marks recipient as failed when sending mail fails', function () {
    $this->job->withFakeBatch();

    Mail::shouldReceive('html')->andThrow(Exception::class, 'Something happened');

    $this->job->handle($this->manager);

    $recipient = $this->manager->findRecipient(
        campaignUuid: 'd58284c9-4e49-4c07-a26c-d220ce62b5ec',
        recipientUuid: '629b33c5-8160-436b-bb33-02866471cfa6'
    );

    expect($recipient)
        ->hasBeenProcessed->toBeFalse()
        ->errorOccured->toBeTrue()
        ->errorMessage->toBe('Something happened');
});
