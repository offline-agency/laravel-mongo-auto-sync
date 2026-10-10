<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use OfflineAgency\MongoAutoSync\Context\SyncContext;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Exceptions\MongoAutoSyncException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use OfflineAgency\MongoAutoSync\Observers\MongoAutoSyncObserver;
use OfflineAgency\MongoAutoSync\Relationships\ReferencedWritePlanner;
use stdClass;

trait MainMongoTrait
{
    /**
     * @return void
     */
    public static function bootMainMongoTrait()
    {
        // observe() instantiates the model: it must run after booting (Laravel 13 forbids it during boot)
        static::whenBooted(fn () => static::observe(MongoAutoSyncObserver::class));
    }

    /**
     * @var bool
     */
    protected $has_partial_request;

    /**
     * Ownership conditions added to the filter of this instance's update and delete queries (referenced children
     * written on behalf of a parent, see withWriteConstraint()).
     *
     * @var list<EloquentBuilder<MDModel>>
     */
    private array $writeConstraints = [];

    /**
     * Number of withoutTargetSync() scopes open on this instance.
     */
    private int $targetSyncDisabledDepth = 0;

    /**
     * Number of withCoalescedTargetSync() scopes open on this instance.
     */
    private int $coalescingDepth = 0;

    /**
     * Target sync recorded by the observer while coalescing, per relation: the union of the destinations to
     * clean and whether a save changed the source.
     *
     * @var array<string, array{relation: array<string, mixed>, removed: list<string>, changed: bool}>
     */
    private array $pendingTargetSync = [];

    /**
     * @var Request|null
     */
    protected $request;

    /**
     * @var array<string, mixed>|null
     */
    protected $target_additional_data;

    /**
     * @var Request|null
     */
    protected $partial_generated_request;

    /**
     * @var array<string, mixed>|null
     */
    protected $options;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected $tempEM;

    /**
     * @param  array<string, mixed>  $additionalData
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $target_additional_data
     * @return static
     *
     * @throws MongoAutoSyncException
     */
    public function storeWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        $this->logSyncDebug('storeWithSync started for model: '.get_class($this));

        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->validateRelationshipRequest($request, $this->getHasPartialRequest());
        $this->withTargetSyncOption($options, fn () => $this->withCoalescedTargetSync(function () use ($request, $options) {
            $this->storeEditAllItems($request, 'add', $options);
            $this->processAllRelationships($request, 'add', '', '', $options);
        }));

        // Dispatch the creation event
        $this->fireModelEvent('storeWithSync');

        $fresh = $this->fresh();
        if ($fresh === null) {
            return $this;
        }

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $target_additional_data
     * @return static
     *
     * @throws MongoAutoSyncException
     */
    public function updateWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        $this->logSyncDebug('updateWithSync started for model: '.get_class($this).' with ID: '.SyncHelper::toString($this->getKey()));

        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->validateRelationshipRequest($request, $this->getHasPartialRequest());
        $this->withTargetSyncOption($options, fn () => $this->withCoalescedTargetSync(function () use ($request, $options) {
            $this->storeEditAllItems($request, 'update', $options);
            $this->processAllRelationships($request, 'update', '', '', $options);
        }));

        // Dispatch the update event
        $this->fireModelEvent('updateWithSync');

        $fresh = $this->fresh();
        if ($fresh === null) {
            return $this;
        }

