<?php

namespace DTApi\Repository;

use DTApi\Models\UserMeta;

class UserMetaRepository extends BaseRepository
{
    protected $model;

    function __construct(UserMeta $model)
    {
        parent::__construct($model);
    }

    public function userMetaWhere($condition, $value)
    {
        return UserMeta::where($condition, $value)
                                ->first();
    }
}