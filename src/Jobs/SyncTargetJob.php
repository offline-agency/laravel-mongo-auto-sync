<?php

namespace OfflineAgency\MongoAutoSync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use OfflineAgency\MongoAutoSync\Observers\MongoAutoSyncObserver;

/**
 * Keeps the mini model of a source document in sync on the target documents of one relation.
 *
 * The payload carries ids only (no SerializesModels): the source is reloaded when the job runs, so the job
 * reflects the latest stored state and can run more than once, or out of order, with the same result.
 */
final class SyncTargetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  class-string<MDModel>  $sourceClass
     * @param  array<string, mixed>  $relation  one entry of getMongoRelation()
     * @param  list<string>  $currentTargetIds  targets that must contain the source mini model (upsert)
     * @param  list<string>  $removedTargetIds  targets that must no longer contain it
     * @param  array<string, mixed>  $requestData
     * @param  array<string, mixed>  $targetAdditionalData
     */
    public function __construct(
        public readonly string $sourceClass,
        public readonly string $sourceId,
        public readonly string $method,
        public readonly array $relation,
        public readonly array $currentTargetIds,
        public readonly array $removedTargetIds = [],
        public readonly array $requestData = [],
        public readonly array $targetAdditionalData = [],
    ) {}

    /**
     * @param  array<string, mixed>  $relation
     * @param  list<string>  $currentTargetIds
     * @param  list<string>  $removedTargetIds
     */
    public static function dispatchFor(MDModel $source, string $method, array $relation, array $currentTargetIds, array $removedTargetIds): PendingDispatch
    {
        $request = $source->getRequest();

        $job = new self(
            $source::class,
            (string) $source->getKey(),
            $method,
            $relation,
            array_values($currentTargetIds),
            array_values($removedTargetIds),
            $request === null ? [] : $request->input(),
            $source->getTargetAdditionalData(),
        );

        $connection = config('laravel-mongo-auto-sync.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        $queue = config('laravel-mongo-auto-sync.queue_name');
        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        return new PendingDispatch($job);
    }

    public function handle(): void
    {
        MongoAutoSyncObserver::withoutDispatching(fn () => $this->sync());
    }

    private function sync(): void
    {
        /** @var class-string<MDModel> $modelTarget */
        $modelTarget = $this->relation['modelTarget'];
        $methodOnTarget = (string) $this->relation['methodOnTarget'];
        $modelOnTarget = (string) $this->relation['modelOnTarget'];
        $typeOnTarget = Arr::get($this->relation, 'typeOnTarget', 'EmbedsMany');
        $is_EO_target = SyncHelper::is_EO($typeOnTarget);
        $is_EM_target = SyncHelper::is_EM($typeOnTarget);

        $source = $this->sourceClass::find($this->sourceId);
        $source = $source instanceof MDModel ? $source : null;

        // Trust the stored source over the payload: a stale job must not re-add a reference removed meanwhile.
        $stillReferenced = $source === null ? [] : SyncHelper::refIds(Arr::get($source->getAttributes(), $this->method));
        $upsertIds = array_values(array_intersect($this->currentTargetIds, $stillReferenced));
        $removeIds = array_values(array_diff(array_unique([...$this->currentTargetIds, ...$this->removedTargetIds]), $stillReferenced));

        if ($upsertIds === [] && $removeIds === []) {
            return;
        }

        $targets = $modelTarget::query()
            ->whereIn('id', [...$upsertIds, ...$removeIds])
            ->get()
            ->keyBy(fn (MDModel $target) => (string) $target->getKey());

        // 1. removals first
        foreach ($removeIds as $id) {
            $target = $targets->get($id);
            if ($target instanceof MDModel) {
                $target->removeRelationWithSync($methodOnTarget, $this->sourceId, $is_EO_target, $is_EM_target);
            }
        }

        if ($source === null || $upsertIds === []) {
            return;
        }

        // 2. upserts with the mini model built as the synchronous path does
        $request = new Request;
        $request->merge($this->requestData);
        $source->setRequest($request, []);
        $source->setTargetAdditionalData($this->targetAdditionalData);
        $source->setMiniModels();
        $miniModel = $source->getEmbedModel($modelOnTarget);

        foreach ($upsertIds as $id) {
            $target = $targets->get($id);
            if ($target instanceof MDModel) {
                $target->updateRelationWithSync($miniModel, $methodOnTarget, $is_EO_target, $is_EM_target);
            }
        }

        // TODO: nested sync (targets of level > 1) is not performed on the background path.
    }
}
