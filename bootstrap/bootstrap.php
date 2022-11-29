<?php

class erLhcoreClassExtensionLhcrestapi
{

    public function __construct()
    {

    }

    public function run()
    {
        $dispatcher = erLhcoreClassChatEventDispatcher::getInstance();

        $dispatcher->listen('chat.webhook_incoming', array(
            $this,
            'verifyCall'
        ));

        $dispatcher->listen('chat.rest_api_before_request', array(
            $this,
            'addVariables'
        ));

        $dispatcher->listen('chat.rest_api_make_request', array(
            $this,
            'makeRequest'
        ));
    }

    /*
     * $commandResponse = erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.rest_api_make_request', array(
            'method_settings' => $methodSettings,
            'params_customer' => $paramsCustomer,
            'params_request' => $paramsRequest,
            'url' => $url,
            'ch'  => & $ch
        ));
        1. You can modify request
        2. Add custom headers to curl $ch variables.
        @see
        https://github.com/LiveHelperChat/livehelperchat/blob/master/lhc_web/lib/core/lhgenericbot/actionTypes/lhgenericbotactionrestapi.php#L792-L797
        https://github.com/LiveHelperChat/livehelperchat/blob/master/lhc_web/lib/core/lhgenericbot/actionTypes/lhgenericbotactionrestapi.php#L804-L808
    */
    public function makeRequest($params)
    {
        if (is_object($params['params_customer']['chat']->incoming_chat) && $params['params_customer']['chat']->incoming_chat->incoming->scope == 'customrequest') {
            include_once 'extension/googlebusinessmessage/vendor/autoload.php';

            // create the Google client
            $client = new Google\Client();

            /**
             * Set your method for authentication. Depending on the API, This could be
             * directly with an access token, API key, or (recommended) using
             * Application Default Credentials.
             */
            $client->useApplicationDefaultCredentials();

            $dataGoogle = include('extension/googlebusinessmessage/settings/google_service.json.php');
            $client->setAuthConfig(json_decode($dataGoogle, true));
            $client->addScope('https://www.googleapis.com/auth/businessmessages');

            // returns a Guzzle HTTP Client
            $httpClient = $client->authorize();

            $messageParams = json_decode($params['params_request']['body'], true);

            if (isset($messageParams['text']) && $messageParams['text'] != '') {
                $text = $messageParams['text'];
                erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.make_plain_message', array(
                    'init' => 'googlebusinessmessage',
                    'msg' => & $text
                ));
                $messageParams['text'] = $text;
                $messageParams['fallback'] = $text;
            }

            $response = $httpClient->post($params['url'], [
                GuzzleHttp\RequestOptions::JSON => $messageParams
            ]);

            return [
                'status' => erLhcoreClassChatEventDispatcher::STOP_WORKFLOW,
                'processed' => true,
                'http_response' => (string)$response->getBody(),
                'http_error' => '',
                'http_code' => $response->getStatusCode(),
            ];
        }
    }

    /*
     * This way you can add custom variables to the chat and later use it in Rest API call.
     * erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.rest_api_before_request', array(
            'restapi' => & $restAPI,
            'chat' => $chat,
            'params' => $params
        ));
     * */
    public function addVariables($params)
    {
        if (is_object($params['chat']->incoming_chat) && $params['chat']->incoming_chat->incoming->scope == 'customrequest') {
            // {{args.chat.dynamic_array.uuidv4}} you can access it in Rest API call
            $params['chat']->dynamic_array = [];
            $params['chat']->dynamic_array['uuidv4'] = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

            if (isset($_SERVER['HTTP_HOST'])) {
                $site_address = (erLhcoreClassSystem::$httpsMode == true ? 'https:' : 'http:') . '//' . $_SERVER['HTTP_HOST'];
            } else {
                if (class_exists('erLhcoreClassInstance')) {
                    $site_address = 'https://' . erLhcoreClassInstance::$instanceChat->address . '.' . erConfigClassLhConfig::getInstance()->getSetting('site', 'seller_domain');
                } else {
                    $site_address = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionLhcphpresque')->settings['site_address'];
                }
            }

            $representative = 'BOT';

            if (isset($params['params']['msg']) && $params['params']['msg']->user_id > 0) {
                $representative = 'HUMAN';
            }

            // Other avatar images just don't work.
            $avatarImage = 'https://developers.google.com/identity/images/g-logo.png';

            $params['chat']->dynamic_array['avatarImage'] = $avatarImage;
            $params['chat']->dynamic_array['representativeType'] = $representative;
        }
    }

    /*
     * During integration process to intercept request and verify it.
     * */
    public function verifyCall($params)
    {
        if (
            isset($params['data']['clientToken']) && ($agent = \LiveHelperChatExtension\googlebusinessmessage\providers\erLhcoreClassModelGoogleBusinessAgent::findOne(['filter' => ['client_token' => $params['data']['clientToken']]])) && is_object($agent) &&
            isset($params['data']['secret'])
        ) {
            $agent->verify_token = $params['data']['secret'];
            $agent->updateThis(['update' => ['verify_token']]);
            echo $params['data']['secret'];
            exit;
        }
    }
}


