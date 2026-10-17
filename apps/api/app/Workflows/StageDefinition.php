<?php

namespace App\Workflows;

use App\Enums\ApprovalPolicy;
use App\Enums\Capability;

class StageDefinition
{
    /**
     * @param  array<int, string>  $dependsOn  Stage keys this stage waits for.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $handler,
        public readonly ApprovalPolicy $approvalPolicy = ApprovalPolicy::NotRequired,
        public readonly array $dependsOn = [],
        public readonly bool $userConfiguredApproval = false,
        public readonly ?Capability $capability = null,
        public readonly ?string $prompt = null,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function toTaskInput(): ?array
    {
        $input = [];

        if ($this->userConfiguredApproval) {
            $input['user_configured'] = true;
        }

        if ($this->capability !== null) {
            $input['capability'] = $this->capability->value;
        }

        if ($this->prompt !== null) {
            $input['prompt'] = $this->prompt;
        }

        return $input === [] ? null : $input;
    }
}
