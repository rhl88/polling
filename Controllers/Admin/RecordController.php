<?php

namespace App\Apps\CmsproPolling\Controllers\Admin;

use App\Apps\CmsproPolling\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RecordController extends Controller
{
    public function __construct(protected ActivityService $activityService)
    {
    }

    /**
     * 投票记录列表
     */
    public function index(Request $request)
    {
        return response()->json(
            $this->activityService->getRecordList($request->all())
        );
    }
}
