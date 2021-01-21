<?php

namespace Tests;

use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use Tests\Models\Navigation;

class MDModelTest extends SyncTestCase
{
    public function test_getId()
    {
        $modelTest = $this->createNavigation();

        $out = $modelTest->getId();

        $this->assertNotNull($out);
    }

    public function test_getCollection()
    {
        $navigation = new Navigation();

        $out = $navigation->getCollection();

        $this->assertEquals('navigation', $out);
    }

    public function test_getRandom()
    {
        Navigation::truncate();

        for ($i = 0; $i < 10; $i++) {
            $this->createNavigation();
        }

        $navigation = new Navigation();
        $out = $navigation->getRandom();
        $this->assertInstanceOf(MongoCollection::class, $out);
        $this->assertCount(3, $out);

        //

        $out = $navigation->getRandom(1);
        $this->assertInstanceOf(MongoCollection::class, $out);
        $this->assertCount(1, $out);

        Navigation::truncate();
    }
}
