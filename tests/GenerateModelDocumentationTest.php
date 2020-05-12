<?php

namespace Tests;

class GenerateModelDocumentationTest extends TestCase
{
    public function test_generate_model_documentation()
    {
        $this->artisan('model-doc:generate', ['collection_name' => 'article'])
            ->expectsOutput('
                /**
                *
                * Plain Fields
                *
                * @property string $title
                *
                **/
                ')
            ->assertExitCode(0);
    }
}
