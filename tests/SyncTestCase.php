<?php

namespace Tests;

use Exception;
use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use stdClass;
use Tests\Models\Article;
use Tests\Models\Category;
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
            'name' => $text,
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
    )
    {
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

    public function createCategory(
        array $data = []
    )
    {
        $faker = Factory::create();
        $request = new Request;

        $category = new Category;

        $article = new Article;

        $category_id = getAID($article);
        $name = Arr::has($data, 'name') ? Arr::get($data, 'name') : $faker->text(50);
        $slug = Arr::has($data, 'slug') ? Arr::get($data, 'slug') : Str::slug($name);
        $description = Arr::has($data, 'description') ? Arr::get($data, 'description') : $faker->text(50);

        $articles= Arr::has($data, 'articles') ? Arr::get($data, 'articles') : json_encode([]);

        $arr = [
            'category_id' => $category_id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'articles' => $articles
        ];

        return $category->storeWithSync($request, $arr);
    }

    public function createArticle(
        array $data = [],
        int $size = 1
    )
    {
        $faker = Factory::create();

        for ($i = 0; $i < $size; $i++) {

            $request = new Request;

            $article = new Article;

            $article_id = getAID($article);
            $title = Arr::has($data, 'title') ? Arr::get($data, 'title') : $faker->text(10);
            $content = Arr::has($data, 'content') ? Arr::get($data, 'name') : $faker->text(100);
            $description = Arr::has($data, 'description') ? Arr::get($data, 'description') : $faker->text(50);
            $slug = Arr::has($data, 'slug') ? Arr::get($data, 'slug') : Str::slug($title);
            $visibility = Arr::has($data, 'visibility') ? Arr::get($data, 'visibility') : $faker->text(50);
            $status = Arr::has($data, 'status') ? Arr::get($data, 'status') : $faker->text(50);
            $last_updated_by = Arr::has($data, 'last_updated_by') ? Arr::get($data, 'last_updated_by') : $faker->text(50);
            $primary_category = Arr::has($data, 'primary_category') ? Arr::get($data, 'primary_category') : $faker->text(50);

            $categories = Arr::has($data, 'categories') ? Arr::get($data, 'categories') : json_encode([]);

            $arr = [
                'article_id' => $article_id,
                'title' => $title,
                'content' => $content,
                'description' => $description,
                'slug' => $slug,
                'visibility' => $visibility,
                'status' => $status,
                'last_updated_by' => $last_updated_by,
                'primary_category' => $primary_category,
                'categories' => $categories
            ];

            $article->storeWithSync($request, $arr);
        }
    }

    public function getMiniCategory(string $category_id = '')
    {
        if ($category_id == '' || is_null($category_id)) {
            $category = $this->createCategory();
        } else {
            $category = Category::find($category_id);
            if (is_null($category)) {
                return json_encode(
                    []
                );
            }
        }
        return json_encode(
            [
                (object)[
                    'ref_id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ],
            ]
        );
    }

    public function getMiniArticle(string $article_id = '')
    {
        if ($article_id == '' || is_null($article_id)) {
            $article = $this->createArticle();
        } else {
            $article = Article::find($article_id);
            if (is_null($article)) {
                return json_encode(
                    []
                );
            }
        }
        return json_encode(
            [
                (object)[
                    'ref_id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'visibility' => $article->visibility,
                    'status' => $article->status,
                    'last_updated_by' => $article->last_updated_by,
                ],
            ]
        );
    }

    /**
     * @param string $navigation_id
     * @return false|string
     * @throws Exception
     */
    public function getMiniNavigation(string $navigation_id = '')
    {
        if ($navigation_id == '' || is_null($navigation_id)) {
            $navigation = $this->createNavigation();
        } else {
            $navigation = Navigation::find($navigation_id);
            if (is_null($navigation)) {
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
                    'title' => getTranslatedContent($navigation->title),
                ],
            ]
        );
    }

    /**
     * @param $navigation
     * @return bool
     */
    public function isNavigationCreated($navigation)
    {
        return !is_null(Navigation::find($navigation->id));
    }

    /**
     * @param $item
     * @return bool
     */
    public function isItemCreated($item)
    {
        return !is_null(Item::find($item->id));
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
