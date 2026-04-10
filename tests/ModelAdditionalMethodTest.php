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

class ModelAdditionalMethodTest extends TestCase
{
    use SyncHelpers;

    public function test_cast_ml()
    {
        $model = new TestModelWithTraits;
        $model->setRequest();

        // []

        $model->setMl([]);

        $parsed_value = $model->castValueToBeSaved('ml', [
            'is-ml' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertEquals([], $parsed_value);

        // null

        $model->setMl(null);

        $parsed_value = $model->castValueToBeSaved('ml', [
            'is-ml' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertCount(1, $parsed_value);
    }

    public function test_cast_md()
    {
        $model = new TestModelWithTraits;
        $model->setRequest();

        // UTCDateTime

        $model->setMd(new UTCDateTime(new DateTime));

        $parsed_value = $model->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertEquals($model->getMd(), $parsed_value);

        // '' empty string

        $model->setMd('');

        $parsed_value = $model->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertNull($parsed_value);

        // null

        $model->setMd(null);

        $parsed_value = $model->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertNull($parsed_value);

        // carbon

        $model->setMd(Carbon::now());

        $parsed_value = $model->castValueToBeSaved('md', [
            'is-md' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);
    }

    public function test_cast_carbon_date()
    {
        $model = new TestModelWithTraits;
        $model->setRequest();

        // Carbon

        $now = Carbon::now();
        $model->setCarbonDate($now);
        $now_utc = new UTCDateTime($now);

        $parsed_value = $model->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertEquals($now_utc, $parsed_value);

        // '' empty string

        $model->setCarbonDate('');

        $parsed_value = $model->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);

        // null

        $model->setCarbonDate(null);

        $parsed_value = $model->castValueToBeSaved('carbon_date', [
            'is-carbon-date' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertInstanceOf(UTCDateTime::class, $parsed_value);
    }

    public function test_cast_array()
    {
        $model = new TestModelWithTraits;
        $model->setRequest();

        // filled array

        $model->setArray(['key' => 'value']);

        $parsed_value = $model->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertArrayHasKey('key', $parsed_value);
        $this->assertEquals('value', $parsed_value['key']);

        // null

        $model->setArray(null);

        $parsed_value = $model->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
        $this->assertEquals([], $parsed_value);

        // getAttributes

        $model->setArray($this->createSubItems());

        $parsed_value = $model->castValueToBeSaved('array', [
            'is-array' => true,
        ], 'Tests\Models\MiniSubItem');

        $this->assertIsArray($parsed_value);
    }

    public function test_unique_mini_model()
    {
        $model = new TestModelWithTraits;
        $model->setMongoRelation([
            'relation' => [
                'type' => 'EmbedsMany',
                'mode' => 'classic',
                'model' => 'App\Models\MiniRelation',
                'modelTarget' => 'App\Models\Relation',
                'methodOnTarget' => 'Related',
            ],
        ]);

        $this->expectException(Exception::class);

        $model->getUniqueMiniModelList();
    }

    public function test_obj_with_ref_id()
    {
        $model = new TestModelWithTraits;
        $this->expectException(Exception::class);

        $model->getObjWithRefId('', [
            'type' => 'fake',
        ]);
    }

    public function test_embed_model()
    {
        $model = new TestModelWithTraits;
        $model->setMiniModels([
            'modelTargets' => 'App\Models\Relation',
        ]);

        $this->expectException(Exception::class);

        $model->getEmbedModel('modelTarget');
    }
}

class TestModelWithTraits
{
    use Helper,
        MainMongoTrait,
        ModelAdditionalMethod;

    protected $ml;

    protected $md;

    protected $carbon_date;

    protected $array;

    protected $mongoRelation;

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

    public function setRequest()
    {
        $this->request = new Request;
    }

    public function setMongoRelation($mongoRelation)
    {
        $this->mongoRelation = $mongoRelation;
    }

    public function setMiniModels($mini_models)
    {
        $this->mini_models = $mini_models;
    }
}
