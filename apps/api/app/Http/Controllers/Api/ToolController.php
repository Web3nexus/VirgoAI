<?php

namespace App\Http\Controllers\Api;

use App\Ai\Data\GenerationRequest;
use App\Ai\Exceptions\NoSuitableModel;
use App\Ai\GenerationService;
use App\Ai\Guardrails;
use App\Ai\ModelRouter;
use App\Billing\InsufficientCredit;
use App\Billing\PriceQuoter;
use App\Billing\PriceUnavailable;
use App\Billing\UsageRecorder;
use App\Billing\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Jobs\PollGeneration;
use App\Models\Generation;
use App\Models\Project;
use App\Settings\SystemSettings;
use App\Skills\SkillLibrary;
use App\Styles\StylePresets;
use App\Tools\Skills\DirectorScript;
use App\Tools\Skills\DirectorSkill;
use App\Tools\ToolCatalog;
use App\Uploads\OwnedReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToolController extends Controller
{
    public function __construct(
        protected ModelRouter $router,
        protected GenerationService $generations,
        protected PriceQuoter $quotes,
        protected Wallet $wallet,
    ) {}

    /**
     * Every tool with the models that can serve it, and whether each model can run right now.
     */
    public function index(): JsonResponse
    {
        $tools = collect(ToolCatalog::TOOLS)->map(function (array $tool, string $key) {
            $models = $this->router->describe($tool['capability']);

            return [
                'key' => $key,
                'label' => $tool['label'],
                'description' => $tool['description'],
                'capability' => $tool['capability']->value,
                'output' => $tool['output'],
                'sizes' => $tool['sizes'],
                'default_size' => $tool['default_size'],
                'choices' => $tool['choices'] ?? [],
                'voices' => config('virgoai.voices', []),
                'ready' => collect($models)->contains(fn ($model) => $model->usable),
                // Only models that can run are listed. Why one cannot run is for the owner, in the admin console.
                'models' => collect($models)->filter(fn ($model) => $model->usable)->map(fn ($model) => [
                    'key' => $model->key,
                    'name' => $model->name,
                    'provider_key' => $model->providerKey,
                    'usable' => true,
                    'price_label' => $model->priceLabel,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $tools]);
    }

    /**
     * Run one tool by hand with the chosen model. Every run is saved, including failed ones.
     *
     * The run is priced and its charge reserved before the provider is called. A run that fails is
     * refunded. Text runs settle to the tokens actually used. Image and video runs are queued at the
     * provider and return as "running"; the completer settles or refunds them when they finish.
     */
    public function run(Request $request, string $tool): JsonResponse
    {
        abort_unless(ToolCatalog::has($tool), 404);
        $definition = ToolCatalog::get($tool);

        // Maintenance stops every user's runs. The owner can still test while it is on.
        if (SystemSettings::maintenanceOn() && ! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => SystemSettings::maintenanceMessage()], 503);
        }

        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:4000'],
            'model_key' => ['nullable', 'string', 'max:128'],
            'project_id' => ['nullable', 'string', 'size:26'],
            'size' => ['nullable', 'string', Rule::in(array_keys(Project::FORMATS))],
            'script_type' => ['nullable', 'string', Rule::in(DirectorSkill::SCRIPT_TYPES)],
            'skill' => ['nullable', 'string', Rule::in(DirectorSkill::SKILLS)],
            'image_url' => ['nullable', 'string', 'max:2048', $tool === 'image' ? new OwnedReference : 'url:https'],
            'resolution' => ['nullable', 'string', Rule::in(['720p', '1080p'])],
            'skill_key' => ['nullable', 'string', Rule::in(SkillLibrary::keys())],
            'styles' => ['nullable', 'array'],
            'styles.environment' => ['nullable', 'string', Rule::in(StylePresets::keys('environment'))],
            'styles.character' => ['nullable', 'string', Rule::in(StylePresets::keys('character'))],
            'styles.look' => ['nullable', 'string', Rule::in(StylePresets::keys('look'))],
        ]);

        // The menus the user picked. They are stored with the run, and their words reach the provider.
        $styles = array_filter((array) ($data['styles'] ?? []));
        $providerPrompt = $styles === [] ? $data['prompt'] : StylePresets::applyToPrompt($data['prompt'], $styles);

        if ($tool === 'video' && empty($data['image_url'])) {
            throw ValidationException::withMessages([
                'image_url' => 'Video needs a keyframe image. Run an image first and paste its provider link.',
            ]);
        }

        $project = null;
        if (! empty($data['project_id'])) {
            $project = $request->user()->projects()->whereKey($data['project_id'])->first();
            abort_if($project === null, 422, 'That project does not belong to you.');
        }

        $size = $definition['sizes'] ? ($data['size'] ?? $definition['default_size']) : null;
        $options = array_filter(['size' => $size], fn ($value) => $value !== null);
        if ($styles !== []) {
            $options['styles'] = $styles;
        }
        // Video quality: the provider renders at this resolution, and the file is then fitted to the exact format size.
        if ($tool === 'video' && isset($data['resolution'])) {
            $options['resolution'] = $data['resolution'];
        }

        // The selected skill, stored with the run so the saved run says how it was made.
        $skillKey = $data['skill_key'] ?? null;
        $brief = SkillLibrary::brief($skillKey);
        if ($skillKey !== null) {
            $options['skill_key'] = $skillKey;
            // The skill decides the shape: its format replaces whatever size the request asked for.
            if ($definition['sizes']) {
                $options['size'] = SkillLibrary::get($skillKey)['defaults']['format'] ?? $definition['default_size'];
            }
        }

        // Reference image for image-to-image and image-to-video. It is an input, not a generation option.
        $inputs = [];
        if (! empty($data['image_url'])) {
            $inputs['image_url'] = $data['image_url'];
            $options['image_url'] = $data['image_url'];
        }

        // Script choices only apply to tools that declare them. The system prompt is sent to the
        // model but not stored, so saved runs stay small.
        $requestOptions = $options;
        if (isset($definition['choices'])) {
            $skill = $data['skill'] ?? 'director';
            $scriptType = $data['script_type'] ?? 'story';
            $options['script_type'] = $scriptType;
            $options['skill'] = $skill;
            $requestOptions = $options;

            $system = DirectorSkill::systemPrompt($skill, $scriptType, config('virgoai.voices', []));
            if ($system !== null) {
                $directive = StylePresets::directive($styles);
                $requestOptions['system'] = $directive === '' ? $system : $system."\n\n".$directive;
            }
        }

        // A text run follows the skill as system instructions. An image or video run gets it as a brief in front of the request.
        // Speech is not steered by a brief, so audio runs take the skill only through a script written first.
        if ($brief !== '') {
            if ($definition['output'] === 'text') {
                $requestOptions['system'] = implode("\n\n", array_filter([$requestOptions['system'] ?? null, $brief]));
            } elseif (in_array($definition['output'], ['image', 'video'], true)) {
                $providerPrompt = $brief."\n\nRequest: ".$providerPrompt;
            }
        }

        // Every model call carries the guardrails. They are appended after the skill, so nothing overrides them.
        if ($definition['output'] === 'text') {
            $requestOptions['system'] = Guardrails::withText($requestOptions['system'] ?? null);
        } elseif (in_array($definition['output'], ['image', 'video'], true)) {
            $providerPrompt = Guardrails::withMedia($providerPrompt);
        }

        try {
            $routed = $data['model_key'] ?? null
                ? $this->router->routeTo($definition['capability'], $data['model_key'])
                : $this->router->route($definition['capability']);
        } catch (NoSuitableModel $e) {
            throw ValidationException::withMessages(['model_key' => $e->getMessage()]);
        }

        $modelKey = $routed->modelKey();
        $providerKey = $routed->providerKey();

        try {
            $quote = $this->quotes->quote($providerKey, $modelKey, $definition['capability'], $data['prompt'], $requestOptions, $inputs);
        } catch (PriceUnavailable $e) {
            throw ValidationException::withMessages(['model_key' => $e->getMessage()]);
        }

        // A single run never costs more than the ceiling, however large the request.
        if ($quote->priceMicros > (int) config('virgoai.guardrails.max_run_price_micros')) {
            throw ValidationException::withMessages(['model_key' => 'This run is over the per-run limit. Shorten the prompt or choose a smaller size.']);
        }

        try {
            $generation = $this->wallet->reserveRun($request->user(), $quote, [
                'user_id' => $request->user()->id,
                'project_id' => $project?->id,
                'tool' => $tool,
                'capability' => $definition['capability']->value,
                'provider_key' => $providerKey,
                'model_key' => $modelKey,
                'prompt' => $data['prompt'],
                'options' => $options,
                'output_type' => $definition['output'],
            ]);
        } catch (InsufficientCredit $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        }

        $result = $this->generations->generate(new GenerationRequest(
            capability: $definition['capability'],
            modelKey: '',
            prompt: $providerPrompt,
            inputs: $inputs,
            options: $requestOptions,
            projectId: $project?->id,
            requestedModelKey: $modelKey,
        ));

        if (! $result->ok) {
            return $this->finishFailed($generation, $result->error ?? 'The run failed.');
        }

        if ($result->isQueued()) {
            $generation->status = Generation::STATUS_RUNNING;
            $generation->provider_job = $result->job;
            $generation->save();

            PollGeneration::dispatch($generation->id)
                ->delay(now()->addSeconds((int) config('virgoai.fal.poll_interval_seconds', 10)));

            return (new GenerationResource($generation))->response()->setStatusCode(201);
        }

        if ($definition['output'] !== 'text') {
            return $this->finishFailed($generation, "The [{$tool}] tool produced no output this provider can return yet.");
        }

        $reply = (string) ($result->output['text'] ?? $result->output['echo'] ?? '');

        if (($options['skill'] ?? null) === DirectorSkill::SKILL_DIRECTOR) {
            $parsed = DirectorScript::parse($reply, $options['script_type'], config('virgoai.voices', []));

            if (! $parsed['ok']) {
                return $this->finishFailed($generation, 'The director script is not usable: '.implode(' ', $parsed['errors']));
            }

            $reply = json_encode($parsed['script'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $actual = $this->quotes->settleText($providerKey, $modelKey, $result->usage);
        if ($actual !== null) {
            $this->wallet->settle($generation, $actual);
            app(UsageRecorder::class)->record($request->user(), UsageRecorder::TYPE_GENERATION, $generation->id, $tool, $providerKey, $modelKey, $actual, [
                'input' => (int) ($result->usage['input_tokens'] ?? 0),
                'output' => (int) ($result->usage['output_tokens'] ?? 0),
                'units' => 1,
                'unit_label' => 'run',
            ]);
        }

        $generation->status = Generation::STATUS_SUCCEEDED;
        $generation->output_text = $reply;
        $generation->save();

        return (new GenerationResource($generation->refresh()))->response()->setStatusCode(201);
    }

    /** Refund a run that did not produce its output, then save it as failed with the reason. */
    private function finishFailed(Generation $generation, string $error): JsonResponse
    {
        $this->wallet->refund($generation, 'Refunded: run failed');

        $generation->status = Generation::STATUS_FAILED;
        $generation->error = $error;
        $generation->save();

        return (new GenerationResource($generation->refresh()))->response()->setStatusCode(201);
    }
}
