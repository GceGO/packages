<?php

declare(strict_types=1);

namespace Gcego\Widgets\Data;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Widgets\Contracts\WidgetDataProvider;
use Gcego\Widgets\Exceptions\InvalidWidgetException;

/**
 * A trusted provider class for a widget's data: new DataProvider(StudentEnrolmentProvider::class).
 * Only definitions name providers; requests never can.
 */
final readonly class DataProvider implements ConfigurationNode
{
    /**
     * @param  class-string<WidgetDataProvider>  $provider
     */
    public function __construct(public string $provider)
    {
        if (! is_subclass_of($provider, WidgetDataProvider::class)) {
            throw InvalidWidgetException::invalid('DataProvider', sprintf('"%s" must implement %s.', mb_substr($provider, 0, 120), WidgetDataProvider::class));
        }
    }

    /**
     * @return array{provider: string}
     */
    public function normalize(): array
    {
        return ['provider' => $this->provider];
    }
}
