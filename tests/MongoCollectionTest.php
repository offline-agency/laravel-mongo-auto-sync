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

        $this->prepareArticleData(['is_deleted' => true], 5);
        $this->prepareArticleData(['is_deleted' => false], 3);

        $allArticles = Article::all();
        $notDeletedArticles = $allArticles->getNotDeleted();

        $this->assertCount(3, $notDeletedArticles);
        $this->assertCount(8, $allArticles);

        Category::truncate();
        Article::truncate();
    }

    public function test_getPublished()
    {
        Category::truncate();
        Article::truncate();

        $this->prepareArticleData(['status' => 'published'], 5);
        $this->prepareArticleData(['status' => 'draft'], 3);

        $allArticles = Article::all();
        $getPublished = $allArticles->getPublished();

        $this->assertCount(5, $getPublished);
        $this->assertCount(8, $allArticles);


    }

    public function test_getPublic()
    {
        Category::truncate();
        Article::truncate();

        $this->prepareArticleData(['visibility' => 'public'], 5);
        $this->prepareArticleData(['visibility' => 'hidden'], 3);

        $allArticles = Article::all();
        $public = $allArticles->getPublic();

        $this->assertCount(5, $public);
        $this->assertCount(8, $allArticles);
    }

    public function test_hasItem()
    {
        Category::truncate();
        Article::truncate();

        $this->prepareArticleData([],1);
        $this->createCategory(['name' => 'news']);

        $idNull = $this->getIdNull();
        $article = Article::all()->first();
        $categoryAssigned = Category::where('name.' . cl(), 'sport')->first();
        $categoryNotAssigned = Category::where('name.' . cl(), 'news')->first();
        $out = $article->categories->hasItem(null);
        $this->assertFalse($out);

        //1
        $out = $article->categories->hasItem($categoryAssigned);
        $this->assertTrue($out);


        //2
        $out = $article->categories->hasItem($categoryNotAssigned);
        $this->assertFalse($out);

        //Get Id Null
        $out = $article->categories->hasItem($idNull);
        $this->assertFalse($out);

        Category::truncate();
        Article::truncate();
    }

    public function test_moveFirst()
    {
        Category::truncate();
        Article::truncate();

        $this->prepareArticleData([],2);

        $allArticle = Article::all();
    }

    public function test_getActive()
    {

        Category::truncate();
        Article::truncate();

        $this->prepareArticleData(['is_active' => 'test'],2);

        $allArticles = Article::all();
        $active = $allArticles->getActive();

        //Equal of Model Article
        $this->assertEquals($allArticles, $active);

        Category::truncate();
        Article::truncate();
    }

    public function test_exist()
    {
        Category::truncate();
        Article::truncate();

        $this->prepareArticleData([], 2);

        $allArticles = Article::all();
        $out = $allArticles->exist();

        $this->assertEquals(true, $out);
        $this->assertCount(2, $allArticles);
        //TODO third assertion 'false'

    }
}
