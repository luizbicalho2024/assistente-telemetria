<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about-project', function () {
    $this->info('Assistente Telemetria - Laravel + MongoDB');
})->purpose('Identifica o projeto.');
