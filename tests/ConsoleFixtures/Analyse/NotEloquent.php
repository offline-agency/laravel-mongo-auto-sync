<?php

namespace Tests\ConsoleFixtures\Analyse;

/**
 * Exposes a relation configuration but is not an Eloquent model, so it has no documents to analyse.
 */
class NotEloquent
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getMongoRelation(): array
    {
        return [
            'links' => [
                'type' => 'EmbedsMany',
                'model' => 'Tests\Models\MiniSubItem',
                'modelTarget' => 'Tests\Models\SubItem',
                'methodOnTarget' => 'navigation',
                'modelOnTarget' => 'Tests\Models\MiniNavigation',
            ],
        ];
    }
}
