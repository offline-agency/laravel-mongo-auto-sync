<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use Tests\Models\Article;
use Tests\Models\Navigation;
use Tests\Models\SubItem;
use Tests\Models\VldPlain;

const VALIDATION_SECRET = 'sentinel-55-secret';

beforeEach(function () {
    foreach (['navigation', 'sub_items', 'articles', 'categories', 'vld_plains'] as $collection) {
        DB::table($collection)->truncate();
    }
});

function articleFields(): array
{
    return ['autoincrement_id' => 1, 'title' => 't', 'content' => 'c', 'slug' => 's', 'visibility' => 'public', 'status' => 'published', 'is_deleted' => false, 'is_active' => true];
}

function navigationFields(): array
{
    return ['text' => 'nav', 'code' => 'N1', 'href' => 'https://n', 'date' => '2026-01-01', 'target' => '_blank', 'title' => 'Nav'];
}

/** Raw documents of a collection, JSON encoded, to compare before/after. */
function rawDocuments(string $collection): array
{
    return array_map(fn ($doc) => json_encode($doc), DB::connection('mongodb')->getCollection($collection)->find([], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray());
}

dataset('missing relationship keys', [
    'EmbedsOne with target' => [SubItem::class, 'sub_items', fn () => ['text' => 'x', 'code' => 'c', 'href' => 'h'], 'navigation'],
    'EmbedsMany with target' => [Navigation::class, 'navigation', fn () => navigationFields(), 'sub_items'],
    'EmbedsOne without target' => [Article::class, 'articles', fn () => articleFields() + ['categories' => '[]'], 'primarycategory'],
    'EmbedsMany with target (second key)' => [Article::class, 'articles', fn () => articleFields() + ['primarycategory' => '[]'], 'categories'],
]);

test('storeWithSync rejects a full request without a relationship key before writing or dispatching', function (string $class, string $collection, Closure $fields, string $missing, bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $this->createNavigation();
    $targetsBefore = rawDocuments('navigation');
    Queue::fake();

    $error = null;
    try {
        (new $class)->storeWithSync(new Request(['password' => VALIDATION_SECRET]), $fields());
    } catch (InvalidRequestException $e) {
        $error = $e;
    }

    expect($error)->not->toBeNull();
    expect($error->getMessage())->toBe("Missing relationship '{$missing}' in the request.");
    expect(DB::table($collection)->count())->toBe($collection === 'navigation' ? 1 : 0);
    expect(rawDocuments('navigation'))->toBe($targetsBefore);
    Queue::assertNothingPushed();
})->with('missing relationship keys')->with(['sync path' => false, 'background' => true]);

test('updateWithSync rejects a full request without a relationship key and leaves the documents unchanged', function (bool $background) {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['text' => 'original', 'navigation' => $this->getMiniNavigation($navigation->id)]);
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $before = [rawDocuments('sub_items'), rawDocuments('navigation')];
    Queue::fake();

    expect(fn () => $subItem->updateWithSync(new Request(['password' => VALIDATION_SECRET]), ['text' => 'changed', 'code' => 'c', 'href' => 'h']))
        ->toThrow(InvalidRequestException::class, "Missing relationship 'navigation' in the request.");

    expect([rawDocuments('sub_items'), rawDocuments('navigation')])->toBe($before);
    Queue::assertNothingPushed();
})->with(['sync path' => false, 'background' => true]);

test('a relationship given only through additionalData satisfies the check', function () {
    $navigation = $this->createNavigation();

    $subItem = (new SubItem)->storeWithSync(new Request, ['text' => 'x', 'code' => 'c', 'href' => 'h', 'navigation' => $this->getMiniNavigation($navigation->id)]);

    expect(SyncHelper::sameId($subItem->navigation->ref_id, $navigation->id))->toBeTrue();
});

test('explicit empty values are accepted as present and store an empty relationship', function (mixed $empty) {
    $subItem = (new SubItem)->storeWithSync(new Request, ['text' => 'x', 'code' => 'c', 'href' => 'h', 'navigation' => $empty]);
    $navigation = (new Navigation)->storeWithSync(new Request, navigationFields() + ['sub_items' => $empty]);

    expect($subItem->navigation?->ref_id)->toBeNull();
    expect($navigation->sub_items)->toHaveCount(0);
})->with(['null' => [null], 'empty string' => [''], 'JSON empty list' => ['[]']]);

