<?php

namespace DTApi\Services;

use Carbon\Carbon;
use DTApi\Helpers\TeHelper;
use DTApi\Repository\JobRepository;
use DTApi\Repository\TranslatorRepository;
use DTApi\Repository\UserRepository;

class UserTypeRoleService
{
    private $allJobs;
    private $requestdata;
    private $data;
    
    public function superAdminRoleUserType($requestinfo)
    {
        $this->requestdata = $requestinfo;
        
        $this->allJobs = JobRepository::getAllJobs();
        
        $count = $this->superAdminCheckFeedback();
        if(isset($count))
            return $count;

        $this->superAdminCheckId();
        $this->superAdminCheckLang();
        $this->superAdminCheckStatus();
        $this->superAdminCheckExpiredAt();
        $this->superAdminCheckWillExpiredAt();
        $this->superAdminCheckCustomerEmail();
        $this->superAdminCheckTranslatorEmail();
        $this->superAdminCheckFilterTimetype();
        $this->superAdminCheckJobType();
        $this->superAdminCheckPhysical();
        $this->superAdminCheckPhone();
        $this->superAdminCheckFlagged();
        $this->superAdminCheckDistance();
        $this->superAdminCheckSalary();

        $count = $this->superAdminCheckCount();
        if(isset($count))
            return $count;

        $this->superAdminCheckConsumerType();
        $this->superAdminCheckBookingType();

        $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'created_at', 'desc');
        $this->allJobs = JobRepository::jobWith($this->allJobs, ['user', 'language', 'feedback.user', 'translatorJobRel.user', 'distance']);

        if($limit == 'all')
            $this->allJobs = JobRepository::jobGet($this->allJobs);
        else
            $this->allJobs = JobRepository::jobPaginate($this->allJobs, 15);

