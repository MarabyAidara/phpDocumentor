<?php

declare(strict_types=1);

namespace phpDocumentor\Guides\RestructuredText;

use League\Flysystem\FilesystemInterface;
use League\Tactician\CommandBus;
use phpDocumentor\Guides\RestructuredText\Command\LoadCacheCommand;
use phpDocumentor\Guides\RestructuredText\Command\ParseDirectoryCommand;
use phpDocumentor\Guides\RestructuredText\Command\PersistCacheCommand;
use phpDocumentor\Guides\RestructuredText\Command\RenderCommand;

class Builder
{
    /** @var CommandBus */
    private $commandBus;

    public function __construct(CommandBus $commandBus)
    {
        $this->commandBus = $commandBus;
    }

    public function build(
        Kernel $kernel,
        FilesystemInterface $origin,
        string $directory,
        FilesystemInterface $destination,
        string $targetDirectory = 'output'
    ) : void {
        $cacheDir = $kernel->getConfiguration()->getCacheDir();

        $this->commandBus->handle(new LoadCacheCommand($kernel, $cacheDir));
        $this->commandBus->handle(new ParseDirectoryCommand($kernel, $origin, $directory));
        $this->commandBus->handle(new RenderCommand($destination, $targetDirectory));
        $this->commandBus->handle(new PersistCacheCommand($kernel, $cacheDir));
    }
}
