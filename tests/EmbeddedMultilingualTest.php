<?php

use Illuminate\Http\Request;
use Tests\Models\SubItem;

/**
 * The navigation mini model of a new SubItem whose payload carries $title for the multilingual 'title' item.
 */
function embeddedTitle(mixed $title): mixed
{
    $navigation = test()->createNavigation();
    $payload = json_encode([['ref_id' => $navigation->id, 'text' => 't', 'code' => 'c', 'title' => $title]]);
    $subItem = (new SubItem)->storeWithSync(new Request, ['text' => 's', 'code' => 'c', 'href' => 'h', 'navigation' => $payload]);
    $subItem = SubItem::find($subItem->id)->updateWithSync(new Request, ['text' => 's', 'code' => 'c', 'href' => 'h', 'navigation' => $payload]);

    return SubItem::find($subItem->id)->getAttributes()['navigation']['title'];
}

it('stores a multilingual value of an embedded model as a map, without nesting it on every write', function (mixed $title, array $expected) {
    expect(embeddedTitle($title))->toBe($expected);
})->with([
    'value of the current locale' => ['Miss', ['en' => 'Miss']],
    'map of locales' => [['en' => 'Miss', 'it' => 'Signorina'], ['en' => 'Miss', 'it' => 'Signorina']],
]);
