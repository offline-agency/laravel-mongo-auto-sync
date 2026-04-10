<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\ModelNotFoundException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class GenerateModelDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model-doc:generate {collection_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the documentation of the given Model. The doc is useful for autocomplete suggestion';

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

        $items = $model->getItems();
        $relations = $model->getMongoRelation();

        $output = "\n\n\n\n/**\n*\n* Plain Fields\n* \n";
        $output .= "* @property string \$id\n";

        foreach ($items as $key => $item) {
            if (SyncHelper::isML($item)) {
                $output .= '* @property array $'.$key."\n";
            } else {
                $output .= '* @property string $'.$key."\n";
            }
        }

        $output .= "*\n*\n*";

        if (count($relations) > 0) {
            $output .= " Relationships\n*\n";
            foreach ($relations as $key => $relation) {
                $modelTarget = str_replace("App\Models\\", '', $relation['model']);

                $output .= '* @property '.$modelTarget.' $'.$key."\n";
            }
            $output .= "*\n**/ \n\n\n\n\n";
        }

        $this->info($output);
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
        $out = '';

        try {
            $results = scandir($path);
        } catch (Exception $e) {
            throw new InvalidConfigurationException('Error directory '.config('laravel-mongo-auto-sync.model_path').' not found');
        }

        if ($results === false) {
            return '';
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
