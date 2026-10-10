<?php

use Tests\Models\Article;

test('drop collection with object', function () {
    $articles = createArticle();

    $this->artisan('drop:collection', ['collection_name' => 'Article'])
        ->assertExitCode(0);

    isDeleted($articles);
});

test('exception model not found', function () {
    $this->expectExceptionMessage('Error ModelThatDoesNotExist Model not found');
    $this->artisan('drop:collection', ['collection_name' => 'ModelThatDoesNotExist'])
        ->assertExitCode(0);
});

test('exception path not found', function () {
    config()->set('laravel-mongo-auto-sync.model_path', 'path_that_does_not_exist');
    $this->expectExceptionMessage('Error directory path_that_does_not_exist not found');
    $this->artisan('model-doc:generate', ['collection_name' => 'ModelThatDoesNotExist'])
        ->assertExitCode(0);
});

function createArticle()
{
    // Full requests: storeWithSync() requires every item declared on the model
    test()->prepareArticleData([], 2);

    return Article::all()->all();
}

function isDeleted($articles)
{
    if ($articles != null) {
        foreach ($articles as $article) {
            expect(Article::find($article->id))->toBeNull();
        }
    }
}
