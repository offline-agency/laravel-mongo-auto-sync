<?php

namespace Tests\Models;

/**
 * A target declared on its own connection: target sync must keep it there, whatever connection the source uses.
 */
class CtxNavigation extends Navigation
{
    protected $connection = 'ctx_targets';
}
