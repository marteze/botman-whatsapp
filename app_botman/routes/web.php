<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::match(['get', 'post'], '/botman', 'BotManController@handle');
Route::get('/botman/tinker', 'BotManController@tinker');

Route::get('/botman/authorize-chatbot', 'BotManController@authorizeChatbot');
Route::get('/botman/support', 'BotManController@support');
Route::get('/botman/privacy', 'BotManController@privacy');
Route::get('/botman/terms', 'BotManController@terms');
Route::get('/botman/documentation', 'BotManController@documentation');
Route::post('/botman/deauthorize', 'BotManController@deauthorize');
Route::post('/botman/send-message', 'BotManController@sendMessage');