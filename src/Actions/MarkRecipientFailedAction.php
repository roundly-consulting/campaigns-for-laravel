<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Actions;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Events\RecipientFailed;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;

/**
 * Record a failed delivery: the recipient is marked failed with the error and
 * RecipientFailed fires. The given recipient object is updated in place.
 */
final readonly class MarkRecipientFailedAction
{
    public function __construct(
        private CampaignStore $store,
    ) {}

    /**
     * @throws RecipientNotFound when the recipient belongs to another campaign
     */
    public function execute(Campaign $campaign, CampaignRecipient $recipient, string $error): CampaignRecipient
    {
        if (! $recipient->belongsTo($campaign->uuid)) {
            throw RecipientNotFound::inCampaign($campaign->uuid, $recipient->uuid);
        }

        $recipient->hasBeenProcessed = false;
        $recipient->errorOccured = true;
        $recipient->errorMessage = $error;

        $this->store->saveRecipients($campaign->uuid, [$recipient]);

        event(new RecipientFailed($campaign, $recipient, $error));

        return $recipient;
    }
}
