<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

class CheckModelConfig extends Command
{
    protected $signature = 'mongo-sync:check-config';

    protected $description = 'Check model configurations for errors';

    /**
     * @return void
     */
    public function handle()
    {
        $this->info('Checking model configurations...');

        $modelPath = config('laravel-mongo-auto-sync.model_path');
        if (! is_string($modelPath)) {
            $this->error('Model path is not a string');

            return;
        }
        $models = $this->getAllModels($modelPath);

        foreach ($models as $modelClass) {
            $this->info("Checking $modelClass...");
            try {
                $model = new $modelClass;
                if (! method_exists($model, 'getMongoRelation')) {
                    continue;
                }

                $relations = $model->getMongoRelation();
                if (is_array($relations)) {
                    foreach ($relations as $method => $relation) {
                        if (is_array($relation)) {
                            $this->validateRelation($modelClass, (string) $method, $relation);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->error("Error in $modelClass: ".$e->getMessage());
            }
        }

        $this->info('Check completed.');
    }

    /**
     * @param  string  $modelClass
     * @param  string  $method
     * @param  array<mixed>  $relation
     * @return void
     */
    private function validateRelation($modelClass, $method, $relation)
    {
        if (! isset($relation['type'])) {
            throw new InvalidConfigurationException("Relation $method in $modelClass missing 'type'");
        }

        $type = $relation['type'];
        if (! in_array($type, ['EmbedsOne', 'EmbedsMany', 'HasOne', 'HasMany'])) {
            throw new InvalidConfigurationException("Relation $method in $modelClass has invalid type '$type'");
        }

        if (! isset($relation['model'])) {
            throw new InvalidConfigurationException("Relation $method in $modelClass missing 'model'");
        }

        if (! class_exists($relation['model'])) {
            throw new InvalidConfigurationException("Relation $method in $modelClass refers to non-existent model '{$relation['model']}'");
        }

        if (SyncHelper::hasTarget($relation)) {
            $requiredKeys = ['modelTarget', 'methodOnTarget', 'modelOnTarget'];
            foreach ($requiredKeys as $key) {
                if (! isset($relation[$key])) {
                    throw new InvalidConfigurationException("Relation $method in $modelClass missing '$key' for target sync");
                }
            }

            if (! class_exists($relation['modelTarget'])) {
                throw new InvalidConfigurationException("Relation $method in $modelClass refers to non-existent target model '{$relation['modelTarget']}'");
            }

            // Check if methodOnTarget exists as a property or method?
            // It's usually a property for EmbedsMany/One, or method for HasMany/One?
            // In this package it seems to be treated as property usually for Embeds.
        }
    }

    /**
     * @param  string  $path
     * @return array<string>
     */
    private function getAllModels($path)
    {
        $out = [];
        try {
            $results = scandir($path);
        } catch (Exception $e) {
            $this->error("Directory $path not found");

            return [];
        }

        if ($results === false) {
            return [];
        }

        foreach ($results as $result) {
            if ($result === '.' or $result === '..') {
                continue;
            }
            $filename = $path.'/'.$result;
            if (is_dir($filename)) {
                $out = array_merge($out, $this->getAllModels($filename));
            } elseif (substr($result, -4) === '.php') {
                $className = config('laravel-mongo-auto-sync.model_namespace').'\\'.substr($result, 0, -4);
                if (class_exists($className)) {
                    $out[] = $className;
                }
            }
        }

        // Also add other_models from config
        $otherModels = config('laravel-mongo-auto-sync.other_models', []);
        if (is_array($otherModels)) {
            foreach ($otherModels as $key => $values) {
                if (is_array($values) && isset($values['model_namespace'])) {
                    $className = $values['model_namespace'].'\\'.Str::ucfirst((string) $key);
                    if (class_exists($className)) {
                        $out[] = $className;
                    }
                }
            }
        }

        return $out;
    }
}
