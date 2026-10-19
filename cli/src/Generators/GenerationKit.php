<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\CliConfig;
use Gcego\Cli\Generation\DefinitionPaths;
use Gcego\Cli\Generation\FieldCode;
use Gcego\Cli\Generation\ModelInspector;
use Gcego\Cli\Generation\NameNormalizer;
use Gcego\Cli\Generation\NamespaceResolver;
use Gcego\Cli\Generation\PathResolver;
use Gcego\Cli\Inspection\Catalog;
use Gcego\Cli\Stubs\StubRenderer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * The services every generator uses, injected once.
 */
final readonly class GenerationKit
{
    public function __construct(
        public NameNormalizer $names,
        public PathResolver $files,
        public DefinitionPaths $paths,
        public NamespaceResolver $namespaces,
        public StubRenderer $stubs,
        public FieldCode $fields,
        public ModelInspector $models,
        public Catalog $catalog,
        public CliConfig $cli,
        public Repository $config,
        public Container $container,
    ) {}
}
