<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
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
     * @return |null
     * @throws Exception
     */
    public function handle()
    {
        $collection_name = $this->argument('collection_name');

        $modelPath = $this->getModelPathByName($collection_name);

        $model = $this->getModel($modelPath);

        $items = $model->getItems();
        $relations = $model->getMongoRelation();

        $output = "\n\n\n\n/**\n*\n* Plain Fields\n* \n";
        $output .=  "* @property string \$id\n";

        foreach ($items as $key => $item) {
            if (isML($item)) {
                $output .= "* @property array \$" . $key . "\n";
            } else {
                $output .= "* @property string \$" . $key . "\n";
            }
        }

        $output .= "*\n*\n*";

        if (sizeof($relations) > 0) {
            $output .= " Relationships\n*\n";
            foreach ($relations as $key => $relation) {

                $modelTarget = str_replace("App\Models\\", "", $relation['model']);

                $output .= "* @property " . $modelTarget . " \$" . $key . "\n";
            }
            $output .= "*\n**/ \n\n\n\n\n";
        }

        $this->info($output);

        return null;
    }

    /**
     * @param $collection_name
     * @return string
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
     */
    public function checkOaModels($path, $collection_name)
    {
        $out = "";
        $results = scandir($path);
        foreach ($results as $result) {
            if ($result === '.' or $result === '..') {
                continue;
            }
            $filename = $path . '/' . $result;
            if (is_dir($filename)) {
                $out = $this->checkOaModels($filename, $collection_name);
            } else if (strtolower(substr($result, 0, -4)) == strtolower($collection_name)) {
                return config('laravel-mongo-auto-sync.model_namespace') . "\\" . substr($result, 0, -4);
            }
        }
        foreach (config('laravel-mongo-auto-sync.other_models') as $key => $values) {
            if (strtolower($collection_name) == $key) {
                return $values['model_namespace'] . '\\' . Str::ucfirst($key);
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
            throw new Exception('Error ' . $this->argument('collection_name') . ' Model not found');
        }
    }
}