        return $this->allJobs;
    }

    private function superAdminCheckCount()
    {
        if(isset($this->requestdata['count']) && $this->requestdata['count'] == 'true') 
            return ['count' => JobRepository::jobCount($this->allJobs)];
    }
    
    private function superAdminCheckFeedback()
    {
        if(isset($this->requestdata['feedback']) && $this->requestdata['feedback'] != 'false') {
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'ignore_feedback', '0');
            $this->allJobs = JobRepository::whereHasFeedback($this->allJobs, 'rating', '<=', '3');

            return $this->superAdminCheckCount();
        }
    }

    private function superAdminCheckId()
    {
        if(isset($this->requestdata['id']) && $this->requestdata['id'] != '') {

            if(is_array($this->requestdata['id']))
                $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'id', $this->requestdata['id']);
            else
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'id', $this->requestdata['id']);

            $this->requestdata = array_only($this->requestdata, ['id']);
        }
    }

    private function superAdminCheckLang()
    {
        if(isset($this->requestdata['lang']) && $this->requestdata['lang'] != '')
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'from_language_id', $this->requestdata['lang']);
    }

    private function superAdminCheckStatus()
    {
        if(isset($this->requestdata['status']) && $this->requestdata['status'] != '')
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'status', $this->requestdata['status']);
    }

    private function superAdminCheckExpiredAt()
    {
        if(isset($this->requestdata['expired_at']) && $this->requestdata['expired_at'] != '')
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'expired_at', $this->requestdata['expired_at'], '>=');
    }

    private function superAdminCheckWillExpiredAt()
    {
        if(isset($this->requestdata['will_expire_at']) && $this->requestdata['will_expire_at'] != '')
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'will_expire_at', $this->requestdata['will_expire_at'], '>=');
    }

    private function superAdminCheckCustomerEmail()
    {
        if(isset($this->requestdata['customer_email']) && count($this->requestdata['customer_email']) && $this->requestdata['customer_email'] != '') {
            $users = UserRepository::getUsersWhereIn('email', $this->requestdata['customer_email']);

            if($users)
                $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'user_id', UserRepository::collectPluck($users, 'id'));    
        }
    }

    private function superAdminCheckTranslatorEmail()
    {
        if(isset($this->requestdata['translator_email']) && count($this->requestdata['translator_email'])) {
            $users = UserRepository::getUsersWhereIn('email', $this->requestdata['translator_email']);

            if($users) {
                $allJobIDs = TranslatorRepository::getUsersWhereNullWhereIn('cancel_at', 'user_id', UserRepository::collectPluck($users, 'id'), 'job_id');

                $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'id', $allJobIDs);
            }
        }
    }

    private function superAdminCheckFilterTimetype()
    {
        if(isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'created') {
            
            if(isset($this->requestdata['from']) && $this->requestdata['from'] != '')
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'created_at', $this->requestdata['from'], '>=');

            if(isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $this->requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'created_at', $to, '<=');
            }
            
            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'created_at', 'desc');
        }

        if (isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'due') {

            if (isset($this->requestdata['from']) && $this->requestdata['from'] != '')
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'due', $this->requestdata['from'], '>=');

            if (isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $this->requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'due', $to, '<=');
            }

            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'due', 'desc');
        }
    }

    private function superAdminCheckJobType()
    {
        if(isset($this->requestdata['job_type']) && $this->requestdata['job_type'] != '')
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'job_type', $this->requestdata['job_type']);
    }

    private function superAdminCheckPhysical()
    {
        if(isset($this->requestdata['physical'])) {
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'customer_physical_type', $this->requestdata['physical']);
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'ignore_physical', 0);
        }
    }

    private function superAdminCheckPhone()
    {
        if(isset($this->requestdata['phone'])) {
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'customer_phone_type', $this->requestdata['phone']);

            if(isset($this->requestdata['physical']))
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'ignore_physical_phone', 0);
        }
    }

    private function superAdminCheckFlagged()
    {
        if(isset($this->requestdata['flagged'])) {
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'flagged', $this->requestdata['flagged']);
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'ignore_flagged', 0);
        }
    }

    private function superAdminCheckDistance()
    {
        if(isset($this->requestdata['distance']) && $this->requestdata['distance'] == 'empty')
            $this->allJobs = JobRepository::jobWhereDoesntHave('distance');
    }

    private function superAdminCheckSalary()
    {
        if(isset($this->requestdata['salary']) &&  $this->requestdata['salary'] == 'yes')
            $this->allJobs = JobRepository::jobWhereDoesntHave('user.salaries');
    }

    private function superAdminCheckConsumerType()
    {
        if(isset($this->requestdata['consumer_type']) && $this->requestdata['consumer_type'] != '')
            $this->allJobs = JobRepository::whereHasConsumerType($this->allJobs, $this->requestdata, 'consumer_type', 'consumer_type');
    }

    private function superAdminCheckBookingType()
    {
        if(isset($this->requestdata['booking_type'])) {
            if($this->requestdata['booking_type'] == 'physical')
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'customer_physical_type', 'yes');

            if($this->requestdata['booking_type'] == 'phone')
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'customer_phone_type', 'yes');
        }
    }

    public function adminRoleUserType($requestinfo)
    {
        $this->requestdata = $requestinfo;
        
        $this->allJobs = JobRepository::getAllJobs();
        
        $this->adminCheckId();
        $this->adminCheckConsumerType();

        $count = $this->adminCheckFeedback();
        if(isset($count))
            return $count;

        $this->adminCheckLang();
        $this->adminCheckStatus();
        $this->adminCheckJobType();
        $this->adminCheckCustomerEmail();
        $this->adminCheckFilterTimeType();

        $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'created_at', 'desc');
        $this->allJobs = JobRepository::jobWith($this->allJobs, ['user', 'language', 'feedback.user', 'translatorJobRel.user', 'distance']);

        if($limit == 'all')
            $this->allJobs = JobRepository::jobGet($this->allJobs);
        else
            $this->allJobs = JobRepository::jobPaginate($this->allJobs, 15);

        return $this->allJobs;
    }

    private function adminCheckId()
    {
        if(isset($this->requestdata['id']) && $this->requestdata['id'] != '') {
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'id', $this->requestdata['id']);

            $this->requestdata = array_only($this->requestdata, ['id']);
        }
    }

    private function adminCheckConsumerType()
    {
        if($this->requestdata['consumer_type'] == 'RWS') 
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'job_type', 'rws');
        else
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'job_type', 'unpaid');
    }

    private function adminCheckFeedback()
    {
        if(isset($this->requestdata['feedback']) && $this->requestdata['feedback'] != 'false') {
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'ignore_feedback', '0');
            $this->allJobs = JobRepository::whereHasFeedback($this->allJobs, 'rating', '<=', '3');

            return $this->adminCheckCount();
        }
    }

    private function adminCheckCount()
    {
        if(isset($this->requestdata['count']) && $this->requestdata['count'] == 'true') 
            return ['count' => JobRepository::jobCount($this->allJobs)];
    }

    private function adminCheckLang()
    {
        if(isset($this->requestdata['lang']) && $this->requestdata['lang'] != '')
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'from_language_id', $this->requestdata['lang']);
    }

    private function adminCheckStatus()
    {
        if(isset($this->requestdata['status']) && $this->requestdata['status'] != '')
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'status', $this->requestdata['status']);
    }

    private function adminCheckJobType()
    {
        if(isset($this->requestdata['job_type']) && $this->requestdata['job_type'] != '')
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'job_type', $this->requestdata['job_type']);
    }
    
    private function adminCheckCustomerEmail()
    {
        if(isset($this->requestdata['customer_email']) && $this->requestdata['customer_email'] != '') {
            $users = UserRepository::getUsersWhere('email', $this->requestdata['customer_email']);

            if($users)
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'user_id', $user->id);
        }
    }

    private function adminCheckFilterTimeType()
    {
        if(isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'created') {

            if(isset($this->requestdata['from']) && $this->requestdata['from'] != '') 
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'created_at', $this->requestdata['from'], '>=');

            if(isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $this->requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'created_at', $to, '<=');
            }

            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'created_at', 'desc');
        }

        if(isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'due') {

            if(isset($this->requestdata['from']) && $this->requestdata['from'] != '')
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'due', $this->requestdata['from'], '>=');

            if(isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $this->requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'due', $to, '<=');
            }

            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'due', 'desc');
        }
    }

    public function customerRoleUserType($user, $data)
    {
        $cuser = $user;

        if(!isset($data['from_language_id'])) {
            $response['status'] = 'fail';
            $response['message'] = 'Du måste fylla in alla fält';
            $response['field_name'] = 'from_language_id';

            return $response;
        }

        if($data['immediate'] == 'no') {
            if(isset($data['due_date']) && $data['due_date'] == '') {
                $response['status'] = 'fail';
                $response['message'] = 'Du måste fylla in alla fält';
                $response['field_name'] = 'due_date';

                return $response;
            }

            if(isset($data['due_time']) && $data['due_time'] == '') {
                $response['status'] = 'fail';
                $response['message'] = 'Du måste fylla in alla fält';
                $response['field_name'] = 'due_time';

                return $response;
            }

            if(!isset($data['customer_phone_type']) && !isset($data['customer_physical_type'])) {
                $response['status'] = 'fail';
                $response['message'] = 'Du måste göra ett val här';
                $response['field_name'] = 'customer_phone_type';

                return $response;
            }

            if(isset($data['duration']) && $data['duration'] == '') {
                $response['status'] = 'fail';
                $response['message'] = 'Du måste fylla in alla fält';
                $response['field_name'] = 'duration';

                return $response;
            }
        } else {
            if(isset($data['duration']) && $data['duration'] == '') {
                $response['status'] = 'fail';
                $response['message'] = 'Du måste fylla in alla fält';
                $response['field_name'] = 'duration';

                return $response;
            }
        }

        if(isset($data['customer_phone_type'])) {
            $data['customer_phone_type'] = 'yes';
        } else {
            $data['customer_phone_type'] = 'no';
        }

        if(isset($data['customer_physical_type'])) {
            $data['customer_physical_type'] = 'yes';
            $response['customer_physical_type'] = 'yes';
        } else {
            $data['customer_physical_type'] = 'no';
            $response['customer_physical_type'] = 'no';
        }

        if($data['immediate'] == 'yes') {
            $due_carbon = Carbon::now()->addMinute($immediatetime);
            $data['due'] = $due_carbon->format('Y-m-d H:i:s');
            $data['immediate'] = 'yes';
            $data['customer_phone_type'] = 'yes';
            $response['type'] = 'immediate';
        } else {
            $due = $data['due_date'] . ' ' . $data['due_time'];
            $response['type'] = 'regular';
            $due_carbon = Carbon::createFromFormat('m/d/Y H:i', $due);
            $data['due'] = $due_carbon->format('Y-m-d H:i:s');

            if($due_carbon->isPast()) {
                $response['status'] = 'fail';
                $response['message'] = "Can't create booking in past";

                return $response;
            }
        }

        if(in_array('male', $data['job_for'])) {
            $data['gender'] = 'male';
        } elseif(in_array('female', $data['job_for'])) {
            $data['gender'] = 'female';
        }

        if(in_array('normal', $data['job_for'])) {
            $data['certified'] = 'normal';
        } else if(in_array('certified', $data['job_for'])) {
            $data['certified'] = 'yes';
        } else if(in_array('certified_in_law', $data['job_for'])) {
            $data['certified'] = 'law';
        } else if(in_array('certified_in_helth', $data['job_for'])) {
            $data['certified'] = 'health';
        }
        
        if(in_array('normal', $data['job_for']) && in_array('certified', $data['job_for'])) {
            $data['certified'] = 'both';
        } else if(in_array('normal', $data['job_for']) && in_array('certified_in_law', $data['job_for'])) {
            $data['certified'] = 'n_law';
        } else if(in_array('normal', $data['job_for']) && in_array('certified_in_helth', $data['job_for'])) {
            $data['certified'] = 'n_health';
        }

        if($data['consumer_type'] == 'rwsconsumer')
            $data['job_type'] = 'rws';
        else if($data['consumer_type'] == 'ngo')
            $data['job_type'] = 'unpaid';
        else if($data['consumer_type'] == 'paid')
            $data['job_type'] = 'paid';

        $data['b_created_at'] = date('Y-m-d H:i:s');

        if(isset($due))
            $data['will_expire_at'] = TeHelper::willExpireAt($due, $data['b_created_at']);

        $data['by_admin'] = isset($data['by_admin']) ? $data['by_admin'] : 'no';

        $job = JobRepository::jobsCreate($cuser, $data);

        $response['status'] = 'success';
        $response['id'] = $job->id;
        $data['job_for'] = array();

        if($job->gender != null) {
            if($job->gender == 'male') {
                $data['job_for'][] = 'Man';
            } elseif($job->gender == 'female') {
                $data['job_for'][] = 'Kvinna';
            }
        }

        if($job->certified != null) {
            if($job->certified == 'both') {
                $data['job_for'][] = 'normal';
                $data['job_for'][] = 'certified';
            } else if($job->certified == 'yes') {
                $data['job_for'][] = 'certified';
            } else {
                $data['job_for'][] = $job->certified;
            }
        }

        $data['customer_town'] = $cuser->userMeta->city;
        $data['customer_type'] = $cuser->userMeta->customer_type;

        //Event::fire(new JobWasCreated($job, $data, '*'));

//            $this->sendNotificationToSuitableTranslators($job->id, $data, '*');// send Push for New job posting
    }

    public function isSuperAdmin($requestinfo, $jobId)
    {
        $this->requestdata = $requestinfo;
        
        $this->allJobs = JobRepository::jobJoin('languages', 'jobs.from_language_id', 'languages.id');
        $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.id', $jobId);
        
        $this->isSuperAdminCheckLang();
        $this->isSuperAdminCheckStatus();
        $this->isSuperAdminCheckCustomerEmail();
        $this->isSuperAdminCheckTranslatorEmail();
        $this->isSuperAdminCheckFilterTimetype();
        $this->isSuperAdminCheckJobType();
        
        $this->allJobs = JobRepository::jobSelect($this->allJobs, 'jobs.*', 'languages.language');    
        $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
        $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.id', $jobId);
        $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'jobs.created_at', 'desc');
        $this->allJobs = JobRepository::jobPaginate(15);

        return $this->allJobs;
    }

    private function isSuperAdminCheckLang()
    {
        if(isset($this->requestdata['lang']) && $this->requestdata['lang'] != '') {
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.from_language_id', $this->requestdata['lang']);
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            /*$allJobs->where('jobs.from_language_id', '=', $this->requestdata['lang']);*/
        }
    }

    private function isSuperAdminCheckStatus()
    {
        if(isset($this->requestdata['status']) && $this->requestdata['status'] != '') {
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.status', $this->requestdata['status']);
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            /*$allJobs->where('jobs.status', '=', $this->requestdata['status']);*/
        }
    }

    private function isSuperAdminCheckCustomerEmail()
    {
        if(isset($this->requestdata['customer_email']) && $this->requestdata['customer_email'] != '') {
            $user = UserRepository::getUserWhereFirst('email', $this->requestdata['customer_email']);

            if($user) {
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.user_id', $user->id);
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            }
        }
    }

    private function isSuperAdminCheckTranslatorEmail()
    {
        if(isset($this->requestdata['translator_email']) && $this->requestdata['translator_email'] != '') {
            $user = UserRepository::getUserWhereFirst('email', $this->requestdata['translator_email']);

            if($user) {
                $allJobIDs = TranslatorRepository::translatorLists('user_id', $user->id, 'job_id');
                
                $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.id', $allJobIDs);
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            }
        }
    }

    private function isSuperAdminCheckFilterTimetype()
    {
        if(isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'created') {
            if(isset($this->requestdata['from']) && $this->requestdata['from'] != '') {
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.created_at', $this->requestdata['from'], '>=');
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            }

            if(isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $this->requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.created_at', $to, '<=');
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            }

            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'jobs.created_at', 'desc');
        }

        if(isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'due') {
            if(isset($this->requestdata['from']) && $this->requestdata['from'] != '') {
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.due', $this->requestdata['from'], '>=');
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            }

            if(isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $this->requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.due', $to, '<=');
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            }
            
            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'jobs.due', 'desc');
        }
    }

    private function isSuperAdminCheckJobType()
    {
        if(isset($this->requestdata['job_type']) && $this->requestdata['job_type'] != '') {
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.job_type', $this->requestdata['job_type']);
            $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore', 0);
            /*$allJobs->where('jobs.job_type', '=', $this->requestdata['job_type']);*/
        }
    }

    public function isAdminOrSuperAdmin($requestinfo)
    {
        $this->requestdata = $requestinfo;
        
        $this->allJobs = JobRepository::jobJoin('languages', 'jobs.from_language_id', 'languages.id');
        $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.id', $jobId);
        
        $this->isAdminOrSuperAdminCheckLang();
        $this->isAdminOrSuperAdminCheckStatus();
        $this->isAdminOrSuperAdminCheckCustomerEmail();
        $this->isAdminOrSuperAdminCheckTranslatorEmail();
        $this->isAdminOrSuperAdminCheckFilterTimetype();
        $this->isAdminOrSuperAdminCheckJobType();
        
        $this->allJobs = JobRepository::jobSelect($this->allJobs, 'jobs.*', 'languages.language');
        
        $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();

        $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'jobs.created_at', 'desc');
        $this->allJobs = JobRepository::jobPaginate(15);

        return $this->allJobs;
    }

    private function isAdminOrSuperAdminWhereStatusIgnoreExpiredDue()
    {
        $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.status', 'pending');
        $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.ignore_expired', 0);
        $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.due', Carbon::now(), '>=');
    }
    
    private function isAdminOrSuperAdminCheckLang()
    {
        if(isset($this->requestdata['lang']) && $this->requestdata['lang'] != '') {
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.from_language_id', $this->requestdata['lang']);

            $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            /*$allJobs->where('jobs.from_language_id', '=', $this->requestdata['lang']);*/
        }
    }

    private function isAdminOrSuperAdminCheckStatus()
    {
        if(isset($this->requestdata['status']) && $this->requestdata['status'] != '') {
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.status', $this->requestdata['status']);
            
            $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            /*$allJobs->where('jobs.status', '=', $this->requestdata['status']);*/
        }
    }

    private function isAdminOrSuperAdminCheckCustomerEmail()
    {
        if(isset($this->requestdata['customer_email']) && $this->requestdata['customer_email'] != '') {
            $user = UserRepository::getUserWhereFirst('email', $this->requestdata['customer_email']);
            
            if($user) {
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.user_id', $user->id);

                $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            }
        }
    }

    private function isAdminOrSuperAdminCheckTranslatorEmail()
    {
        if(isset($this->requestdata['translator_email']) && $this->requestdata['translator_email'] != '') {
            $user = UserRepository::getUserWhereFirst('email', $this->requestdata['translator_email']);

            if($user) {
                $allJobIDs = TranslatorRepository::translatorLists('user_id', $user->id, 'job_id');

                $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.id', $allJobIDs);
                
                $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            }
        }
    }

    private function isAdminOrSuperAdminCheckFilterTimetype()
    {
        if(isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'created') {
            if(isset($this->requestdata['from']) && $this->requestdata['from'] != '') {
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.created_at', $requestdata['from'], '>=');
                
                $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            }
            
            if(isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.created_at', $to, '<=');
                
                $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            }

            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'jobs.created_at', 'desc');
        }

        if(isset($this->requestdata['filter_timetype']) && $this->requestdata['filter_timetype'] == 'due') {
            if(isset($this->requestdata['from']) && $this->requestdata['from'] != '') {
                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.due', $requestdata['from'], '>=');
                
                $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            }

            if(isset($this->requestdata['to']) && $this->requestdata['to'] != '') {
                $to = $requestdata['to'] . ' 23:59:00';

                $this->allJobs = JobRepository::jobWhere($this->allJobs, 'jobs.due', $to, '<=');
                
                $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            }

            $this->allJobs = JobRepository::jobOrderBy($this->allJobs, 'jobs.due', 'desc');
        }
    }

    
    private function isAdminOrSuperAdminCheckJobType()
    {
        if(isset($this->requestdata['job_type']) && $this->requestdata['job_type'] != '') {
            $this->allJobs = JobRepository::jobWhereIn($this->allJobs, 'jobs.job_type', $this->requestdata['job_type']);
            
            $this->isAdminOrSuperAdminWhereStatusIgnoreExpiredDue();
            /*$allJobs->where('jobs.job_type', '=', $this->requestdata['job_type']);*/
        }
    }
}