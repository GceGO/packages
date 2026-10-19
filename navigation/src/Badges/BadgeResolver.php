<?php

declare(strict_types=1);

namespace Gcego\Navigation\Badges;

use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\NavigationBadgeProvider;
use Gcego\Navigation\Resolution\NavigationContext;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Resolves badges of visible items, when the navigation renders (lazily: a
 * hidden item's provider never runs). Providers are classes from trusted
 * definitions, resolved through the container. A failing provider is
 * reported and its badge left out.
 *
 * With Item::BadgeTtl the result is cached per user and tenant: the key
 * hashes the navigation, the item, a per-navigation version (forget()) and
 * the security context fingerprint, so values never cross users or tenants.
 */
final readonly class BadgeResolver
{
    public function __construct(private Container $container, private CacheFactory $cache, private ?string $store) {}

    public function resolve(Item $item, NavigationContext $context, string $node): ?NavigationBadge
    {
        try {
            $provider = $item->badgeProvider();
            $model = $item->badgeCount();

            if ($provider === null && $model === null) {
                return $this->static($item);
            }

            $compute = fn (): ?NavigationBadge => $provider !== null ? $this->compute($provider, $item, $context) : $this->count($model, $item, $context);
            $ttl = $item->get(Item::BadgeTtl);

            if (! is_int($ttl)) {
                return $compute();
            }

            $cached = $this->repository()->remember($this->key($context, $node), $ttl, fn (): array|false => $compute()?->toArray() ?? false);

            return is_array($cached) ? NavigationBadge::fromArray($cached) : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Invalidate every cached badge of a navigation.
     */
    public function forget(string $navigation): void
    {
        $key = 'gcego-navigation:badges:'.$navigation.':version';
        $version = $this->repository()->get($key, 0);
        $this->repository()->forever($key, (is_int($version) ? $version : 0) + 1);
    }

    public function key(NavigationContext $context, string $node): string
    {
        $version = $this->repository()->get('gcego-navigation:badges:'.$context->navigation.':version', 0);

        return 'gcego-navigation:badge:'.hash('sha256', (string) json_encode([$context->navigation, $node, $version, $context->fingerprint]));
    }

    private function static(Item $item): ?NavigationBadge
    {
        $badge = $item->badge();
        $variant = $item->get(Item::BadgeVariant);
        $label = $item->get(Item::BadgeLabel);
        $label = is_string($label) ? $label : null;

        return match (true) {
            is_int($badge) => NavigationBadge::count($badge, $label, is_string($variant) ? $variant : 'primary'),
            is_string($badge) => new NavigationBadge($badge, is_string($variant) ? $variant : 'default', $label),
            default => null,
        };
    }

    /**
     * @param  class-string<NavigationBadgeProvider>  $provider
     */
    private function compute(string $provider, Item $item, NavigationContext $context): ?NavigationBadge
    {
        $instance = $this->container->make($provider);

        if (! $instance instanceof NavigationBadgeProvider) {
            return null;
        }

        $badge = $instance->badge($context);
        $label = $item->get(Item::BadgeLabel);
        $variant = $item->get(Item::BadgeVariant);

        return is_int($badge) ? NavigationBadge::count($badge, is_string($label) ? $label : null, is_string($variant) ? $variant : 'primary') : $badge;
    }

    /**
     * @param  class-string<Model>|null  $model
     */
    private function count(?string $model, Item $item, NavigationContext $context): ?NavigationBadge
    {
        if ($model === null) {
            return null;
        }

        $label = $item->get(Item::BadgeLabel);
        $variant = $item->get(Item::BadgeVariant);

        return NavigationBadge::count($context->count($model), is_string($label) ? $label : null, is_string($variant) ? $variant : 'default', max: 999);
    }

    private function repository(): Repository
    {
        return $this->cache->store($this->store);
    }
}
