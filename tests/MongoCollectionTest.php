<?php

namespace Tests;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Models\Article;
use Tests\Models\Category;
use Tests\Models\Permission;
use Tests\Models\Role;
use Tests\Models\User;
use Throwable;

class MongoCollectionTest extends SyncTestCase
{
    public function test_getBySlugAndStatus()
    {
        $this->cleanDb();

        $this->prepareArticleData(['status' => 'published'], 15);

        $this->prepareArticleData(['status' => 'draft'], 5);

        //Not found by slug 404

        try {
            Article::all()->getBySlugAndStatus('sport', 'articolo');
        } catch (Throwable $e) {
            $this->assertEquals(
                new NotFoundHttpException('error.Tests\Models\Article'),
                $e
            );
        }


        //Not found by all parameters null 404
        try {
            Article::all()->getBySlugAndStatus(null, null);
        } catch (Throwable $e) {
            $this->assertEquals(
                new NotFoundHttpException('error.Tests\Models\Article'),
                $e
            );
        }


        //Not found by category null  404
        try {
            Article::all()->getBySlugAndStatus(null, 'articolo-1');
        } catch (Throwable $e) {
            $this->assertEquals(
                new NotFoundHttpException('error.Tests\Models\Article'),
                $e
            );
        }


        //Not found by title null 404
        try {
           Article::all()->getBySlugAndStatus('sport', null);
        } catch (Throwable $e) {
            $this->assertEquals(
                new NotFoundHttpException('error.Tests\Models\Article'),
                $e
            );
        }

        //Not found by category not existing 404
        try {
            Article::all()->getBySlugAndStatus('category-not-existing', 'articolo-1');
        } catch (Throwable $e) {
            $this->assertEquals(
                new NotFoundHttpException('error.Tests\Models\Article'),
                $e
            );
        }


        //Not found draft article not existing 404
        try {
            Article::all()->getBySlugAndStatus('sport', 'articolo-16');
        } catch (Throwable $e) {
            $this->assertEquals(
                new NotFoundHttpException('error.Tests\Models\Article'),
                $e
            );
        }


        //Check if instance of Article is passed
        $outPublished = Article::all()->getBySlugAndStatus('sport', 'articolo-1');
        $this->assertInstanceOf(Article::class, $outPublished);
        $this->assertEquals('articolo 1', getTranslatedContent($outPublished->title));
        $this->assertEquals('articolo-1', getTranslatedContent($outPublished->slug));
        $this->assertEquals('sport', getTranslatedContent($outPublished->primarycategory->name));


        $this->cleanDb();
    }

    public function test_getBySlug()
    {
        $this->cleanDb();

        $article = $this->prepareArticleData([], 5);

        $this->expectException(NotFoundHttpException::class);

        $out = Article::all()->getBySlug('articolo-1');
        $this->assertInstanceOf(Article::class, $out);

        $outNotFoundBySlug = Article::all()->getBySlugAndStatus('sport', 'articolo');
        $outNotFoundByCategory = Article::all()->getBySlugAndStatus('sports', 'articolo-1');

        $this->cleanDb();
    }

    public function test_getNotDeleted()
    {
        $this->cleanDb();

        $this->prepareArticleData(['is_deleted' => true], 5);
        $this->prepareArticleData(['is_deleted' => false], 3);

        $allArticles = Article::all();
        $notDeletedArticles = $allArticles->getNotDeleted();

        $this->assertCount(3, $notDeletedArticles);
        $this->assertCount(8, $allArticles);

        $this->cleanDb();
    }

    public function test_getPublished()
    {
        $this->cleanDb();

        $this->prepareArticleData(['status' => 'published'], 5);
        $this->prepareArticleData(['status' => 'draft'], 3);

        $allArticles = Article::all();
        $getPublished = $allArticles->getPublished();

        $this->assertCount(5, $getPublished);
        $this->assertCount(8, $allArticles);

        $this->cleanDb();
    }

    public function test_getPublic()
    {
        $this->cleanDb();

        $this->prepareArticleData(['visibility' => 'public'], 5);
        $this->prepareArticleData(['visibility' => 'hidden'], 3);

        $allArticles = Article::all();
        $public = $allArticles->getPublic();

        $this->assertCount(5, $public);
        $this->assertCount(8, $allArticles);

        $this->cleanDb();
    }

