<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Command;

use function array_shift;
use function basename;
use function count;
use function is_file;

use NhanAZ\DevTools\Build\BuildConfigResolver;
use NhanAZ\DevTools\Build\BuildException;
use NhanAZ\DevTools\Build\PharArchiveReader;
use NhanAZ\DevTools\Build\PharBuilder;
use NhanAZ\DevTools\Build\PharExtractor;
use NhanAZ\DevTools\Build\PluginProjectLocator;
use NhanAZ\DevTools\Build\VirionResolver;
use NhanAZ\DevTools\Loader\FolderPluginDiscovery;
use NhanAZ\DevTools\Support\Path;
use NhanAZ\DevTools\Validation\PluginManifestReader;
use NhanAZ\DevTools\Validation\PluginProjectValidator;
use NhanAZ\DevTools\Virion\VirionRegistry;
use NhanAZ\DevTools\Virion\VirionStatus;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;

use function preg_match;
use function realpath;
use function str_ends_with;
use function strtolower;
use function substr;

use Throwable;

final class DevToolsCommandHandler
{
    public function __construct(
        private readonly Plugin $plugin,
        private readonly FolderPluginDiscovery $pluginDiscovery,
        private readonly PluginProjectValidator $validator,
        private readonly PluginManifestReader $manifestReader,
        private readonly BuildConfigResolver $configResolver,
        private readonly VirionResolver $resolver,
        private readonly PluginProjectLocator $projectLocator,
        private readonly PharBuilder $builder,
        private readonly PharExtractor $extractor,
        private readonly VirionRegistry $virions,
        private readonly string $pluginsDirectory,
        private readonly string $virionsDirectory,
        private readonly string $buildDirectory,
    ) {}

    /** @param list<string> $arguments */
    public function handle(CommandSender $sender, string $label, array $arguments): bool
    {
        $label = strtolower($label);
        if ($label === 'makeplugin') {
            array_unshift($arguments, 'build');
        } elseif ($label === 'extractplugin') {
            array_unshift($arguments, 'extract');
        }

        $subcommand = strtolower(array_shift($arguments) ?? 'help');
        try {
            $this->validateArguments($subcommand, $arguments);
            return match ($subcommand) {
                'status' => $this->status($sender),
                'doctor' => $this->doctor($sender, $arguments),
                'build' => $this->build($sender, $arguments),
                'extract' => $this->extract($sender, $arguments),
                'virions' => $this->virions($sender),
                default => $this->help($sender),
            };
        } catch (Throwable $error) {
            $sender->sendMessage("DevTools could not complete the command.\n{$error->getMessage()}");

            return true;
        }
    }

    /** @param list<string> $arguments */
    private function validateArguments(string $command, array $arguments): void
    {
        if (in_array($command, ['build', 'extract'], true)) {
            if ($arguments === []) {
                return;
            }
            if (str_starts_with($arguments[0], '--') || !in_array(array_slice($arguments, 1), [[], ['--overwrite']], true)) {
                throw new BuildException("Usage: /devtools {$command} <selection> [--overwrite]. Unknown or duplicate arguments are not accepted.");
            }
        } elseif ($command === 'doctor') {
            if (count($arguments) > 1 || (isset($arguments[0]) && str_starts_with($arguments[0], '--'))) {
                throw new BuildException('Usage: /devtools doctor [plugin]. Extra arguments are not accepted.');
            }
        } elseif (in_array($command, ['status', 'virions', 'help'], true) && $arguments !== []) {
            throw new BuildException("Usage: /devtools {$command}. This command takes no arguments.");
        }
    }

    private function status(CommandSender $sender): bool
    {
        $projects = $this->pluginDiscovery->discover($this->pluginsDirectory);
        $loaded = $this->virions->loaded();
        $async = $loaded === [] ? 'not needed yet' : ($loaded[0]->asyncSupported ? 'available' : 'not registered');
        $sender->sendMessage(
            "DevTools {$this->plugin->getDescription()->getVersion()}\n"
            . 'Development plugin folders found: ' . count($projects) . "\n"
            . 'Shared virions loaded: ' . count($loaded) . "\n"
            . "Async worker class loading: {$async}\n"
            . "Build output: {$this->buildDirectory}",
        );

        return true;
    }

