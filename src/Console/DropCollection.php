<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\ModelNotFoundException;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class DropCollection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drop:collection {collection_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all elements of the collection given as input';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return void |null
     *
     * @throws Exception
     */
    public function handle()
    {
        /** @var string */
        $collection_name = $this->argument('collection_name');

        $modelPath = $this->getModelPathByName($collection_name);

        $model = $this->getModel($modelPath);

        $count = $model->count();
        $bar = $this->output->createProgressBar($count);

        if ($count > 0) {
            $i = 0;
            foreach ($model->cursor() as $item) {
                $bar->advance();
                $item->destroyWithSync();
                $this->line(($i + 1).') Destroy item document with id #'.$item->getId());
                $i++;
            }
        } else {
            $this->warn('No record found on collection '.strtolower($collection_name));
        }
    }

    /**
     * @param  string  $collection_name
     * @return string
     *
     * @throws Exception
     */
    public function getModelPathByName($collection_name)
    {
        $path = config('laravel-mongo-auto-sync.model_path');

        if (! is_string($path)) {
            throw new Exception('Config laravel-mongo-auto-sync.model_path is not set or invalid');
        }

        return $this->checkOaModels($path, $collection_name);
    }

    /**
     * @param  string  $path
     * @param  string  $collection_name
     * @return string
     *
     * @throws InvalidConfigurationException
     */
    public function checkOaModels($path, $collection_name)
    {
        $namespace = config('laravel-mongo-auto-sync.model_namespace');
        $found = $this->findModelInDirectory($path, $collection_name, is_string($namespace) ? $namespace : '');
        if ($found !== '') {
            return $found;
        }

        foreach (config('laravel-mongo-auto-sync.other_models') as $key => $values) {
            if (strtolower($collection_name) == $key) {
                return $values['model_namespace'].'\\'.Str::ucfirst($key);
            }
        }

        return '';
    }

    /**
     * Search $path recursively; a model in a subdirectory lives in the matching sub-namespace (PSR-4).
     *
     * @throws InvalidConfigurationException
     */
    private function findModelInDirectory(string $path, string $collection_name, string $namespace): string
    {
        $results = @scandir($path);
        if ($results === false) {
            throw new InvalidConfigurationException('Error directory '.config('laravel-mongo-auto-sync.model_path').' not found');
        }

        foreach ($results as $result) {
            if ($result === '.' or $result === '..') {
                continue;
            }
            $filename = $path.'/'.$result;
            if (is_dir($filename)) {
                $found = $this->findModelInDirectory($filename, $collection_name, $namespace.'\\'.$result);
                if ($found !== '') {
                    return $found;
                }
            } elseif (strtolower(substr($result, 0, -4)) == strtolower($collection_name)) {
                return $namespace.'\\'.substr($result, 0, -4);
            }
        }

        return '';
    }

    /**
     * @return MDModel
     *
     * @throws ModelNotFoundException
     */
    private function getModel(string $modelPath)
    {
        if (class_exists($modelPath)) {
            /** @var MDModel */
            return new $modelPath;
        } else {
            $collection_name = $this->argument('collection_name');
            $name = is_string($collection_name) ? $collection_name : 'unknown';
            throw new ModelNotFoundException('Error '.$name.' Model not found');
        }
    }
}