test('a partial update keeps omitted relationships and propagates the updated fields to their copies', function (bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $navigation = $this->createNavigation(['text' => 'nav before']);
    $subItem = $this->createSubItems(['text' => 'item before', 'navigation' => $this->getMiniNavigation($navigation->id)]);

    $subItem->updateWithSync(new Request, ['text' => 'item after'], ['request_type' => 'partial']);
    Navigation::find($navigation->id)->updateWithSync(new Request, ['text' => 'nav after'], ['request_type' => 'partial']);

    $storedNavigation = Navigation::find($navigation->id);
    $storedSubItem = SubItem::find($subItem->id);
    expect(collect($storedNavigation->sub_items)->pluck('ref_id')->map(fn ($id) => (string) $id)->all())->toBe([(string) $subItem->id]);
    expect(getTranslatedContent($storedNavigation->sub_items[0]->text))->toBe('item after');
    expect(SyncHelper::sameId($storedSubItem->navigation->ref_id, $navigation->id))->toBeTrue();
    expect($storedSubItem->navigation->text)->toBe('nav after');
})->with(['sync path' => false, 'background' => true]);

test('the same instance can be updated partially and then fully', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    $subItem->updateWithSync(new Request, ['text' => 'partial'], ['request_type' => 'partial']);

    expect(fn () => $subItem->updateWithSync(new Request, ['text' => 'full', 'code' => 'c', 'href' => 'h']))
        ->toThrow(InvalidRequestException::class, "Missing relationship 'navigation' in the request.");
    $subItem->updateWithSync(new Request, ['text' => 'full', 'code' => 'c', 'href' => 'h', 'navigation' => $this->getMiniNavigation($navigation->id)]);
    expect(getTranslatedContent(SubItem::find($subItem->id)->text))->toBe('full');
});

test('a model without relationships is stored and updated without relationship keys', function () {
    $model = (new VldPlain)->storeWithSync(new Request, ['name' => 'first']);
    $model->updateWithSync(new Request, ['name' => 'second']);

    expect(VldPlain::find($model->id)->name)->toBe('second');
});

test('a missing plain field is reported by name without the request content', function () {
    expect(fn () => (new VldPlain)->storeWithSync(new Request(['password' => VALIDATION_SECRET]), []))
        ->toThrow(function (InvalidRequestException $e) {
            expect($e->getMessage())->toContain("'name'")->not->toContain(VALIDATION_SECRET);
        });
});

test('updateWithSync rejects a missing key for EmbedsMany with target and for a relation without target', function (string $missing, bool $background) {
    $article = (new Article)->storeWithSync(new Request, articleFields() + ['primarycategory' => '[]', 'categories' => '[]']);
    $navigation = (new Navigation)->storeWithSync(new Request, navigationFields() + ['sub_items' => '[]']);
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $before = [rawDocuments('articles'), rawDocuments('navigation')];
    Queue::fake();

    $call = match ($missing) {
        'sub_items' => fn () => $navigation->updateWithSync(new Request(['password' => VALIDATION_SECRET]), ['text' => 'changed'] + navigationFields()),
        'primarycategory' => fn () => $article->updateWithSync(new Request(['password' => VALIDATION_SECRET]), ['title' => 'changed', 'categories' => '[]'] + articleFields()),
    };

    expect($call)->toThrow(InvalidRequestException::class, "Missing relationship '{$missing}' in the request.");
    expect([rawDocuments('articles'), rawDocuments('navigation')])->toBe($before);
    Queue::assertNothingPushed();
})->with(['EmbedsMany with target' => ['sub_items'], 'EmbedsOne without target' => ['primarycategory']])
    ->with(['sync path' => false, 'background' => true]);

test('explicit empty values on update empty the relationship', function (mixed $empty) {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    $updated = $subItem->updateWithSync(new Request, ['text' => 'x', 'code' => 'c', 'href' => 'h', 'navigation' => $empty]);

    expect($updated->navigation?->ref_id)->toBeNull();
})->with(['null' => [null], 'empty string' => [''], 'JSON empty list' => ['[]']]);
