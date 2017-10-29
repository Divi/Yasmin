<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Models;

/**
 * Represents a Group DM channel.
 */
class GroupDMChannel extends DMChannel {
    protected $applicationID;
    
    /**
     * @access private
     */
    function __construct(\CharlotteDunois\Yasmin\Client $client, array $channel) {
        parent::__construct($client, $channel);
        
        $this->applicationID = $channel['application_id'] ?? null;
    }
    
    /**
     * @property-read  string|null  $applicationID  Returns the application ID which created the group DM channel.
     */
    function __get($name) {
        if(\property_exists($this, $name)) {
            return $this->$name;
        }
        
        return parent::__get($name);
    }
}