    public function test_hasItem()
    {
        $this->cleanDb();

        $this->prepareArticleData([],1);
        $this->createCategory(['name' => 'news']);

        $idNull = $this->getIdNull();
        $article = Article::all()->first();
        $categoryAssigned = Category::where('name.' . cl(), 'sport')->first();
        $categoryNotAssigned = Category::where('name.' . cl(), 'news')->first();
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

    public function test_moveFirst()
    {
        $this->cleanDb();

        $this->prepareArticleDataWithTwoCategories();

        $article = Article::all()->first();
        $out = $article->categories->moveFirst($article->primarycategory->ref_id);

        $this->assertEquals('news', getTranslatedContent($out->first()->name));
        $this->assertCount(2, $out);

        $this->cleanDb();
    }

    public function test_getActive()
    {
        $this->cleanDb();

        $this->prepareArticleData(['is_active' => true],2);
        $this->prepareArticleData(['is_active' => false]);

        $allArticles = Article::all();
        $active = $allArticles->getActive();
        $notActiveCount = $allArticles->count() - $active->count();

        $this->assertCount(2, $active);
        $this->assertCount(3, $allArticles);
        $this->assertEquals(1, $notActiveCount);

        $this->cleanDb();
    }

    public function test_exist()
    {
        $this->cleanDb();

        //Test not Exist - return value false
        $allArticles = Article::all();
        $out = $allArticles->exist();

        $this->assertFalse($out, $out);
        $this->assertCount(0, $allArticles);

        //Test Exist - return value true
        $this->prepareArticleData([], 2);

        $allArticles = Article::all();
        $out = $allArticles->exist();

        $this->assertEquals($out, $out);
        $this->assertCount(2, $allArticles);

        $this->cleanDb();
    }

    public function test_findByAID(){
        $this->cleanDb();
        $this->prepareArticleData(['title' => 'My autoincrement title']);
        $this->prepareArticleData([], 2);

        $allArticles = Article::all();
        $out = $allArticles->findByAID(1);

        $this->assertEquals('My autoincrement title',getTranslatedContent($out->title));
        $this->assertCount(3,$allArticles);
        $this->cleanDb();


        $this->cleanDb();
        $this->prepareArticleData([], 2);
        $this->prepareArticleData(['title' => 'My autoincrement title']);

        $allArticles = Article::all();
        $out = $allArticles->findByAID(3);

        $this->assertEquals('My autoincrement title',getTranslatedContent($out->title));
        $this->assertCount(3,$allArticles);
        $this->cleanDb();
    }

    public function test_hasPermission()
    {
        $this->cleanDbUPR();

        $this->prepareUserData();

        $permissionNotExisting = '111';

        $out = User::all()->first()->permissions->hasPermission($permissionNotExisting);

        $this->assertFalse($out);

        $permissionExisting = Permission::where('name', 'EditArticle')->first();
        $out = User::all()->first()->permissions->hasPermission($permissionExisting->id);

        $this->assertTrue($out);

        $out = User::all()->first()->permissions->hasPermission(null);

        $this->assertFalse($out);

        $this->cleanDbUPR();
    }

    public function test_hasRole()
    {
        $this->cleanDbUPR();

        $this->prepareUserData();

        $out = User::all()->first()->roles->hasRole('RoleNotExisting');

        $this->assertFalse($out);

        $out = User::all()->first()->roles->hasRole('SuperAdmin');

        $this->assertTrue($out);

        $out = User::all()->first()->roles->hasRole(null);

        $this->assertFalse($out);

        $this->cleanDbUPR();
    }

    public function test_checkPermission()
    {
        $this->cleanDbUPR();

        $this->prepareUserData();

        $permissionNotExisting = 'CreateArticle';

        $out = User::all()->first()->permissions->checkPermission($permissionNotExisting);

        $this->assertFalse($out);

        $permissionExisting = 'EditArticle';
        $out = User::all()->first()->permissions->checkPermission($permissionExisting);

        $this->assertTrue($out);

        $out = User::all()->first()->permissions->checkPermission(null);

        $this->assertFalse($out);

        $this->cleanDbUPR();
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
