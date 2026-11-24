<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Models\Article;
use Tests\Models\Category;

class ConsoleCommandsTest extends SyncTestCase
{
    private string $modelsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelsDir = sys_get_temp_dir().'/lmas-models-'.uniqid();
        File::makeDirectory($this->modelsDir.'/Nested', 0777, true);
        File::put($this->modelsDir.'/Nested/Article.php', '<?php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modelsDir);

        parent::tearDown();
    }

    public function test_generate_documentation_lists_plain_fields_and_relationships()
    {
        $this->assertSame(0, Artisan::call('model-doc:generate', ['collection_name' => 'Article']));

        $output = Artisan::output();
        $this->assertStringContainsString('@property array $title', $output);
        $this->assertStringContainsString('@property string $visibility', $output);
        $this->assertStringContainsString('Relationships', $output);
        $this->assertStringContainsString('@property Tests\Models\MiniCategory $primarycategory', $output);
    }

    public function test_generate_documentation_without_relationships()
    {
        $this->assertSame(0, Artisan::call('model-doc:generate', ['collection_name' => 'MiniArticle']));

        $output = Artisan::output();
        $this->assertStringContainsString('@property string $ref_id', $output);
        $this->assertStringNotContainsString('Relationships', $output);
    }

    public function test_generate_documentation_looks_into_sub_directories()
    {
        config()->set('laravel-mongo-auto-sync.model_path', $this->modelsDir);

        $this->artisan('model-doc:generate', ['collection_name' => 'Article'])
            ->expectsOutputToContain('@property array $title')
            ->assertExitCode(0);
    }

    public function test_generate_documentation_uses_other_models()
    {
        $emptyDir = $this->modelsDir.'/Empty';
        File::makeDirectory($emptyDir);
        config()->set('laravel-mongo-auto-sync.model_path', $emptyDir);
        config()->set('laravel-mongo-auto-sync.other_models', [
            'article' => ['model_path' => $emptyDir, 'model_namespace' => 'Tests\Models'],
        ]);

        $this->artisan('model-doc:generate', ['collection_name' => 'article'])
            ->expectsOutputToContain('@property array $title')
            ->assertExitCode(0);
    }

    public function test_generate_documentation_with_unknown_model_fails()
    {
        $this->expectExceptionMessage('Error ModelThatDoesNotExist Model not found');

        $this->artisan('model-doc:generate', ['collection_name' => 'ModelThatDoesNotExist']);
    }

    public function test_drop_collection_warns_when_there_are_no_records()
    {
        Article::truncate();

        $this->artisan('drop:collection', ['collection_name' => 'Article'])
            ->expectsOutputToContain('No record found on collection article')
            ->assertExitCode(0);
    }

    public function test_drop_collection_with_missing_models_directory_fails()
    {
        config()->set('laravel-mongo-auto-sync.model_path', 'path_that_does_not_exist');

        $this->expectExceptionMessage('Error directory path_that_does_not_exist not found');

        $this->artisan('drop:collection', ['collection_name' => 'Article']);
    }

    public function test_drop_collection_looks_into_sub_directories()
    {
        Article::truncate();
        Category::truncate();
        config()->set('laravel-mongo-auto-sync.model_path', $this->modelsDir);
        config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\Models');

        $this->artisan('drop:collection', ['collection_name' => 'Article'])
            ->expectsOutputToContain('No record found on collection article')
            ->assertExitCode(0);
    }

    public function test_drop_collection_uses_other_models()
    {
        Article::truncate();
        $emptyDir = $this->modelsDir.'/Empty';
        File::makeDirectory($emptyDir);
        config()->set('laravel-mongo-auto-sync.model_path', $emptyDir);
        config()->set('laravel-mongo-auto-sync.other_models', [
            'article' => ['model_path' => $emptyDir, 'model_namespace' => 'Tests\Models'],
        ]);

        $this->artisan('drop:collection', ['collection_name' => 'article'])
            ->expectsOutputToContain('No record found on collection article')
            ->assertExitCode(0);
    }
}
