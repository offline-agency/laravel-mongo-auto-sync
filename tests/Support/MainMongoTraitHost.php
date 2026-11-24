<?php

namespace Tests\Support;

use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Traits\MainMongoTrait;

/**
 * Plain host for MainMongoTrait (see ModelAdditionalMethodHost).
 */
class MainMongoTraitHost
{
    use MainMongoTrait;

    public function callCheckRequestExistence(Request $request, string $key)
    {
        return $this->checkRequestExistence($request, $key);
    }
}
