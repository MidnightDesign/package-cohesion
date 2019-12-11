<?php

declare(strict_types=1);

use ComposerRequireChecker\ASTLocator\LocateASTFromFiles;
use ComposerRequireChecker\DefinedSymbolsLocator\LocateDefinedSymbolsFromASTRoots;
use ComposerRequireChecker\FileLocator\LocateComposerPackageSourceFiles;
use ComposerRequireChecker\UsedSymbolsLocator\LocateUsedSymbolsFromASTRoots;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\ParserFactory;

require_once __DIR__ . '/vendor/autoload.php';

$packageDirectory = $argv[1];
$composerJsonFile = $packageDirectory . '/composer.json';
$composerData = json_decode(file_get_contents($composerJsonFile), true);

$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
$locateASTFromFiles = new LocateASTFromFiles($parser, new Throwing());

$locateUsedSymbols = new LocateUsedSymbolsFromASTRoots();
$locateDefinedSymbols = new LocateDefinedSymbolsFromASTRoots();

$symbolMap = [];
foreach ((new LocateComposerPackageSourceFiles())($composerData, $packageDirectory) as $file) {
    $usedSymbols = $locateUsedSymbols($locateASTFromFiles(new ArrayObject([$file])));
    $definedSymbols = $locateDefinedSymbols($locateASTFromFiles(new ArrayObject([$file])));
    $symbolMap[$file] = [
        'used' => $usedSymbols,
        'defined' => $definedSymbols,
    ];
}

function getFileDefiningSymbol(string $symbol): ?string
{
    global $symbolMap;
    foreach ($symbolMap as $fileName => $symbols) {
        foreach ($symbols['defined'] as $definedSymbol) {
            if ($definedSymbol !== $symbol) {
                continue;
            }
            return $fileName;
        }
    }
    return null;
}

$fileDependencyMap = [];
foreach ($symbolMap as $fileName => $symbols) {
    $dependencies = [];
    foreach ($symbols['used'] as $usedSymbol) {
        $definingFile = getFileDefiningSymbol($usedSymbol);
        if ($definingFile === null) {
            continue;
        }
        $dependencies[] = realpath($definingFile);
    }
    $fileDependencyMap[realpath($fileName)] = $dependencies;
}

class CollectDependencies
{
    /** @var string[] */
    private $seen = [];
    /** @var array<mixed[]> */
    private $fileDependencyMap;

    public function __construct(array $fileDependencyMap)
    {
        $this->fileDependencyMap = $fileDependencyMap;
    }

    public function __invoke($fileName): iterable
    {
        if ($this->wasSeen($fileName)) {
            return;
        }
        $this->seen[] = $fileName;
        yield $fileName;
        foreach ($this->dependencies($fileName) as $dependency) {
            if ($this->wasSeen($dependency)) {
                continue;
            }
            yield from $this($dependency);
        }
        foreach ($this->dependants($fileName) as $dependant) {
            if ($this->wasSeen($dependant)) {
                continue;
            }
            yield from $this($dependant);
        }
    }

    private function wasSeen(string $fileName): bool
    {
        return in_array($fileName, $this->seen, true);
    }

    /**
     * @return iterable<string>
     */
    private function dependencies(string $fileName): iterable
    {
        return $this->fileDependencyMap[$fileName];
    }

    private function dependants(string $fileName): iterable
    {
        foreach ($this->fileDependencyMap as $file => $dependencies) {
            foreach ($dependencies as $dependency) {
                if ($dependency !== $fileName) {
                    continue;
                }
                yield $file;
            }
        }
    }
}

$clusters = [];
foreach (array_keys($fileDependencyMap) as $fileName) {
    foreach ($clusters as $existingCluster) {
        if (!in_array($fileName, $existingCluster, true)) {
            continue;
        }
        continue 2;
    }
    $cluster = [];
    foreach ((new CollectDependencies($fileDependencyMap))($fileName) as $clusterFile) {
        $cluster[] = $clusterFile;
    }
    sort($cluster);
    $clusters[] = $cluster;
}

foreach ($clusters as $index => $files) {
    $clusterNumber = $index + 1;
    $numberOfFiles = count($files);
    if ($index !== 0) {
        echo "\n";
    }
    echo "Cluster $clusterNumber contains $numberOfFiles files:\n";
    foreach ($files as $file) {
        echo "$file\n";
    }
}
