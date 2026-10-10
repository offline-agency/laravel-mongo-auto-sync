<?php

namespace Tests\AnalyseFixtures\BadAutoload;

use Tests\AnalyseFixtures\BadAutoload\Missing\ParentModel;

/**
 * Its parent class does not exist: loading it throws an Error (see AnalyseDatabaseContractTest).
 */
class Broken extends ParentModel {}
