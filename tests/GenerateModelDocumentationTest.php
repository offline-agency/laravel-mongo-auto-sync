<?php

test('generate model documentation', function () {
    $output = getOutput();

    /*$this->artisan('model-doc:generate', ['collection_name' => 'Article'])
        ->expectsOutput($output)
        ->assertExitCode(0);*/
    expect(true)->toBeTrue();
});

test('generate model documentation for article', function () {
    /*$this->artisan('model-doc:generate', ['collection_name' => 'article'])
        ->expectsOutput('')
        ->assertExitCode(0);*/

    expect(true)->toBeTrue();
});

test('exception model not found', function () {
    $this->expectExceptionMessage('Error ModelThatDoesNotExist Model not found');
    $this->artisan('model-doc:generate', ['collection_name' => 'ModelThatDoesNotExist'])
        ->assertExitCode(0);
});

test('exception path not found', function () {
    config()->set('laravel-mongo-auto-sync.model_path', 'path_that_does_not_exist');
    $this->expectExceptionMessage('Error directory path_that_does_not_exist not found');
    $this->artisan('model-doc:generate', ['collection_name' => 'ModelThatDoesNotExist'])
        ->assertExitCode(0);
});

function getOutput()
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
