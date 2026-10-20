<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Virion;

use NhanAZ\DevTools\Support\PhpSourceInspector;
use NhanAZ\DevTools\Tests\TestCase;
use NhanAZ\DevTools\Virion\VirionClassScanner;
use NhanAZ\DevTools\Virion\VirionException;
use NhanAZ\DevTools\Virion\VirionManifest;
use NhanAZ\DevTools\Virion\VirionProject;
use NhanAZ\DevTools\Virion\VirionSourceFingerprint;

final class VirionSourceTraversalTest extends TestCase
{
    /** @dataProvider traversalProvider */
    public function test_source_links_are_rejected_before_reading_external_files(bool $fingerprint, bool $linkedRoot): void
    {
        $source = $this->temporaryDirectory . '/src';
        $outside = $this->temporaryDirectory . '/outside';
        $this->filesystem->ensureDirectory($outside);
        file_put_contents($outside . '/Invalid.php', '<?php this is not valid PHP');
        if (!$linkedRoot) {
            $this->filesystem->ensureDirectory($source);
        }
        $this->createSymbolicLink($outside, $linkedRoot ? $source : $source . '/Linked');

        $this->expectException(VirionException::class);
        $this->expectExceptionMessage('symbolic link');
        if ($fingerprint) {
            (new VirionSourceFingerprint())->hash($source);
        } else {
            $project = new VirionProject(
                $this->temporaryDirectory,
                $source,
                new VirionManifest('SourceProbe', '1.0.0', 'Probe', [], ['8.4'], [], ''),
                false,
            );
            (new VirionClassScanner(new PhpSourceInspector()))->scan($project);
        }
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function traversalProvider(): iterable
    {
        yield 'scanner linked root' => [false, true];
        yield 'scanner nested link' => [false, false];
        yield 'fingerprint linked root' => [true, true];
        yield 'fingerprint nested link' => [true, false];
    }
}
