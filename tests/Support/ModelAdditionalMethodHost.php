<?php

namespace Tests\Support;

use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Traits\Helper;
use OfflineAgency\MongoAutoSync\Traits\MainMongoTrait;
use OfflineAgency\MongoAutoSync\Traits\ModelAdditionalMethod;

/**
 * Plain host for the package traits.
 *
 * The traits cannot be mixed into a PHPUnit test case: Helper::isArray()
 * clashes with the final PHPUnit\Framework\Assert::isArray() method.
 */
class ModelAdditionalMethodHost
{
    use Helper,
        MainMongoTrait,
        ModelAdditionalMethod;

    protected $ml;

    protected $md;

    protected $carbon_date;

    protected $array;

    protected $request;

    protected $mongoRelation;

    protected $mini_models;

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
