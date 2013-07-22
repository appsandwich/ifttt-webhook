<?php

/**
 * Runkeeper plugin.
 * 
 * This plugin takes the content of the body and assumes that it is valid json, decodes it and replaces 
 * any of the original variables passed.
 * 
 * This lets you mimic payloads expected by various webhook endpoints.
 */
class Runkeeper extends Plugin {
    
    public function execute($plugin, $object, $raw) {
        
        __log("Raw JSON string passed: '{$object->description}'");
        
        $json = json_decode($object->description);
        if (!$json) {
            __log("Invalid JSON payload '$json'", 'ERROR');
            return false;
        }
        
        // Convert some of values from seconds to minutes (Runkeeper uses minutes, weirdly enough)
        $json->total_sleep = $json->total_sleep / 60.0;
        $json->deep = $json->deep / 60.0;
        $json->light = $json->light / 60.0;
        $json->awake = $json->awake / 60.0;
        
        // FellAsleepAt is formatted: August 23, 2013 at 11:01PM
        // Convert to Runkeeper's preferred format: Sat, 1 Jan 2011 00:00:00
        date_default_timezone_set('UTC');
        $date = $json->timestamp;
        $date_stripped = str_replace(" at ", " ", $date);
        $dateInfo = date_parse_from_format('F d, Y H:iA', $date_stripped);
        $unixTimestamp = mktime($dateInfo['hour'], $dateInfo['minute'], $dateInfo['second'], $dateInfo['month'], $dateInfo['day'], $dateInfo['year']);
        $rk_timestamp = date("D, j M Y H:i:s", $unixTimestamp);
        $json->timestamp = $rk_timestamp;
        
        $json_string = json_encode($json);
        
        __log("Raw JSON string to submit: '{$json_string}'");
        
        $ch = curl_init('https://api.runkeeper.com/sleep');
        
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Verify that we're actually connecting to the Runkeeper API
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, getcwd() . "/certs/api-runkeeper-com.crt");
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Accept: application/vnd.com.runkeeper.NewSleepSet+json',                                                                                
            'Content-Type: application/vnd.com.runkeeper.NewSleepSet+json',                                                                                
            'Content-Length: ' . strlen($json_string),
            'Authorization: Bearer YOUR_ACCESS_TOKEN')
        );
        
        $result = curl_exec($ch);
        
        return $json;
        
    }
}
