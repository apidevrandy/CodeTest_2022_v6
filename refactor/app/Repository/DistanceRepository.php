<?php

namespace DTApi\Repository;

use DTApi\Models\Distance;

class DistanceRepository extends BaseRepository
{
    protected $model;

    function __construct(Distance $model)
    {
        parent::__construct($model);
    }

    public function distanceUpdate($update, $condition, $value)
    {
        return Distance::where($condition, $value)
                        ->update($update);
    }
}