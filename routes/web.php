<?php

use App\Ai\Agents\Jarvis;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('users', 'pages::users')->name('users.index');
});


Route::get('/agent/{prompt}',function($prompt){
    $response = (new Jarvis)
    ->prompt($prompt);
 
    return (string) $response;
});
require __DIR__.'/settings.php';