    /** @param list<string> $arguments */
    private function doctor(CommandSender $sender, array $arguments): bool
    {
        $selection = array_shift($arguments);
        $projects = $selection === null
            ? $this->pluginDiscovery->discover($this->pluginsDirectory)
            : [$this->projectLocator->locate($selection, $this->pluginsDirectory)];
        if ($projects === []) {
            $sender->sendMessage("No development plugin folders were found in {$this->pluginsDirectory}.");

            return true;
        }
        foreach ($projects as $project) {
            $result = $this->validator->validate($project);
            $sender->sendMessage("Checking {$project}");
            foreach ($result->issues() as $issue) {
                $sender->sendMessage($issue->format());
            }
            if ($result->hasErrors()) {
                continue;
            }
            try {
                $manifest = $this->manifestReader->read($project . DIRECTORY_SEPARATOR . 'plugin.yml');
                $config = $this->configResolver->resolve($project, $manifest->description->getName());
                $resolved = $this->resolver->resolve($config->virions, $this->virionsDirectory);
                $sender->sendMessage('Project structure and dependency selection passed (' . count($resolved) . ' virions). Shading, static analysis and runtime tests were not run. Check /devtools virions for the shared server registry.');
            } catch (Throwable $error) {
                $sender->sendMessage("Dependency check failed for {$project}: {$error->getMessage()}");
            }
        }

        return true;
    }

    /** @param list<string> $arguments */
    private function build(CommandSender $sender, array $arguments): bool
    {
        $selection = array_shift($arguments);
        if ($selection === null) {
            $sender->sendMessage('Usage: /devtools build <plugin> [--overwrite]');

            return true;
        }
        $overwrite = in_array('--overwrite', $arguments, true);
        $project = $this->projectLocator->locate($selection, $this->pluginsDirectory);
        $sender->sendMessage("Validating and staging {$project}...");
        $result = $this->builder->build($project, $this->virionsDirectory, $this->buildDirectory, $overwrite);
        $sender->sendMessage(
            "Built {$result->pluginName} successfully.\n"
            . "Files: {$result->fileCount}\n"
            . 'Shaded virions: ' . count($result->shadedVirions) . "\n"
            . "Output: {$result->outputPath}",
        );

        return true;
    }

    /** @param list<string> $arguments */
    private function extract(CommandSender $sender, array $arguments): bool
    {
        $selection = array_shift($arguments);
        if ($selection === null) {
            $sender->sendMessage('Usage: /devtools extract <phar> [--overwrite]');

            return true;
        }
        $overwrite = in_array('--overwrite', $arguments, true);
        $path = $this->locatePhar($selection);
        $name = basename($path);
        if (str_ends_with(strtolower($name), '.phar')) {
            $name = substr($name, 0, -5);
        }
        if ($name === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/D', $name) !== 1) {
            throw new BuildException("Cannot derive a safe plugin folder name from {$path}.");
        }
        try {
            Path::normalizeRelative($name);
        } catch (\RuntimeException $error) {
            throw new BuildException("Cannot derive a portable plugin folder name from {$path}: {$error->getMessage()}", 0, $error);
        }
        $pluginsBase = realpath($this->pluginsDirectory);
        if ($pluginsBase === false) {
            throw new BuildException("Cannot resolve plugins directory {$this->pluginsDirectory}.");
        }
        $destination = Path::join($pluginsBase, $name);
        if (dirname($destination) !== $pluginsBase) {
            throw new BuildException("Refusing extraction destination outside {$pluginsBase}.");
        }
        $count = $this->extractor->extract(new PharArchiveReader($path), $destination, $overwrite);
        $sender->sendMessage("Extracted {$count} files to {$destination}.");

        return true;
    }

    private function virions(CommandSender $sender): bool
    {
        $entries = $this->virions->entries();
        if ($entries === []) {
            $sender->sendMessage("No virions were found in {$this->virionsDirectory}.");

            return true;
        }
        foreach ($entries as $entry) {
            $name = $entry->manifest === null ? basename($entry->location) : $entry->manifest->name;
            $sender->sendMessage(ucfirst($entry->status->value) . " {$name}: {$entry->message}");
        }

        return true;
    }

    private function help(CommandSender $sender): bool
    {
        $sender->sendMessage(
            "DevTools commands:\n"
            . "/devtools status\n"
            . "/devtools doctor [plugin]\n"
            . "/devtools build <plugin> [--overwrite]\n"
            . "/devtools extract <phar> [--overwrite]\n"
            . '/devtools virions',
        );

        return true;
    }

    private function locatePhar(string $selection): string
    {
        $candidates = [
            $selection,
            $this->buildDirectory . DIRECTORY_SEPARATOR . $selection,
            $this->pluginsDirectory . DIRECTORY_SEPARATOR . $selection,
        ];
        if (!str_ends_with(strtolower($selection), '.phar')) {
            $candidates[] = $this->buildDirectory . DIRECTORY_SEPARATOR . $selection . '.phar';
            $candidates[] = $this->pluginsDirectory . DIRECTORY_SEPARATOR . $selection . '.phar';
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $real = realpath($candidate);
                if ($real !== false) {
                    return $real;
                }
            }
        }

        throw new BuildException("Cannot find PHAR {$selection}. Check the path or the build/ directory.");
    }
}
