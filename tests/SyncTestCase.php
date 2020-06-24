<?php

namespace Tests;

use Exception;
use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use stdClass;
use Tests\Models\Item;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

class SyncTestCase extends TestCase
{
    /**
     * @param array $data
     * @return SubItem
     * @throws Exception
     */
    public function createSubItems(array $data = [])
    {
        $sub_item = new SubItem;
        $faker = Factory::create();
        $request = new Request;

        $text = Arr::has($data, 'text') ? Arr::get($data, 'text') : $faker->text(50);
        $code = Arr::has($data, 'code') ? Arr::get($data, 'code') : $faker->creditCardNumber;
        $href = Arr::has($data, 'href') ? Arr::get($data, 'href') : $faker->url;
        $navigation = Arr::has($data, 'navigation') ? Arr::get($data, 'navigation') : json_encode([]);

        $arr = [
            'text' => $text,
            'code' => $code,
            'href' => $href,
            'navigation' => $navigation,
        ];

        return $sub_item->storeWithSync($request, $arr);
    }

    /**
     * @param array $data
     * @return Item
     * @throws Exception
     */
    public function createItems(array $data = [])
    {
        $sub_item = new Item;
        $faker = Factory::create();
        $request = new Request;

        $text = Arr::has($data, 'text') ? Arr::get($data, 'text') : $faker->text(50);
        $code = Arr::has($data, 'code') ? Arr::get($data, 'code') : $faker->creditCardNumber;
        $href = Arr::has($data, 'href') ? Arr::get($data, 'href') : $faker->url;
        $navigation = Arr::has($data, 'navigation') ? Arr::get($data, 'navigation') : json_encode([]);

        $arr = [
            'text' => $text,
            'code' => $code,
            'href' => $href,
            'navigation' => $navigation,
        ];

        return $sub_item->storeWithSync($request, $arr);
    }

    /**
     * @param array $data
     * @return Navigation
     * @throws Exception
     */
    public function createNavigation(
        array $data = []
    ) {
        $faker = Factory::create();
        $request = new Request;

        $navigation = new Navigation;

        $text = Arr::has($data, 'text') ? Arr::get($data, 'text') : $faker->text(50);
        $code = Arr::has($data, 'code') ? Arr::get($data, 'code') : $faker->creditCardNumber;
        $href = Arr::has($data, 'href') ? Arr::get($data, 'href') : $faker->url;
        $date = Arr::has($data, 'date') ? Arr::get($data, 'date') : Date::now();
        $target = Arr::has($data, 'target') ? Arr::get($data, 'target') : $faker->text(50);
        $title = Arr::has($data, 'title') ? Arr::get($data, 'title') : $faker->title;

        $sub_items = Arr::has($data, 'sub_items') ? Arr::get($data, 'sub_items') : json_encode([]);

        $arr = [
            'text' => $text,
            'code' => $code,
            'href' => $href,
            'date' => $date,
            'target' => $target,
            'title' => $title,
            'sub_items' => $sub_items,
        ];

        return $navigation->storeWithSync($request, $arr);
    }

    /**
     * @param string $navigation_id
     * @return false|string
     * @throws Exception
     */
    public function getMiniNavigation(string $navigation_id = "")
    {
        if ($navigation_id == "" || is_null($navigation_id)){
            $navigation = $this->createNavigation();
        }else{
            $navigation = Navigation::find($navigation_id);
            if (is_null($navigation)){
                return json_encode(
                    []
                );
            }
        }

        return json_encode(
            [
                (object)[
                    'ref_id' => $navigation->id,
                    'text' => $navigation->text,
                    'code' => $navigation->code,
                    'title' => getTranslatedContent($navigation->title)
                ]
            ]
        );
    }

    /**
     * @param $navigation
     * @return bool
     */
    public function isNavigationCreated($navigation)
    {
        return ! is_null(Navigation::find($navigation->id));
    }

    /**
     * @param $item
     * @return bool
     */
    public function isItemCreated($item)
    {
        return ! is_null(Item::find($item->id));
    }

    /**
     * @param Navigation $navigation
     * @param SubItem $sub_item
     * @throws Exception
     */
    public function cleanUp(Navigation $navigation, SubItem $sub_item)
    {
        $sub_item->delete();
        $navigation->delete();
    }

    /**
     * @param $item
     * @return bool
     */
    public function isItemUpdatedCorrectly($item)
    {
        return Str::contains($item->name, 'Aggiornato');
    }

    /**
     * @param $navigation
     * @return bool
     */
    public function isNavigationUpdatedCorrectly($navigation)
    {
        return Str::contains($navigation->text, 'Aggiornato');
    }

    /**
     * @param $navigation
     * @return bool
     */
    public function isUpdated($navigation)
    {
        return $navigation->text == 'Aggiornato';
    }

    /**
     * @param $navigation_id
     * @param $item_id
     * @return bool
     */
    public function isItemAddedInNavigationCollection($navigation_id, $item_id)
    {
        $navigation = Navigation::where('id', '=', $navigation_id)->get()->first();
        $return = false;
        if (isset($navigation->items)) {
            foreach ($navigation->items as $item) {
                $return = $item->ref_id == $item_id;
            }
        }

        return $return;
    }

    /**
     * @param $navigation_code
     * @return false|string
     */
    public function getNavigation($navigation_code)
    {
        $arr = [];
        $navigation = Navigation::where('code', '=', $navigation_code)->get()->first();

        $newNavigation = new stdClass;
        $newNavigation->ref_id = $navigation->id;
        $newNavigation->code = $navigation_code;
        $newNavigation->title[cl()] = $navigation->title;
        $newNavigation->text = $navigation->text;

        $arr[] = $newNavigation;

        return json_encode($arr);
    }
}
