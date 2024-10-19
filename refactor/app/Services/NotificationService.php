<?php

namespace DTApi\Services;

use Illuminate\Support\Facades\Log;
use DTApi\Helpers\ApiHelper;
use DTApi\Helpers\CommonHelper;
use DTApi\Helpers\DateTimeHelper;
use DTApi\Helpers\LogHelper;
use DTApi\Helpers\SendSMSHelper;
use DTApi\Helpers\TeHelper;
use DTApi\Mailers\MailerInterface;
use DTApi\Repository\JobRepository;
use DTApi\Repository\UserRepository;
use DTApi\Repository\UserLanguagesRepository;
use DTApi\Repository\UserMetaRepository;
use DTApi\Repository\UsersBlacklistRepository;

class NotificationService
{
    public function isNeedToSendPush($userId)
    {
        $notGetNotification = TeHelper::getUsermeta($userId, 'not_get_notification');

        if($notGetNotification == 'yes') 
            return false;

        return true;
    }

    public function isNeedToDelayPush($userId)
    {
        if(!DateTimeHelper::isNightTime()) 
            return false;

        $notGetNighttime = TeHelper::getUsermeta($userId, 'not_get_nighttime');

        if($notGetNighttime == 'yes')
            return true;

        return false;
    }

    public function sendPushNotificationToSpecificUsers($users, $jobId, $data, $messageText, $isNeedDelay)
    {
        LogHelper::customPushLogger('push');
        LogHelper::customAddLogInfo('Push send for job ' . $jobId, [$users, $data, $messageText, $isNeedDelay]);

        if(env('APP_ENV') == 'prod')
            $onesignalAppID = config('app.prodOnesignalAppID');
        else
            $onesignalAppID = config('app.devOnesignalAppID');

        $userTags = CommonHelper::getUserTagsStringFromArray($users);

        $data['job_id'] = $jobId;
        $iosSound = 'default';
        $androidSound = 'default';

        if($data['notification_type'] == 'suitable_job') {
            if($data['immediate'] == 'no') {
                $androidSound = 'normal_booking';
                $iosSound = 'normal_booking.mp3';
            } else {
                $androidSound = 'emergency_booking';
                $iosSound = 'emergency_booking.mp3';
            }
        }

        $fields = array(
            'app_id'         => $onesignalAppID,
            'tags'           => json_decode($userTags),
            'data'           => $data,
            'title'          => array('en' => 'DigitalTolk'),
            'contents'       => $messageText,
            'ios_badgeType'  => 'Increase',
            'ios_badgeCount' => 1,
            'android_sound'  => $androidSound,
            'ios_sound'      => $iosSound
        );

        if($isNeedDelay) {
            $next_business_time = DateTimeHelper::getNextBusinessTimeString();

            $fields['send_after'] = $next_business_time;
        }

        $fields = json_encode($fields);

        $response = ApiHelper::send($fields, 'https://onesignal.com/api/v1/notifications');

        LogHelper::customAddLogInfo('Push send for job ' . $jobId . ' curl answer', [$response]);
    }

    public function sendNotificationTranslator($job, $data = [], $exclude_user_id)
    {
        $users = UserRepository::getUserAll();

        $translator_array = array();            // suitable translators (no need to delay push)
        $delpay_translator_array = array();     // suitable translators (need to delay push)

        foreach($users as $oneUser) {
            if($oneUser->user_type == '2' && $oneUser->status == '1' && $oneUser->id != $exclude_user_id) { // user is translator and he is not disabled
                if(!$this->isNeedToSendPush($oneUser->id)) 
                    continue;

                $not_get_emergency = TeHelper::getUsermeta($oneUser->id, 'not_get_emergency');

                if($data['immediate'] == 'yes' && $not_get_emergency == 'yes')
                    continue;

                $jobs = $this->getPotentialJobIdsWithUserId($oneUser->id); // get all potential jobs of this user

                foreach($jobs as $oneJob) {
                    if($job->id == $oneJob->id) { // one potential job is the same with current job
                        $userId = $oneUser->id;

                        $job_for_translator = JobRepository::assignedToPaticularTranslator($userId, $oneJob->id);

                        if($job_for_translator == 'SpecificJob') {
                            $job_checker = JobRepository::checkParticularJob($userId, $oneJob);

                            if(($job_checker != 'userCanNotAcceptJob')) {
                                if($this->isNeedToDelayPush($oneUser->id))
                                    $delpay_translator_array[] = $oneUser;
                                else
                                    $translator_array[] = $oneUser;
                            }
                        }
                    }
                }
            }
        }

        $data['language'] = TeHelper::fetchLanguageFromJobId($data['from_language_id']);
        $data['notification_type'] = 'suitable_job';

        $messageContents = '';

        if($data['immediate'] == 'no')
            $messageContents = 'Ny bokning för ' . $data['language'] . 'tolk ' . $data['duration'] . 'min ' . $data['due'];
        else
            $messageContents = 'Ny akutbokning för ' . $data['language'] . 'tolk ' . $data['duration'] . 'min';

        $messageText = array(
            "en" => $messageContents
        );

        LogHelper::customPushLogger('push');
        LogHelper::customAddLogInfo('Push send for job ' . $job->id, [$translator_array, $delpay_translator_array, $messageText, $data]);

        $this->sendPushNotificationToSpecificUsers($translator_array, $job->id, $data, $messageText, false);       // send new booking push to suitable translators(not delay)
        $this->sendPushNotificationToSpecificUsers($delpay_translator_array, $job->id, $data, $messageText, true); // send new booking push to suitable translators(need to delay)
    }

