<?php

namespace App\Apps\CmsproPolling\Controllers\User;

use App\Apps\CmsproPolling\Models\PollingRecord;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RecordController extends Controller
{
    /**
     * 我的参与记录
     */
    public function index(Request $request)
    {
        $userId = (int) auth('web')->id();

        $query = PollingRecord::where('user_id', $userId)
            ->with(['activity', 'option']);

        if (!empty($request->input('activity_id'))) {
            $query->where('activity_id', (int) $request->input('activity_id'));
        }

        $perPage = (int) ($request->input('per_page') ?? 20);
        $items = $query->orderByDesc('id')->paginate($perPage);

        $items->through(function (PollingRecord $record) {
            return [
                'id' => $record->id,
                'activity_id' => $record->activity_id,
                'activity_title' => $record->activity?->title ?? '-',
                'option_title' => $record->option?->title ?? '-',
                'vote_no' => $record->vote_no,
                'rank' => $record->rank,
                'create_time' => $record->create_time,
            ];
        });

        return response()->json(ApiResponse::paginate($items));
    }
}
