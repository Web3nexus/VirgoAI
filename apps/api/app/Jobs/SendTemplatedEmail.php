<?php

namespace App\Jobs;

use App\Email\MailConfigurator;
use App\Email\TemplateRenderer;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Settings\SystemSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one template to one address and writes a row to the email log. A disabled or missing template
 * is logged as skipped, so nothing is sent without the owner's switch on.
 */
class SendTemplatedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, string|int|float>  $data
     */
    public function __construct(
        public readonly string $templateKey,
        public readonly string $to,
        public readonly array $data = [],
        public readonly ?int $userId = null,
    ) {}

    public function handle(TemplateRenderer $renderer): void
    {
        $template = EmailTemplate::query()->where('key', $this->templateKey)->first();

        if ($template === null || ! $template->enabled) {
            $this->log(EmailLog::STATUS_SKIPPED, $this->templateKey, 'Template is off or missing.');

            return;
        }

        $rendered = $renderer->render($template, $this->data);

        app(MailConfigurator::class)->apply();

        try {
            Mail::raw($rendered['body'], function ($message) use ($rendered) {
                $message->to($this->to)
                    ->from(SystemSettings::fromAddress(), SystemSettings::fromName())
                    ->subject($rendered['subject']);
            });
        } catch (Throwable $e) {
            Log::warning('Email send failed', ['template' => $this->templateKey, 'error' => $e->getMessage()]);
            $this->log(EmailLog::STATUS_FAILED, $this->templateKey, $e->getMessage(), $rendered['subject']);

            throw $e;
        }

        $this->log(EmailLog::STATUS_SENT, $this->templateKey, null, $rendered['subject']);
    }

    private function log(string $status, string $templateKey, ?string $error, ?string $subject = null): void
    {
        EmailLog::create([
            'template_key' => $templateKey,
            'to_email' => $this->to,
            'subject' => mb_substr((string) ($subject ?? $templateKey), 0, 200),
            'status' => $status,
            'error' => $error,
            'user_id' => $this->userId,
        ]);
    }
}
