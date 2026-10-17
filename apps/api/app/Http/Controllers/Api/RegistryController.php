<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiModelResource;
use App\Http\Resources\AiProviderResource;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegistryController extends Controller
{
    /**
     * List the configured AI providers (never exposes credentials).
     */
    public function providers(): AnonymousResourceCollection
    {
        return AiProviderResource::collection(
            AiProvider::query()->withCount('models')->orderBy('priority')->get(),
        );
    }

    /**
     * List registered models with their provider and capabilities.
     */
    public function models(): AnonymousResourceCollection
    {
        return AiModelResource::collection(
            AiModel::query()
                ->with(['provider', 'capabilities'])
                ->orderBy('priority')
                ->get(),
        );
    }
}
