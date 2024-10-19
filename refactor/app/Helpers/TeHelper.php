<?php

namespace DTApi\Helpers;

class TeHelper
{
    public function jobToData($job)
    {
        $data = array();            // save job's information to data for sending Push
        $data['job_id'] = $job->id;
        $data['from_language_id'] = $job->from_language_id;
        $data['immediate'] = $job->immediate;
        $data['duration'] = $job->duration;
        $data['status'] = $job->status;
        $data['gender'] = $job->gender;
        $data['certified'] = $job->certified;
        $data['due'] = $job->due;
        $data['job_type'] = $job->job_type;
        $data['customer_phone_type'] = $job->customer_phone_type;
        $data['customer_physical_type'] = $job->customer_physical_type;
        $data['customer_town'] = $job->town;
        $data['customer_type'] = $job->user->userMeta->customer_type;

        $explodeDueDate = explode(" ", $job->due);
        $dueDate = $explodeDueDate[0];
        $dueTime = $explodeDueDate[1];

        $data['due_date'] = $dueDate;
        $data['due_time'] = $dueTime;
        $data['job_for'] = array();

        if($job->gender != null) {
            if($job->gender == 'male') {
                $data['job_for'][] = 'Man';
            } else if($job->gender == 'female') {
                $data['job_for'][] = 'Kvinna';
            }
        }

        if($job->certified != null) {
            if($job->certified == 'both') {
                $data['job_for'][] = 'Godkänd tolk';
                $data['job_for'][] = 'Auktoriserad';
            } else if($job->certified == 'yes') {
                $data['job_for'][] = 'Auktoriserad';
            } else if($job->certified == 'n_health') {
                $data['job_for'][] = 'Sjukvårdstolk';
            } else if($job->certified == 'law' || $job->certified == 'n_law') {
                $data['job_for'][] = 'Rätttstolk';
            } else {
                $data['job_for'][] = $job->certified;
            }
        }

        return $data;
    }
}