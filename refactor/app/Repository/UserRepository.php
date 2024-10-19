<?php

namespace DTApi\Repository;

use DB;
use DTApi\Models\User;

class UserRepository extends BaseRepository
{
    protected $model;
    protected $table = 'users';

    function __construct(User $model)
    {
        parent::__construct($model);
    }
    
    public function userFind($id)
    {
        return User::find($id);
    }

    public function getUsersWhereIn($condition, $value)
    {
        return DB::table($this->table)
                ->whereIn($condition, $value)
                ->get();
    }

    public function collectPluck($collect, $pluck)
    {
        return collect($collect)
                ->pluck($pluck)
                ->all();
    }

    public function getUsersWhere($condition, $value)
    {
        return DB::table($this->table)
                ->whereIn($condition, $value)
                ->first();
    }

    public function getUserWhere($condition, $value)
    {
        return User::where($condition, $value)
                    ->first();
    }

    public function getUserAll()
    {
        return User::all();
    }

    public function getPotentialUsers($translatorType, $joblanguage, $gender, $translatorLevel, $translatorsId)
    {
        // query hidden

        return;
    }

    public function usersLists($condition, $value, $lists)
    {
        return DB::table($this->table)
                    ->where($condition, $value)
                    ->lists($lists);
    }

    public function getUserWhereFirst($condition, $value)
    {
        return DB::table('users')
                    ->where($condition, $value)
                    ->first();
    }
}