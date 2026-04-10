<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Benchmark;

use function Termwind\render;

class BenchmarkCommand extends Command
{
    protected $signature = 'mongo-sync:benchmark {model} {count=100}';

    protected $description = 'Benchmark MongoDB operations';

    /**
     * @return void
     */
    public function handle()
    {
        /** @var string */
        $modelClass = $this->argument('model');
        /** @var int */
        $count = $this->argument('count');

        if (! class_exists($modelClass)) {
            $this->error("Class $modelClass does not exist.");

            return;
        }

        render('<div class="px-1 bg-green-500 text-white">Starting Benchmark...</div>');

        $times = [];

        // Insert
        // @phpstan-ignore-next-line
        $times['Insert'] = Benchmark::measure(function () use ($modelClass, $count) {
            for ($i = 0; $i < $count; $i++) {
                $model = new $modelClass;
                // Assume storeWithSync works with empty request or minimal
                // We need some data?
                // This is hard to genericize without factories.
                // Just doing simple save() if storeWithSync is too complex
                // @phpstan-ignore-next-line
                $model->save();
            }
        });

        // Read
        // @phpstan-ignore-next-line
        $times['Read'] = Benchmark::measure(function () use ($modelClass, $count) {
            $modelClass::limit($count)->get();
        });

        // Update
        $items = $modelClass::limit($count)->get();
        // @phpstan-ignore-next-line
        $times['Update'] = Benchmark::measure(function () use ($items) {
            foreach ($items as $item) {
                $item->touch();
            }
        });

        // Delete
        // @phpstan-ignore-next-line
        $times['Delete'] = Benchmark::measure(function () use ($items) {
            foreach ($items as $item) {
                $item->delete();
            }
        });

        render('<table>
            <thead>
                <tr>
                    <th>Operation</th>
                    <th>Time (ms)</th>
                    <th>Avg (ms)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Insert</td>
                    <td>'.$times['Insert'].'</td>
                    <td>'.($times['Insert'] / $count).'</td>
                </tr>
                <tr>
                    <td>Read</td>
                    <td>'.$times['Read'].'</td>
                    <td>'.($times['Read'] / $count).'</td>
                </tr>
                <tr>
                    <td>Update</td>
                    <td>'.$times['Update'].'</td>
                    <td>'.($times['Update'] / $count).'</td>
                </tr>
                <tr>
                    <td>Delete</td>
                    <td>'.$times['Delete'].'</td>
                    <td>'.($times['Delete'] / $count).'</td>
                </tr>
            </tbody>
        </table>');
    }
}
