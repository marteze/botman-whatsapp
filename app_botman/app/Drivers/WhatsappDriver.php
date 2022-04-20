<?php

namespace App\Drivers;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\WebAccess;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use Symfony\Component\HttpFoundation\ParameterBag;
use function GuzzleHttp\json_encode;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Attachments\File;

class WhatsappDriver extends HttpDriver
{
    const DRIVER_NAME = 'Whatsapp';
    
    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->config = Collection::make($this->config->get('whatsapp', []));
        
        $serverRequestData = $request->request;
        
        try  {
            $params = [
                "driver" => "whatsapp",
                "sender" => $serverRequestData->get('from'),
                "author" => $serverRequestData->get('author'),
                "message" => $serverRequestData->get('body'),
                "attachment" => null,
                "interactive" => false,
                "attachment_data" => null,
                "raw" => $serverRequestData->all()
            ];
        } catch (\Exception $e) {
            $this->payload = Collection::make([]);
            $this->event = Collection::make([]);
            return;
        }
        
        $this->payload = new ParameterBag($params);
        
        $this->event = Collection::make($this->payload);
    }
    
    /**
     * @param IncomingMessage $matchingMessage
     * @return \BotMan\BotMan\Users\User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        return new User($matchingMessage->getSender(), null, null, null, null);
    }
    
    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return (strpos($this->event->get('sender'), '@c.us') !== false) || (strpos($this->event->get('sender'), '@g.us') !== false);
    }
    
    /**
     * @param  IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        $interactive = $this->event->get('interactive', false);
        
        if (is_string($interactive)) {
            $interactive = ($interactive !== 'false') && ($interactive !== '0');
        } else {
            $interactive = (bool) $interactive;
        }
        
        return Answer::create($message->getText())
        ->setValue($this->event->get('value', $message->getText()))
        ->setMessage($message)
        ->setInteractiveReply($interactive);
    }
    
    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = $this->event->get('message');
            $sender = $this->event->get('sender');
            
            $incomingMessage = new IncomingMessage($message, $sender, $sender, $this->payload);
            
            $this->messages = [$incomingMessage];
        }
        
        return $this->messages;
    }
    
    /**
     *
     * @param string|Question|OutgoingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        if ($message instanceof OutgoingMessage) {
            if ($message->getAttachment() == false) {
                return [
                    'media' => false,
                    'message' => $message->getText()
                ];
            } else {
                $attachment = $message->getAttachment();
                
                if (($attachment instanceof Image) || ($attachment instanceof Audio) || ($attachment instanceof Video) || ($attachment instanceof File)) {
                    return [
                        'media' => true,
                        'url' => $attachment->getUrl(),
                        'caption' => $message->getText()
                    ];
                }
            }
        } elseif ($message instanceof Question) {
            return [
                'media' => false,
                'message' => $message->getText()
            ];
        } elseif ($message instanceof Image) {
            return [
                'media' => true,
                'url' => $message->getUrl(),
                'caption' => $message->getTitle()
            ];
        } elseif (($message instanceof Audio) || ($message instanceof Video) || ($message instanceof File)) {
            return [
                'media' => true,
                'url' => $message->getUrl(),
                'caption' => ' '
            ];
        } elseif (is_scalar($message) == true) {
            return [
                'media' => false,
                'message' => $message . ''
            ];
        }
    }
    
    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        $ehGrupo = (strpos($this->event->get('sender'), '@g.us') !== false);
        
        $number = str_replace('@c.us', '', str_replace('@g.us', '', $this->payload->get('sender')));
        
        $headers = [
            'Content-Type: application/json',
        ];
        
        if ($payload['media'] == false) {
            $post = [
                'number' => $number,
                'is_group' => $ehGrupo,
                'message' => $payload['message']
            ];
            
            $url = $this->config->get('whatsbox_url') . "/sendText";
        } else {
            $post = [
                'number' => $number,
                'is_group' => $ehGrupo,
                'url' => $payload['url'],
                'caption' => $payload['caption'],
            ];
            
            if ($post['caption'] == '') {
                $post['caption'] = ' ';
            }
            
            $url = $this->config->get('whatsbox_url') . "/sendMedia";
        }
        
        $response = $this->http->post($url, [], $post, $headers, true);
        
        if ($response->getStatusCode() >= 400) {
            throw new \Exception('Whatsbox server returned status code ' . $response->getStatusCode() . '! Message: ' . $response->getContent());
        }
        
        return $response;
    }
    
    /**
     * @param $messages
     * @return array
     */
    protected function buildReply($messages)
    {
        $replyData = Collection::make($messages)->transform(function ($replyData) {
            $reply = [];
            $message = $replyData['message'];
            $additionalParameters = $replyData['additionalParameters'];
            
            if ($message instanceof WebAccess) {
                $reply = $message->toWebDriver();
            } elseif ($message instanceof OutgoingMessage) {
                $attachmentData = (is_null($message->getAttachment())) ? null : $message->getAttachment()->toWebDriver();
                $reply = [
                    'type' => 'text',
                    'text' => $message->getText(),
                    'attachment' => $attachmentData,
                ];
            }
            $reply['additionalParameters'] = $additionalParameters;
            
            return $reply;
        })->toArray();
        
        return $replyData;
    }
    
    /**
     * @return bool
     */
    public function isConfigured()
    {
        return $this->config->get('whatsbox_url');
    }
    
    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        return false;
    }
    
    public function setActionsLimitDisplay($limit) {
        $this->actionsLimitDisplay = $limit;
    }
    
    private function writeDebug($value) {
        if (is_scalar($value) == true) {
            file_put_contents('/tmp/debug.txt', $value . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents('/tmp/debug.txt', print_r($value, true), FILE_APPEND | LOCK_EX);
        }
    }
}