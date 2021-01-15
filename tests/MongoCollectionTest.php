<?php

namespace Tests;

use Tests\Models\Article;
use Tests\Models\Category;

class MongoCollectionTest extends SyncTestCase
{
    public function test_getBySlugAndStatus()
    {
        Category::truncate();
        Article::truncate();

        //Categoria
        $category = $this->createCategory(['name' => 'sport']);
        $miniCategory = $this->getMiniCategory($category->id);

        //Articoli
        $articlePublished = $this->createArticle([
            'primary_category' => $miniCategory,
            'categories' => $miniCategory,
            'status' => 'published'
        ],
            15
        );

        $articleNotPublished = $this->createArticle([
            'primary_category' => $miniCategory,
            'categories' => $miniCategory,
            'status' => 'draft'
        ],
            5
        );
        $out = Article::all()->getBySlugAndStatus('sport','articolo-1');


        //Test Errore 404
        /*
                $response = Article::all()->getBySlugAndStatus('sport','articolo 1');
                $response->assertStatus();
        */
        Category::truncate();
        Article::truncate();
    }

    public function test_getBySlug()
    {


    }

    public function test_getNotDeleted()
    {


//        $this->assertEquals(false, $out);

    }
}
