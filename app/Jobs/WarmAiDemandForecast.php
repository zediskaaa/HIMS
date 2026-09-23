<?php

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AiDemandForecastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class WarmAiDemandForecast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly int $actorId,
        public readonly int $analysisDays,
        public readonly int $forecastDays,
        public readonly string $lockOwner,
    ) {}

    public function handle(AiDemandForecastService $forecasts): void
    {
        $lock = Cache::restoreLock(
            "demand-forecast:v3:warmup:{$this->analysisDays}:{$this->forecastDays}",
            $this->lockOwner,
        );

        try {
            $actor = User::find($this->actorId);
            $cached = $forecasts->cached($this->analysisDays, $this->forecastDays);

            if ($actor?->status === UserStatus::Active && ($cached['pending'] ?? false) === true) {
                $forecasts->generate($actor, $this->analysisDays, $this->forecastDays);
            }
        } finally {
            $lock->release();
        }
    }
}
