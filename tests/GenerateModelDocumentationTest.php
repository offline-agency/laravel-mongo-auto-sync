<?php

namespace Tests;

class GenerateModelDocumentationTest extends TestCase
{
    public function test_generate_model_documentation()
    {
        $this->artisan('model-doc:generate', ['collection_name' => 'Article'])
            ->expectsOutput('')
            ->assertExitCode(0);
    }

    public function test_generate_model_documentation_for_article()
    {
        $this->artisan('model-doc:generate', ['collection_name' => 'article'])
            ->expectsOutput('')
            ->assertExitCode(0);
    }

    public function test_exception_model_not_found()
    {
        $this->expectExceptionMessage('Error ModelDoesNotExist Model not found');
        $this->artisan('model-doc:generate', ['collection_name' => 'ModelDoesNotExist'])
            ->assertExitCode(0);
    }
}

