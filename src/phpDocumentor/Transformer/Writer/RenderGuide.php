<?php

declare(strict_types=1);

/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link https://phpdoc.org
 */

namespace phpDocumentor\Transformer\Writer;

use phpDocumentor\Descriptor\ProjectDescriptor;
use phpDocumentor\Descriptor\VersionDescriptor;
use phpDocumentor\Guides\BuildContext;
use phpDocumentor\Guides\KernelFactory;
use phpDocumentor\Guides\RestructuredText\Builder;
use phpDocumentor\Parser\Cache\Locator;
use phpDocumentor\Parser\FlySystemFactory;
use phpDocumentor\Transformer\Transformation;
use phpDocumentor\Transformer\Writer\Twig\EnvironmentFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use function sprintf;

/**
 * @experimental Do not use; this stage is meant as a sandbox / playground to experiment with generating guides.
 */
final class RenderGuide extends WriterAbstract implements ProjectDescriptor\WithCustomSettings
{
    private const FEATURE_FLAG = 'guides.enabled';
    private const SETTING_CACHE = 'guides.cache';

    /** @var FlySystemFactory */
    private $flySystemFactory;

    /** @var KernelFactory */
    private $kernelFactory;

    /** @var Locator */
    private $cacheLocator;

    /** @var LoggerInterface */
    private $logger;

    /** @var EnvironmentFactory */
    private $environmentFactory;

    /** @var Builder */
    private $builder;

    public function __construct(
        FlySystemFactory $flySystemFactory,
        KernelFactory $kernelFactory,
        Locator $cacheLocator,
        LoggerInterface $logger,
        EnvironmentFactory $environmentFactory,
        Builder $builder
    ) {
        $this->flySystemFactory = $flySystemFactory;
        $this->kernelFactory = $kernelFactory;
        $this->cacheLocator = $cacheLocator;
        $this->logger = $logger;
        $this->environmentFactory = $environmentFactory;
        $this->builder = $builder;
    }

    public function transform(ProjectDescriptor $project, Transformation $transformation) : void
    {
        // Feature flag: Guides are disabled by default since this is an experimental feature
        if (!($project->getSettings()->getCustom()[self::FEATURE_FLAG])) {
            return;
        }

        $this->logger->warning(
            'Generating guides is experimental, no BC guarantees are given, use at your own risk'
        );

        $output = $transformation->getTransformer()->destination();
        $cachePath = (string) $this->cacheLocator->locate('guide');

        /** @var VersionDescriptor $version */
        foreach ($project->getVersions() as $version) {
            foreach ($version->getDocumentationSets() as $documentationSet) {
                $stopwatch = new Stopwatch(true);
                $stopwatch->start('guide');
                $dsn = $documentationSet->getSource()['dsn'];
                $this->logger->info('Rendering guide ' . $dsn);
                $buildContext = new BuildContext(
                    $output,
                    $documentationSet->getOutput(),
                    'default',
                    $cachePath,
                    $project->getSettings()->getCustom()[self::SETTING_CACHE]
                );

                $input = $this->flySystemFactory->create($dsn);
                $inputFolder = $documentationSet->getSource()['paths'][0] ?? '';

                $environment = $this->environmentFactory->create(
                    $project,
                    $transformation,
                    $documentationSet->getOutput()
                );

                $kernel = $this->kernelFactory->createKernel($buildContext, $environment);
                $this->builder->build($kernel, $input, $inputFolder, $output, $documentationSet->getOutput());

                $stopwatchEvent = $stopwatch->stop('guide');
                $this->logger->info(
                    sprintf(
                        'Completed rendering guide %s in %.2fms using %.2f mb memory',
                        (string) $dsn,
                        $stopwatchEvent->getDuration(),
                        $stopwatchEvent->getMemory() / 1024 / 1024
                    )
                );
            }
        }
    }

    public function getDefaultSettings() : array
    {
        return [
            self::FEATURE_FLAG => false,
            self::SETTING_CACHE => true,
        ];
    }
}
