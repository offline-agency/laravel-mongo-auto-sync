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
use Tests\Models\Permission;
use Tests\Models\Role;
use Tests\Models\SubItem;
use Tests\Models\User;

class SyncTestCase extends TestCase
{
    /**
     * @param  array  $data
     * @return SubItem
     *
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
     * @param  array  $data
     * @return Item
     *
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
     * @param  array  $data
     * @return Navigation
     *
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
     * @param  array  $data
     * @return Category
     *
     * @throws Exception
     */
    public function createCategory(
        array $data = []
    ) {
        $faker = Factory::create();
        $request = new Request;

        $category = new Category;

        $category_id = getAID($category);
        $name = Arr::has($data, 'name') ? Arr::get($data, 'name') : $faker->text(50);
        $slug = Arr::has($data, 'slug') ? Arr::get($data, 'slug') : Str::slug($name);
        $description = Arr::has($data, 'description') ? Arr::get($data, 'description') : $faker->text(50);

        $articles = Arr::has($data, 'articles') ? Arr::get($data, 'articles') : json_encode([]);

        $arr = [
            'category_id' => $category_id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'articles' => $articles,
        ];

        return $category->storeWithSync($request, $arr);
    }

    /**
     * @param  array  $data
     * @return Permission
     *
     * @throws Exception
     */
    public function createPermission(
        array $data = []
    ) {
        $faker = Factory::create();
        $request = new Request;

        $permission = new Permission;

        $name = Arr::has($data, 'name') ? Arr::get($data, 'name') : $faker->name;
        $label = Arr::has($data, 'label') ? Arr::get($data, 'label') : $faker->name;

        $roles = Arr::has($data, 'roles') ? Arr::get($data, 'roles') : json_encode([]);
        $users = Arr::has($data, 'users') ? Arr::get($data, 'users') : json_encode([]);

        $arr = [
            'name' => $name,
            'label' => $label,
            'roles' => $roles,
            'users' => $users,
        ];

        return $permission->storeWithSync($request, $arr);
    }

    /**
     * @param  array  $data
     * @return Role
     *
     * @throws Exception
     */
    public function createRole(
        array $data = []
    ) {
        $faker = Factory::create();
        $request = new Request;

        $role = new Role;

        $name = Arr::has($data, 'name') ? Arr::get($data, 'name') : $faker->name;
        $label = Arr::has($data, 'label') ? Arr::get($data, 'label') : $faker->name;

        $permissions = Arr::has($data, 'permissions') ? Arr::get($data, 'permissions') : json_encode([]);
        $users = Arr::has($data, 'users') ? Arr::get($data, 'users') : json_encode([]);

        $arr = [
            'name' => $name,
            'label' => $label,
            'permissions' => $permissions,
            'users' => $users,
        ];

        return $role->storeWithSync($request, $arr);
    }

    /**
     * @param  array  $data
     * @param  int  $size
     *
     * @throws Exception
     */
    public function createArticles(
        array $data = [],
        int $size = 1
    ) {
        $faker = Factory::create();

        for ($i = 0; $i < $size; $i++) {
            $request = new Request;
            $article = new Article;

            $autoincrement_id = getAID($article);
            $title = Arr::has($data, 'title') ? Arr::get($data, 'title') : 'articolo '.$autoincrement_id;
            $content = Arr::has($data, 'content') ? Arr::get($data, 'name') : $faker->text(100);
            $slug = Arr::has($data, 'slug') ? Arr::get($data, 'slug') : Str::slug($title);
            $visibility = Arr::has($data, 'visibility') ? Arr::get($data, 'visibility') : $faker->text(50);
            $status = Arr::has($data, 'status') ? Arr::get($data, 'status') : $faker->text(50);
            $is_deleted = Arr::has($data, 'is_deleted') ? Arr::get($data, 'is_deleted') : $faker->boolean;
            $is_active = Arr::has($data, 'is_active') ? Arr::get($data, 'is_active') : $faker->text(50);
            $primarycategory = Arr::has($data, 'primarycategory') ? Arr::get($data, 'primarycategory') : $faker->text(50);

            $categories = Arr::has($data, 'categories') ? Arr::get($data, 'categories') : json_encode([]);

            $arr = [
                'autoincrement_id' => $autoincrement_id,
                'title' => $title,
                'content' => $content,
                'slug' => $slug,
                'visibility' => $visibility,
                'status' => $status,
                'is_deleted' => $is_deleted,
                'is_active' => $is_active,
                'primarycategory' => $primarycategory,
                'categories' => $categories,
            ];

            $article->storeWithSync($request, $arr);
        }
    }

