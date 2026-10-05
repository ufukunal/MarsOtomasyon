<?php

namespace App\Support\Channels;

use App\Contracts\Channels\ChannelAdapter;
use App\Enums\SalesChannelPlatform;
use App\Models\SalesChannelAccount;
use DomainException;

final class ChannelAdapterResolver
{
    public function hasAdapter(SalesChannelPlatform|string $platform): bool
    {
        $value = $platform instanceof SalesChannelPlatform ? $platform->value : $platform;
        $class = config('channels.adapters.'.$value);

        return is_string($class)
            && $class !== ''
            && class_exists($class)
            && is_a($class, ChannelAdapter::class, true);
    }

    public function resolve(SalesChannelAccount|SalesChannelPlatform|string $source): ChannelAdapter
    {
        $platform = $source instanceof SalesChannelAccount
            ? $source->platform
            : ($source instanceof SalesChannelPlatform ? $source : SalesChannelPlatform::from($source));

        $class = config('channels.adapters.'.$platform->value);

        if (! is_string($class)
            || $class === ''
            || ! class_exists($class)
            || ! is_a($class, ChannelAdapter::class, true)) {
            throw new DomainException(
                sprintf('%s adapter implementasyonu henüz etkin değil.', $platform->label()),
            );
        }

        return app($class);
    }
}
