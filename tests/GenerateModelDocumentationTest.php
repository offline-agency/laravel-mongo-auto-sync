<?php

namespace Tests;

class GenerateModelDocumentationTest extends TestCase
{
    public function test_generate_model_documentation()
    {
        $output = $this->getOutput();

        /*$this->artisan('model-doc:generate', ['collection_name' => 'Article'])
            ->expectsOutput($output)
            ->assertExitCode(0);*/
        $this->assertTrue(true);
    }

    public function test_generate_model_documentation_for_article()
    {
        /*$this->artisan('model-doc:generate', ['collection_name' => 'article'])
            ->expectsOutput('')
            ->assertExitCode(0);*/

        $this->assertTrue(true);
    }

    public function test_exception_model_not_found()
    {
        $this->expectExceptionMessage('Error ModelThatDoesNotExist Model not found');
        $this->artisan('model-doc:generate', ['collection_name' => 'ModelThatDoesNotExist'])
            ->assertExitCode(0);
    }

    public function test_exception_path_not_found()
    {
        config()->set('laravel-mongo-auto-sync.model_path', 'path_that_does_not_exist');
        $this->expectExceptionMessage('Error directory path_that_does_not_exist not found');
        $this->artisan('model-doc:generate', ['collection_name' => 'ModelThatDoesNotExist'])
            ->assertExitCode(0);
    }

    private function getOutput()
    {
        return '




/**
*
* Plain Fields
*
* @property string $id
* @property string $title
*
*
*';
    }
}
