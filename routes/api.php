<?php
use App\Http\Controllers\Api\VideoDownloaderController;
use Illuminate\Support\Facades\Route;

Route::post('/get-video', [VideoDownloaderController::class, 'getVideoInfo']);

Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Engine is running perfectly!',
        'time' => now()->toDateTimeString()
    ], 200);
});