<?php

namespace Tests\Models;

/**
 * A SubItem whose target (CtxNavigation) is declared on its own connection.
 */
class CtxSubItem extends SubItem
{
    protected $table = 'sub_items';

    protected $mongoRelation = [
        'navigation' => [
            'type' => 'EmbedsOne',
            'mode' => 'classic',
            'model' => MiniNavigation::class,
            'modelTarget' => CtxNavigation::class,
            'methodOnTarget' => 'sub_items',
            'modelOnTarget' => MiniSubItem::class,
            'typeOnTarget' => 'EmbedsMany',
        ],
    ];
}
