<?php

namespace App\Skills;

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
            ])
            ->filter(fn (array $skill) => $category === null || $category === '' || $skill['category'] === $category)
            ->filter(fn (array $skill) => $query === '' || str_contains(mb_strtolower($skill['label'].' '.$skill['description']), $query))
            ->values()
            ->all();
    }
}
