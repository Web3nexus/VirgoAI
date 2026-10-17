<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Uploads\ReferenceImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A reference image the user attaches to a run. Images only, checked by content, with a daily limit per account.
 */
class ReferenceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:'.implode(',', ReferenceImage::MIMES), 'max:'.ReferenceImage::MAX_KILOBYTES],
        ]);

        $user = $request->user();
        $limit = (int) config('virgoai.references.daily_limit', 30);

        if (! RateLimiter::attempt('reference-upload:'.$user->id, $limit, fn () => true, 86400)) {
            return response()->json(['message' => "You can attach up to {$limit} reference images a day."], 429);
        }

        try {
            $url = ReferenceImage::store($user, $request->file('image'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['image' => $e->getMessage()]);
        }

        return response()->json(['url' => $url], 201);
    }
}
