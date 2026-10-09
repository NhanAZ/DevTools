<?php

declare(strict_types=1);

/** @param list<string> $command */
function smokeProcess(array $command, string $directory, string $log, int $timeout): void
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log . '.err', 'w']], $pipes, $directory, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start smoke-test process.');
    }
    $deadline = time() + $timeout;
    do {
        usleep(100000);
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
    } while (time() < $deadline);
    if ($status['running']) {
        fwrite($pipes[0], "stop\n");
        usleep(500000);
        proc_terminate($process);
    }
    fclose($pipes[0]);
    proc_close($process);
    if ($status['running'] || $status['exitcode'] !== 0) {
        throw new RuntimeException("Process failed or exceeded {$timeout}s. See {$log} and {$log}.err.");
    }
}

function smokeWrite(string $path, string $contents): void
{
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true)) {
        throw new RuntimeException("Cannot create directory for {$path}.");
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Cannot write {$path}.");
    }
}

function smokeRead(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Cannot read {$path}.");
    }

    return $contents;
}

function smokePlugin(string $root, string $name, string $other, string $version): void
{
    $fixture = dirname(__DIR__) . '/tests/Fixtures/RuntimeSmoke';
    smokeWrite($root . '/plugin.yml', "name: {$name}\nversion: 1.0.0\nmain: RuntimeSmoke\\{$name}\\Main\napi: 5.0.0\nsrc-namespace-prefix: RuntimeSmoke\\{$name}\n");
    if ($name === 'SmokeA') {
        file_put_contents($root . '/plugin.yml', "depend: [SmokeB]\n", FILE_APPEND);
    }
    smokeWrite($root . '/devtools.yml', "virions:\n  - name: SmokeLibrary\n    version: ^{$version}.0.0\n");
    smokeWrite($root . '/resources/probe.txt', 'resource-ok');
    foreach (['Main', 'AsyncProbe'] as $class) {
        smokeWrite($root . '/src/' . $class . '.php', strtr(smokeRead($fixture . '/' . $class . '.php.tpl'), ['{{PLUGIN}}' => $name, '{{OTHER}}' => $other, '{{VERSION}}' => $version]));
    }
}

function smokeVirion(string $root, string $version): void
{
    smokeWrite($root . '/virion.yml', "name: SmokeLibrary\nversion: {$version}.0.0\nantigen: RuntimeSmoke\\Library\napi: 5.0.0\n");
    smokeWrite($root . '/src/Value.php', strtr(smokeRead(dirname(__DIR__) . '/tests/Fixtures/RuntimeSmoke/Value.php.tpl'), ['{{VERSION}}' => $version]));
}