    /**
     * @param  array  $data
     * @param  int  $size
     *
     * @throws Exception
     */
    public function createUsers(
        array $data = [],
        int $size = 1
    ) {
        $faker = Factory::create();

        for ($i = 0; $i < $size; $i++) {
            $request = new Request;
            $user = new User;

            $name = Arr::has($data, 'name') ? Arr::get($data, 'name') : $faker->firstName;
            $surname = Arr::has($data, 'surname') ? Arr::get($data, 'surname') : $faker->lastName;
            $email = Arr::has($data, 'email') ? Arr::get($data, 'email') : $faker->email;

            $roles = Arr::has($data, 'roles') ? Arr::get($data, 'roles') : json_encode([]);
            $permissions = Arr::has($data, 'permissions') ? Arr::get($data, 'permissions') : json_encode([]);

            $arr = [
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'roles' => $roles,
                'permissions' => $permissions,
            ];
            $user->storeWithSync($request, $arr);
        }
    }

    /**
     * @param  array  $data
     * @param  int  $size
     *
     * @throws Exception
     */
    public function prepareArticleData(
        array $data = [],
        int $size = 1
    ) {
        $category = $this->createCategory(['name' => 'sport']);
        $miniCategory = $this->getMiniCategory($category->id);

        $relationshipValues = [
            'primarycategory' => $miniCategory,
            'categories' => $miniCategory,
        ];

        $mergedData = array_merge($relationshipValues, $data);

        $this->createArticles($mergedData, $size);
    }

    public function prepareUserData(
        array $data = [],
        int $size = 1
    ) {
        $first_permission = $this->createPermission(['name' => 'EditArticle']);
        $second_permission = $this->createPermission(['name' => 'CreateUser']);

        $first_role = $this->createRole(['name' => 'SuperAdmin']);
        $second_role = $this->createRole(['name' => 'Editor']);

        $miniPermissions = $this->getMiniPermissions([
            $first_permission->id,
            $second_permission->id,
        ]);

        $miniRoles = $this->getMiniRoles([
            $first_role->id,
            $second_role->id, ]
        );

        $relationshipValues = [
            'permissions' => $miniPermissions,
            'roles' => $miniRoles,
        ];

        $mergedData = array_merge($relationshipValues, $data);

        $this->createUsers($mergedData, $size);
    }

    public function prepareArticleDataWithTwoCategories(
        array $data = [],
        int $size = 1
    ) {
        $first_category = $this->createCategory(['name' => 'sport']);
        $second_category = $this->createCategory(['name' => 'news']);
        $miniPrimaryCategory = $this->getMiniCategory($second_category->id);

        $miniCategories = $this->getMiniCategory([$second_category->id, $first_category->id]);

        $relationshipValues = [
            'primarycategory' => $miniPrimaryCategory,
            'categories' => $miniCategories,
        ];
        //dd($relationshipValues);
        $mergedData = array_merge($relationshipValues, $data);

        $this->createArticles($mergedData, $size);
    }

    /**
     * @param  string|array  $category_id
     * @return string|false
     *
     * @throws Exception
     */
    public function getMiniCategory($category_id = '')
    {
        if (is_array($category_id)) {
            $out = [];
            foreach ($category_id as $category) {
                $out[] = $this->prepareSingleMiniCategory($category);
            }
        } else {
            $out[] = $this->prepareSingleMiniCategory($category_id);
        }

        return json_encode($out);
    }

    /**
     * @param  string|array  $permission_id
     * @return string|false
     *
     * @throws Exception
     */
    public function getMiniPermissions($permission_id = '')
    {
        if (is_array($permission_id)) {
            $out = [];
            foreach ($permission_id as $permission) {
                $out[] = $this->prepareSingleMiniPermission($permission);
            }
        } else {
            $out[] = $this->prepareSingleMiniPermission($permission_id);
        }

        return json_encode($out);
    }

    /**
     * @param  string|array  $role_id
     * @return string|false
     *
     * @throws Exception
     */
    public function getMiniRoles($role_id = '')
    {
        if (is_array($role_id)) {
            $out = [];
            foreach ($role_id as $role) {
                $out[] = $this->prepareSingleMiniRole($role);
            }
        } else {
            $out[] = $this->prepareSingleMiniRole($role_id);
        }

        return json_encode($out);
    }

