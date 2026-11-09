<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('appleworm:about', function () { $this->info('Apple Worm Adventure — Laravel game app'); })->purpose('Display the game application name');
