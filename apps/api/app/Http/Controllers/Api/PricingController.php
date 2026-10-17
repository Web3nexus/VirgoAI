<?php

namespace App\Http\Controllers\Api;

use App\Billing\Catalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public pricing for the marketing page. No sign-in, and no margin.
 */
class PricingController extends Controller
{
    public function index(Catalog $catalog): JsonResponse
    {
        return response()->json($catalog->public());
    }
}
