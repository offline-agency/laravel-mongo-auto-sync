<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
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
     * @throws Exception
     */
    public function handle()
    {
        $collection_name = $this->argument('collection_name');

        $modelPath = $this->getModelPathByName($collection_name);

        $model = $this->getModel($modelPath);

        if (! is_null($model)) {
            $model = $model->all();

            $count = $model->count();
            $bar = $this->output->createProgressBar($count);

            if ($count > 0) {
                for ($i = 0; $i <= $count - 1; $i++) {
                    $bar->advance();
                    $model[$i]->destroyWithSync();
                    $this->line($i + 1 .') Destroy item document with id #'.$model[$i]->getId());
                }
            } else {
                $this->warn('No record found on collection '.strtolower($collection_name));
            }
        } else {
            $this->error('Error Model not found \n');
        }
    }

    /**
     * @param $collection_name
     * @return string
     * @throws Exception
     */
    public function getModelPathByName($collection_name)
    {
        $path = config('laravel-mongo-auto-sync.model_path');

        return $this->checkOaModels($path, $collection_name);
    }

    /**
     * @param $path
     * @param $collection_name
     * @return string
     * @throws Exception
     */
    public function checkOaModels($path, $collection_name)
    {
        $out = '';

        try {
            $results = scandir($path);
        } catch (Exception $e) {
            throw new Exception('Error directory '.config('laravel-mongo-auto-sync.model_path').' not found');
        }

        foreach ($results as $result) {
            if ($result === '.' or $result === '..') {
                continue;
            }
            $filename = $path.'/'.$result;
            if (is_dir($filename)) {
                $out = $this->checkOaModels($filename, $collection_name);
            } elseif (strtolower(substr($result, 0, -4)) == strtolower($collection_name)) {
                return config('laravel-mongo-auto-sync.model_namespace').'\\'.substr($result, 0, -4);
            }
        }

        foreach (config('laravel-mongo-auto-sync.other_models') as $key => $values) {
            if (strtolower($collection_name) == $key) {
                return $values['model_namespace'].'\\'.Str::ucfirst($key);
            }
        }

        return $out;
    }

    /**
     * @param string $modelPath
     * @return MDModel
     * @throws Exception
     */
    private function getModel(string $modelPath)
    {
        if (class_exists($modelPath)) {
            return new $modelPath;
        } else {
            throw new Exception('Error '.$this->argument('collection_name').' Model not found');
        }
    }
}
