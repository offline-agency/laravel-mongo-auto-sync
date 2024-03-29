<?php

namespace Tests;

use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Traits\Helper;
use OfflineAgency\MongoAutoSync\Traits\MainMongoTrait;
use OfflineAgency\MongoAutoSync\Traits\ModelAdditionalMethod;

class ModelAdditionalMethodTest extends SyncTestCase
{
    use ModelAdditionalMethod,
        Helper,
        MainMongoTrait;

    protected $ml;
    protected $md;
    protected $carbon_date;
    protected $array;

    protected $request;

    protected $mongoRelation;

    protected $mini_models;

    public function test_cast_ml()
    {
        $this->setRequest();

        // []

        $this->setMl([]);

        $parsed_value = $this->castValueToBeSaved('ml', [
            'is-ml' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertEquals([], $parsed_value);

        // null

        $this->setMl(null);

        $parsed_value = $this->castValueToBeSaved('ml', [
            'is-ml' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertCount(1, $parsed_value);
    }

    public function test_cast_md()
    {
        $this->setRequest();

        // UTCDateTime

        $this->setMd(new UTCDateTime(new DateTime()));

        $parsed_value = $this->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertEquals($this->getMd(), $parsed_value);

        // '' empty string

        $this->setMd('');

        $parsed_value = $this->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertNull($parsed_value);

        // null

        $this->setMd(null);

        $parsed_value = $this->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertNull($parsed_value);

        // carbon

        $this->setMd(Carbon::now());

        $parsed_value = $this->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);
    }

    public function test_cast_carbon_date()
    {
        $this->setRequest();

        // Carbon

        $now = Carbon::now();
        $this->setCarbonDate($now);
        $now_utc = new UTCDateTime($now);

        $parsed_value = $this->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertEquals($now_utc, $parsed_value);

        // '' empty string

        $this->setCarbonDate('');

        $parsed_value = $this->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);

        // null

        $this->setCarbonDate(null);

        $parsed_value = $this->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);
    }

    public function test_cast_array()
    {
        $this->setRequest();

        // filled array

        $this->setArray(['key' => 'value']);

        $parsed_value = $this->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertArrayHasKey('key', $parsed_value);
        $this->assertEquals('value', $parsed_value['key']);

        // null

        $this->setArray(null);

        $parsed_value = $this->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertEquals([], $parsed_value);

        // getAttributes

        $this->setArray($this->createSubItems());

        $parsed_value = $this->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
    }

    public function test_unique_mini_model()
    {
        $this->setMongoRelation([
            'relation' => [
                'type' => 'EmbedsMany',
                'mode' => 'classic',
                'model' => 'App\Models\MiniRelation',
                'modelTarget' => 'App\Models\Relation',
                'methodOnTarget' => 'Related',
            ],
        ]);

        $this->expectException(Exception::class);

        $this->getUniqueMiniModelList();
    }

    public function test_obj_with_ref_id()
    {
        $this->expectException(Exception::class);

        $this->getObjWithRefId('', [
            'type' => 'fake',
        ]);
    }

    public function test_embed_model()
    {
        $this->setMiniModels([
            'modelTargets' => 'App\Models\Relation',
        ]);

        $this->expectException(Exception::class);

        $this->getEmbedModel('modelTarget');
    }

    /* GETTERs & SETTERs */

    public function getMl()
    {
        return $this->ml;
    }

    public function setMl($ml): void
    {
        $this->ml = $ml;
    }

    public function getMd()
    {
        return $this->md;
    }

    public function setMd($md): void
    {
        $this->md = $md;
    }

    public function getCarbonDate()
    {
        return $this->carbon_date;
    }

    public function setCarbonDate($carbon_date): void
    {
        $this->carbon_date = $carbon_date;
    }

    public function getArray()
    {
        return $this->array;
    }

    public function setArray($array): void
    {
        $this->array = $array;
    }

    private function setRequest()
    {
        $this->request = new Request();
    }

    private function setMongoRelation($mongoRelation)
    {
        $this->mongoRelation = $mongoRelation;
    }

    private function setMiniModels($mini_models)
    {
        $this->mini_models = $mini_models;
    }
}
