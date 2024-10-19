<?php

namespace DTApi\Repository;

use DTApi\Models\Throttles;

class ThrottlesRepository extends BaseRepository
{
    protected $model;

    function __construct(Throttles $model)
    {
        parent::__construct($model);
    }

    public function throttlesWhereWithPaginate($condition, $value, $with, $paginate)
    {
        return Throttles::where($condition,  $value)
                        ->with($with)
                        ->paginate($paginate);
    }

    public function throttlesFind($find)
    {
        return Throttles::find($find);
    }

    public function throttlesSave($throttle)
    {
        $throttle->save();
    }
}