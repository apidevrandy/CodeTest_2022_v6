<?php

namespace DTApi\Repository;

use DTApi\Models\UserLanguages;

class UserLanguagesRepository extends BaseRepository
{
    protected $model;

    function __construct(UserLanguages $model)
    {
        parent::__construct($model);
    }

    public function userLanguagesWhere($condition, $value)
    {
        return UserLanguages::where($condition, $value)
                                ->get();
    }

    public function userLanguagesCollectPluck($userLanguages, $collect, $pluck)
    {
        return collect($collect)
                ->pluck($pluck)
                ->all();
    }
}