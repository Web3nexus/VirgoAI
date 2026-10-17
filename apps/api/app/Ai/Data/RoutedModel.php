<?php

namespace App\Ai\Data;

use App\Models\AiModel;
use App\Models\AiProvider;

final class RoutedModel
{
    public function __construct(
        public readonly AiModel $model,
        public readonly AiProvider $provider,
    ) {}

    public function modelKey(): string
    {
        return $this->model->key;
    }

    public function providerKey(): string
    {
        return $this->provider->key;
    }
}
