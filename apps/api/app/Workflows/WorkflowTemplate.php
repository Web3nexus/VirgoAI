<?php

namespace App\Workflows;

class WorkflowTemplate
{
    /**
     * @param  array<int, StageDefinition>  $stages
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly array $stages,
    ) {}

    /**
     * @return array<string, StageDefinition>
     */
    public function stagesByKey(): array
    {
        $keyed = [];
        foreach ($this->stages as $stage) {
            $keyed[$stage->key] = $stage;
        }

        return $keyed;
    }
}
