<?php

namespace DTApi\Repository;

use DB;
use DTApi\Models\Translator;

class TranslatorRepository extends BaseRepository
{
    protected $model;
    protected $table = 'translator_job_rel';

    function __construct(Translator $model)
    {
        parent::__construct($model);
    }

    public function getUsersWhereNullWhereIn($null, $condition, $value, $lists)
    {
        return DB::table($this->table)
                ->whereNull($null)
                ->whereIn($condition, $value)
                ->lists($lists);
    }

    public function translatorCreate($data)
    {
        return Translator::create($data);
    }

    public function translatorUpdate($update, $condition1, $value1, $condition2, $value)
    {
        Translator::where($condition1, $value1)
                    ->where($condition2, $value)
                    ->update($update);
    }

    public function translatorLists($condtion, $value, $lists)
    {
        return DB::table($this->table)
                    ->where($condtion, $value)
                    ->lists($lists);
    }
}