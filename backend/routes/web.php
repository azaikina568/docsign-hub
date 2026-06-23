<?php

use App\Http\Controllers\DiagramsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/api/v1/health');
});

Route::get('docs/diagrams', DiagramsController::class)->name('docs.diagrams');
