<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Actions;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Events\RecipientProcessed;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;

/**
 * Record a successful delivery: the recipient is marked processed (any earlier error is
 * cleared) and RecipientProcessed fires. The given recipient object is updated in place.
 */
final readonly class MarkRecipientProcessedAction
{
    public function __construct(
        private CampaignStore $store,
    ) {}

    /**
     * @throws RecipientNotFound when the recipient belongs to another campaign
     */
    public function execute(Campaign $campaign, CampaignRecipient $recipient): CampaignRecipient
    {
        if (! $recipient->belongsTo($campaign->uuid)) {
            throw RecipientNotFound::inCampaign($campaign->uuid, $recipient->uuid);
        }

        $recipient->hasBeenProcessed = true;
        $recipient->errorOccured = false;
        $recipient->errorMessage = null;

        $this->store->saveRecipients($campaign->uuid, [$recipient]);

        event(new RecipientProcessed($campaign, $recipient));

        return $recipient;
    }
}