        return $fresh;
    }

    /**
     * Run $operation with the target sync of THIS model instance disabled: the source and its own (embedded and
     * referenced) relationships are written and every Eloquent / package event fires, but no target document is
     * written and no SyncTargetJob is dispatched for this instance (plain save() included). Other models saved
     * inside the operation keep syncing; scopes nest and are restored after exceptions. Jobs queued by earlier
     * operations are not cancelled.
     *
     * @template TReturn
     *
     * @param  callable(static): TReturn  $operation
     * @return TReturn
     */
    public function withoutTargetSync(callable $operation): mixed
    {
        $this->targetSyncDisabledDepth++;

        try {
            return $operation($this);
        } finally {
            $this->targetSyncDisabledDepth--;
        }
    }

    /**
     * Run $operation adding the conditions of $owner (a query of the relation that owns this model) to the filter
     * of every update and delete of this instance: when the model no longer satisfies them, the write matches no
     * document instead of overwriting or deleting it.
     *
     * @template TReturn
     *
     * @param  EloquentBuilder<MDModel>  $owner
     * @param  callable(static): TReturn  $operation
     * @return TReturn
     */
    public function withWriteConstraint(EloquentBuilder $owner, callable $operation): mixed
    {
        $this->writeConstraints[] = $owner;

        try {
            return $operation($this);
        } finally {
            array_pop($this->writeConstraints);
        }
    }

    /**
     * @param  EloquentBuilder<static>  $query
     * @return EloquentBuilder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        parent::setKeysForSaveQuery($query);
        foreach ($this->writeConstraints as $owner) {
            $query->mergeConstraintsFrom($owner);
        }

        return $query;
    }

    public function targetSyncEnabled(): bool
    {
        return $this->targetSyncDisabledDepth === 0;
    }

    /**
     * Run $operation dispatching at most one SyncTargetJob per relation of THIS instance (background sync): the
     * saves made inside are recorded and, when the outermost scope ends, one job per relation carries the
     * destinations of the final stored state and the union of every destination left on the way. storeWithSync()
     * and updateWithSync() run in such a scope. When $operation throws, the work already persisted is still
     * dispatched before the exception is rethrown. The record lives in memory only: it is not a durable outbox.
     *
     * @template TReturn
     *
     * @param  callable(static): TReturn  $operation
     * @return TReturn
     */
    public function withCoalescedTargetSync(callable $operation): mixed
    {
        $this->coalescingDepth++;

        try {
            return $operation($this);
        } finally {
            if (--$this->coalescingDepth === 0) {
                $this->flushCoalescedTargetSync();
            }
        }
    }

    public function isCoalescingTargetSync(): bool
    {
        return $this->coalescingDepth > 0;
    }

    /**
     * Called by the observer instead of dispatching while a coalescing scope is open.
     *
     * @param  array<string, mixed>  $relation
     * @param  list<string>  $removed
     */
    public function recordTargetSync(string $method, array $relation, array $removed, bool $changed): void
    {
        $pending = $this->pendingTargetSync[$method] ?? ['relation' => $relation, 'removed' => [], 'changed' => false];
        $this->pendingTargetSync[$method] = [
            'relation' => $relation,
            'removed' => array_values(array_unique([...$pending['removed'], ...$removed])),
            'changed' => $pending['changed'] || $changed,
        ];
    }

    private function flushCoalescedTargetSync(): void
    {
        $pending = $this->pendingTargetSync;
        $this->pendingTargetSync = [];

        foreach ($pending as $method => $entry) {
            $current = SyncHelper::refIds(Arr::get($this->getAttributes(), $method));
            $removed = array_values(array_diff($entry['removed'], $current));
            if ($removed !== [] || ($entry['changed'] && $current !== [])) {
                SyncTargetJob::dispatchFor($this, $method, $entry['relation'], $current, $removed);
            }
        }
    }

    /**
     * Run $operation honouring the 'sync_targets' option of storeWithSync() / updateWithSync() (default true).
     *
     * @param  array<string, mixed>  $options
     *
     * @throws InvalidConfigurationException
     */
    private function withTargetSyncOption(array $options, callable $operation): void
    {
        $syncTargets = $options['sync_targets'] ?? true;
        if (! is_bool($syncTargets)) {
            throw new InvalidConfigurationException('The sync_targets option must be a boolean.');
        }

        $syncTargets ? $operation() : $this->withoutTargetSync($operation);
    }

    private function logSyncDebug(string $message): void
    {
        if (config('laravel-mongo-auto-sync.logging')) {
            Log::debug($message);
        }
    }

    /**
     * Checks the merged request (body + additionalData) before anything is written or dispatched:
     * a full request must contain every top-level relationship key (an explicit null, "" or "[]" counts
     * as present), and relationship payloads must hold a list of documents that reference documents only
     * by a plain id.
     * Partial requests leave omitted relationships unchanged. This is not a recursive validation of
     * nested relationships.
     *
     * @throws InvalidRequestException
     */
    private function validateRelationshipRequest(Request $request, bool $partial): void
    {
        foreach ($this->getMongoRelation() as $method => $relation) {
            $method = (string) $method;
            if (! $request->has($method)) {
                if (! $partial) {
                    throw new InvalidRequestException("Missing relationship '{$method}' in the request.");
                }

                continue;
            }
            $value = $request->input($method);
            SyncHelper::assertSafeRefIds($method, $value, SyncHelper::idKeysFor($relation['type'] ?? null));
            $documents = $value === null ? [] : SyncHelper::payloadDocuments($method, $value);
            $type = SyncHelper::toString($relation['type'] ?? null);
            if (SyncHelper::is_HM($type) || SyncHelper::is_HO($type)) {
                // reads only: ownership, writable fields, ids and cardinality are checked before anything is written
                ReferencedWritePlanner::plan($this, $method, $relation, $documents);
            }
        }
    }

    /**
     * Check, without writing, the full storeWithSync() / updateWithSync() request a parent sends to this model
     * as a referenced child: every item and every top-level relationship key present, and every relationship
     * payload valid (nested referenced relationships included).
     *
     * @throws InvalidRequestException
     * @throws InvalidConfigurationException
     */
    public function assertFullRequestIsValid(Request $request): void
    {
        foreach (array_keys($this->getItems()) as $key) {
            $this->checkRequestExistence($request, $key);
        }
        $this->validateRelationshipRequest($request, false);
    }

    /**
     * @param  bool  $syncTargets
     * @return $this
     */
    public function destroyWithSync($syncTargets = true)
    {
        // Get the relation info
        $relations = $this->getMongoRelation();
        // validated for every relationship before anything is deleted
        $deletesChildren = array_map(fn (array $relation) => SyncHelper::deletesReferencedChildren($relation), $relations);

        // Process all relationships
        foreach ($relations as $method => $relation) {
            // Get Relation Save Mode
            $type = $relation['type'];
            $hasTarget = SyncHelper::hasTarget($relation);
            if (SyncHelper::is_HM($type) || SyncHelper::is_HO($type)) {
                // referenced children follow their onParentDelete policy; destroyWithSync(false) never deletes them
                if ($syncTargets && $deletesChildren[$method]) {
                    $this->deleteReferencedObj($method, $relation);
                }

                continue;
            }
            if ($hasTarget && $syncTargets) {
                $modelTarget = SyncHelper::toString($relation['modelTarget']);
                $methodOnTarget = SyncHelper::toString($relation['methodOnTarget']);
                $modelOnTarget = $relation['modelOnTarget'];
                $typeOnTarget = Arr::has($relation, 'typeOnTarget') ? Arr::get($relation, 'typeOnTarget') : 'EmbedsMany';

                $is_EO = SyncHelper::is_EO($type);
                $is_EM = SyncHelper::is_EM($type);

                $is_EO_target = SyncHelper::is_EO($typeOnTarget);
                $is_EM_target = SyncHelper::is_EM($typeOnTarget);

                if ($is_EO || $is_EM) {// EmbedsOne Create - EmbedsMany Create
                    // Delete EmbedsMany or EmbedsOne on Target
                    $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO, $is_EO_target, $is_EM_target);
                }
            }
        }
        // Delete current object (without background sync of the targets when $syncTargets is false)
        if ($syncTargets) {
            $this->delete();
        } else {
            $this->withoutTargetSync(fn () => $this->delete());
        }

        // Dispatch the destroy event
        $this->fireModelEvent('destroyWithSync');

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return bool|mixed
     */
    private function getOptionValue(array $options, string $key)
    {
        return Arr::has($options, $key) ? $options[$key] : '';
    }

    /**
     * @param  object  $obj
     * @param  string  $method
     * @param  string  $model
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function checkPropertyExistence($obj, string $EOkey, $method = '', $model = '')
    {
        if (! property_exists($obj, $EOkey)) {
            // the object is not part of the message: it is request payload and may hold personal data
            $msg = 'Error - '.$EOkey.' attribute not found in relationship '.($method === '' ? '(unknown)' : "'{$method}'").($model === '' ? '' : ' of '.$model).'.';
            throw new InvalidConfigurationException($msg);
        }
    }

    /**
     * @param  array<string, mixed>  $arr
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function checkArrayExistence($arr, string $key)
    {
        if (! Arr::has($arr, $key)) {
            $msg = 'Error - '.$key.' attribute not found in the given array.';
            throw new InvalidConfigurationException($msg);
        }
    }

    /**
     * @return void
     *
     * @throws InvalidRequestException
     */
    private function checkRequestExistence(Request $request, string $key)
    {
        if (! $request->has($key)) {
            $msg = "Error - '{$key}' attribute not found in the request.";
            throw new InvalidRequestException($msg);
        }
    }

    /**
     * @param  bool  $request_has_key
     * @param  bool  $hasTarget
     * @return bool
     */
    public function getIsSkippable($request_has_key, $hasTarget = false)
    {
        return ! $request_has_key && $this->getHasPartialRequest() && ! $hasTarget;
    }

    /**
     * @return bool
     */
    public function getHasPartialRequest()
    {
        return $this->has_partial_request;
    }

    public function setHasPartialRequest(): void
    {
        $this->has_partial_request = $this->getOptionValue(
            $this->getOptions(),
            'request_type'
        ) == 'partial';
    }

    /**
     * @return MDModel|null
     *
     * @throws InvalidConfigurationException
     */
    private function getModelTobeSync(string $modelTarget, stdClass $obj)
    {
        $this->checkPropertyExistence($obj, 'ref_id');
        $target_id = $obj->ref_id;

        // Init the Target Model
        /** @var MDModel $modelToBeSync */
        $modelToBeSync = SyncContext::targetModel(static::class, SyncContext::connectionName($this), $modelTarget);

        $found = $modelToBeSync->find($target_id);
        if ($found instanceof MDModel) {
            return $found;
        }

        return null;
    }

    /**
     * @return mixed
     *
     * @throws InvalidRequestException
     */
    private function getRelationshipRequest(string $key, Request $request)
    {
        $this->checkRequestExistence(
            $request,
            $key
        );

        return $request->input($key);
    }

    /**
     * @return Request|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function setRequest(Request $request, array $additionalData): void
    {
        $request = $request->merge(self::plainObjectsToArrays($additionalData));
        $this->request = $request;
    }

    /**
     * Symfony's InputBag rejects stdClass values: store them as arrays (same BSON document once saved).
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function plainObjectsToArrays(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof stdClass) {
                $value = (array) $value;
            }
            if (is_array($value)) {
                $value = self::plainObjectsToArrays($value);
            }
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * @return Request|null
     */
    public function getPartialGeneratedRequest()
    {
        return $this->partial_generated_request;
    }

    /**
     * @param  array<string, mixed>  $arr
     */
    public function setPartialGeneratedRequest(array $arr): void
    {
        $partial_generated_request = new Request;
        $partial_generated_request->merge($arr);

        $this->partial_generated_request = $partial_generated_request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions()
    {
        if ($this->options === null) {
            return [];
        }

        return $this->options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTargetAdditionalData()
    {
        if ($this->target_additional_data === null) {
            return [];
        }

        return $this->target_additional_data;
    }

    /**
     * @param  array<string, mixed>  $target_additional_data
     */
    public function setTargetAdditionalData($target_additional_data): void
    {
        $this->target_additional_data = $target_additional_data;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $target_additional_data
     * @return void
     */
    public function initDataForSync(Request $request, array $additionalData, array $options, array $target_additional_data)
    {
        $this->setRequest($request, $additionalData);
        $this->setTargetAdditionalData($target_additional_data);
        $this->setOptions($options);
        $this->setHasPartialRequest();
    }
}
