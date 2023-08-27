<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $response = []; // or any other default

        if ( $user_id = $request->user_id ) {
            $response = $this->repository->getUsersJobs( $user_id );
        } elseif ( $this->isAdminOrSuperAdmin($request) ) {
            $response = $this->repository->getAll( $request );
        }

        return response( $response );
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository
            ->with('translatorJobRel.user')
            ->find( $id );

        return response( $job );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $user = $request->__authenticatedUser;
        $data = $request->all();

        $response = $this->repository->store( $user, $data );

        return response( $response );
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $user = $request->__authenticatedUser;
        $data = array_except($request->all(), ['_token', 'submit']);

        $response = $this->repository->updateJob( $id, $data, $user );

        return response( $response );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->storeJobEmail( $data );

        return response( $response );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $response = []; // or any other default

        if ( $user_id = $request->user_id ) {
            $response = $this->repository->getUsersJobsHistory( $user_id, $request );
        }

        return response( $response );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob( $data, $user );

        return response( $response );
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->job_id;
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId( $data, $user );

        return response( $response );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax( $data, $user );

        return response( $response );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob( $data );

        return response( $response );

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall( $data );

        return response( $response );

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs( $user );

        return response( $response );
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = isset( $data['distance'] ) ? $data['distance'] : '';
        $time = isset( $data['time'] ) ? $data['time'] : '';
        $job_id = isset( $data['jobid'] ) ? $data['jobid'] : '';
        $session_time = isset( $data['session_time'] ) ? $data['session_time'] : '';
        $flagged = ( $data['flagged'] == 'true' ) ? 'yes' : 'no';
        $manually_handled = ( $data['manually_handled'] == 'true' ) ? 'yes' : 'no';
        $by_admin = ( $data['by_admin'] == 'true' ) ? 'yes' : 'no';
        $admincomment = isset( $data['admincomment'] ) ? $data['admincomment'] : '';

        if ( $flagged == 'yes' && empty($admin_comment) ) {
            return "Please, add comment";
        }

        if ( $time || $distance ) {
            Distance::where('job_id', $job_id)
                ->update( compact('time', 'distance') );
        }

        Job::find( $job_id )
            ->update(
                compact(
                    'admin_comments', 'session_time',
                    'flagged', 'manually_handled', 'by_admin'
                )
            );

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->reopen( $data );

        return response( $response );
    }

    public function resendNotifications(Request $request)
    {
        $job_id = $request->jobid;
        $job = $this->repository->find( $job_id );
        $job_data = $this->repository->jobToData( $job );

        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $job_id = $request->jobid;
        $job = $this->repository->find( $job_id );

        try {
            $this->repository->sendSMSNotificationToTranslator( $job );
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return bool
     */
    private function isAdminOrSuperAdmin(Request $request): bool
    {
        $userType = optional($request->__authenticatedUser)->user_type;
        return in_array($userType, [env('ADMIN_ROLE_ID'), env('SUPERADMIN_ROLE_ID')]);
    }

}
