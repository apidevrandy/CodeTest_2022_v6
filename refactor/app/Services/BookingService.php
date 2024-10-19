<?php

namespace DTApi\Services;

use Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use DTApi\Events\JobWasCanceled;
use DTApi\Events\JobWasCreated;
use DTApi\Events\SessionEnded;
use DTApi\Helpers\LogHelper;
use DTApi\Helpers\TeHelper;
use DTApi\Mailers\AppMailer;
use DTApi\Mailers\MailerInterface;
use DTApi\Repository\JobRepository;
use DTApi\Repository\LanguageRepository;
use DTApi\Repository\ThrottlesRepository;
use DTApi\Repository\TranslatorRepository;
use DTApi\Repository\UserLanguagesRepository;
use DTApi\Repository\UserRepository;
use DTApi\Services\NotificationService;
use DTApi\Services\UserTypeRoleService;

class BookingService
{
    function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }
    
    public function index(Request $request)
    {
        if($request->get('user_id')) {
            $response = $this->getUsersJobs($request->get('user_id'));
        } elseif($request->__authenticatedUser->user_type == env('ADMIN_ROLE_ID') || $request->__authenticatedUser->user_type == env('SUPERADMIN_ROLE_ID')) {
            $response = $this->getAll($request);
        }

        return $response;
    }

    /**
     * @param $user_id
     * @return array
     */
    private function getUsersJobs($userId)
    {
        $cuser = UserRepository::userFind($userId);

        $usertype = '';
        $emergencyJobs = array();
        $noramlJobs = array();

        if($cuser && $cuser->is('customer')) {
            $jobs = JobRepository::getCuserJobs($cuser);

            $usertype = 'customer';
        } elseif($cuser && $cuser->is('translator')) {
            $jobs = JobRepository::getTranslatorJobs($cuser->id, 'new');
            $jobs = JobRepository::jobPluck($jobs, 'jobs');
            
            $usertype = 'translator';
        }

        if($jobs) {
            foreach($jobs as $jobitem) {
                if($jobitem->immediate == 'yes') {
                    $emergencyJobs[] = $jobitem;
                } else {
                    $noramlJobs[] = $jobitem;
                }
            }

            $noramlJobs = JobRepository::collectCheckParticularJob($noramlJobs, $userId);
        }

        return ['emergencyJobs' => $emergencyJobs, 'noramlJobs' => $noramlJobs, 'cuser' => $cuser, 'usertype' => $usertype];
    }

    private function getAll(Request $request, $limit = null)
    {
        $requestdata = $request->all();
        $cuser = $request->__authenticatedUser;
        $consumer_type = $cuser->consumer_type;

        if($cuser) {         
            if($cuser->user_type == env('SUPERADMIN_ROLE_ID')) {
                $allJobs = UserTypeRoleService::superAdminRoleUserType($requestdata);
            } else {
                $allJobs = UserTypeRoleService::adminRoleUserType($requestdata);
            }
        }

        return $allJobs;
    }

    public function show($id)
    {
        $job = JobRepository::showFind('translatorJobRel.user', $id);

        return $job;
    }

    public function store($user, $data)
    {
        $immediatetime = 5;
        $consumer_type = $user->userMeta->consumer_type;

        if($user->user_type == env('CUSTOMER_ROLE_ID')) {
            $response = UserTypeRoleService::customerRoleUserType($user, $data);
        } else {
            $response['status'] = 'fail';
            $response['message'] = "Translator can not create booking";
        }

        return $response;
    }

    public function updateJob($id, $data, $cuser)
    {
        $job = JobRepository::jobFind($id);

        $currentTranslator = JobRepository::jobTranslatorJobRelWhere($job, 'cancel_at', Null);

        if(is_null($currentTranslator))
            $currentTranslator = JobRepository::jobTranslatorJobRelWhereNot($job, 'completed_at', Null);

        $logData = [];

        $langChanged = false;

        $changeTranslator = $this->changeTranslator($currentTranslator, $data, $job);

        if($changeTranslator['translatorChanged']) 
            $logData[] = $changeTranslator['log_data'];

        $changeDue = $this->changeDue($job->due, $data['due']);

        if($changeDue['dateChanged']) {
            $oldTime = $job->due;
            $job->due = $data['due'];
            $logData[] = $changeDue['log_data'];
        }

        if($job->from_language_id != $data['from_language_id']) {
            $logData[] = [
                'old_lang' => TeHelper::fetchLanguageFromJobId($job->from_language_id),
                'new_lang' => TeHelper::fetchLanguageFromJobId($data['from_language_id'])
            ];

            $oldLang = $job->from_language_id;
            $job->from_language_id = $data['from_language_id'];
            $langChanged = true;
        }

        $changeStatus = $this->changeStatus($job, $data, $changeTranslator['translatorChanged']);

        if($changeStatus['statusChanged'])
            $logData[] = $changeStatus['log_data'];

        $job->admin_comments = $data['admin_comments'];

        LogHelper::customAdminLogger('admin');
        LogHelper::customAddLogInfo('USER #' . $cuser->id . '(' . $cuser->name . ')' . ' has been updated booking <a class="openjob" href="/admin/jobs/' . $id . '">#' . $id . '</a> with data:  ', $logData);

        $job->reference = $data['reference'];

        if($job->due <= Carbon::now()) {
            JobRepository::jobSave($job);

            return ['Updated'];
        } else {
            JobRepository::jobSave($job);
            
            if($changeDue['dateChanged'])
                NotificationService::sendChangedDateNotification($job, $oldTime);

            if($changeTranslator['translatorChanged'])
                NotificationService::sendChangedTranslatorNotification($job, $currentTranslator, $changeTranslator['new_translator']);

            if($langChanged)
                NotificationService::sendChangedLangNotification($job, $oldLang);
        }
    }

    private function changeTranslator($currentTranslator, $data, $job)
    {
        $translatorChanged = false;

        if(!is_null($currentTranslator) || (isset($data['translator']) && $data['translator'] != 0) || $data['translator_email'] != '') {
            $logData = [];
            
            if(!is_null($currentTranslator) && ((isset($data['translator']) && $currentTranslator->user_id != $data['translator']) || $data['translator_email'] != '') && (isset($data['translator']) && $data['translator'] != 0)) {
                if($data['translator_email'] != '') 
                    $data['translator'] = UserRepository::getUserWhere('email', $data['translator_email'])->id;

                $newTranslator = $currentTranslator->toArray();
                $newTranslator['user_id'] = $data['translator'];
                unset($newTranslator['id']);
                $newTranslator = TranslatorRepository::translatorCreate($newTranslator);

                $currentTranslator->cancel_at = Carbon::now();

                JobRepository::jobSave($currentTranslator);

                $logData[] = [
                    'old_translator' => $currentTranslator->user->email,
                    'new_translator' => $newTranslator->user->email
                ];

                $translatorChanged = true;

            } elseif(is_null($currentTranslator) && isset($data['translator']) && ($data['translator'] != 0 || $data['translator_email'] != '')) {
                if($data['translator_email'] != '') 
                    $data['translator'] = UserRepository::getUserWhere('email', $data['translator_email'])->id;

                $newTranslator = TranslatorRepository::translatorCreate(['user_id' => $data['translator'], 'job_id' => $job->id]);

                $logData[] = [
                    'old_translator' => null,
                    'new_translator' => $newTranslator->user->email
                ];

                $translatorChanged = true;
            }

            if($translatorChanged)
                return ['translatorChanged' => $translatorChanged, 'new_translator' => $newTranslator, 'log_data' => $logData];
        }

        return ['translatorChanged' => $translatorChanged];
    }

    public function storeJobEmail($data)
    {
        $userType = $data['user_type'];

        $job = JobRepository::jobFindOrFail(@$data['user_email_job_id']);
        $job->user_email = @$data['user_email'];
        $job->reference = isset($data['reference']) ? $data['reference'] : '';

        $user = JobRepository::getJobUser($job);

        if(isset($data['address'])) {
            $job->address = ($data['address'] != '') ? $data['address'] : $user->userMeta->address;
            $job->instructions = ($data['instructions'] != '') ? $data['instructions'] : $user->userMeta->instructions;
            $job->town = ($data['town'] != '') ? $data['town'] : $user->userMeta->city;
        }

        JobRepository::jobSave($job);

        if(!empty($job->user_email)) {
            $email = $job->user_email;
            $name = $user->name;
        } else {
            $email = $user->email;
            $name = $user->name;
        }

        $subject = 'Vi har mottagit er tolkbokning. Bokningsnr: #' . $job->id;

        $sendData = [
            'user' => $user,
            'job'  => $job
        ];

        $this->mailer->send($email, $name, $subject, 'emails.job-created', $sendData);

        $response['type'] = $userType;
        $response['job'] = $job;
        $response['status'] = 'success';

        $data = TeHelper::jobToData($job);

        Event::fire(new JobWasCreated($job, $data, '*'));

        return $response;
    }

    public function getUsersJobsHistory($user_id, Request $request)
    {
        if(!$user_id)
            return null;

        $page = $request->get('page');

        if(isset($page)) {
            $pagenum = $page;
        } else {
            $pagenum = "1";
        }

        $cuser = UserRepository::userFind($user_id);

        $usertype = '';
        $emergencyJobs = array();
        $noramlJobs = array();

        if($cuser && $cuser->is('customer')) {
            $jobs = JobRepository::jobsWithOrderbyPaginate(
                $cuser,
                ['user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback', 'distance'],
                'status',
                ['completed', 'withdrawbefore24', 'withdrawafter24', 'timedout'],
                'due',
                'desc',
                15
            );
            
            $usertype = 'customer';

            return ['emergencyJobs' => $emergencyJobs, 'noramlJobs' => [], 'jobs' => $jobs, 'cuser' => $cuser, 'usertype' => $usertype, 'numpages' => 0, 'pagenum' => 0];

        } elseif($cuser && $cuser->is('translator')) {
            $jobs_ids = JobRepository::getTranslatorJobsHistoric($cuser->id, 'historic', $pagenum);
            
            $totaljobs = JobRepository::jobTotal($jobs_ids);

            $numpages = ceil($totaljobs / 15);
            $usertype = 'translator';
            $jobs = $jobs_ids;
            $noramlJobs = $jobs_ids;
//            $jobs['data'] = $noramlJobs;
//            $jobs['total'] = $totaljobs;
            $response = ['emergencyJobs' => $emergencyJobs, 'noramlJobs' => $noramlJobs, 'jobs' => $jobs, 'cuser' => $cuser, 'usertype' => $usertype, 'numpages' => $numpages, 'pagenum' => $pagenum];

            return $response;
        }
    }

    public function acceptJob($data, $user)
    {
        $adminemail = config('app.admin_email');
        $adminSenderEmail = config('app.admin_sender_email');

        $cuser = $user;
        $jobId = $data['job_id'];

        $job = JobRepository::jobFindOrFail($jobId);

        if(!JobRepository::isTranslatorAlreadyBooked($jobId, $cuser->id, $job->due)) {
            if($job->status == 'pending' && JobRepository::insertTranslatorJobRel($cuser->id, $jobId)) {
                $job->status = 'assigned';

                JobRepository::jobSave($job);

                $user = JobRepository::getJobUser($job);

                $mailer = new AppMailer();

                if(!empty($job->user_email))
                    $email = $job->user_email;
                else
                    $email = $user->email;

                $name = $user->name;
                $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';

                $data = [
                    'user' => $user,
                    'job'  => $job
                ];

                $mailer->send($email, $name, $subject, 'emails.job-accepted', $data);
            }
            /*@todo
                add flash message here.
            */
            $jobs = $this->getPotentialJobs($cuser);

            $response = array();
            $response['list'] = json_encode(['jobs' => $jobs, 'job' => $job], true);
            $response['status'] = 'success';
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'Du har redan en bokning den tiden! Bokningen är inte accepterad.';
        }

        return $response;
    }

    public function acceptJobWithId($jobId, $cuser)
    {
        $adminemail = config('app.admin_email');
        $adminSenderEmail = config('app.admin_sender_email');

        $job = JobRepository::jobFindOrFail($jobId);

        $response = array();

        if(!JobRepository::isTranslatorAlreadyBooked($jobId, $cuser->id, $job->due)) {
            if($job->status == 'pending' && JobRepository::insertTranslatorJobRel($cuser->id, $jobId)) {
                $job->status = 'assigned';

                JobRepository::jobSave($job);

                $user = JobRepository::getJobUser($job);

                $mailer = new AppMailer();

                if(!empty($job->user_email))
                    $email = $job->user_email;
                else
                    $email = $user->email;

                $name = $user->name;
                $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';

                $data = [
                    'user' => $user,
                    'job'  => $job
                ];

                $mailer->send($email, $name, $subject, 'emails.job-accepted', $data);

                $data = array();
                $data['notification_type'] = 'job_accepted';
                $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);

                $messageText = array(
                    "en" => 'Din bokning för ' . $language . ' translators, ' . $job->duration . 'min, ' . $job->due . ' har accepterats av en tolk. Vänligen öppna appen för att se detaljer om tolken.'
                );

                if(NotificationService::isNeedToSendPush($user->id)) {
                    $users_array = array($user);

                    NotificationService::sendPushNotificationToSpecificUsers($users_array, $jobId, $data, $messageText, NotificationService::isNeedToDelayPush($user->id));
                }
                // Your Booking is accepted sucessfully
                $response['status'] = 'success';
                $response['list']['job'] = $job;
                $response['message'] = 'Du har nu accepterat och fått bokningen för ' . $language . 'tolk ' . $job->duration . 'min ' . $job->due;
            } else {
                // Booking already accepted by someone else
                $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
                $response['status'] = 'fail';
                $response['message'] = 'Denna ' . $language . 'tolkning ' . $job->duration . 'min ' . $job->due . ' har redan accepterats av annan tolk. Du har inte fått denna tolkning';
            }
        } else {
            // You already have a booking the time
            $response['status'] = 'fail';
            $response['message'] = 'Du har redan en bokning den tiden ' . $job->due . '. Du har inte fått denna tolkning';
        }
        
        return $response;
    }

    public function cancelJobAjax($data, $user)
    {
        $response = array();
        /*@todo
            add 24hrs loging here.
            If the cancelation is before 24 hours before the booking tie - supplier will be informed. Flow ended
            if the cancelation is within 24
            if cancelation is within 24 hours - translator will be informed AND the customer will get an addition to his number of bookings - so we will charge of it if the cancelation is within 24 hours
            so we must treat it as if it was an executed session
        */
        $cuser = $user;
        $jobId = $data['job_id'];

        $job = JobRepository::jobFindOrFail($jobId);

        $translator = JobRepository::getJobsAssignedTranslatorDetail($job);

        if($cuser->is('customer')) {
            $job->withdraw_at = Carbon::now();

            if($job->withdraw_at->diffInHours($job->due) >= 24)
                $job->status = 'withdrawbefore24';
            else
                $job->status = 'withdrawafter24';

            $response['jobstatus'] = 'success';

            JobRepository::jobSave($job);

            Event::fire(new JobWasCanceled($job));

            $response['status'] = 'success';
            $response['jobstatus'] = 'success';

            if($translator) {
                $data = array();
                $data['notification_type'] = 'job_cancelled';

                $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);

                $messageText = array(
                    "en" => 'Kunden har avbokat bokningen för ' . $language . 'tolk, ' . $job->duration . 'min, ' . $job->due . '. Var god och kolla dina tidigare bokningar för detaljer.'
                );

                if(NotificationService::isNeedToSendPush($translator->id)) {
                    $users_array = array($translator);

                    NotificationService::sendPushNotificationToSpecificUsers($users_array, $jobId, $data, $messageText, NotificationService::isNeedToDelayPush($translator->id));// send Session Cancel Push to Translaotor
                }
            }
        } else {
            if($job->due->diffInHours(Carbon::now()) > 24) {
                $customer = JobRepository::getJobUser($job);
                
                if($customer) {
                    $data = array();
                    $data['notification_type'] = 'job_cancelled';

                    $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);

                    $messageText = array(
                        "en" => 'Er ' . $language . 'tolk, ' . $job->duration . 'min ' . $job->due . ', har avbokat tolkningen. Vi letar nu efter en ny tolk som kan ersätta denne. Tack.'
                    );

                    if(NotificationService::isNeedToSendPush($customer->id)) {
                        $users_array = array($customer);

                        NotificationService::sendPushNotificationToSpecificUsers($users_array, $jobId, $data, $messageText, NotificationService::isNeedToDelayPush($customer->id));     // send Session Cancel Push to customer
                    }
                }

                $job->status = 'pending';
                $job->created_at = date('Y-m-d H:i:s');
                $job->will_expire_at = TeHelper::willExpireAt($job->due, date('Y-m-d H:i:s'));

                JobRepository::jobSave($job);
//                Event::fire(new JobWasCanceled($job));
                JobRepository::deleteTranslatorJobRel($translator->id, $jobId);

                $data = TeHelper::jobToData($job);

                NotificationService::sendNotificationTranslator($job, $data, $translator->id);   // send Push all sutiable translators

                $response['status'] = 'success';
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'Du kan inte avboka en bokning som sker inom 24 timmar genom DigitalTolk. Vänligen ring på +46 73 75 86 865 och gör din avbokning over telefon. Tack!';
            }
        }
        
        return $response;
    }

    /*Function to get the potential jobs for paid,rws,unpaid translators*/
    public function getPotentialJobs($cuser)
    {
        $cuserMeta = $cuser->userMeta;
        $jobType = 'unpaid';
        $translatorType = $cuserMeta->translator_type;

        if($translatorType == 'professional')
            $jobType = 'paid';   /*show all jobs for professionals.*/
        elseif($translatorType == 'rwstranslator')
            $jobType = 'rws';  /* for rwstranslator only show rws jobs. */
        elseif($translatorType == 'volunteer')
            $jobType = 'unpaid';  /* for volunteers only show unpaid jobs. */

        $languages = UserLanguagesRepository::userLanguagesWhere('user_id', $cuser->id);

        $userlanguage = UserLanguagesRepository::userLanguagesCollectPluck($languages, 'lang_id');

        $gender = $cuserMeta->gender;
        $translatorLevel = $cuserMeta->translator_level;
        /*Call the town function for checking if the job physical, then translators in one town can get job*/
        $jobIds = JobRepository::getJobs($cuser->id, $jobType, 'pending', $userlanguage, $gender, $translatorLevel);

        foreach($jobIds as $k => $job) {
            $jobuserid = $job->user_id;

            $job->specific_job = JobRepository::assignedToPaticularTranslator($cuser->id, $job->id);
            $job->check_particular_job = JobRepository::checkParticularJob($cuser->id, $job);

            $checktown = JobRepository::checkTowns($jobuserid, $cuser->id);

            if($job->specific_job == 'SpecificJob') {
                if($job->check_particular_job == 'userCanNotAcceptJob')
                    unset($jobIds[$k]);
            }

            if(($job->customer_phone_type == 'no' || $job->customer_phone_type == '') && $job->customer_physical_type == 'yes' && $checktown == false)
                unset($jobIds[$k]);
        }
//        $jobs = TeHelper::convertJobIdsInObjs($jobIds);
        return $jobIds;
    }

    public function endJob($postData)
    {
        $completeddate = date('Y-m-d H:i:s');
        $jobid = $postData['job_id'];

        $jobDetail = JobRepository::showFind('translatorJobRel', $jobid);

        if($jobDetail->status != 'started')
            return ['status' => 'success'];

        $duedate = $jobDetail->due;
        $start = date_create($duedate);
        $end = date_create($completeddate);
        $diff = date_diff($end, $start);
        $interval = $diff->h . ':' . $diff->i . ':' . $diff->s;

        $job = $jobDetail;
        $job->end_at = date('Y-m-d H:i:s');
        $job->status = 'completed';
        $job->session_time = $interval;

        $user = JobRepository::getJobUser($job);

        if(!empty($job->user_email)) {
            $email = $job->user_email;
        } else {
            $email = $user->email;
        }

        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
        $sessionExplode = explode(':', $job->session_time);
        $sessionTime = $sessionExplode[0] . ' tim ' . $sessionExplode[1] . ' min';

        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $sessionTime,
            'for_text'     => 'faktura'
        ];

        $mailer = new AppMailer();
        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        JobRepository::jobSave($job);

        $tr = JobRepository::jobTranslatorJobRelWheres($job, 'completed_at', Null, 'cancel_at', Null);

        Event::fire(new SessionEnded($job, ($postData['user_id'] == $job->user_id) ? $tr->user_id : $job->user_id));

        $user = JobRepository::getJobUserFirst($tr);
        $email = $user->email;
        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;

        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $sessionTime,
            'for_text'     => 'lön'
        ];

        $mailer = new AppMailer();
        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        $tr->completed_at = $completeddate;
        $tr->completed_by = $postData['user_id'];

        JobRepository::jobSave($tr);

        $response['status'] = 'success';

        return $response;
    }

    public function customerNotCall($postData)
    {
        $completeddate = date('Y-m-d H:i:s');
        $jobid = $postData["job_id"];

        $jobDetail = JobRepository::showFind('translatorJobRel', $jobid);

        $duedate = $jobDetail->due;
        $start = date_create($duedate);
        $end = date_create($completeddate);
        $diff = date_diff($end, $start);
        $interval = $diff->h . ':' . $diff->i . ':' . $diff->s;

        $job = $jobDetail;
        $job->end_at = date('Y-m-d H:i:s');
        $job->status = 'not_carried_out_customer';

        $tr = JobRepository::jobTranslatorJobRelWheres($job, 'completed_at', Null, 'cancel_at', Null);
        $tr->completed_at = $completeddate;
        $tr->completed_by = $tr->user_id;

        JobRepository::jobSave($job);
        JobRepository::jobSave($tr);

        $response['status'] = 'success';

        return $response;
    }

    public function distanceFeed($data)
    {
        if(isset($data['distance']) && $data['distance'] != '')
            $distance = $data['distance'];
        else
            $distance = '';

        if(isset($data['time']) && $data['time'] != '')
            $time = $data['time'];
        else
            $time = '';

        if(isset($data['jobid']) && $data['jobid'] != '')
            $jobid = $data['jobid'];

        if(isset($data['session_time']) && $data['session_time'] != '')
            $session = $data['session_time'];
        else
            $session = '';

        if($data['flagged'] == 'true') {
            if($data['admincomment'] == '') return "Please, add comment";
                $flagged = 'yes';
        } else {
            $flagged = 'no';
        }
        
        if($data['manually_handled'] == 'true')
            $manually_handled = 'yes';
        else
            $manually_handled = 'no';

        if($data['by_admin'] == 'true')
            $by_admin = 'yes';
        else
            $by_admin = 'no';

        if(isset($data['admincomment']) && $data['admincomment'] != '')
            $admincomment = $data['admincomment'];
        else
            $admincomment = '';

        if($time || $distance)
            $affectedRows = DistanceRepository::distanceUpdate(array('distance' => $distance, 'time' => $time), 'job_id', $jobid);

        if($admincomment || $session || $flagged || $manually_handled || $by_admin)
            $affectedRows1 = JobRepository::jobUpdate(array('admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin), 'id', $jobid);

        return 'Record updated!';
    }

    public function reopen($request)
    {
        $jobid = $request['jobid'];
        $userid = $request['userid'];

        $job = JobRepository::jobFind($jobid);
        $job = $job->toArray();

        $data = array();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['will_expire_at'] = TeHelper::willExpireAt($job['due'], $data['created_at']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['user_id'] = $userid;
        $data['job_id'] = $jobid;
        $data['cancel_at'] = Carbon::now();

        $datareopen = array();
        $datareopen['status'] = 'pending';
        $datareopen['created_at'] = Carbon::now();
        $datareopen['will_expire_at'] = TeHelper::willExpireAt($job['due'], $datareopen['created_at']);
        //$datareopen['updated_at'] = date('Y-m-d H:i:s');

//        $this->logger->addInfo('USER #' . Auth::user()->id . ' reopen booking #: ' . $jobid);

        if($job['status'] != 'timedout') {
            $affectedRows = JobRepository::jobUpdate($datareopen, 'id', $jobid);

            $new_jobid = $jobid;
        } else {
            $job['status'] = 'pending';
            $job['created_at'] = Carbon::now();
            $job['updated_at'] = Carbon::now();
            $job['will_expire_at'] = TeHelper::willExpireAt($job['due'], date('Y-m-d H:i:s'));
            $job['updated_at'] = date('Y-m-d H:i:s');
            $job['cust_16_hour_email'] = 0;
            $job['cust_48_hour_email'] = 0;
            $job['admin_comments'] = 'This booking is a reopening of booking #' . $jobid;
            //$job[0]['user_email'] = $user_email;
            $affectedRows = JobRepository::jobCreate($job);

            $new_jobid = $affectedRows['id'];
        }
        //$result = DB::table('translator_job_rel')->insertGetId($data);

        TranslatorRepository::translatorUpdate(['cancel_at' => $data['cancel_at']], 'job_id', $jobid, 'cancel_at', NULL);

        $translator = TranslatorRepository::translatorCreate($data);

        if(isset($affectedRows)) {
            NotificationService::sendNotificationByAdminCancelJob($new_jobid);

            return ["Tolk cancelled!"];
        } else {
            return ["Please try again!"];
        }
    }

    public function resendNotifications($data)
    {
        $job = JobRepository::jobFind($data['jobid']);

        $jobData = TeHelper::jobToData($job);

        NotificationService::sendNotificationTranslator($job, $jobData, '*');

        return ['success' => 'Push sent'];
    }

    public function resendSMSNotifications($data)
    {
        $job = JobRepository::jobFind($data['jobid']);

        $jobData = TeHelper::jobToData($job);

        try {
            NotificationService::sendSMSNotificationToTranslator($job);

            return ['success' => 'SMS sent'];
        } catch (\Exception $e) {
            return ['success' => $e->getMessage()];
        }
    }

    public function jobEnd($postData = array())
    {
        $completeddate = date('Y-m-d H:i:s');
        $jobid = $postData["job_id"];

        $jobDetail = JobRepository::showFind('translatorJobRel', $jobid);

        $duedate = $jobDetail->due;
        $start = date_create($duedate);
        $end = date_create($completeddate);
        $diff = date_diff($end, $start);
        $interval = $diff->h . ':' . $diff->i . ':' . $diff->s;

        $job = $jobDetail;
        $job->end_at = date('Y-m-d H:i:s');
        $job->status = 'completed';
        $job->session_time = $interval;

        $user = JobRepository::getJobUser($job);

        if(!empty($job->user_email))
            $email = $job->user_email;
        else
            $email = $user->email;

        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;

        $session_explode = explode(':', $job->session_time);
        $session_time = $session_explode[0] . ' tim ' . $session_explode[1] . ' min';

        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $session_time,
            'for_text'     => 'faktura'
        ];
        
        $mailer = new AppMailer();
        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        JobRepository::jobSave($job);

        $tr = JobRepository::jobTranslatorJobRelWheres($job, 'completed_at', Null, 'cancel_at', Null);

        Event::fire(new SessionEnded($job, ($postData['userid'] == $job->user_id) ? $tr->user_id : $job->user_id));

        $user = JobRepository::getJobUser($tr);

        $email = $user->email;
        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;

        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $session_time,
            'for_text'     => 'lön'
        ];

        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        $tr->completed_at = $completeddate;
        $tr->completed_by = $postData['userid'];

        JobRepository::jobSave($tr);
    }

    private function changeStatus($job, $data, $changedTranslator)
    {
        $oldStatus = $job->status;
        $statusChanged = false;

        if($oldStatus != $data['status']) {
            switch ($job->status) {
                case 'timedout':
                    $statusChanged = $this->changeTimedoutStatus($job, $data, $changedTranslator);
                    break;

                case 'completed':
                    $statusChanged = $this->changeCompletedStatus($job, $data);
                    break;

                case 'started':
                    $statusChanged = $this->changeStartedStatus($job, $data);
                    break;

                case 'pending':
                    $statusChanged = $this->changePendingStatus($job, $data, $changedTranslator);
                    break;

                case 'withdrawafter24':
                    $statusChanged = $this->changeWithdrawafter24Status($job, $data);
                    break;

                case 'assigned':
                    $statusChanged = $this->changeAssignedStatus($job, $data);
                    break;

                default:
                    $statusChanged = false;
                    break;
            }

            if($statusChanged) {
                $log_data = [
                    'old_status' => $oldStatus,
                    'new_status' => $data['status']
                ];

                $statusChanged = true;

                return ['statusChanged' => $statusChanged, 'log_data' => $log_data];
            }
        }
    }

    private function changeTimedoutStatus($job, $data, $changedTranslator)
    {
//        if(in_array($data['status'], ['pending', 'assigned']) && date('Y-m-d H:i:s') <= $job->due) {
        $oldStatus = $job->status;
        $job->status = $data['status'];

        $user = JobRepository::getJobUser($job);

        if(!empty($job->user_email))
            $email = $job->user_email;
        else
            $email = $user->email;

        $name = $user->name;

        $dataEmail = [
            'user' => $user,
            'job'  => $job
        ];

        if($data['status'] == 'pending') {
            $job->created_at = date('Y-m-d H:i:s');
            $job->emailsent = 0;
            $job->emailsenttovirpal = 0;

            JobRepository::jobSave($job);

            $jobData = TeHelper::jobToData($job);

            $subject = 'Vi har nu återöppnat er bokning av ' . TeHelper::fetchLanguageFromJobId($job->from_language_id) . 'tolk för bokning #' . $job->id;

            $this->mailer->send($email, $name, $subject, 'emails.job-change-status-to-customer', $dataEmail);

            NotificationService::sendNotificationTranslator($job, $jobData, '*');   // send Push all sutiable translators

            return true;
        } elseif($changedTranslator) {
            JobRepository::jobSave($job);

            $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';

            $this->mailer->send($email, $name, $subject, 'emails.job-accepted', $dataEmail);

            return true;
        }

//        }
        return false;
    }

    private function changeCompletedStatus($job, $data)
    {
//        if(in_array($data['status'], ['withdrawnbefore24', 'withdrawafter24', 'timedout'])) {
        $job->status = $data['status'];

        if($data['status'] == 'timedout') {
            if($data['admin_comments'] == '')
                return false;

            $job->admin_comments = $data['admin_comments'];
        }

        JobRepository::jobSave($job);

        return true;
//        }
        return false;
    }

    private function changeStartedStatus($job, $data)
    {
//        if(in_array($data['status'], ['withdrawnbefore24', 'withdrawafter24', 'timedout', 'completed'])) {
        $job->status = $data['status'];

        if($data['admin_comments'] == '')
            return false;
        $job->admin_comments = $data['admin_comments'];

        if($data['status'] == 'completed') {
            $user = JobRepository::getJobUser($job);

            if($data['sesion_time'] == '')
                return false;

            $interval = $data['sesion_time'];
            $diff = explode(':', $interval);
            $job->end_at = date('Y-m-d H:i:s');
            $job->session_time = $interval;
            $session_time = $diff[0] . ' tim ' . $diff[1] . ' min';

            if(!empty($job->user_email))
                $email = $job->user_email;
            else
                $email = $user->email;

            $name = $user->name;
            
            $dataEmail = [
                'user'         => $user,
                'job'          => $job,
                'session_time' => $session_time,
                'for_text'     => 'faktura'
            ];

            $subject = 'Information om avslutad tolkning för bokningsnummer #' . $job->id;
            $this->mailer->send($email, $name, $subject, 'emails.session-ended', $dataEmail);

            $user = JobRepository::jobTranslatorJobRelWheres($job, 'completed_at', Null, 'cancel_at', Null);

            $email = $user->user->email;
            $name = $user->user->name;
            $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;

            $dataEmail = [
                'user'         => $user,
                'job'          => $job,
                'session_time' => $session_time,
                'for_text'     => 'lön'
            ];

            $this->mailer->send($email, $name, $subject, 'emails.session-ended', $dataEmail);
        }

        JobRepository::jobSave($job);
        
        return true;
//        }
        return false;
    }

    private function changePendingStatus($job, $data, $changedTranslator)
    {
//        if(in_array($data['status'], ['withdrawnbefore24', 'withdrawafter24', 'timedout', 'assigned'])) {
        $job->status = $data['status'];

        if($data['admin_comments'] == '' && $data['status'] == 'timedout')
            return false;

        $job->admin_comments = $data['admin_comments'];

        $user = JobRepository::getJobUser($job);

        if(!empty($job->user_email))
            $email = $job->user_email;
        else
            $email = $user->email;

        $name = $user->name;

        $dataEmail = [
            'user' => $user,
            'job'  => $job
        ];

        if($data['status'] == 'assigned' && $changedTranslator) {
            JobRepository::jobSave($job);

            $jobData = TeHelper::jobToData($job);

            $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';

            $this->mailer->send($email, $name, $subject, 'emails.job-accepted', $dataEmail);

            $translator = JobRepository::getJobsAssignedTranslatorDetail($job);

            $this->mailer->send($translator->email, $translator->name, $subject, 'emails.job-changed-translator-new-translator', $dataEmail);

            $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);

            NotificationService::sendSessionStartRemindNotification($user, $job, $language, $job->due, $job->duration);
            NotificationService::sendSessionStartRemindNotification($translator, $job, $language, $job->due, $job->duration);

            return true;
        } else {
            $subject = 'Avbokning av bokningsnr: #' . $job->id;

            $this->mailer->send($email, $name, $subject, 'emails.status-changed-from-pending-or-assigned-customer', $dataEmail);

            JobRepository::jobSave($job);

            return true;
        }
//        }
        return false;
    }

    private function changeWithdrawafter24Status($job, $data)
    {
        if(in_array($data['status'], ['timedout'])) {
            $job->status = $data['status'];
            
            if($data['admin_comments'] == '')
                return false;

            $job->admin_comments = $data['admin_comments'];

            JobRepository::jobSave($job);

            return true;
        }

        return false;
    }

    private function changeAssignedStatus($job, $data)
    {
        if(in_array($data['status'], ['withdrawbefore24', 'withdrawafter24', 'timedout'])) {
            $job->status = $data['status'];

            if($data['admin_comments'] == '' && $data['status'] == 'timedout')
                return false;

            $job->admin_comments = $data['admin_comments'];

            if(in_array($data['status'], ['withdrawbefore24', 'withdrawafter24'])) {
                $user = JobRepository::getJobUser($job);

                if(!empty($job->user_email))
                    $email = $job->user_email;
                else
                    $email = $user->email;

                $name = $user->name;

                $dataEmail = [
                    'user' => $user,
                    'job'  => $job
                ];

                $subject = 'Information om avslutad tolkning för bokningsnummer #' . $job->id;

                $this->mailer->send($email, $name, $subject, 'emails.status-changed-from-pending-or-assigned-customer', $dataEmail);

                $user = JobRepository::jobTranslatorJobRelWheres($job, 'completed_at', Null, 'cancel_at', Null);

                $email = $user->user->email;
                $name = $user->user->name;
                $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;

                $dataEmail = [
                    'user' => $user,
                    'job'  => $job
                ];

                $this->mailer->send($email, $name, $subject, 'emails.job-cancel-translator', $dataEmail);
            }

            JobRepository::jobSave($job);

            return true;
        }

        return false;
    }
    
    private function changeDue($oldDue, $newDue)
    {
        $dateChanged = false;

        if($oldDue != $newDue) {
            $log_data = [
                'old_due' => $oldDue,
                'new_due' => $newDue
            ];

            $dateChanged = true;

            return ['dateChanged' => $dateChanged, 'log_data' => $log_data];
        }

        return ['dateChanged' => $dateChanged];
    }

    public function alerts()
    {
        $jobs = JobRepository::jobAll();

        $sesJobs = [];
        $jobId = [];
        $diff = [];
        $i = 0;

        foreach($jobs as $job) {
            $sessionTime = explode(':', $job->session_time);

            if(count($sessionTime) >= 3) {
                $diff[$i] = ($sessionTime[0] * 60) + $sessionTime[1] + ($sessionTime[2] / 60);

                if($diff[$i] >= $job->duration) {
                    if($diff[$i] >= $job->duration * 2)
                        $sesJobs [$i] = $job;
                }

                $i++;
            }
        }

        foreach($sesJobs as $job) {
            $jobId [] = $job->id;
        }

        $languages = LanguageRepository::languageWhereOrderby('active', '1', 'language');

        $requestdata = Request::all();

        $allCustomers = UserRepository::usersLists('user_type', '1', 'email');
        $allTranslators = UserRepository::usersLists('user_type', '2', 'email');

        $cuser = Auth::user();

        $consumer_type = TeHelper::getUsermeta($cuser->id, 'consumer_type');

        if($cuser && $cuser->is('superadmin'))
            $allJobs = UserTypeRoleService::isSuperAdmin($requestdata, $jobId);

        return ['allJobs' => $allJobs, 'languages' => $languages, 'all_customers' => $allCustomers, 'all_translators' => $allTranslators, 'requestdata' => $requestdata];
    }

    public function userLoginFailed()
    {
        $throttles = ThrottlesRepository::throttlesWhereWithPaginate('ignore', 0, 'user', 15);

        return ['throttles' => $throttles];
    }

    public function bookingExpireNoAccepted()
    {
        $languages = LanguageRepository::languageWhereOrderby('active', '1', 'language');

        $requestdata = Request::all();

        $allCustomers = UserRepository::usersLists('user_type', '1', 'email');
        $allTranslators = UserRepository::usersLists('user_type', '2', 'email');

        $cuser = Auth::user();

        $consumer_type = TeHelper::getUsermeta($cuser->id, 'consumer_type');

        if($cuser && ($cuser->is('superadmin') || $cuser->is('admin')))
            $allJobs = UserTypeRoleService::isAdminOrSuperAdmin($requestdata);

        return ['allJobs' => $allJobs, 'languages' => $languages, 'all_customers' => $allCustomers, 'all_translators' => $allTranslators, 'requestdata' => $requestdata];
    }

    public function ignoreExpiring($id)
    {
        $job = JobRepository::jobFind($id);

        $job->ignore = 1;

        JobRepository::jobSave($job);

        return ['success', 'Changes saved'];
    }

    public function ignoreExpired($id)
    {
        $job = JobRepository::jobFind($id);

        $job->ignore_expired = 1;

        JobRepository::jobSave($job);
        
        return ['success', 'Changes saved'];
    }

    public function ignoreThrottle($id)
    {
        $throttle = ThrottlesRepository::throttlesFind($id);

        $throttle->ignore = 1;

        ThrottlesRepository::throttlesSave($throttle);

        return ['success', 'Changes saved'];
    }
}