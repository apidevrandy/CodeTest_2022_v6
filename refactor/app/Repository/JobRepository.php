<?php

namespace DTApi\Repository;

use DTApi\Models\Job;

class JobRepository extends BaseRepository
{
    protected $model;
    protected $table = 'jobs';

    function __construct(Job $model)
    {
        parent::__construct($model);
    }
    
    public function getCuserJobs($cuser)
    {
        return $cuser->jobs()
                ->with('user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback')
                ->whereIn('status', ['pending', 'assigned', 'started'])
                ->orderBy('due', 'asc')
                ->get();
    }

    public function getTranslatorJobs($cuserId, $value)
    {
        // query hidden
        
        return;
    }

    public function jobPluck($jobs, $pluck)
    {
        return $jobs->pluck($pluck)
                    ->all();
    }

    public function collectCheckParticularJob($noramlJobs, $userId)
    {
        return collect($noramlJobs)->each(function ($item, $key) use ($userId) {
            $item['usercheck'] = $this->checkParticularJob($userId, $item);
        })->sortBy('due')->all();
    }

    public function getAllJobs()
    {
        return Job::query();
    }

    public function jobWhere($job, $condition, $value, $comparison = '=')
    {
        return $job->where($condition, $comparison, $value);
    }

    public function jobWhereIn($job, $condition, $value)
    {
        return $job->whereIn($condition, $value);
    }

    public function jobWhereDoesntHave($job, $condition)
    {
        return $job->whereDoesntHave($condition);
    }

    public function whereHasFeedback($job, $condition, $comparison, $value)
    {
        return $job->whereHas('feedback', function ($q) {
            $this->jobWhere($q, $condition, $value, $comparison);
        });
    }

    public function whereHasConsumerType($job, $use, $condition, $value)
    {
        return $job->whereHas('user.userMeta', function($q) use ($use) {
            $this->where($q, $condition, $use[$value]);
        });
    }

    public function jobCount($job)
    {
        return $job->count();
    }

    public function jobOrderBy($job, $order, $by)
    {
        return $job->orderBy($order, $by);
    }

    public function jobWith($job, $array)
    {
        return $job->with($array);
    }

    public function jobGet($job)
    {
        return $job->get();
    }

    public function jobPaginate($job, $page)
    {
        return $job->paginate($page);
    }

    public function showFind($with, $id)
    {
        return $this->with($with)
                    ->find($id);
    }

    public function jobsCreate($cuser, $data)
    {
        return $cuser->jobs()
                ->create($data);
    }

    public function jobFind($id)
    {
        return Job::find($id);
    }

    public function jobTranslatorJobRelWhere($job, $condition, $value)
    {
        return $job->translatorJobRel
                    ->where($condition, $value)
                    ->first();
    }

    public function jobTranslatorJobRelWhereNot($job, $condition, $value)
    {
        return $job->translatorJobRel
                    ->where($condition, '!=', $value)
                    ->first();
    }

    public function jobSave($job)
    {
        $job->save();
    }

    public function jobFindOrFail($find)
    {
        return Job::findOrFail($find);
    }

    public function getJobUser($job)
    {
        return $job->user()
                    ->get()
                    ->first();
    }

    public function jobsWithWhereinOrderbyPaginate($cuser, $with, $condition, $value, $order, $by, $paginate)
    {
        return $cuser->jobs()
                    ->with($with)
                    ->whereIn($condition, $value)
                    ->orderBy($order, $by)
                    ->paginate($paginate);
    }

    public function getTranslatorJobsHistoric($id, $value, $pagenum)
    {
        // query hidden
        
        return $ids;
    }

    public function jobTotal($job)
    {
        return $job->total();
    }

    public function isTranslatorAlreadyBooked($jobId, $cuserId, $jobDue)
    {
        // query hidden

        return;
    }

    public function insertTranslatorJobRel($cuserId, $jobId)
    {
        // query hidden

        return;
    }

    public function getJobs($cuserId, $jobType, $status, $userlanguage, $gender, $translatorLevel)
    {
        // query hidden

        return $ids;
    }

    public function assignedToPaticularTranslator($cuserId, $jobId)
    {
        // query hidden

        return;
    }

    public function checkParticularJob($cuserId, $job)
    {
        // query hidden

        return;
    }

    public function checkTowns($jobUserId, $cuserId)
    {
        // query hidden

        return;
    }

    public function getJobsAssignedTranslatorDetail($job)
    {
        // query hidden

        return;
    }

    public function deleteTranslatorJobRel($translatorId, $jobId)
    {
        // query hidden
    }

    public function jobTranslatorJobRelWheres($job, $condition1, $value1, $condition2, $value2)
    {
        return $job->translatorJobRel
                    ->where($condition1, $value1)
                    ->where($condition2, $value2)
                    ->first();
    }

    public function getJobUserFirst($job)
    {
        return $job->user()
                    ->first();
    }

    public function jobUsermetaFirst($job)
    {
        return $job->user
                    ->userMeta()
                    ->first();
    }

    public function jobUpdate($update, $condition, $value)
    {
        return Job::where($condition, $value)
                    ->update($update);
    }

    public function jobCreate($create)
    {
        return Job::create($create);
    }

    public function jobAll()
    {
        return Job::all();
    }

    public function jobJoin($join, $joinCondition1, $joinCondition2)
    {
        return DB::table($this->table)
                ->join($join, $joinCondition1, $joinCondition2);
    }

    public function jobSelect($job, $select1, $select2)
    {
        return $job->select($select1, $select2);
    }
}