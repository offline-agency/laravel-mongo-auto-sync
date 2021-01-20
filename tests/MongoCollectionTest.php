<?php

namespace Tests;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Models\Article;
use Tests\Models\Category;

class MongoCollectionTest extends SyncTestCase
{
    public function test_getBySlugAndStatus()
    {
        Category::truncate();
        Article::truncate();

        $articlePublished = $this->prepareArticleData(['status' => 'published'], 15);

        $articleNotPublished = $this->prepareArticleData(['status' => 'draft'], 5);

        //Expect error 404
        $this->expectException(NotFoundHttpException::class);

        //Check if instance of Article is passed
        $outPublished = Article::all()->getBySlugAndStatus('sport', 'articolo-1');
        $this->assertInstanceOf(Article::class, $outPublished);

        //Check error 404 return
        $outNotFoundBySlug = Article::all()->getBySlugAndStatus('sport', 'articolo');
        $outNotFoundByCategory = Article::all()->getBySlugAndStatus('sports', 'articolo-1');

        Category::truncate();
        Article::truncate();
    }

    public function test_getBySlug()
    {
        Category::truncate();
        Article::truncate();

        $article = $this->prepareArticleData([], 5);

        $this->expectException(NotFoundHttpException::class);

        $out = Article::all()->getBySlug('articolo-1');
        $this->assertInstanceOf(Article::class, $out);

        $outNotFoundBySlug = Article::all()->getBySlugAndStatus('sport', 'articolo');
        $outNotFoundByCategory = Article::all()->getBySlugAndStatus('sports', 'articolo-1');

        Category::truncate();
        Article::truncate();
    }

    public function test_getNotDeleted()
    {
        Category::truncate();
        Article::truncate();

        $article = $this->prepareArticleData([], 10);

        $getNotDeletedArticles = Article::all()->getNotDeleted();

        //Get articles with 'is_deleted = false'
        $this->assertFalse(false, $getNotDeletedArticles);
    }
}
