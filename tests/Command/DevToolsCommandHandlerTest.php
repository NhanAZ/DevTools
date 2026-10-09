<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Command;

use NhanAZ\DevTools\Build\BuildConfigResolver;
use NhanAZ\DevTools\Build\PharExtractor;
use NhanAZ\DevTools\Build\PluginProjectLocator;
use NhanAZ\DevTools\Build\VirionResolver;
use NhanAZ\DevTools\Command\DevToolsCommandHandler;
use NhanAZ\DevTools\Loader\FolderPluginDiscovery;
use NhanAZ\DevTools\Support\YamlReader;
use NhanAZ\DevTools\Tests\TestCase;
use NhanAZ\DevTools\Virion\VirionDiscovery;
use NhanAZ\DevTools\Virion\VirionManifestReader;
use NhanAZ\DevTools\Virion\VirionProjectFactory;
use NhanAZ\DevTools\Virion\VirionProjectSelector;
use NhanAZ\DevTools\Virion\VirionRegistry;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;

final class DevToolsCommandHandlerTest extends TestCase
{
    public function test_server_doctor_reports_missing_declared_dependency(): void
    {
        $this->copyFixture('BuildPlugin', 'plugins/Source');
        $messages = $this->command(['doctor', 'BuildFixture']);
        self::assertStringContainsString('Dependency check failed', $messages);
        self::assertStringContainsString('SharedVirion', $messages);
        self::assertStringNotContainsString('Project structure and dependency selection passed', $messages);
    }

    public function test_server_doctor_reports_invalid_build_declaration(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/Source');
        file_put_contents($project . '/devtools.yml', "unknown: true\n");
        $messages = $this->command(['doctor', 'BuildFixture']);
        self::assertStringContainsString('Unknown key', $messages);
        self::assertStringNotContainsString('Project structure and dependency selection passed', $messages);
    }

    public function test_server_doctor_describes_only_checks_it_performed(): void
    {
        $this->copyFixture('BuildPlugin', 'plugins/Source');
        $this->copyFixture('SharedVirion', 'virions/Shared');
        $messages = $this->command(['doctor', 'BuildFixture']);
        self::assertStringContainsString('Project structure and dependency selection passed (1 virions)', $messages);
        self::assertStringContainsString('runtime tests were not run', $messages);
        self::assertStringContainsString('/devtools virions', $messages);
    }

    public function test_build_and_legacy_alias_reject_typo_options_before_creating_output(): void
    {
        $this->copyFixture('BuildPlugin', 'plugins/Source');
        $this->copyFixture('SharedVirion', 'virions/Shared');
        foreach ([['devtools', ['build', 'BuildFixture', '--overwite']], ['makeplugin', ['BuildFixture', '--overwrite', '--overwrite']]] as [$label, $arguments]) {
            $messages = $this->command($arguments, $label);
            self::assertStringContainsString('Unknown or duplicate arguments', $messages);
            self::assertDirectoryDoesNotExist($this->temporaryDirectory . '/build');
        }
    }

    public function test_extract_rejects_unknown_option_before_writing_files(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/Source');
        $this->copyFixture('SharedVirion', 'virions/Shared');
        $result = $this->builder()->build($project, $this->temporaryDirectory . '/virions', $this->temporaryDirectory . '/build');
        $messages = $this->command([$result->outputPath, '--overwite'], 'extractplugin');
        self::assertStringContainsString('Unknown or duplicate arguments', $messages);
        self::assertDirectoryDoesNotExist($this->temporaryDirectory . '/plugins/BuildFixture');
    }

    /** @param list<string> $arguments */
    private function command(array $arguments, string $label = 'devtools'): string
    {
        [$reader, $validator] = $this->validationServices();
        $discovery = new FolderPluginDiscovery();
        $yaml = new YamlReader();
        $handler = new DevToolsCommandHandler(
            $this->createMock(Plugin::class),
            $discovery,
            $validator,
            $reader,
            new BuildConfigResolver($yaml),
            new VirionResolver(new VirionDiscovery($this->filesystem), new VirionProjectFactory(new VirionManifestReader($yaml)), new VirionProjectSelector()),
            new PluginProjectLocator($discovery, $reader),
            $this->builder(),
            new PharExtractor($this->filesystem),
            new VirionRegistry(),
            $this->temporaryDirectory . '/plugins',
            $this->temporaryDirectory . '/virions',
            $this->temporaryDirectory . '/build',
        );
        $messages = [];
        $sender = $this->createMock(CommandSender::class);
        $sender->method('sendMessage')->willReturnCallback(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });
        $handler->handle($sender, $label, $arguments);
        return implode("\n", $messages);
    }
}
