<?php

declare(strict_types=1);

namespace Fixture\AsyncProbe;

use pocketmine\plugin\PluginBase;

final class Main extends PluginBase
{
    protected function onEnable(): void
    {
        $resource = file_get_contents($this->getResourcePath('probe.txt'));
        file_put_contents(
            $this->getFile() . DIRECTORY_SEPARATOR . 'folder-resource-result.txt',
            $resource === false ? 'unreadable' : trim($resource),
        );
        $this->getServer()->getAsyncPool()->submitTask(
            new VirionProbeTask($this->getFile() . DIRECTORY_SEPARATOR . 'async-virion-result.txt'),
        );
    }
}
