<?php

namespace DTApi\Repository;

use DTApi\Models\Language;

class LanguageRepository extends BaseRepository
{
    protected $model;

    function __construct(Language $model)
    {
        parent::__construct($model);
    }

    public function languageWhereOrderby($condition, $value, $order, $by = 'asc')
    {
        return Language::where($condition, $value)
                    ->orderBy($order, $by)
                    ->get();
    }
}