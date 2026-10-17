<?php

namespace App\Http\Controllers\Api;

use App\Billing\UsageRecorder;
use App\Http\Controllers\Controller;
use App\Models\ClipRun;
use App\Models\ClipSource;
use App\Models\Generation;
use App\Models\Remix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token and credit totals for one production. Users see tokens and credits; cost and margin are owner-only.
 */
class UsageController extends Controller
{
    public function __construct(protected UsageRecorder $usage) {}

    public function show(Request $request, string $type, string $id): JsonResponse
    {
        abort_unless(in_array($type, [UsageRecorder::TYPE_REMIX, UsageRecorder::TYPE_CLIP_RUN, UsageRecorder::TYPE_CLIP_SOURCE, UsageRecorder::TYPE_GENERATION], true), 404);

        $this->assertOwned($request, $type, $id);

        return response()->json(['production' => ['type' => $type, 'id' => $id], ...$this->usage->userSummary($type, $id)]);
    }

    /** Owner only: cost, price and what the platform kept, per step. */
    public function owner(Request $request, string $type, string $id): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 404);

        return response()->json(['production' => ['type' => $type, 'id' => $id], ...$this->usage->ownerSummary($type, $id)]);
    }

    private function assertOwned(Request $request, string $type, string $id): void
    {
        $owner = match ($type) {
            UsageRecorder::TYPE_REMIX => Remix::query()->whereKey($id)->value('user_id'),
            UsageRecorder::TYPE_CLIP_RUN => ClipRun::query()->whereKey($id)->value('user_id'),
            UsageRecorder::TYPE_CLIP_SOURCE => ClipSource::query()->whereKey($id)->value('user_id'),
            UsageRecorder::TYPE_GENERATION => Generation::query()->whereKey($id)->value('user_id'),
        };

        abort_unless($owner !== null && (int) $owner === (int) $request->user()->id, 404);
    }
}