    /**
     * @param  $category_id
     * @return object
     *
     * @throws Exception
     */
    public function prepareSingleMiniCategory($category_id)
    {
        if ($category_id == '' || is_null($category_id)) {
            $category = $this->createCategory();
        } else {
            $category = Category::find($category_id);
            if (is_null($category)) {
                return null;
            }
        }

        return (object) [
            'ref_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
        ];
    }

    /**
     * @param  $permission_id
     * @return object
     *
     * @throws Exception
     */
    public function prepareSingleMiniPermission($permission_id)
    {
        if ($permission_id == '' || is_null($permission_id)) {
            $permission = $this->createPermission();
        } else {
            $permission = Permission::find($permission_id);
            if (is_null($permission)) {
                return null;
            }
        }

        return (object) [
            'ref_id' => $permission->id,
            'name' => $permission->name,
            'label' => getTranslatedContent($permission->label),
        ];
    }

    /**
     * @param  $role_id
     * @return object
     *
     * @throws Exception
     */
    public function prepareSingleMiniRole($role_id)
    {
        if ($role_id == '' || is_null($role_id)) {
            $role = $this->createRole();
        } else {
            $role = Role::find($role_id);
            if (is_null($role)) {
                return null;
            }
        }

        return (object) [
            'ref_id' => $role->id,
            'name' => $role->name,
            'label' => $role->label,
        ];
    }

    /**
     * @param  string  $autoincrement_id
     * @return false|string
     */
    public function getMiniArticle(string $autoincrement_id = '')
    {
        if ($autoincrement_id == '' || is_null($autoincrement_id)) {
            $article = $this->createArticle();
        } else {
            $article = Article::find($autoincrement_id);
            if (is_null($article)) {
                return json_encode(
                    []
                );
            }
        }

        return json_encode(
            [
                (object) [
                    'ref_id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'visibility' => $article->visibility,
                    'status' => $article->status,
                ],
            ]
        );
    }

    /**
     * @return object
     */
    public function getIdNull()
    {
        return (object) [
            'id' => null,
        ];
    }

    /**
     * @param  string  $navigation_id
     * @return false|string
     *
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
                (object) [
                    'ref_id' => $navigation->id,
                    'text' => $navigation->text,
                    'code' => $navigation->code,
                    'title' => getTranslatedContent($navigation->title),
                ],
            ]
        );
    }

    /**
     * @param  $navigation
     * @return bool
     */
    public function isNavigationCreated($navigation)
    {
        return ! is_null(Navigation::find($navigation->id));
    }

    /**
     * @param  $item
     * @return bool
     */
    public function isItemCreated($item)
    {
        return ! is_null(Item::find($item->id));
    }

    /**
     * @param  Navigation  $navigation
     * @param  SubItem  $sub_item
     *
     * @throws Exception
     */
    public function cleanUp(Navigation $navigation, SubItem $sub_item)
    {
        $sub_item->delete();
        $navigation->delete();
    }

    /**
     * @param  $item
     * @return bool
     */
    public function isItemUpdatedCorrectly($item)
    {
        return Str::contains($item->name, 'Aggiornato');
    }

    /**
     * @param  $navigation
     * @return bool
     */
    public function isNavigationUpdatedCorrectly($navigation)
    {
        return Str::contains($navigation->text, 'Aggiornato');
    }

    /**
     * @param  $navigation
     * @return bool
     */
    public function isUpdated($navigation)
    {
        return $navigation->text == 'Aggiornato';
    }

    /**
     * @param  $navigation_id
     * @param  $item_id
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
     * @param  $navigation_code
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

    protected function getMiniSubItem(string $sub_item_id)
    {
        if ($sub_item_id == '' || is_null($sub_item_id)) {
            $sub_item = $this->createSubItems();
        } else {
            $sub_item = SubItem::find($sub_item_id);
            if (is_null($sub_item)) {
                return json_encode(
                    []
                );
            }
        }

        return json_encode(
            [
                (object) [
                    'ref_id' => $sub_item->id,
                    'code' => $sub_item->code,
                    'href' => $sub_item->href,
                    'text' => getTranslatedContent($sub_item->text),
                ],
            ]
        );
    }
}
