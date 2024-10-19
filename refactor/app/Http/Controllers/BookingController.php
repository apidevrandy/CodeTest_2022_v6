<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;

use DTApi\Services\BookingService;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    protected $service;

    public function __construct(BookingService $bookingService)
    {
        $this->service = $bookingService;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $response = $this->service->index($request);

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $response = $this->service->show($id);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $response = $this->service->store($request->__authenticatedUser, $data);

        return response($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;

        $response = $this->service->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();

        $response = $this->service->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $response = $this->service->getUsersJobsHistory($request->get('user_id'), $request);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->service->acceptJob($data, $user);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->service->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->service->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->service->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->service->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->service->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $response = $this->service->distanceFeed($data);

        return response($response);
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->service->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        
        $response = $this->service->resendNotifications($data);

        return response($response);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        
        $response = $this->service->resendSMSNotifications($data);

        return response($response);
    }

}
