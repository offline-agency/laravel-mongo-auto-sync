<?php

namespace src\Console\GenerateModelDocumentation;

use Illuminate\Console\Command;

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $collection_name = $this->argument( 'collection_name' );

        $modelPath = $this->getModelPathByName($collection_name);
        $model = new $modelPath;

        if(!is_null($model)){
            $items = $model->getItems();
            $relations = $model->getMongoRelation();

            echo "\n\n\n\n/**\n*\n* Plain Fields\n* \n";
            echo "* @property string \$id\n";

            foreach ($items as $key => $item){
                if(isML($item)){
                    echo "* @property array \$" . $key . "\n";
                }else{
                    echo "* @property string \$" . $key . "\n";
                }
            }
            echo "*\n*\n*";
            if(sizeof($relations) > 0){
                echo " Relationships\n*\n";
                foreach ($relations as $key => $relation){

                    $modelTarget = str_replace("App\Models\\","",$relation['model']);

                    echo "* @property " . $modelTarget . " \$" . $key . "\n";
                }
                echo "*\n**/ \n\n\n\n\n";
            }
        }else{
            $this->error('Error Model not found \n');
        }
    }

    public function getModelPathByName($collection_name)
    {
        $path = app_path() . "/Models";

        return $this->checkOaModels($path, $collection_name);
    }

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
                return 'App\Models\\' . substr($result, 0, -4);
            }
        }
        if (strtolower($collection_name) == "user") {
            $out = 'App\Models\\' . "Auth\User\User";
        }
        return $out;
    }
}
