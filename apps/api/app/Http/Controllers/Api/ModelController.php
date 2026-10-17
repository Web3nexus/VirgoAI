<?php

namespace App\Http\Controllers\Api;

use App\Ai\Data\ToolModelOption;
use App\Ai\ModelDefaults;
use App\Ai\ModelRouter;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The models the studio can run, by what they make, with the default for each and whether it can run now.
 */
class ModelController extends Controller
{
    public function index(ModelRouter $router): JsonResponse
    {
        $groups = [];

        foreach (['video' => Capability::Video, 'image' => Capability::Image, 'text' => Capability::Text] as $name => $capability) {
            $groups[$name] = [
                'default' => ModelDefaults::defaultFor($name),
                // The echo adapter is a dev stub. It is not a model a user can pick.
                'models' => array_values(array_map(fn (ToolModelOption $option) => [
                    'key' => $option->key,
                    'name' => $option->name,
                    'provider_key' => $option->providerKey,
                    'usable' => $option->usable,
                    'reason' => $option->reason,
                    'price_label' => $option->priceLabel,
                ], array_filter($router->describe($capability), fn (ToolModelOption $option) => $option->providerKey !== 'echo'))),
            ];
        }

        return response()->json(['data' => $groups]);
    }
}
