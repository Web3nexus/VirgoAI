<?php

namespace App\Workflows;

use App\Workflows\Handlers\ModelStageHandler;
use App\Workflows\Handlers\TestStageHandler;
use InvalidArgumentException;

class HandlerRegistry
{
    /** @var array<string, class-string<StageHandler>> */
    protected array $handlers = [
        'test' => TestStageHandler::class,
        'model' => ModelStageHandler::class,
    ];

    /**
     * @param  class-string<StageHandler>  $handler
     */
    public function register(string $key, string $handler): void
    {
        $this->handlers[$key] = $handler;
    }

    public function resolve(string $key): StageHandler
    {
        if (! isset($this->handlers[$key])) {
            throw new InvalidArgumentException("No stage handler registered for [{$key}].");
        }

        return app($this->handlers[$key]);
    }
}
