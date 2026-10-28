<?php

namespace App\Http\Controllers\Api;

use App\Billing\RateCard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** The rate card: what each unit costs in credits. Users see sell prices; the owner also sees cost and what is kept. */
class RateCardController extends Controller
{
    public function __construct(protected RateCard $rateCard) {}

    public function show(): JsonResponse
    {
        return response()->json($this->rateCard->public());
    }

    public function owner(): JsonResponse
    {
        return response()->json(['card' => $this->rateCard->owner(), 'problems' => $this->rateCard->verify()]);
    }
}
