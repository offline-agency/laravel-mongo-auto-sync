<?php

namespace Tests;

use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use Tests\Support\ModelAdditionalMethodHost;

class ModelAdditionalMethodTest extends SyncTestCase
{
    protected ModelAdditionalMethodHost $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = new ModelAdditionalMethodHost;
    }

    public function test_cast_ml()
    {
        $this->host->setRequest();

        // []

        $this->host->setMl([]);

        $parsed_value = $this->host->castValueToBeSaved('ml', [
            'is-ml' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertEquals([], $parsed_value);

        // null

        $this->host->setMl(null);

        $parsed_value = $this->host->castValueToBeSaved('ml', [
            'is-ml' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertCount(1, $parsed_value);
    }

    public function test_cast_md()
    {
        $this->host->setRequest();

        // UTCDateTime

        $this->host->setMd(new UTCDateTime(new DateTime()));

        $parsed_value = $this->host->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertEquals($this->host->getMd(), $parsed_value);

        // '' empty string

        $this->host->setMd('');

        $parsed_value = $this->host->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertNull($parsed_value);

        // null

        $this->host->setMd(null);

        $parsed_value = $this->host->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertNull($parsed_value);

        // carbon

        $this->host->setMd(Carbon::now());

        $parsed_value = $this->host->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);
    }

    public function test_cast_carbon_date()
    {
        $this->host->setRequest();

        // Carbon

        $now = Carbon::now();
        $this->host->setCarbonDate($now);
        $now_utc = new UTCDateTime($now);

        $parsed_value = $this->host->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertEquals($now_utc, $parsed_value);

        // '' empty string

        $this->host->setCarbonDate('');

        $parsed_value = $this->host->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);

        // null

        $this->host->setCarbonDate(null);

        $parsed_value = $this->host->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);
    }

    public function test_cast_array()
    {
        $this->host->setRequest();

        // filled array

        $this->host->setArray(['key' => 'value']);

        $parsed_value = $this->host->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertArrayHasKey('key', $parsed_value);
        $this->assertEquals('value', $parsed_value['key']);

        // null

        $this->host->setArray(null);

        $parsed_value = $this->host->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertEquals([], $parsed_value);

        // getAttributes

        $this->host->setArray($this->createSubItems());

        $parsed_value = $this->host->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
    }

    public function test_unique_mini_model()
    {
        $this->host->setMongoRelation([
            'relation' => [
                'type' => 'EmbedsMany',
                'mode' => 'classic',
                'model' => 'App\Models\MiniRelation',
                'modelTarget' => 'App\Models\Relation',
                'methodOnTarget' => 'Related',
            ],
        ]);

        $this->expectException(Exception::class);

        $this->host->getUniqueMiniModelList();
    }

    public function test_obj_with_ref_id()
    {
        $this->expectException(Exception::class);

        $this->host->getObjWithRefId('', [
            'type' => 'fake',
        ]);
    }

    public function test_embed_model()
    {
        $this->host->setMiniModels([
            'modelTargets' => 'App\Models\Relation',
        ]);

        $this->expectException(Exception::class);

        $this->host->getEmbedModel('modelTarget');
    }
}