    private function getPotentialJobIdsWithUserId($userId)
    {
        $userMeta = UserMeta::userMetaWhere('user_id', $userId);

        $translatorType = $userMeta->translator_type;
        $jobType = 'unpaid';

        if($translatorType == 'professional')
            $jobType = 'paid';   /*show all jobs for professionals.*/
        else if($translatorType == 'rwstranslator')
            $jobType = 'rws';  /* for rwstranslator only show rws jobs. */
        else if($translatorType == 'volunteer')
            $jobType = 'unpaid';  /* for volunteers only show unpaid jobs. */


        $languages = UserLanguagesRepository::userLanguagesWhere('user_id', $userId);
        $userlanguage = UserLanguagesRepository::userLanguagesCollectPluck($languages, 'lang_id');

        $gender = $userMeta->gender;
        $translatorLevel = $userMeta->translator_level;

        $jobIds = JobRepository::getJobs($cuser->id, $jobType, 'pending', $userlanguage, $gender, $translatorLevel);

        foreach($jobIds as $key => $value) {    // checking translator town
            $job = JobRepository::jobFind($value->id);
            
            $jobuserid = $job->user_id;
            $checktown = JobRepository::checkTowns($jobuserid, $userId);

            if(($job->customer_phone_type == 'no' || $job->customer_phone_type == '') && $job->customer_physical_type == 'yes' && $checktown == false)
                unset($jobIds[$key]);
        }

        $jobs = TeHelper::convertJobIdsInObjs($jobIds);

        return $jobs;
    }

    public function sendNotificationByAdminCancelJob($jobId)
    {
        $job = JobRepository::jobFindOrFail($jobId);

        $job = JobRepository::jobUsermetaFirst($job);

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
        $data['customer_town'] = $userMeta->city;
        $data['customer_type'] = $userMeta->customer_type;

        $explodeDueDate = explode(" ", $job->due);
        $dueDate = $explodeDueDate[0];
        $dueTime = $dueDate[1];
        $data['due_date'] = $dueDate;
        $data['due_time'] = $dueTime;
        $data['job_for'] = array();

        if($job->gender != null) {
            if($job->gender == 'male')
                $data['job_for'][] = 'Man';
            elseif($job->gender == 'female')
                $data['job_for'][] = 'Kvinna';
        }
        
        if($job->certified != null) {
            if($job->certified == 'both') {
                $data['job_for'][] = 'normal';
                $data['job_for'][] = 'certified';
            } elseif($job->certified == 'yes') {
                $data['job_for'][] = 'certified';
            } else {
                $data['job_for'][] = $job->certified;
            }
        }
        
        $this->sendNotificationTranslator($job, $data, '*');   // send Push all sutiable translators
    }

