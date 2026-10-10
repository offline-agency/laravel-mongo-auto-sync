<?php

namespace OfflineAgency\MongoAutoSync\Observers;

use Illuminate\Support\Arr;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;

class MongoAutoSyncObserver
{
    /**
     * True while a SyncTargetJob writes targets: those saves must not dispatch jobs back to the source.
     */
    private static bool $dispatchingSuspended = false;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutDispatching(callable $callback): mixed
    {
        $previous = self::$dispatchingSuspended;
        self::$dispatchingSuspended = true;

        try {
            return $callback();
        } finally {
            self::$dispatchingSuspended = $previous;
        }
    }

    public function saved(MDModel $model): void
    {
        if (! $this->isEnabled() || ! $model->targetSyncEnabled()) {
            return;
        }

        foreach ($this->embeddedRelationsWithTarget($model) as $method => $relation) {
            $current = SyncHelper::refIds(Arr::get($model->getAttributes(), $method));
            $original = SyncHelper::refIds($model->getRawOriginal($method));
            $removed = array_values(array_diff($original, $current));
            $added = array_values(array_diff($current, $original));

            $mustSync = $removed !== [] || $added !== []
                || ($current !== [] && ($model->wasRecentlyCreated || $model->wasChanged()));

            if ($mustSync) {
                SyncTargetJob::dispatchFor($model, $method, $relation, $current, $removed);
            }
        }
    }

    public function deleted(MDModel $model): void
    {
        if (! $this->isEnabled() || ! $model->targetSyncEnabled()) {
            return;
        }

        foreach ($this->embeddedRelationsWithTarget($model) as $method => $relation) {
            $removed = SyncHelper::refIds(Arr::get($model->getAttributes(), $method));

            if ($removed !== []) {
                SyncTargetJob::dispatchFor($model, $method, $relation, [], $removed);
            }
        }
    }

    private function isEnabled(): bool
    {
        return ! self::$dispatchingSuspended && (bool) config('laravel-mongo-auto-sync.use_background_sync');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function embeddedRelationsWithTarget(MDModel $model): array
    {
        return array_filter(
            $model->getMongoRelation(),
            fn (array $relation) => SyncHelper::hasTarget($relation)
                && (SyncHelper::is_EO($relation['type'] ?? null) || SyncHelper::is_EM($relation['type'] ?? null))
        );
    }
}
