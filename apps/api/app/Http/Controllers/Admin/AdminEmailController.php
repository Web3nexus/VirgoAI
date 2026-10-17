<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendTemplatedEmail;
use App\Models\AdminAuditLog;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Settings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminEmailController extends Controller
{
    public function templates(): JsonResponse
    {
        return response()->json([
            'data' => EmailTemplate::query()->orderBy('id')->get()->map(fn (EmailTemplate $template) => $this->present($template))->values(),
            'placeholders' => ['name', 'email', 'amount', 'balance', 'support', 'app'],
            'from' => ['name' => SystemSettings::fromName(), 'address' => SystemSettings::fromAddress()],
            'mailer' => config('mail.default'),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $template = EmailTemplate::create($this->validated($request));
        AdminAuditLog::record($request->user(), 'email.template_created', $template, ['key' => $template->key]);

        return response()->json($this->present($template), 201);
    }

    public function updateTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        $template->update($this->validated($request, $template));
        AdminAuditLog::record($request->user(), 'email.template_updated', $template, ['key' => $template->key]);

        return response()->json($this->present($template->refresh()));
    }

    public function destroyTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        $key = $template->key;
        $template->delete();
        AdminAuditLog::record($request->user(), 'email.template_deleted', null, ['key' => $key]);

        return response()->json(['message' => 'Template deleted.']);
    }

    /** Send the template to the owner, with sample values, to check it before users receive it. */
    public function test(Request $request, EmailTemplate $template): JsonResponse
    {
        SendTemplatedEmail::dispatch($template->key, $request->user()->email, [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'amount' => '$10.00',
            'balance' => '$12.50',
        ], $request->user()->id);

        return response()->json(['message' => "A test was queued to {$request->user()->email}."]);
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = EmailLog::query()->latest('id')->paginate(25);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (EmailLog $log) => [
                'id' => $log->id,
                'template_key' => $log->template_key,
                'to' => $log->to_email,
                'subject' => $log->subject,
                'status' => $log->status,
                'error' => $log->error,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage()],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?EmailTemplate $existing = null): array
    {
        return $request->validate([
            'key' => [
                $existing ? 'sometimes' : 'required', 'string', 'max:64', 'regex:/^[a-z_]+$/',
                Rule::unique('email_templates', 'key')->ignore($existing?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'enabled' => ['required', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(EmailTemplate $template): array
    {
        return [
            'id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'subject' => $template->subject,
            'body' => $template->body,
            'enabled' => (bool) $template->enabled,
        ];
    }
}
