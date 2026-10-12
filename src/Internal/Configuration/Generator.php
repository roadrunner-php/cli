<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Internal\Configuration;

use Spiral\RoadRunner\Console\Internal\Configuration\Section\Rpc;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\SectionInterface;
use Spiral\RoadRunner\Console\Internal\Configuration\Section\Version;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class Generator
{
    /** @psalm-var non-empty-array<class-string<SectionInterface>> */
    private const REQUIRED_SECTIONS = [
        Version::class,
        Rpc::class,
    ];

    /** @var SectionInterface[] */
    private array $sections = [];

    public function generate(Plugins $plugins): string
    {
        $this->collectSections($plugins->getPlugins());

        return Yaml::dump($this->getContent(), 10, 4, Yaml::DUMP_OBJECT_AS_MAP);
    }

    private function getContent(): array
    {
        $content = [];
        foreach ($this->sections as $section) {
            $content += $section->render();
        }

        return $content;
    }

    /**
     * @param array<class-string<SectionInterface>> $plugins
     */
    private function collectSections(array $plugins): void
    {
        $sections = \array_merge(self::REQUIRED_SECTIONS, $plugins);

        foreach ($sections as $section) {
            $this->fromSection(new $section());
        }
    }

    private function fromSection(SectionInterface $section): void
    {
        if (!isset($this->sections[\get_class($section)])) {
            $this->sections[\get_class($section)] = $section;
        }

        foreach ($section->getRequired() as $required) {
            $this->fromSection(new $required());
        }
    }
}
