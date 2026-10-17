<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Workflows\WorkflowTemplates;
use Illuminate\Http\JsonResponse;

class WorkflowTemplateController extends Controller
{
    /**
     * List available workflow templates and their stage graphs.
     */
    public function index(): JsonResponse
    {
        $templates = array_map(fn ($template) => [
            'key' => $template->key,
            'name' => $template->name,
            'stages' => array_map(fn ($stage) => [
                'key' => $stage->key,
                'name' => $stage->name,
                'handler' => $stage->handler,
                'approval_policy' => $stage->approvalPolicy->value,
                'capability' => $stage->capability?->value,
                'depends_on' => $stage->dependsOn,
            ], $template->stages),
        ], array_values(WorkflowTemplates::all()));

        return response()->json(['data' => $templates]);
    }
}
