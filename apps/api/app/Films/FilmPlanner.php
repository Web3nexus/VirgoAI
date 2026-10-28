<?php

namespace App\Films;

use App\Ai\Data\GenerationRequest;
use App\Ai\GenerationService;
use App\Ai\Guardrails;
use App\Billing\PriceQuoter;
use App\Billing\Quote;
use App\Billing\UsageRecorder;
use App\Enums\Capability;
use App\Models\Film;
use App\Productions\ProductionService;
use App\Styles\StylePresets;
use DomainException;

/**
 * The director. It writes the story bible, then one chapter at a time. Every chapter call is given the bible and the
 * memory the chapter before it left behind, so the story continues instead of restarting. Replies are checked before
 * they are used, and a reply that breaks the rules is asked for once more.
 */
final class FilmPlanner
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{1,31}$/';

    public const BIBLE_SYSTEM = <<<'TXT'
You are the director and writer of a film made from generated video. Plan the whole film before any shot is made.
Reply with JSON only, in this exact shape:
{"title":"Short title","logline":"One sentence","visual_style":"The look of the whole film in one sentence","tone":"One word or short phrase",
"cast":[{"key":"mia","name":"Mia","look":"Age, face, hair, outfit and what stays the same in every shot (at least 20 words)"}],
"locations":[{"key":"kitchen","name":"The kitchen","look":"What the place looks like, its light and its fixed details (at least 20 words)"}],
"story":"The complete story outline: a beginning, a middle and an end, paced for the stated length, in short paragraphs"}
Rules: keys are lowercase letters, digits and underscores, starting with a letter. Give each cast member and location a single key.
Keep every person and place consistent with the look you give them. No real public figures, no brand names, no copyrighted characters, songs or lines.
TXT;

    public const CHAPTER_SYSTEM = <<<'TXT'
