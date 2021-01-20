<?php

namespace Tests;

use Tests\Models\Article;
use Tests\Models\Category;

class DropCollectionTest extends SyncTestCase
{
    public function test_drop_collection_with_object()
    {
        Article::truncate();
        Category::truncate();

        $this->prepareArticleData([], 10);

        $this->artisan('drop:collection', ['collection_name' => 'Article'])
            ->assertExitCode(0);

        $articles = Article::all();
        $this->assertEmpty($articles);
        $category = Category::where('name.'.cl(), 'sport')->first();

        $this->assertEmpty($category->articles);

        Article::truncate();
        Category::truncate();
    }

    public function test_exception_model_not_found()
    {
        $this->expectExceptionMessage('Error ModelThatDoesNotExist Model not found');
        $this->artisan('drop:collection', ['collection_name' => 'ModelThatDoesNotExist'])
            ->assertExitCode(0);
    }

    public function test_exception_path_not_found()
    {
        config()->set('laravel-mongo-auto-sync.model_path', 'path_that_does_not_exist');
        $this->expectExceptionMessage('Error directory path_that_does_not_exist not found');
        $this->artisan('model-doc:generate', ['collection_name' => 'ModelThatDoesNotExist'])
            ->assertExitCode(0);
    }

    public function isDeleted($articles)
    {
        if ($articles != null) {
            foreach ($articles as $article) {
                $this->assertNull(Article::find($article->id));
            }
        }
    }
}
