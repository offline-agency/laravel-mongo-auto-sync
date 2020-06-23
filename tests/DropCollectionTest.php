<?php

namespace Tests;

use Illuminate\Http\Request;
use Tests\Models\Article;

class DropCollectionTest extends TestCase
{
    public function test_drop_collection_with_object()
    {
        $articles = $this->createArticle();

        $this->artisan('drop:collection', ['collection_name' => 'Article'])
            ->assertExitCode(0);

        $this->isDeleted($articles);
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

    public function createArticle()
    {
        $articles = [];

        for ($i = 0; $i < 2; $i++) {
            $article = new Article;
            $request = new Request;
            $arr = [
                'title' => 'Article #'.$i,
            ];

            $article->storeWithSync($request, $arr);

            $articles[$i] = $article;
        }

        return $articles;
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
