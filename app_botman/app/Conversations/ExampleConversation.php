<?php

namespace App\Conversations;

use Illuminate\Foundation\Inspiring;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;

class ExampleConversation extends Conversation
{
    /**
     * First question
     */
    public function askReason()
    {
        $question = Question::create(
            '*Huh - you woke me up.* What do you need?' . PHP_EOL .
            '1 - Tell a joke' . PHP_EOL .
            '2 - Give me a fancy quote')
            ->fallback('Unable to ask question')
            ->callbackId('ask_reason');

        return $this->ask($question, function (Answer $answer) {
            if ($answer->getValue() === '1') {
                $joke = json_decode(file_get_contents('http://api.icndb.com/jokes/random'));
                
                $this->say(html_entity_decode($joke->value->joke));
            } elseif ($answer->getValue() === '2') {
                $this->say(Inspiring::quote());
            } else {
                $this->say('Please choose one of the displayed options.');
                $this->askReason();
            }
        });
    }

    /**
     * Start the conversation
     */
    public function run()
    {
        $this->askReason();
    }
}
