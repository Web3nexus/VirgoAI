<?php

namespace App\Workflows;

use App\Enums\ApprovalPolicy;
use App\Enums\Capability;
use InvalidArgumentException;

class WorkflowTemplates
{
    public const DEFAULT = 'test_pipeline';

    public const GENERATIVE_VIDEO = 'generative_video';

    /**
     * @return array<string, WorkflowTemplate>
     */
    public static function all(): array
    {
        return [
            self::DEFAULT => new WorkflowTemplate(
                key: self::DEFAULT,
                name: 'Test pipeline',
                stages: [
                    new StageDefinition(
                        key: 'story',
                        name: 'Story concept',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::Always,
                    ),
                    new StageDefinition(
                        key: 'script',
                        name: 'Script',
                        handler: 'test',
                        dependsOn: ['story'],
                    ),
                    new StageDefinition(
                        key: 'scenes',
                        name: 'Scene breakdown',
                        handler: 'test',
                        dependsOn: ['script'],
                    ),
                    new StageDefinition(
                        key: 'images',
                        name: 'Keyframe images',
                        handler: 'test',
                        dependsOn: ['scenes'],
                    ),
                ],
            ),
            // MVP generative video pipeline. Stage keys and gates mirror
            // docs/pipelines/generative-video.yaml (DEC-0008). Every stage uses the placeholder
            // handler until its provider adapter is verified, so outputs are labelled placeholder.
            self::GENERATIVE_VIDEO => new WorkflowTemplate(
                key: self::GENERATIVE_VIDEO,
                name: 'Generative video',
                stages: [
                    new StageDefinition(
                        key: 'story',
                        name: 'Story & brief',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::GuidedOnly,
                    ),
                    new StageDefinition(
                        key: 'script',
                        name: 'Script',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::GuidedOnly,
                        dependsOn: ['story'],
                    ),
                    new StageDefinition(
                        key: 'characters',
                        name: 'Characters',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::GuidedOnly,
                        dependsOn: ['script'],
                    ),
                    new StageDefinition(
                        key: 'environments',
                        name: 'Environments',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::GuidedOnly,
                        dependsOn: ['script'],
                    ),
                    new StageDefinition(
                        key: 'scenes',
                        name: 'Scene plan',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::GuidedOnly,
                        dependsOn: ['script', 'characters', 'environments'],
                    ),
                    new StageDefinition(
                        key: 'keyframes',
                        name: 'Keyframe storyboard',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::CriticalOnly,
                        dependsOn: ['scenes', 'characters', 'environments'],
                    ),
                    new StageDefinition(
                        key: 'motion',
                        name: 'Motion clips',
                        handler: 'test',
                        dependsOn: ['keyframes'],
                    ),
                    new StageDefinition(
                        key: 'narration',
                        name: 'Narration & dialogue',
                        handler: 'test',
                        dependsOn: ['script'],
                    ),
                    new StageDefinition(
                        key: 'edit',
                        name: 'Edit & render',
                        handler: 'test',
                        dependsOn: ['motion', 'narration', 'scenes'],
                    ),
                    new StageDefinition(
                        key: 'final_review',
                        name: 'Final review',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::CriticalOnly,
                        dependsOn: ['edit'],
                    ),
                    new StageDefinition(
                        key: 'thumbnail',
                        name: 'Thumbnail',
                        handler: 'test',
                        approvalPolicy: ApprovalPolicy::GuidedOnly,
                        dependsOn: ['final_review', 'script'],
                    ),
                ],
            ),
            'content_pipeline' => new WorkflowTemplate(
                key: 'content_pipeline',
                name: 'Content pipeline (provider-backed)',
                stages: [
                    new StageDefinition(
                        key: 'story',
                        name: 'Story concept',
                        handler: 'model',
                        approvalPolicy: ApprovalPolicy::GuidedOnly,
                        capability: Capability::Text,
                        prompt: 'Draft a compelling story concept for this project.',
                    ),
                    new StageDefinition(
                        key: 'script',
                        name: 'Script',
                        handler: 'model',
                        capability: Capability::Text,
                        dependsOn: ['story'],
                        prompt: 'Write a tight video script based on the approved story.',
                    ),
                    new StageDefinition(
                        key: 'scenes',
                        name: 'Scene breakdown',
                        handler: 'model',
                        capability: Capability::Text,
                        dependsOn: ['script'],
                        prompt: 'Break the script into individual scenes with shots.',
                    ),
                ],
            ),
        ];
    }

    public static function get(string $key): WorkflowTemplate
    {
        $templates = self::all();

        if (! isset($templates[$key])) {
            throw new InvalidArgumentException("Unknown workflow template [{$key}].");
        }

        return $templates[$key];
    }
}
