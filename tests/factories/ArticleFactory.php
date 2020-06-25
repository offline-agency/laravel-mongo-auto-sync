<?php

use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Tests\Models\Article;

/** @var Factory $factory */
$factory->define(Article::class, function (Faker $faker) {
    return [
        'title' => $faker->text(20),
    ];
});
