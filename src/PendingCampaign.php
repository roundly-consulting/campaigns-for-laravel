<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\Managers\Manager;

final class PendingCampaign
{
    private string $uuid;

    private string $fromName = '';

    private string $fromAddress = '';

    /** @var list<CampaignRecipient> */
    private array $recipients = [];

    public function __construct(
        private readonly Manager $manager,
        private string $subject,
        private string $content,
    ) {
        $this->uuid = (string) Str::uuid();
    }

    public function uuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function from(string $address, ?string $name = null): self
    {
        $this->fromAddress = $address;
        $this->fromName = $name ?? $address;

        return $this;
    }

    /**
     * Add one or more recipients. Accepts an email/route string, a
     * CampaignRecipient, or an iterable of either. Calls are additive.
     *
     * @param  string|CampaignRecipient|iterable<mixed>  $recipients
     */
    public function to(string|CampaignRecipient|iterable $recipients): self
    {
        if (is_string($recipients) || $recipients instanceof CampaignRecipient) {
            $this->recipients[] = $this->normalise($recipients);

            return $this;
        }

        foreach ($recipients as $recipient) {
            $this->recipients[] = $this->normalise($recipient);
        }

        return $this;
    }

    /**
     * Prepare the campaign (create the batch, set it to Pending) without sending.
     */
    public function prepare(): Campaign
    {
        $campaign = $this->buildCampaign();

        $this->manager->prepare($campaign);

        if ($this->recipients !== []) {
            $this->manager->pushRecipientsToCampaign($this->uuid, $this->recipients);
        }

        return $this->manager->findOrFail($this->uuid);
    }

    /**
     * Prepare and start the campaign in one call.
     */
    public function dispatch(): Campaign
    {
        $this->prepare();

        $this->manager->start($this->uuid);

        return $this->manager->findOrFail($this->uuid);
    }

    private function buildCampaign(): Campaign
    {
        return new Campaign(
            uuid: $this->uuid,
            subject: $this->subject,
            content: $this->content,
            fromName: $this->fromName,
            fromAddress: $this->fromAddress,
        );
    }

    private function normalise(mixed $recipient): CampaignRecipient
    {
        if ($recipient instanceof CampaignRecipient) {
            return $recipient;
        }

        if (is_string($recipient)) {
            return new CampaignRecipient(
                uuid: (string) Str::uuid(),
                name: $recipient,
                reachableAt: $recipient,
            );
        }

        throw new \InvalidArgumentException(
            'Recipients must be a string or a CampaignRecipient instance.'
        );
    }
}
