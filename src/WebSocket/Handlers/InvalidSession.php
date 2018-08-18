<?php
/**
 * Yasmin
 * Copyright 2017-2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\WebSocket\Handlers;

/**
 * WS Event handler
 * @internal
 */
class InvalidSession implements \CharlotteDunois\Yasmin\Interfaces\WSHandlerInterface {
    protected $wshandler;
    
    function __construct(\CharlotteDunois\Yasmin\WebSocket\WSHandler $wshandler) {
        $this->wshandler = $wshandler;
    }
    
    function handle($data): void {
        if(!$data['d']) {
            $this->wshandler->wsmanager->setSessionID(null);
        }
        
        $this->wshandler->client->getLoop()->addTimer(\mt_rand(1, 5), function () {
            $this->wshandler->wsmanager->sendIdentify();
        });
    }
}
