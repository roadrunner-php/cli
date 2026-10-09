<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Configuration;

use Spiral\RoadRunner\Console\Configuration\Section\Rpc;
use Spiral\RoadRunner\Console\Configuration\Section\SectionInterface;
use Spiral\RoadRunner\Console\Configuration\Section\Version;
use Symfony\Component\Yaml\Yaml;

class Generator
{
    /** @psalm-var non-empty-array<class-string<SectionInterface>> */
    protected const REQUIRED_SECTIONS = [
        Version::class,
        Rpc::class,
    ];

    /** @var SectionInterface[] */
    protected array $sections = [];

    public function generate(Plugins $plugins): string
    {
        $this->collectSections($plugins->getPlugins());

        return Yaml::dump($this->getContent(), 10);
    }

    protected function getContent(): array
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
    protected function collectSections(array $plugins): void
    {
        $sections = \array_merge(self::REQUIRED_SECTIONS, $plugins);

        foreach ($sections as $section) {
            $this->fromSection(new $section());
        }
    }

    protected function fromSection(SectionInterface $section): void
    {
        if (!isset($this->sections[\get_class($section)])) {
            $this->sections[\get_class($section)] = $section;
        }

        foreach ($section->getRequired() as $required) {
            $this->fromSection(new $required());
        }
    }
}
