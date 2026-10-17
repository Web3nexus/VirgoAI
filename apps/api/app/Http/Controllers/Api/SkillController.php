<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Skills\SkillLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The skills library: what each skill is for, without the instructions the model is given.
 */
class SkillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['data' => SkillLibrary::search($data['q'] ?? null, $data['category'] ?? null)]);
    }
}
