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
            'anime_story' => [
                'label' => 'AI Anime Story',
                'category' => 'Story',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A short story in a 2D anime style: bold linework, expressive faces and vivid, hand-painted backgrounds.',
                'produces' => 'Full video, landscape 16:9, up to 6 scenes of 5 seconds (about 30 s), in anime style.',
                'instructions' => <<<'TXT'
Write a short anime story of 3 to 6 scenes. Keep one consistent anime look throughout: the same character designs,
hair, outfits and colour palette in every scene. Use expressive close-ups for emotion and wide shots for setting. Each
scene needs one clear action and a visible change, so the story moves forward. Do not put on-screen text or captions in the picture.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'fashion_try_on' => [
                'label' => 'Fashion Try On',
                'category' => 'Marketing',
                'mode' => 'image',
                'icon' => 'marketing',
                'description' => 'Put a garment on a person from your photo: the same face, pose and lighting, wearing the new outfit.',
                'produces' => 'One image, portrait 4:5, the person from your photo wearing the garment.',
                'instructions' => <<<'TXT'
Edit the reference photo so the person wears the garment the request describes. Keep the person's face, body, pose and
hair exactly as they are. Match the garment's fit, fabric and colour to the description, and keep the original lighting
and background. Do not add text, logos or a brand name to the garment.
TXT,
                'defaults' => ['format' => 'portrait_4_5'],
            ],
            'poster' => [
                'label' => 'Poster',
                'category' => 'Branding Assets',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'A poster with one strong image and one message, composed for a clear read from a distance.',
                'produces' => 'One image, portrait 4:5, with no words in the picture.',
                'instructions' => <<<'TXT'
Design one poster: a single bold focal image, a clear composition with generous negative space, and a palette of no more
than three colours. Leave an open area where the message would sit. Do not write any words into the picture itself.
TXT,
                'defaults' => ['format' => 'portrait_4_5'],
            ],
            'logo' => [
                'label' => 'Logo',
                'category' => 'Branding Assets',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'A simple, flat logo mark on a plain background, built to still read at small sizes.',
                'produces' => 'One image, square 1:1, a logo mark on a plain background.',
                'instructions' => <<<'TXT'
Design one logo mark: a simple, flat symbol with no more than three colours, on a plain light background. Keep the shape
bold and clear enough to read at favicon size. Do not add a name or any letters unless the request asks for them.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'logo_3d' => [
                'label' => '3D Logo',
                'category' => 'Branding Assets',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'A logo mark rendered as a 3D object with soft studio lighting and believable material.',
                'produces' => 'One image, square 1:1, a 3D logo mark on a plain background.',
                'instructions' => <<<'TXT'
Render one logo mark as a 3D object: a single solid form with a clear material, soft studio lighting and a gentle shadow.
Keep the form simple enough to read at a glance. Use a plain background. Do not add text or letters unless the request asks for them.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'product_listing' => [
                'label' => 'Product Listing',
                'category' => 'Branding Assets',
                'mode' => 'image',
                'icon' => 'marketing',
                'description' => 'A clean product photo for a listing: the product alone, sharp, centred and evenly lit.',
                'produces' => 'One image, square 1:1, the product on a clean background.',
                'instructions' => <<<'TXT'
Create one product listing photo of the product in the reference image. Show the whole product, centred, on a clean
seamless background with even, soft light. Keep the product's shape, colour and label unchanged. Do not add text,
badges, prices or props that compete with the product.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'wes_anderson' => [
                'label' => 'Wes Anderson Style',
                'category' => 'Creative Storytelling',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A story told in Wes Anderson\'s style: symmetrical frames, pastel palettes and deadpan staging.',
                'produces' => 'Full video, landscape 16:9, up to 6 scenes of 5 seconds (about 30 s), in a symmetrical pastel style.',
                'instructions' => <<<'TXT'
Write a short film of 3 to 6 scenes in the style of Wes Anderson. Frame each shot symmetrically, with the subject centred.
Use a pastel palette, flat even light, and deadpan, precise staging. Keep the same characters, costumes and set in every scene.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'character_story_video' => [
                'label' => 'Character Story Video',
                'category' => 'Creative Storytelling',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A story video that keeps one character looking the same across every scene.',
                'produces' => 'Full video, landscape 16:9, up to 6 scenes of 5 seconds (about 30 s), with one consistent character.',
                'instructions' => <<<'TXT'
Write a story of 3 to 6 scenes built around one main character. Describe the character's face, hair, outfit and props once,
then repeat them identically in every scene. Each scene shows one clear action and a visible change in the story.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'comic_drama' => [
                'label' => 'Comic Drama',
                'category' => 'Creative Storytelling',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A comic-panel drama: a script turned into a 3 by 3 comic page and a 15-second episode.',
                'produces' => 'Full video, landscape 16:9, 3 scenes of 5 seconds (about 15 s), in comic-panel style.',
                'instructions' => <<<'TXT'
Turn the request into a 15-second dramatic episode of 3 scenes, staged like comic panels: strong inked outlines, bold
colour blocks and a clear panel-by-panel reveal. Keep each character identical from panel to panel.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'character_identity_pack' => [
                'label' => 'Character Identity Pack',
                'category' => 'Creative Storytelling',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'An original character introduced on camera: a master look, then the same person in a short clip.',
                'produces' => 'Full video, landscape 16:9, 3 scenes of 5 seconds (about 15 s), presenting one original character.',
                'instructions' => <<<'TXT'
Design one original character from the request and introduce them in 3 scenes: a clear front portrait, a turn to show
the costume, and a short line of action. Keep the same face, hair, costume and colours in every scene.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'character_ip' => [
                'label' => 'Character IP',
                'category' => 'Image',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'An original character with a distinct look, personality and visual world, shown as one hero still.',
                'produces' => 'One image, square 1:1, an original character on a clean background.',
                'instructions' => <<<'TXT'
Draw one original character as a hero still: a distinct silhouette, a signature colour and one memorable detail. Show the
character in a three-quarter view on a clean, simple background. Do not add text, logos or a copied franchise look.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'ai_influencer' => [
                'label' => 'AI Influencer',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A presenter who speaks to camera in their own setting, built from the look you describe.',
                'produces' => 'Full video, vertical 9:16, 4 scenes of 5 seconds (about 20 s), one presenter.',
                'instructions' => <<<'TXT'
Create a short vertical video of one presenter speaking to camera in 4 scenes: a hook, two points, and a call to action.
Keep the presenter's look, outfit and setting identical throughout. Do not invent claims, prices or testimonials.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'faceless_reels' => [
                'label' => 'Faceless Reels',
                'category' => 'Social',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A vertical reel built from visuals alone, with no person on camera: b-roll, motion and a narration line per scene.',
                'produces' => 'Full video, vertical 9:16, 4 scenes of 5 seconds (about 20 s), with no on-camera person.',
                'instructions' => <<<'TXT'
Write a vertical reel of 4 scenes built from b-roll and motion graphics only. Do not show a person's face. Each scene
has one visual idea and one short line of narration. Open with a hook in the first two seconds.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'social_hook' => [
                'label' => 'Social Hook',
                'category' => 'Social',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A short clip built to stop the scroll: a strong opening in the first second, then one payoff.',
                'produces' => 'Full video, vertical 9:16, 3 scenes of 5 seconds (about 15 s).',
                'instructions' => <<<'TXT'
Write a 15-second social clip of 3 scenes. The first second must show something surprising or unresolved, the middle
develops it, and the last scene pays it off. Keep the frame simple and the motion fast.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'motion_design_ad' => [
                'label' => 'Motion Design Ad',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A motion-graphics ad: shapes, type-like graphic overlays and clean transitions around one message.',
                'produces' => 'Full video, landscape 16:9, 3 scenes of 5 seconds (about 15 s), in a motion-design style.',
                'instructions' => <<<'TXT'
Write a motion-design advert of 3 scenes: a graphic opening, one message shown as bold graphic shapes, and a closing
brand frame. Keep the palette to three colours and use clean, timed transitions. Do not add readable words in the picture.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'product_launch_film' => [
                'label' => 'Product Launch Film',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A launch film for a product, SaaS or page: the reveal, the key feature and the call to action.',
                'produces' => 'Full video, landscape 16:9, 5 scenes of 5 seconds (about 25 s).',
                'instructions' => <<<'TXT'
Write a product launch film of 5 scenes: a teaser, the reveal, the key feature in action, one proof point, and the call
to action. Use only the product facts the request states. Do not invent features, numbers or customers.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'ad_recreator' => [
                'label' => 'Ad Recreator',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'Takes the persuasion structure of a reference ad and makes one original ad for your product.',
                'produces' => 'Full video, square 1:1, 3 scenes of 5 seconds (about 15 s), with the reference\'s structure.',
                'instructions' => <<<'TXT'
Keep the persuasion structure of the reference ad: hook, problem, reveal and call to action. Write an original ad for
the product in the request, with its own scenes, look and wording. Do not copy the reference ad's shots or claims.
TXT,
                'defaults' => ['format' => 'square_1_1', 'quality' => '720p'],
            ],
            'product_360' => [
                'label' => 'Product 360° Showcase',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A product still turned into a 360° turntable and a macro close-up.',
                'produces' => 'Full video, square 1:1, 3 scenes of 5 seconds (about 15 s), a turntable and macro shots.',
                'instructions' => <<<'TXT'
Show the product from the reference image on a slow 360 degree turntable, then a macro close-up of its material and one
detail. Keep the product's shape, colour and label unchanged on a clean studio background.
TXT,
                'defaults' => ['format' => 'square_1_1', 'quality' => '720p'],
            ],
            'cinematic_ad_vignette' => [
                'label' => 'Cinematic Ad Vignette',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'One cinematic product shot with atmospheric light and slow movement, as a short ad.',
                'produces' => 'Full video, square 1:1, 2 scenes of 5 seconds (about 10 s), one cinematic product shot.',
                'instructions' => <<<'TXT'
Film the product from the reference image in one or two cinematic shots with atmospheric light, a slow camera move and
shallow focus. Keep the product unchanged. Do not add text or prices.
TXT,
                'defaults' => ['format' => 'square_1_1', 'quality' => '720p'],
            ],
            'ugc_product_video' => [
                'label' => 'UGC Product Video',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A person holding the product in a handheld, lifestyle video, shot the way creators shoot on a phone.',
                'produces' => 'Full video, vertical 9:16, 3 scenes of 5 seconds (about 15 s), handheld lifestyle style.',
                'instructions' => <<<'TXT'
Create a handheld, phone-style lifestyle video of one person showing the product from the reference image in 3 scenes:
a hook, the product in use, and a closing line to camera. Keep the product's look unchanged. Do not add prices or claims.
TXT,
                'defaults' => ['format' => 'vertical_9_16', 'quality' => '720p'],
            ],
            'jewelry_product_video' => [
                'label' => 'Jewelry Product Video',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A luxury jewellery film: slow reveals, sparkle and sharp macro detail on a dark background.',
                'produces' => 'Full video, landscape 16:9, 3 scenes of 5 seconds (about 15 s), in a luxury macro style.',
                'instructions' => <<<'TXT'
Film the jewellery in 3 slow macro scenes on a dark background: a reveal, the stone or metal catching light, and a final
wide shot. Keep the piece's design and colour exactly as described. Do not add text or prices.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'future_tech_product_ad' => [
                'label' => 'Future Tech Product Ad',
                'category' => 'Marketing',
                'mode' => 'full_video',
                'icon' => 'marketing',
                'description' => 'A high-energy futuristic ad for an electronics product: neon light, cinematic motion and a hero reveal.',
                'produces' => 'Full video, landscape 16:9, 3 scenes of 5 seconds (about 15 s), futuristic and cinematic.',
                'instructions' => <<<'TXT'
Make a 15-second futuristic product ad of 3 scenes: a dark opening, a hero reveal of the product from the reference image
lit with neon and cool light, and a final wide shot. Keep the product unchanged. Do not add readable text.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            '3d_logo_animation' => [
                'label' => '3D Logo Animation',
                'category' => 'Branding Assets',
                'mode' => 'full_video',
                'icon' => 'video',
                'description' => 'A flat 2D logo turned into a 3D mark, then revealed with a cinematic animation.',
                'produces' => 'Full video, landscape 16:9, 2 scenes of 5 seconds (about 10 s), a 3D logo reveal.',
                'instructions' => <<<'TXT'
Turn the logo in the reference image into a dimensional 3D mark, then reveal it in 2 scenes: an empty studio, then the
logo rising into the light with a soft shadow. Keep the logo's shape and colours exactly as in the reference.
TXT,
                'defaults' => ['format' => 'landscape_16_9', 'quality' => '720p'],
            ],
            'ai_storyboard' => [
                'label' => 'AI Storyboard',
                'category' => 'Planning',
                'mode' => 'image',
                'icon' => 'storyboard',
                'description' => 'Concept frames that map a story before you shoot: four cinematic frames on one sheet.',
                'produces' => 'One image, landscape 16:9, a sheet of four cinematic frames with no words.',
                'instructions' => <<<'TXT'
Lay out one storyboard sheet of four cinematic frames in a 2 by 2 grid. Each frame shows one beat of the story: the
setting, the subject and the camera distance. Keep the look consistent across the frames. Do not write any words on the sheet.
TXT,
                'defaults' => ['format' => 'landscape_16_9'],
            ],
            'interior_design_visualizer' => [
                'label' => 'Interior Design Visualizer',
                'category' => 'Image',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'A room turned into a furnished, photorealistic interior in the style you describe.',
                'produces' => 'One image, landscape 16:9, a furnished photorealistic interior.',
                'instructions' => <<<'TXT'
Render one furnished, photorealistic interior in the style the request describes, with believable materials and soft
natural light. If a reference room is given, keep its walls, windows and layout. Do not add text or logos.
TXT,
                'defaults' => ['format' => 'landscape_16_9'],
            ],
            'dramatic_angle_gallery' => [
                'label' => 'Dramatic Angle Gallery',
                'category' => 'Image',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'One subject shot from a dramatic camera angle, as a striking still.',
                'produces' => 'One image, square 1:1, a subject from a dramatic angle.',
                'instructions' => <<<'TXT'
Show the subject from one dramatic camera angle: low, high or tilted, with strong perspective and clear light. Keep the
subject faithful to the description or reference. Do not include readable text or logos.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'image_kit' => [
                'label' => 'Image Kit',
                'category' => 'Image',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'One reference image expanded into a visual kit that keeps the same style, colour and look.',
                'produces' => 'One image, square 1:1, a single frame in the same style as the reference.',
                'instructions' => <<<'TXT'
Create one new image in the exact style, colour palette and lighting of the reference image. Keep the look consistent,
and vary only the subject or composition the request asks for. Do not add text or logos.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'campaign_launch_pack' => [
                'label' => 'Campaign Launch Pack',
                'category' => 'Marketing',
                'mode' => 'image',
                'icon' => 'marketing',
                'description' => 'A launch image for a product name and campaign message, with the product as the hero.',
                'produces' => 'One image, portrait 4:5, the product as the hero of a launch visual.',
                'instructions' => <<<'TXT'
Create one launch visual with the product as the hero, the campaign message implied through mood and light rather
than written text, and generous space for the headline. Do not write any words into the picture.
TXT,
                'defaults' => ['format' => 'portrait_4_5'],
            ],
            'amazon_four_image_set' => [
                'label' => 'Amazon Four-Image Set',
                'category' => 'Branding Assets',
                'mode' => 'image',
                'icon' => 'marketing',
                'description' => 'A product photo turned into four Amazon-style stills: a pure white hero and three lifestyle views.',
                'produces' => 'One image, square 1:1, a pure white product hero for an Amazon listing.',
                'instructions' => <<<'TXT'
Create one Amazon-style product still: the product alone on a pure white background, centred, evenly lit, with its
shape and label unchanged. Do not add text, badges or props.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'amazon_listing_images' => [
                'label' => 'Amazon Listing Images',
                'category' => 'Branding Assets',
                'mode' => 'image',
                'icon' => 'marketing',
                'description' => 'A guided Amazon listing photo of the product: clean, centred and evenly lit.',
                'produces' => 'One image, square 1:1, a clean listing photo of the product.',
                'instructions' => <<<'TXT'
Create one clean Amazon listing photo of the product in the reference image on a seamless light background, evenly lit,
with the product fully in frame. Keep its shape, colour and label unchanged. Do not add text or badges.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'packaging_system' => [
                'label' => 'Packaging System',
                'category' => 'Branding Assets',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'On-brand packaging for a product: a box and its surroundings in one consistent style.',
                'produces' => 'One image, square 1:1, on-brand packaging for the product.',
                'instructions' => <<<'TXT'
Show one packaging design for the product: a box in a consistent brand style, with its colour palette and graphic
language. Keep the product's own colours from the reference. Do not add readable brand names or prices.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'meta_ad_creative_images' => [
                'label' => 'Meta Ad Creative Images',
                'category' => 'Marketing',
                'mode' => 'image',
                'icon' => 'marketing',
                'description' => 'A product photo placed in a social ad frame, composed for the feed.',
                'produces' => 'One image, square 1:1, a product in a feed-ready ad frame.',
                'instructions' => <<<'TXT'
Place the product from the reference image in a feed-ready ad frame: the product large and clear, with calm space for a
headline. Keep the product unchanged. Do not write any words into the picture.
TXT,
                'defaults' => ['format' => 'square_1_1'],
            ],
            'design_moodboard' => [
                'label' => 'Design Moodboard',
                'category' => 'Image',
                'mode' => 'image',
                'icon' => 'image',
                'description' => 'A brief turned into one moodboard image: colour, texture and light that set the direction.',
                'produces' => 'One image, landscape 16:9, a moodboard of colour, texture and light.',
                'instructions' => <<<'TXT'
Create one moodboard sheet for the brief: a palette of three colours, two textures, and one light study, arranged as a
clean collage. Do not write any words on the sheet.
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
        $productPhoto = ['key' => 'product_image', 'type' => 'reference', 'label' => 'Product photo', 'brief' => null, 'required' => true];
        $productName = ['key' => 'product_name', 'type' => 'text', 'label' => "What's it called?", 'brief' => 'Product', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Glow Lip Balm'];

        return [
            'product_360' => [$productPhoto],
            'cinematic_ad_vignette' => [$productPhoto],
            'ugc_product_video' => [$productPhoto],
            'future_tech_product_ad' => [$productPhoto],
            'jewelry_product_video' => [$productPhoto],
            'ad_recreator' => [['key' => 'reference_ad', 'type' => 'reference', 'label' => 'Reference ad', 'brief' => null, 'required' => true], $productName],
            '3d_logo_animation' => [['key' => 'logo_image', 'type' => 'reference', 'label' => 'Logo', 'brief' => null, 'required' => true]],
            'image_kit' => [['key' => 'reference_image', 'type' => 'reference', 'label' => 'Reference image', 'brief' => null, 'required' => true]],
            'amazon_four_image_set' => [$productName],
            'amazon_listing_images' => [$productPhoto, $productName],
            'packaging_system' => [$productPhoto],
            'meta_ad_creative_images' => [$productPhoto],
            'campaign_launch_pack' => [$productName, ['key' => 'message', 'type' => 'textarea', 'label' => 'What is the campaign saying?', 'brief' => 'Message', 'required' => true, 'max' => 300, 'placeholder' => 'e.g. Summer skin, ready for the heat']],
            'product_launch_film' => [['key' => 'brief', 'type' => 'textarea', 'label' => 'Product URL, launch script, or what it does', 'brief' => 'Product', 'required' => true, 'max' => 1500, 'placeholder' => 'Paste a product page link, your launch script, or describe the product in a few lines.']],
            'anime_story' => [
                ['key' => 'story', 'type' => 'textarea', 'label' => "What's your anime story about?", 'brief' => 'Story', 'required' => true, 'max' => 5000, 'placeholder' => 'A girl finds a map that leads to a city under the sea…'],
                ['key' => 'length', 'type' => 'slider', 'label' => 'How long should it be?', 'brief' => 'Length', 'required' => false, 'min' => 10, 'max' => 30, 'step' => 5, 'default' => 30, 'unit' => 'seconds'],
            ],
            'fashion_try_on' => [
                ['key' => 'person', 'type' => 'reference', 'label' => 'Photo of the person', 'brief' => null, 'required' => true],
                ['key' => 'garment', 'type' => 'textarea', 'label' => 'What should they wear?', 'brief' => 'Garment', 'required' => true, 'max' => 500, 'placeholder' => 'e.g. A cream linen blazer over a black silk top, relaxed fit'],
            ],
            'poster' => [
                ['key' => 'message', 'type' => 'textarea', 'label' => 'What is the poster for?', 'brief' => 'Message', 'required' => true, 'max' => 500, 'placeholder' => 'e.g. A summer jazz festival in the park, Saturday 14 June'],
            ],
            'logo' => [
                ['key' => 'brand', 'type' => 'text', 'label' => "What's the brand?", 'brief' => 'Brand', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Northwind Coffee'],
                ['key' => 'idea', 'type' => 'textarea', 'label' => 'What should the mark suggest?', 'brief' => 'Idea', 'required' => false, 'max' => 300, 'placeholder' => 'e.g. Warmth, a morning sun over a cup'],
            ],
            'logo_3d' => [
                ['key' => 'brand', 'type' => 'text', 'label' => "What's the brand?", 'brief' => 'Brand', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Northwind Coffee'],
                ['key' => 'idea', 'type' => 'textarea', 'label' => 'What should the 3D mark suggest?', 'brief' => 'Idea', 'required' => false, 'max' => 300, 'placeholder' => 'e.g. A rounded, glossy bean shape'],
            ],
            'product_listing' => [
                ['key' => 'product_image', 'type' => 'reference', 'label' => 'Product photo', 'brief' => null, 'required' => true],
                ['key' => 'product_name', 'type' => 'text', 'label' => "What's it called?", 'brief' => 'Product', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Glow Lip Balm'],
            ],
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