    public function sendSMSNotificationToTranslator($job)
    {
        $translators = $this->getPotentialTranslators($job);

        $jobPosterMeta = UserMeta::userMetaWhere('user_id', $job->user_id);

        // prepare message templates
        $date = date('d.m.Y', strtotime($job->due));
        $time = date('H:i', strtotime($job->due));
        $duration = DateTimeHelper::convertToHoursMins($job->duration);

        $jobId = $job->id;
        $city = $job->city ? $job->city : $jobPosterMeta->city;

        $phoneJobMessageTemplate = trans('sms.phone_job', ['date' => $date, 'time' => $time, 'duration' => $duration, 'jobId' => $jobId]);

        $physicalJobMessageTemplate = trans('sms.physical_job', ['date' => $date, 'time' => $time, 'town' => $city, 'duration' => $duration, 'jobId' => $jobId]);

        // analyse weather it's phone or physical; if both = default to phone
        if($job->customer_physical_type == 'yes' && $job->customer_phone_type == 'no') {
            // It's a physical job
            $message = $physicalJobMessageTemplate;
        } else if($job->customer_physical_type == 'no' && $job->customer_phone_type == 'yes') {
            // It's a phone job
            $message = $phoneJobMessageTemplate;
        } else if($job->customer_physical_type == 'yes' && $job->customer_phone_type == 'yes') {
            // It's both, but should be handled as phone job
            $message = $phoneJobMessageTemplate;
        } else {
            // This shouldn't be feasible, so no handling of this edge case
            $message = '';
        }

        Log::info($message);

        // send messages via sms handler
        foreach ($translators as $translator) {
            // send message to translator
            $status = SendSMSHelper::send(env('SMS_NUMBER'), $translator->mobile, $message);

            Log::info('Send SMS to ' . $translator->email . ' (' . $translator->mobile . '), status: ' . print_r($status, true));
        }

        return count($translators);
    }

    private function getPotentialTranslators(Job $job)
    {
        $jobType = $job->job_type;

        if($jobType == 'paid')
            $translatorType = 'professional';
        else if($jobType == 'rws')
            $translatorType = 'rwstranslator';
        else if($jobType == 'unpaid')
            $translatorType = 'volunteer';

        $joblanguage = $job->from_language_id;
        $gender = $job->gender;
        $translatorLevel = [];

        if(!empty($job->certified)) {
            if($job->certified == 'yes' || $job->certified == 'both') {
                $translatorLevel[] = 'Certified';
                $translatorLevel[] = 'Certified with specialisation in law';
                $translatorLevel[] = 'Certified with specialisation in health care';
            }
            elseif($job->certified == 'law' || $job->certified == 'n_law')
            {
                $translatorLevel[] = 'Certified with specialisation in law';
            }
            elseif($job->certified == 'health' || $job->certified == 'n_health')
            {
                $translatorLevel[] = 'Certified with specialisation in health care';
            }
            else if($job->certified == 'normal' || $job->certified == 'both') {
                $translatorLevel[] = 'Layman';
                $translatorLevel[] = 'Read Translation courses';
            }
            elseif($job->certified == null) {
                $translatorLevel[] = 'Certified';
                $translatorLevel[] = 'Certified with specialisation in law';
                $translatorLevel[] = 'Certified with specialisation in health care';
                $translatorLevel[] = 'Layman';
                $translatorLevel[] = 'Read Translation courses';
            }
        }


        $blacklist = UsersBlacklistRepository::usersBlacklistWhere('user_id', $job->user_id);
        $translatorsId = UsersBlacklistRepository::usersBlacklistCollectPluck($blacklist, 'translator_id');

        $users = UserRepository::getPotentialUsers($translatorType, $joblanguage, $gender, $translatorLevel, $translatorsId);

//        foreach ($job_ids as $k => $v)     // checking translator town
//        {
//            $job = Job::find($v->id);
//            $jobuserid = $job->user_id;
//            $checktown = Job::checkTowns($jobuserid, $user_id);
//            if(($job->customer_phone_type == 'no' || $job->customer_phone_type == '') && $job->customer_physical_type == 'yes' && $checktown == false) {
//                unset($job_ids[$k]);
//            }
//        }
//        $jobs = TeHelper::convertJobIdsInObjs($job_ids);
        return $users;
    }

    public function sendSessionStartRemindNotification($user, $job, $language, $due, $duration)
    {
        $data = array();
        $data['notification_type'] = 'session_start_remind';
        $dueExplode = explode(' ', $due);

        if($job->customer_physical_type == 'yes')
            $messageText = array(
                "en" => 'Detta är en påminnelse om att du har en ' . $language . 'tolkning (på plats i ' . $job->town . ') kl ' . $dueExplode[1] . ' på ' . $dueExplode[0] . ' som vara i ' . $duration . ' min. Lycka till och kom ihåg att ge feedback efter utförd tolkning!'
            );
        else
            $messageText = array(
                "en" => 'Detta är en påminnelse om att du har en ' . $language . 'tolkning (telefon) kl ' . $dueExplode[1] . ' på ' . $dueExplode[0] . ' som vara i ' . $duration . ' min.Lycka till och kom ihåg att ge feedback efter utförd tolkning!'
            );

        if($this->isNeedToSendPush($user->id)) {
            $users_array = array($user);
            $this->sendPushNotificationToSpecificUsers($users_array, $job->id, $data, $messageText, $this->isNeedToDelayPush($user->id));

            LogHelper::customAdminLogger('cron');
            LogHelper::customAddLogInfo('sendSessionStartRemindNotification ', ['job' => $job->id]);
        }
    }

