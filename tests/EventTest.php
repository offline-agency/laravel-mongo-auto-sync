<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tests\Models\Article;

class EventTest extends TestCase
{
    public function test_store_event()
    {
        Event::fake();

        $this->createArticle();

        Event::assertDispatched('storeWithSync');
    }

    public function test_update_event()
    {
        Event::fake();

        $this->updateArticle();

        Event::assertDispatched('updateWithSync');
    }

    public function test_destroy_event()
    {
        Event::fake();

        $this->destroyArticle();

        Event::assertDispatched('destroyWithSync');
    }

    public function createArticle()
    {
        $article = new Article;
        $request = new Request;
        $arr = [
            'title' => 'ArticleTitle'
        ];

        $article->storeWithSync($request, $arr);

        return $article;
    }

    public function updateArticle()
    {
        $article = $this->createArticle();

        $request = new Request;

        $updateArr = [
            'title' => 'UpdatedArticleTitle'
        ];

        $article->updateWithSync($request, $updateArr);
    }

    public function destroyArticle()
    {
        $article = $this->createArticle();

        $article->destroyWithSync();
    }
}
