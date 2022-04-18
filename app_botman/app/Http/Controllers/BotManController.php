<?php

namespace App\Http\Controllers;

use BotMan\BotMan\BotMan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Conversations\ExampleConversation;
use Illuminate\Http\Response;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Http\Curl;
use BotMan\BotMan\Cache\ArrayCache;
use BotMan\BotMan\Storages\Drivers\FileStorage;
use BotMan\BotMan\BotManFactory;

class BotManController extends Controller
{
    /**
     * Place your BotMan logic here.
     */
    public function handle()
    {
        $botman = app('botman');
        
        $botman->listen();
    }
    
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tinker()
    {
        return view('tinker');
    }
    
    /**
     * Loaded through routes/botman.php
     * @param  BotMan $bot
     */
    public function startConversation(BotMan $bot)
    {
        $bot->startConversation(new ExampleConversation());
    }
    
    /**
     * Send a message
     * @param  BotMan $bot
     */
    public function sendMessage(Request $request)
    {
        $response = new Response();
        
        if (config('botman.zoom.send_message_secret') != "") {
            if ($request->header("authorization") != config('botman.zoom.send_message_secret')) {
                $response->setStatusCode("401");
                return $response;
            }
        }
        
        $content = json_decode($request->getContent(), true);
        $botman = resolve('botman');
        $botman->say($content['message'], $content['account'], 'App\Drivers\ZoomDriver');
        
        $response->header('Content-Type', 'application/json');
        $response->setContent(json_encode('ok'));
        
        return $response;
    }
}
