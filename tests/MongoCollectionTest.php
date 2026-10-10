<?php

namespace Tests;

use Tests\Models\Article;
use Tests\Models\Category;
use Tests\Models\Permission;
use Tests\Models\Role;
use Tests\Models\User;

class MongoCollectionTest extends TestCase
{
    public function test_has_item()
    {
        $this->cleanDb();

        $this->prepareArticleData([], 1);
        $this->createCategory(['name' => 'news']);

        $idNull = $this->getIdNull();
        $article = Article::all()->first();
        $categoryAssigned = Category::where('name.'.cl(), 'sport')->first();
        $categoryNotAssigned = Category::where('name.'.cl(), 'news')->first();
        $out = $article->categories->hasItem(null);
        $this->assertFalse($out);

        $out = $article->categories->hasItem($categoryAssigned);
        $this->assertTrue($out);

        $out = $article->categories->hasItem($categoryNotAssigned);
        $this->assertFalse($out);

        $out = $article->categories->hasItem($idNull);
        $this->assertFalse($out);

        $this->cleanDb();
    }

    public function test_move_first()
    {
        $this->cleanDb();

        $this->prepareArticleDataWithTwoCategories();

        $article = Article::all()->first();
        $out = $article->categories->moveFirst($article->primarycategory->ref_id);

        $this->assertEquals('news', getTranslatedContent($out->first()->name));
        $this->assertCount(2, $out);

        $this->cleanDb();
    }

    public function test_exist()
    {
        $this->cleanDb();

        // Test not Exist - return value false
        $allArticles = Article::all();
        $out = $allArticles->exist();

        $this->assertFalse($out, $out);
        $this->assertCount(0, $allArticles);

        // Test Exist - return value true
        $this->prepareArticleData([], 2);

        $allArticles = Article::all();
        $out = $allArticles->exist();

        $this->assertEquals($out, $out);
        $this->assertCount(2, $allArticles);

        $this->cleanDb();
    }

    private function cleanDb()
    {
        Category::truncate();
        Article::truncate();
    }

    private function cleanDbUPR()
    {
        User::truncate();
        Permission::truncate();
        Role::truncate();
    }
}
