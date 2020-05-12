<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DropCollection extends Command {
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
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $collection_name = $this->argument( 'collection_name' );

        $modelPath = getModelPathByName($collection_name);
        $model = new $modelPath;

        if(!is_null($model)){

            $model = $model->all();

            $count = $model->count();
            $bar   = $this->output->createProgressBar( $count );

            if($count > 0){
                for ( $i = 0; $i <= $count-1; $i ++ ) {
                    $bar->advance();
                    $model[$i]->destroyWithSync();
                    $this->line(' _Destroy row #' . ($i+1));
                }
            }else{
                $this->warn('No record found on collection ' . strtolower($collection_name));
            }
        }else{
            $this->error('Error Model not found \n');
        }

    }
}
