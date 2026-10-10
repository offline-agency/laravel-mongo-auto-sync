<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONDocument;
use Tests\Models\RefEvent;
use Tests\Models\RefMiniSlot;

/**
 * @param  array<string, mixed>  $data
 */
function traitsStoreEvent(array $data = []): RefEvent
{
    return (new RefEvent)->storeWithSync(new Request, array_merge([
        'name' => 'event '.uniqid(),
        'starts_at' => '2026-01-02 10:00:00',
        'main_slot' => json_encode([['label' => 'main', 'due_at' => '2026-01-03 09:30:00', 'updated_at' => null]]),
        'slots' => json_encode([['label' => 's1', 'due_at' => '', 'updated_at' => null]]),
    ], $data));
}

/**
 * The document as stored in MongoDB (the driver hydrates BSON dates as Carbon when reading through a model).
 */
function traitsRawEvent(string $id): BSONDocument
{
    return DB::connection('mongodb')->getCollection('ref_events')->findOne(['_id' => new ObjectId($id)]);
}

/**
 * @return list<string>
 */
function traitsSlotLabels(RefEvent $event): array
{
    return array_map(fn (array $slot) => $slot['label'], $event->getAttributes()['slots']);
}

test('storeWithSync stores is-md fields as mongo dates', function () {
    $event = traitsStoreEvent();

    $startsAt = traitsRawEvent($event->id)['starts_at'];
    expect($startsAt)->toBeInstanceOf(UTCDateTime::class);
    expect($startsAt->toDateTime()->format('Y-m-d H:i:s'))->toBe('2026-01-02 10:00:00');
});

test('storeWithSync stores an empty is-md field as null', function () {
    $event = traitsStoreEvent(['starts_at' => '']);

    expect(traitsRawEvent($event->id)['starts_at'])->toBeNull();
});

test('embedded is-md fields are stored as mongo dates and empty ones as null', function () {
    $event = traitsRawEvent(traitsStoreEvent()->id);

    $mainDue = $event['main_slot']['due_at'];
    expect($mainDue)->toBeInstanceOf(UTCDateTime::class);
    expect($mainDue->toDateTime()->format('Y-m-d H:i:s'))->toBe('2026-01-03 09:30:00');
    expect($event['slots'][0]['due_at'])->toBeNull();
});

test('embedded timestamps are set by the package regardless of the input', function () {
    $before = time();
    $event = traitsRawEvent(traitsStoreEvent()->id);

    foreach ([$event['main_slot'], $event['slots'][0]] as $slot) {
        expect($slot['updated_at'])->toBeInstanceOf(UTCDateTime::class);
        expect($slot['updated_at']->toDateTime()->getTimestamp())->toBeGreaterThanOrEqual($before);
    }
});

test('updateWithSync replaces an EmbedsMany relationship without target', function () {
    $event = traitsStoreEvent();

    $updated = RefEvent::find($event->id)->updateWithSync(new Request, [
        'name' => $event->name,
        'starts_at' => '',
        'main_slot' => json_encode([['label' => 'main', 'due_at' => '', 'updated_at' => null]]),
        'slots' => json_encode([
            ['label' => 's2', 'due_at' => '2026-02-01', 'updated_at' => null],
            ['label' => 's3', 'due_at' => '', 'updated_at' => null],
        ]),
    ]);

    $stored = RefEvent::find($event->id);
    expect(traitsSlotLabels($stored))->toBe(['s2', 's3']);
    expect(traitsSlotLabels($updated))->toBe(['s2', 's3']);
    expect(traitsRawEvent($event->id)['starts_at'])->toBeNull();
    expect(traitsRawEvent($event->id)['main_slot']['due_at'])->toBeNull();
});

test('updateWithSync with an empty EmbedsMany relationship clears it', function () {
    $event = traitsStoreEvent();

    RefEvent::find($event->id)->updateWithSync(new Request, [
        'name' => $event->name,
        'starts_at' => '2026-01-02',
        'main_slot' => json_encode([['label' => 'main', 'due_at' => '', 'updated_at' => null]]),
        'slots' => '[]',
    ]);

    expect(RefEvent::find($event->id)->getAttributes()['slots'])->toBe([]);
});

test('processOneEmbeddedRelationship starts the pending EmbedsMany list on a fresh model', function () {
    $event = new RefEvent;

    $event->processOneEmbeddedRelationship(
        new Request,
        (object) ['label' => 'first', 'due_at' => null, 'updated_at' => null],
        'EmbedsMany',
        RefMiniSlot::class,
        'slots',
        '', '', '',
        'add',
        false,
        false,
        true,
        false,
        false,
        0,
        true,
        []
    );

    $pending = (fn () => $this->tempEM)->call($event);
    expect($pending)->toHaveCount(1);
    expect($pending[0]['label'])->toBe('first');
    expect($pending[0]['due_at'])->toBeNull();
});
