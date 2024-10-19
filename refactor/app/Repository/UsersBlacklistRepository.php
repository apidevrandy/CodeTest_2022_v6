<?php

namespace DTApi\Repository;

use DTApi\Models\UsersBlacklist;

class UsersBlacklistRepository extends BaseRepository
{
    protected $model;

    function __construct(UsersBlacklist $model)
    {
        parent::__construct($model);
    }

    public function usersBlacklistWhere($condition, $value)
    {
        return UsersBlacklist::where($condition, $value)
                                ->get();
    }

    public function usersBlacklistCollectPluck($collect, $pluck)
    {
        return collect($collect)
                    ->pluck($pluck)
                    ->all();
    }
}