    public function sendChangedTranslatorNotification($job, $currentTranslator, $newTranslator)
    {
        $user = JobRepository::getJobUserFirst($job);

        if(!empty($job->user_email))
            $email = $job->user_email;
        else
            $email = $user->email;

        $name = $user->name;
        $subject = 'Meddelande om tilldelning av tolkuppdrag för uppdrag # ' . $job->id . ')';

        $data = [
            'user' => $user,
            'job'  => $job
        ];

        MailerInterface::send($email, $name, $subject, 'emails.job-changed-translator-customer', $data);

        if($currentTranslator) {
            $user = $currentTranslator->user;
            $name = $user->name;
            $email = $user->email;
            $data['user'] = $user;

            MailerInterface::send($email, $name, $subject, 'emails.job-changed-translator-old-translator', $data);
        }

        $user = $newTranslator->user;
        $name = $user->name;
        $email = $user->email;
        $data['user'] = $user;

        MailerInterface::send($email, $name, $subject, 'emails.job-changed-translator-new-translator', $data);
    }

    public function sendChangedDateNotification($job, $oldTime)
    {
        $user = JobRepository::getJobUserFirst($job);

        if(!empty($job->user_email))
            $email = $job->user_email;
        else
            $email = $user->email;

        $name = $user->name;
        $subject = 'Meddelande om ändring av tolkbokning för uppdrag # ' . $job->id . '';

        $data = [
            'user'     => $user,
            'job'      => $job,
            'old_time' => $oldTime
        ];

        MailerInterface::send($email, $name, $subject, 'emails.job-changed-date', $data);

        $translator = JobRepository::getJobsAssignedTranslatorDetail($job);

        $data = [
            'user'     => $translator,
            'job'      => $job,
            'old_time' => $oldTime
        ];

        MailerInterface::send($translator->email, $translator->name, $subject, 'emails.job-changed-date', $data);
    }

    public function sendChangedLangNotification($job, $oldLang)
    {
        $user = JobRepository::getJobUserFirst($job);

        if(!empty($job->user_email))
            $email = $job->user_email;
        else
            $email = $user->email;

        $name = $user->name;
        $subject = 'Meddelande om ändring av tolkbokning för uppdrag # ' . $job->id . '';

        $data = [
            'user'     => $user,
            'job'      => $job,
            'old_lang' => $oldLang
        ];

        MailerInterface::send($email, $name, $subject, 'emails.job-changed-lang', $data);

        $translator = JobRepository::getJobsAssignedTranslatorDetail($job);

        MailerInterface::send($translator->email, $translator->name, $subject, 'emails.job-changed-date', $data);
    }

    public function sendExpiredNotification($job, $user)
    {
        $data = array();
        $data['notification_type'] = 'job_expired';
        $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);

        $messageText = array(
            "en" => 'Tyvärr har ingen tolk accepterat er bokning: (' . $language . ', ' . $job->duration . 'min, ' . $job->due . '). Vänligen pröva boka om tiden.'
        );

        if($this->isNeedToSendPush($user->id)) {
            $users_array = array($user);

            $this->sendPushNotificationToSpecificUsers($users_array, $job->id, $data, $messageText, $this->isNeedToDelayPush($user->id));
        }
    }

    public function sendNotificationChangePending($user, $job, $language, $due, $duration)
    {
        $data = array();
        $data['notification_type'] = 'session_start_remind';

        if($job->customer_physical_type == 'yes')
            $messageText = array(
                "en" => 'Du har nu fått platstolkningen för ' . $language . ' kl ' . $duration . ' den ' . $due . '. Vänligen säkerställ att du är förberedd för den tiden. Tack!'
            );
        else
            $messageText = array(
                "en" => 'Du har nu fått telefontolkningen för ' . $language . ' kl ' . $duration . ' den ' . $due . '. Vänligen säkerställ att du är förberedd för den tiden. Tack!'
            );

        if($this->isNeedToSendPush($user->id)) {
            $users_array = array($user);

            $this->sendPushNotificationToSpecificUsers($users_array, $job->id, $data, $messageText, $this->isNeedToDelayPush($user->id));
        }
    }
}