try {
    $root = dirname(__DIR__);
    $server = null;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--server=')) {
            $server = realpath(substr($argument, 9));
        } elseif ($argument === '--help') {
            fwrite(STDOUT, "Usage: php -d phar.readonly=0 bin/runtime-smoke.php --server=/path/to/prepared/axolotl\nCreates fresh data directories under build/runtime-smoke-*. Requires the exact Axolotl-PM source from composer.lock and its installed dependencies. No downloads are performed.\n");
            exit(0);
        } else {
            throw new RuntimeException("Unknown argument: {$argument}");
        }
    }
    if (!is_string($server) || !is_file($server . '/vendor/autoload.php')) {
        throw new RuntimeException('Pass --server=<prepared Axolotl-PM source directory> with its own installed vendor dependencies.');
    }
    if (!extension_loaded('pmmpthread') || ini_get('phar.readonly') !== '0') {
        throw new RuntimeException('Use the Axolotl-PM-compatible PHP binary with pmmpthread and -d phar.readonly=0.');
    }
    require $root . '/vendor/autoload.php';
    $reference = Composer\InstalledVersions::getReference('axolotl-pm/pocketmine-mp');
    $installed = Composer\InstalledVersions::getInstallPath('axolotl-pm/pocketmine-mp');
    if ($reference === null || $installed === null) {
        throw new RuntimeException('Install DevTools development dependencies from composer.lock first.');
    }
    if ($reference !== 'b9a3b244993fb8a6df97241f3a3fbe44e2076f4c') {
        throw new RuntimeException('Runtime smoke target changed. Review the Axolotl-PM pin and runtime documentation before updating this harness.');
    }
    foreach (['src', 'generated', 'resources'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($installed . '/' . $directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
                continue;
            }
            $relative = substr($entry->getPathname(), strlen($installed) + 1);
            if (!is_file($server . '/' . $relative) || hash_file('sha256', $entry->getPathname()) !== hash_file('sha256', $server . '/' . $relative)) {
                throw new RuntimeException("Server differs from locked Axolotl-PM {$reference}: {$relative}");
            }
        }
    }
    if (hash_file('sha256', $server . '/composer.lock') !== hash_file('sha256', $installed . '/composer.lock')) {
        throw new RuntimeException('Prepared server composer.lock differs from the locked Axolotl-PM source.');
    }
    $run = $root . '/build/runtime-smoke-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    smokeWrite($run . '/target.json', json_encode(['axolotl' => $reference, 'php' => PHP_VERSION, 'pmmpthread' => phpversion('pmmpthread')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    smokeProcess([PHP_BINARY, '-d', 'phar.readonly=0', $root . '/bin/devtools-build.php', '--project=' . $root, '--out=' . $run . '/tools'], $root, $run . '/build-devtools.log', 60);
    $passed = [];
    foreach (['shared-folder', 'shared-phar', 'private-phar'] as $mode) {
        $data = $run . '/' . $mode;
        smokeWrite($data . '/server.properties', "language=eng\nserver-ip=127.0.0.1\nserver-port=0\nenable-ipv6=off\nenable-query=off\nxbox-auth=off\nlevel-type=FLAT\nview-distance=2\nmax-players=1\n");
        smokeWrite($data . '/pocketmine.yml', "settings:\n  async-workers: 2\n  enable-dev-builds: true\n  send-usage: false\nauto-report:\n  enabled: false\nauto-updater:\n  enabled: false\nnetwork:\n  upnp-forwarding: false\n");
        $private = $mode === 'private-phar';
        $plain = $private ? $run . '/inputs/SmokePlain' : $data . '/plugins/SmokePlain';
        smokeWrite($plain . '/plugin.yml', "name: SmokePlain\nversion: 1.0.0\nmain: RuntimeSmoke\\Plain\\Main\napi: 5.0.0\nsrc-namespace-prefix: RuntimeSmoke\\Plain\n");
        smokeWrite($plain . '/src/Main.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace RuntimeSmoke\Plain;
final class Main extends \pocketmine\plugin\PluginBase {
    protected function onEnable(): void {
        file_put_contents($this->getServer()->getDataPath() . 'SmokePlain.passed', 'passed');
    }
}
PHP);
        if ($private) {
            smokeProcess([PHP_BINARY, '-d', 'phar.readonly=0', $root . '/bin/devtools-build.php', '--project=' . $plain, '--out=' . $data . '/plugins'], $root, $data . '/build-SmokePlain.log', 60);
        }
        foreach (['SmokeA' => 'SmokeB', 'SmokeB' => 'SmokeA'] as $name => $other) {
            $version = $private && $name === 'SmokeB' ? '2' : '1';
            $project = $private ? $run . '/inputs/' . $name : $data . '/plugins/' . $name;
            smokePlugin($project, $name, $other, $version);
            if ($private) {
                $virions = $run . '/inputs/virions-' . $version;
                smokeVirion($virions . '/SmokeLibrary', $version);
                smokeProcess([PHP_BINARY, '-d', 'phar.readonly=0', $root . '/bin/devtools-build.php', '--project=' . $project, '--virions=' . $virions, '--out=' . $data . '/plugins'], $root, $data . '/build-' . $name . '.log', 60);
            }
        }
        if (!$private) {
            copy($run . '/tools/DevTools.phar', $data . '/plugins/DevTools.phar');
            if ($mode === 'shared-folder') {
                smokeVirion($data . '/virions/SmokeLibrary', '1');
            } else {
                smokeVirion($run . '/inputs/virion-phar', '1');
                mkdir($data . '/virions', 0777, true);
                $phar = new Phar($data . '/virions/SmokeLibrary.phar');
                $phar->buildFromDirectory($run . '/inputs/virion-phar');
                $phar->setStub('<?php __HALT_COMPILER();');
                unset($phar);
            }
        }
        putenv('DEVTOOLS_SMOKE_MODE=' . ($private ? 'private' : 'shared'));
        smokeProcess([PHP_BINARY, $server . '/src/PocketMine.php', '--no-wizard', '--disable-ansi', '--data=' . $data, '--plugins=' . $data . '/plugins'], $data, $data . '/console.log', 90);
        foreach (['SmokeA', 'SmokeB', 'SmokePlain'] as $name) {
            if (!is_file($data . '/' . $name . '.passed')) {
                throw new RuntimeException("{$mode}: {$name} did not complete. Inspect {$data}/console.log.");
            }
        }
        $passed[] = $mode;
        fwrite(STDOUT, "Passed {$mode}. Checked plain plugin loading, dependency order, resources, cross-plugin types and static state, and async virion calls.\n");
    }
    smokeWrite($run . '/result.json', json_encode(['axolotl' => $reference, 'php' => PHP_VERSION, 'passed' => $passed, 'clean_artifact_plugins' => ['SmokeA.phar', 'SmokeB.phar', 'SmokePlain.phar']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    fwrite(STDOUT, "Runtime evidence: {$run}\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Runtime smoke failed: {$error->getMessage()}\n");
    exit(1);
}
