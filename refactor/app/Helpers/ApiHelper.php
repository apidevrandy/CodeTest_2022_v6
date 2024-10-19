<?php

namespace DTApi\Helpers;

class ApiHelper
{
    public function send($request, $url)
    {
        if (env('APP_ENV') == 'prod') {
            $onesignalRestAuthKey = sprintf("Authorization: Basic %s", config('app.prodOnesignalApiKey'));
        } else {
            $onesignalRestAuthKey = sprintf("Authorization: Basic %s", config('app.devOnesignalApiKey'));
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $onesignalRestAuthKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}