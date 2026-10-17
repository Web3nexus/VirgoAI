<?php

namespace App\Skills;

use App\Ai\ModelDefaults;
use App\Models\Project;

/**
 * The skills a user can select. A skill is a named set of instructions the model follows, the kind of output it
 * makes, and sensible defaults. Selecting one changes what the AI is told to do, not just a label.
 *
 * mode: where the skill runs. full_video, image and audio open that tool; text opens the script tool (no media is
 * rendered, so nothing is charged for media); agent leaves the mode the user picked.
 */
final class SkillLibrary
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'agent' => [
                'label' => 'Agent Mode',
                'category' => 'Agent',
                'mode' => 'agent',
                'icon' => 'agent',
                'description' => 'Tell it what you want. It decides whether that is a video, an image or audio, and writes the brief.',
                'produces' => 'Writes a brief from your request, then makes what the mode you picked makes.',
                'instructions' => <<<'TXT'
You are a production agent. Read the request, then turn it into a clear, concrete brief. Keep the request's own facts,
names and intent. Do not add a product, a claim, a price or a person the request does not contain.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'video_generator' => [
                'label' => 'Video Generator',
                'category' => 'Video',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A complete short film in 3 to 6 scenes, with continuous characters and a clear beginning, middle and end.',
                'produces' => 'Full video, landscape 16:9, up to 6 scenes of 5 seconds (about 30 s).',
                'instructions' => <<<'TXT'
Write a production script for a short film of 3 to 6 scenes. Each scene shows one clear action in one place.
Keep every character's appearance identical from scene to scene: name the same clothes, hair and props each time.
Make the camera plan explicit (wide, medium, close-up; static or slow move). Give every scene a visible change so the
story moves forward. Do not use on-screen text, logos or captions inside the picture.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'image_generator' => [
                'label' => 'Image Generator',
                'category' => 'Image',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'One striking still: subject, setting, light, lens and colour, composed for the chosen frame.',
                'produces' => 'One still image, landscape 16:9.',
                'instructions' => <<<'TXT'
Describe one single still image. Name the subject, the setting, the time of day and the light source. Choose a camera
distance and a lens feel. Compose for the frame: leave room for the subject to breathe. Use at most one colour story.
Do not include readable text, logos or watermarks.
TXT,
                'defaults' => ['format' => 'landscape_16_9'],
            ],
            'audio_generator' => [
                'label' => 'Audio Generator',
                'category' => 'Audio',
                'mode' => 'text',
                'icon' => 'audio',
                'description' => 'A spoken narration written to be heard: short sentences, natural pauses, timed to the pace.',
                'produces' => 'A narration script to read aloud. No audio is generated.',
                'instructions' => <<<'TXT'
Write a narration to be spoken aloud. Use short sentences and natural breaths. Put a line break where a pause belongs.
No stage directions, no headings, no bullet points. Aim for about 150 words per minute of finished audio.
TXT,
                'defaults' => ['format' => 'landscape_16_9'],
            ],
            'ad_spot' => [
                'label' => 'Product Ad (15–30s)',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A short product ad: a hook in the first two seconds, one benefit, one clear call to action.',
                'produces' => 'Full video, square 1:1, 3 scenes of 5 seconds (about 15 s).',
                'instructions' => <<<'TXT'
Write a 15 to 30 second product advert in 3 scenes: a hook that shows the problem in the first two seconds, the product
solving it, and a closing frame with a single call to action. Show the product clearly in the middle scene. Use only the
benefits the request states. Do not invent prices, awards or testimonials.
TXT,
                'defaults' => ['format' => 'square_1_1', 'quality' => '720p'],
            ],
            'short_form' => [
                'label' => 'Short Vertical Video',
                'category' => 'Social',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A vertical short for phones: a strong first second, fast cuts, and a payoff at the end.',
                'produces' => 'Full video, vertical 9:16, 4 scenes of 5 seconds (about 20 s).',
                'instructions' => <<<'TXT'
Write a vertical short in 4 scenes for a phone screen. The first scene must grab attention within one second with a
visible action. Keep each scene to one idea. Put the payoff in the last scene. Frame subjects in the centre third.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'long_vertical' => [
                'label' => 'Long Vertical Video',
                'category' => 'Social',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A longer vertical video built as clean parts, so each part can be cut out as its own clip.',
                'produces' => 'Full video in parts, vertical 9:16, up to 6 scenes of 5 seconds (about 30 s). Each part can be cut as a clip.',
                'instructions' => <<<'TXT'
Write a longer vertical video for a phone screen in parts. Each part is one self-contained beat of 15 to 30 seconds with its
own hook in the first second and its own payoff at the end, so any part works as a clip on its own. Keep one idea per part.
Mark each part with a clear section id (part_1, part_2 and so on) and keep the same characters and setting across parts.
Frame subjects in the centre third of the frame.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'explainer' => [
                'label' => 'Explainer',
                'category' => 'Education',
                'mode' => 'full_video',
                'icon' => 'explainer',
                'description' => 'A short explainer that teaches one idea step by step, with a visual for each step.',
                'produces' => 'Full video, landscape 16:9, 5 scenes of 5 seconds (about 25 s).',
                'instructions' => <<<'TXT'
Explain one idea in 4 to 5 scenes, in this order: the question, the simple picture of it, the first step, the second
step, and the takeaway. Each scene shows the idea visually, not with text. Keep every claim to what the request says.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'music_video' => [
                'label' => 'Music Video',
                'category' => 'Music',
                'mode' => 'full_video',
                'icon' => 'music',
                'description' => 'Visuals that move with a song: a change of shot on each beat, a mood for each verse.',
                'produces' => 'Full video, landscape 16:9, 6 scenes of 5 seconds (about 30 s), 1080p by default.',
                'instructions' => <<<'TXT'
Build a music video in 4 to 6 scenes. Match the mood of each section of the song to a setting and a colour. Change the
shot on the strong beats. Keep the performer the same in every scene. Use movement that suits the tempo: slow and wide
for ballads, quick cuts for dance tracks.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '1080p'],
            ],
            'real_estate' => [
                'label' => 'Real Estate Tour',
                'category' => 'Real Estate',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A property walkthrough: the outside, the main rooms in the order a visitor walks them, and a closing view.',
                'produces' => 'Full video, landscape 16:9, 5 scenes of 5 seconds (about 25 s).',
                'instructions' => <<<'TXT'
Write a property walkthrough in 4 to 6 scenes. Open on the exterior or the entrance. Move through the main rooms in the
order a visitor would walk them, one room per scene. Close on the outside view or the best room. Use only the features the
request names (rooms, size, views). Do not invent a price, a floor area, a school district or a sale claim. Keep the
camera slow and steady: a dolly or a pan, never a fast spin.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'ugc_ad' => [
                'label' => 'UGC-Style Ad',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A handheld ad that looks shot on a phone by a real person showing the product in daily life.',
                'produces' => 'Full video, vertical 9:16, 4 scenes of 5 seconds (about 20 s).',
                'instructions' => <<<'TXT'
Write a short ad shot to look filmed on a phone by one real-looking person at home or out and about. Use handheld camera
moves and natural light. Scene 1 is a direct hook to camera. Scene 2 shows the product in use. Scene 3 shows the result.
Scene 4 is one call to action, shown on the product or in the presenter's hand. Keep the presenter identical in every
scene. Do not invent a testimonial, a rating, a before-and-after result or a health, money or safety claim.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'micro_drama' => [
                'label' => 'Micro Drama',
                'category' => 'Story',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'One episode of a vertical drama: a conflict in the first second, a reversal, and a cliffhanger.',
                'produces' => 'Full video, vertical 9:16, 6 scenes of 5 seconds (about 30 s), one episode.',
                'instructions' => <<<'TXT'
Write one episode of a vertical drama in 5 to 6 scenes. Open on the conflict within the first second. Put one reversal in
the middle. End on a cliffhanger that makes the next episode necessary. Use two or three characters at most, and keep
their faces and wardrobe the same in every scene. Use close-ups for emotion and keep each scene to one exchange. Do not
put subtitles or captions in the picture.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'brand_film' => [
                'label' => 'Brand Film',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A brand story told through a mood, real people at work and one closing line, rather than a feature list.',
                'produces' => 'Full video, landscape 16:9, 5 scenes of 5 seconds (about 25 s).',
                'instructions' => <<<'TXT'
Write a brand film in 4 to 5 scenes that shows what the brand stands for rather than listing features. Scene 1 sets a place
and a mood. Scenes 2 and 3 show people doing the work or using the product in a real setting. Scene 4 shows the result.
Close on a single line of brand copy. Use the colours and tone the request names. Do not add a claim, a statistic or an
award.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'film_trailer' => [
                'label' => 'Film Trailer',
                'category' => 'Story',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A cinematic teaser: the world, the hero, a rising threat, and one held final image.',
                'produces' => 'Full video, landscape 16:9, 6 scenes of 5 seconds (about 30 s).',
                'instructions' => <<<'TXT'
Write a cinematic trailer in 5 to 6 scenes. Begin with the world, then introduce the hero and the conflict. Build tension
with faster cuts, and end on one held image that is the strongest shot. Vary the scale: wide shots for the world,
close-ups for the hero. Keep the hero identical across scenes. Do not put titles, credits or on-screen text in the picture.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'promo_video' => [
                'label' => 'Promo Video',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A promotion for a product, event or offer: what it is, the benefit in use, and the one action to take.',
                'produces' => 'Full video, landscape 16:9, 4 scenes of 5 seconds (about 20 s).',
                'instructions' => <<<'TXT'
Write a promotional video in 4 scenes: the thing being promoted, the benefit in use, the proof the request states, and a
closing frame with the one action to take. Use only the offer, date and benefits the request contains. Do not invent a
price, a discount or a deadline.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'storyboard' => [
                'label' => 'Storyboard Only',
                'category' => 'Planning',
                'mode' => 'text',
                'icon' => 'storyboard',
                'description' => 'A frame-by-frame plan with camera, action and dialogue. Nothing is rendered, so nothing is charged for media.',
                'produces' => 'A numbered shot list of 6 to 12 frames. Text only; nothing is rendered.',
                'instructions' => <<<'TXT'
Write a numbered storyboard. For each frame give: the shot size, the camera move, the action, and the line spoken if any.
Keep it to 6 to 12 frames. Do not describe rendering or software.
TXT,
                'defaults' => ['format' => 'landscape_16_9'],
            ],
            'thumbnail' => [
                'label' => 'Thumbnail',
                'category' => 'Image',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'A thumbnail: one clear subject, high contrast, and at most four words of headline.',
                'produces' => 'One image, landscape 16:9, with no words in the picture.',
                'instructions' => <<<'TXT'
Design one thumbnail: a single clear subject with a strong expression, high contrast, and room on one side for a headline
of no more than four words. Do not put any words into the picture itself.
TXT,
                'defaults' => ['format' => 'landscape_16_9'],
            ],
        ];
    }

    /**
     * The guided form a Quick Start shows before it runs. Each field is one of:
     * text, textarea (max characters), choice (options), slider (min, max, step, default), reference (one image).
     * brief is the label the value is written under in the prompt sent to the model.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function quickStarts(): array
    {
        return [
            'real_estate' => [
                ['key' => 'property', 'type' => 'textarea', 'label' => 'Describe the property', 'brief' => 'Property', 'required' => true, 'max' => 1000, 'placeholder' => 'Rooms, size, outdoor space, views and the neighbourhood. Or paste the listing details.'],
                ['key' => 'preferences', 'type' => 'textarea', 'label' => 'Any preferences for your video?', 'brief' => 'Preferences', 'required' => false, 'max' => 500, 'placeholder' => 'e.g. Start with the exterior, focus on the kitchen and pool, and keep the pacing calm and inviting.'],
            ],
            'ad_spot' => [
                ['key' => 'product_image', 'type' => 'reference', 'label' => 'Product image', 'brief' => null, 'required' => true],
                ['key' => 'product_name', 'type' => 'text', 'label' => "What's it called?", 'brief' => 'Product', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Glow Lip Balm'],
                ['key' => 'benefits', 'type' => 'textarea', 'label' => 'Why is it worth buying?', 'brief' => 'Benefits', 'required' => true, 'max' => 300, 'placeholder' => 'Highlight key benefits, results and the feelings it delivers.'],
            ],
            'micro_drama' => [
                ['key' => 'characters', 'type' => 'textarea', 'label' => 'Who is in your story?', 'brief' => 'Characters', 'required' => true, 'max' => 800, 'placeholder' => 'Describe each character (up to four): age, look, wardrobe and role.'],
                ['key' => 'story', 'type' => 'textarea', 'label' => 'What happens in your story?', 'brief' => 'Story', 'required' => true, 'max' => 5000, 'placeholder' => 'e.g. A barista keeps finding cryptic notes hidden in coffee cups'],
                ['key' => 'vibe', 'type' => 'choice', 'label' => "What's the vibe?", 'brief' => 'Vibe', 'required' => false, 'default' => null, 'options' => ['romance' => 'Romance', 'thriller' => 'Thriller', 'comedy' => 'Comedy', 'horror' => 'Horror']],
            ],
            'explainer' => [
                ['key' => 'topic', 'type' => 'textarea', 'label' => 'What do you want to explain?', 'brief' => 'Topic', 'required' => true, 'max' => 500, 'placeholder' => 'e.g. How photosynthesis works, why sleep matters, or how to use our new feature…'],
                ['key' => 'audience', 'type' => 'text', 'label' => "Who's your audience?", 'brief' => 'Audience', 'required' => false, 'max' => 200, 'placeholder' => 'e.g. High school students, Professionals, New customers'],
                ['key' => 'style', 'type' => 'choice', 'label' => 'Visual style', 'brief' => 'Style', 'required' => true, 'default' => 'cinematic', 'options' => ['cinematic' => 'Cinematic', 'whiteboard' => 'Whiteboard', 'minimal' => 'Minimal', 'illustrated' => 'Illustrated']],
                ['key' => 'reference', 'type' => 'reference', 'label' => 'Add reference', 'brief' => null, 'required' => false],
            ],
            'short_form' => [
                ['key' => 'characters', 'type' => 'textarea', 'label' => 'Who is in your story?', 'brief' => 'Characters', 'required' => true, 'max' => 800, 'placeholder' => 'Describe the person on camera: age, look, wardrobe and voice.'],
                ['key' => 'topic', 'type' => 'textarea', 'label' => 'What are they talking about?', 'brief' => 'Talking about', 'required' => true, 'max' => 500, 'placeholder' => 'Your values, mission, and what makes you different…'],
                ['key' => 'vibe', 'type' => 'choice', 'label' => "What's the vibe?", 'brief' => 'Vibe', 'required' => false, 'default' => null, 'options' => ['casual' => 'Casual', 'funny' => 'Funny', 'inspiring' => 'Inspiring', 'chill' => 'Chill', 'luxury' => 'Luxury']],
            ],
            'video_generator' => [
                ['key' => 'story', 'type' => 'textarea', 'label' => "What's your film about?", 'brief' => 'Film', 'required' => true, 'max' => 5000, 'placeholder' => 'Write your story, a sense, or even a feeling…'],
                ['key' => 'style', 'type' => 'choice', 'label' => 'Visual style', 'brief' => 'Visual style', 'required' => false, 'default' => 'cinematic', 'options' => ['cinematic' => 'Cinematic', 'experimental' => 'Experimental', 'monochrome' => 'Monochrome', 'animated' => 'Animated', 'artistic' => 'Artistic']],
                // The pipeline makes at most 6 scenes of 5 seconds, so the longest film is 30 seconds (DEC-0015).
                ['key' => 'length', 'type' => 'slider', 'label' => 'How long should it be?', 'brief' => 'Length', 'required' => false, 'min' => 10, 'max' => 30, 'step' => 5, 'default' => 30, 'unit' => 'seconds'],
                ['key' => 'reference', 'type' => 'reference', 'label' => 'Add reference', 'brief' => null, 'required' => false],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function inputs(?string $key): array
    {
        return $key === null ? [] : (self::quickStarts()[$key] ?? []);
    }

    /**
     * The errors in a Quick Start's answers, keyed by field. Reference images are checked by the caller, which owns the upload.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public static function validateInputs(string $key, array $values): array
    {
        $errors = [];

        foreach (self::inputs($key) as $field) {
            if ($field['type'] === 'reference') {
                continue;
            }

            $value = self::valueOf($field, $values);
            $empty = $value === null || trim((string) $value) === '';

            if ($empty) {
                if ($field['required']) {
                    $errors[$field['key']] = $field['label'].' is required.';
                }

                continue;
            }

            if ($field['type'] === 'choice' && ! array_key_exists((string) $value, $field['options'])) {
                $errors[$field['key']] = $field['label'].' must be one of the options.';
            } elseif ($field['type'] === 'slider' && (! is_numeric($value) || $value < $field['min'] || $value > $field['max'])) {
                $errors[$field['key']] = $field['label'].' must be between '.$field['min'].' and '.$field['max'].'.';
            } elseif (in_array($field['type'], ['text', 'textarea'], true) && mb_strlen(trim((string) $value)) > $field['max']) {
                $errors[$field['key']] = $field['label'].' must be '.$field['max'].' characters or fewer.';
            }
        }

        return $errors;
    }

    /**
     * The prompt a Quick Start run is made from: each answer written under its brief label, in form order.
     * Answers that are empty (and were not required) are left out.
     *
     * @param  array<string, mixed>  $values
     */
    public static function compose(string $key, array $values): string
    {
        $lines = [];

        foreach (self::inputs($key) as $field) {
            $value = self::valueOf($field, $values);
            if ($field['brief'] === null || $value === null || trim((string) $value) === '') {
                continue;
            }

            $text = match ($field['type']) {
                'choice' => $field['options'][(string) $value],
                'slider' => $value.' '.$field['unit'],
                default => trim((string) $value),
            };

            $lines[] = $field['brief'].': '.$text;
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $field  @param  array<string, mixed>  $values */
    private static function valueOf(array $field, array $values): mixed
    {
        return $values[$field['key']] ?? $field['default'] ?? null;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? self::all()['agent'];
    }

    /**
     * The instructions the model is given for a selected skill. Empty when no skill is selected.
     * It is placed after any format the system prompt already sets, so a JSON contract still wins.
     */
    public static function brief(?string $key): string
    {
        if ($key === null || ! self::exists($key)) {
            return '';
        }

        $skill = self::get($key);

        $format = Project::FORMATS[$skill['defaults']['format'] ?? Project::DEFAULT_FORMAT];

        return implode("\n", [
            'Skill: '.$skill['label'].'.',
            'Produces: '.$skill['produces'],
            'Format: '.$format['label'].' ('.$format['width'].' x '.$format['height'].' pixels). Compose every frame for this shape.',
            $skill['instructions'],
            'If earlier instructions set a reply format, keep that format.',
        ]);
    }

    /**
     * The skills a search and a category filter return, without the instructions (those are for the model).
     *
     * @return list<array<string, mixed>>
     */
    public static function search(?string $query = null, ?string $category = null): array
    {
        $query = $query === null ? '' : mb_strtolower(trim($query));

        return collect(self::all())
            ->map(fn (array $skill, string $key) => [
                'key' => $key,
                'label' => $skill['label'],
                'category' => $skill['category'],
                'mode' => $skill['mode'],
                'icon' => $skill['icon'],
                'description' => $skill['description'],
                'produces' => $skill['produces'],
                'format' => $skill['defaults']['format'] ?? Project::DEFAULT_FORMAT,
                'defaults' => $skill['defaults'],
                'capability' => ModelDefaults::capabilityFor($skill['mode']),
                'default_model' => ModelDefaults::defaultFor(ModelDefaults::capabilityFor($skill['mode'])),
                'inputs' => self::inputs($key),
            ])
            ->filter(fn (array $skill) => $category === null || $category === '' || $skill['category'] === $category)
            ->filter(fn (array $skill) => $query === '' || str_contains(mb_strtolower($skill['label'].' '.$skill['description']), $query))
            ->values()
            ->all();
    }
}
