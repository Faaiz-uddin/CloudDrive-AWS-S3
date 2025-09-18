<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\S3Controller;




Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->prefix('s3')->group(function () {

    Route::post('upload', [S3Controller::class, 'upload']);
    Route::get('download/{filePath}', [S3Controller::class, 'download'])
     ->where('filePath', '.*');
    Route::get('temp-url/{filePath}', [S3Controller::class, 'getTemporaryUrl'])
        ->where('filePath', '.*');
    Route::delete('delete/{filePath}', [S3Controller::class, 'delete'])->where('filePath', '.*');
    Route::get('list/{folder?}', [S3Controller::class, 'listFiles']);
    Route::post('move', [S3Controller::class, 'move']);
});




Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

?>
