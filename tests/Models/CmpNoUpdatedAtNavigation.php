<?php

namespace Tests\Models;

/**
 * Navigation without an updated_at column (UPDATED_AT = null), used as a sync target.
 */
class CmpNoUpdatedAtNavigation extends Navigation
{
    public const UPDATED_AT = null;
}
