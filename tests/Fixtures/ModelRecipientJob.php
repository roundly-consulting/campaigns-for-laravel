<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests\Fixtures;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\ProcessesCampaignRecipient;

/**
 * A host delivery job that delivers to the model a recipient stands for — what a host's
 * in-app notification job looks like. It records the model it resolved.
 */
final class ModelRecipientJob implements ProcessesCampaignRecipient, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var list<array{model: Model|null, meta: array<string, mixed>|null, reachableAt: string|null}> */
    public static array $delivered = [];

    public function __construct(public Campaign $campaign, public CampaignRecipient $recipient) {}

    public function handle(CampaignManager $campaigns): void
    {
        $model = $this->recipient->model();

        self::$delivered[] = ['model' => $model, 'meta' => $this->recipient->meta, 'reachableAt' => $this->recipient->reachableAt];

        $model === null
            ? $campaigns->campaign($this->campaign)->markFailed($this->recipient, 'model gone')
            : $campaigns->campaign($this->campaign)->markProcessed($this->recipient);
    }
}