You direct one chapter of a film. You are given the story bible, the memory the earlier chapters left, and a numbered
list of takes with their lengths. Write what the camera sees and hears in every take.
Reply with JSON only, in this exact shape:
{"summary":"What happens in this chapter, in 2 to 4 sentences",
"memory":"The state at the end of this chapter, in 120 to 250 words: where each person is, what they know, what has changed, and the open threads",
"takes":[{"n":1,"cast":["mia"],"location":"kitchen","action":"What happens on screen, in one or two sentences","dialogue":[{"speaker":"mia","line":"What is said"}]}]}
Rules: exactly one entry in takes for each take given, in the same order, with the same n. "cast" holds keys from the cast list only,
up to 4 per take. "location" is a key from the locations list. Each dialogue speaker is a key from that take's cast.
Keep dialogue short enough to be spoken in the take's length, about two and a half words per second. No real public figures,
no brand names, no copyrighted characters, songs or lines.
TXT;

    public function __construct(
        protected GenerationService $generations,
        protected PriceQuoter $quotes,
        protected UsageRecorder $usage,
    ) {}

    /**
     * @return array{bible: array<string, mixed>, quote: Quote}
     */
    public function bible(Film $film): array
    {
        $message = [
            'brief' => $film->brief,
            'length_seconds' => $film->length_seconds,
            'style_choices' => StylePresets::directive((array) $film->styles),
        ];

        $call = $this->ask(self::BIBLE_SYSTEM, $message, $film, 'film_bible');

        return ['bible' => $this->checkBible($call['json']), 'quote' => $call['quote']];
    }

    /**
     * @param  array<string, mixed>  $bible
     * @param  list<array{number: int, seconds: int}>  $takes
     * @param  list<array{index: int, summary: string, memory: string}>  $earlier
     * @return array{summary: string, memory: string, takes: list<array{cast: list<string>, location: string, action: string, dialogue: list<array{speaker: string, line: string}>}>, quote: Quote}
     */
    public function chapter(Film $film, array $bible, int $index, int $chapters, array $takes, array $earlier): array
    {
        $message = [
            'story_bible' => [
                'title' => $bible['title'],
                'visual_style' => $bible['visual_style'],
                'tone' => $bible['tone'],
                'story' => $bible['story'],
                'cast' => $bible['cast'],
                'locations' => $bible['locations'],
            ],
            'chapter' => ['index' => $index, 'of' => $chapters],
            'memory_so_far' => $earlier === [] ? 'None. This is the opening chapter.' : array_map(
                fn (array $e) => ['chapter' => $e['index'], 'summary' => $e['summary'], 'memory' => $e['memory']],
                $earlier,
            ),
            'takes' => $takes,
        ];

        $allowedCast = array_keys($bible['cast']);
        $allowedLocations = array_keys($bible['locations']);
        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $call = $this->ask(self::CHAPTER_SYSTEM, $message + ($lastError ? ['previous_reply_problem' => $lastError] : []), $film, 'film_chapter');

                return ['quote' => $call['quote'], ...$this->checkChapter($call['json'], $takes, $allowedCast, $allowedLocations)];
            } catch (DomainException $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new DomainException('The director could not write chapter '.$index.': '.$lastError);
    }

    /**
     * Ask the language model for one JSON reply, and settle its cost. The cost is added to the film the moment the
     * reply arrives, before it is checked, so a reply that is thrown away is still paid for.
     *
     * @param  array<string, mixed>  $message
     * @return array{json: array<string, mixed>, quote: Quote}
     */
    private function ask(string $system, array $message, Film $film, string $stage): array
    {
        $result = $this->generations->generate(new GenerationRequest(
            capability: Capability::Text,
            modelKey: '',
            prompt: (string) json_encode($message, JSON_UNESCAPED_UNICODE),
            options: ['system' => Guardrails::withText($system)],
            requestedModelKey: ProductionService::TEXT_MODEL,
        ));

        if (! $result->ok) {
            throw new DomainException($result->error ?? 'The director could not reply.');
        }

        $quote = $this->quotes->settleText('anthropic', ProductionService::TEXT_MODEL, $result->usage) ?? Quote::free();

        $film->forceFill([
            'planning_price' => (int) $film->planning_price + $quote->priceMicros,
            'planning_cost' => (int) $film->planning_cost + $quote->costMicros,
        ])->save();

        $this->usage->record($film->user, UsageRecorder::TYPE_FILM, $film->id, $stage, 'anthropic', ProductionService::TEXT_MODEL, $quote, [
            'input' => (int) ($result->usage['input_tokens'] ?? 0),
            'output' => (int) ($result->usage['output_tokens'] ?? 0),
            'units' => 1,
            'unit_label' => $stage,
        ]);

        $json = $this->parseJson((string) ($result->output['text'] ?? ''));

        if ($json === null) {
            throw new DomainException('The director replied in the wrong form. Try the film again.');
        }

        return ['json' => $json, 'quote' => $quote];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function checkBible(array $data): array
    {
        $cast = $this->keyed($data['cast'] ?? null, (int) config('virgoai.films.max_cast', 6), 'cast');
        $locations = $this->keyed($data['locations'] ?? null, (int) config('virgoai.films.max_locations', 5), 'locations');

        // A cast member and a location share one reference lookup, so their keys must differ.
        if (array_intersect_key($cast, $locations) !== []) {
            throw new DomainException('A cast member and a location cannot share a key. Try the film again.');
        }

        foreach (['title', 'visual_style', 'tone', 'story'] as $field) {
            if (! is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                throw new DomainException("The director did not write the {$field}. Try the film again.");
            }
        }

        if (mb_strlen((string) $data['story']) < 100) {
            throw new DomainException('The director wrote too short a story. Try the film again.');
        }

        return [
            'title' => mb_substr(trim((string) $data['title']), 0, 200),
            'visual_style' => trim((string) $data['visual_style']),
            'tone' => trim((string) $data['tone']),
            'story' => trim((string) $data['story']),
            'cast' => $cast,
            'locations' => $locations,
        ];
    }

    /**
     * @param  list<array{number: int, seconds: int}>  $takes
     * @param  list<string>  $allowedCast
     * @param  list<string>  $allowedLocations
     * @return array{summary: string, memory: string, takes: list<array{cast: list<string>, location: string, action: string, dialogue: list<array{speaker: string, line: string}>}>}
     */
    private function checkChapter(array $data, array $takes, array $allowedCast, array $allowedLocations): array
    {
        foreach (['summary', 'memory'] as $field) {
            if (! is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                throw new DomainException("The chapter has no {$field}.");
            }
        }

        $replies = is_array($data['takes'] ?? null) ? array_values($data['takes']) : [];

        if (count($replies) !== count($takes)) {
            throw new DomainException('The chapter has '.count($replies).' takes but '.count($takes).' were asked for.');
        }

        $checked = [];

        foreach ($takes as $i => $take) {
            $reply = $replies[$i];

            if (! is_array($reply) || ($reply['n'] ?? null) !== $take['number']) {
                throw new DomainException("Take {$take['number']} is missing or out of order.");
            }

            $cast = is_array($reply['cast'] ?? null) ? array_values(array_unique($reply['cast'])) : [];
            if ($cast === [] || count($cast) > TakePrompt::MAX_CAST_PER_TAKE || array_diff($cast, $allowedCast) !== []) {
                throw new DomainException("Take {$take['number']} uses cast that is not in the bible.");
            }

            $location = (string) ($reply['location'] ?? '');
            if (! in_array($location, $allowedLocations, true)) {
                throw new DomainException("Take {$take['number']} uses a location that is not in the bible.");
            }

            $dialogue = [];
            foreach ((array) ($reply['dialogue'] ?? []) as $line) {
                $speaker = (string) ($line['speaker'] ?? '');
                $text = trim((string) ($line['line'] ?? ''));

                if ($text === '') {
                    continue;
                }
                if (! in_array($speaker, $cast, true)) {
                    throw new DomainException("Take {$take['number']} has a line from someone who is not in the take.");
                }

                $dialogue[] = ['speaker' => $speaker, 'line' => $text];
            }

            $checked[] = [
                'cast' => $cast,
                'location' => $location,
                'action' => trim((string) ($reply['action'] ?? '')) ?: 'The scene continues.',
                'dialogue' => $dialogue,
            ];
        }

        return ['summary' => trim((string) $data['summary']), 'memory' => trim((string) $data['memory']), 'takes' => $checked];
    }

    /**
     * @return array<string, array{name: string, look: string}>
     */
    private function keyed(mixed $items, int $max, string $label): array
    {
        if (! is_array($items) || $items === [] || count($items) > $max) {
            throw new DomainException("The director must plan between 1 and {$max} {$label}.");
        }

        $keyed = [];

        foreach ($items as $item) {
            $key = (string) ($item['key'] ?? '');
            $name = trim((string) ($item['name'] ?? ''));
            $look = trim((string) ($item['look'] ?? ''));

            if (preg_match(self::KEY_PATTERN, $key) !== 1 || isset($keyed[$key])) {
                throw new DomainException("The director gave a {$label} entry an invalid or repeated key.");
            }
            if ($name === '' || mb_strlen($look) < 20) {
                throw new DomainException("The director did not describe a {$label} entry properly.");
            }

            $keyed[$key] = ['name' => mb_substr($name, 0, 120), 'look' => $look];
        }

        return $keyed;
    }

    /** @return array<string, mixed>|null */
    private function parseJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }
}
