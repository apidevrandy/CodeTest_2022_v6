<?php

namespace DTApi\Helpers;

class CommonHelper
{
    public function getUserTagsStringFromArray($users)
    {
        $userTags = "[";
        $first = true;

        foreach ($users as $oneUser) {
            if ($first) {
                $first = false;
            } else {
                $userTags .= ',{"operator": "OR"},';
            }
            $userTags .= '{"key": "email", "relation": "=", "value": "' . strtolower($oneUser->email) . '"}';
        }

        $userTags .= ']';
        
        return $userTags;
    }
}