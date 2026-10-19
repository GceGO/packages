{{-- gcego-media::partials.thumbnail — a small preview: the "thumb" variant or the original for viewable images, else a type icon. --}}
@php($presets = \Gcego\Media\Presets\MediaPresets::class)
@if ($media->isImage() && app(\Gcego\Media\Access\MediaAccess::class)->available($media))
    <img src="{{ $manager->url($media, $media->variant('thumb') !== null ? 'thumb' : null) }}" alt="{{ $media->alt ?? '' }}" loading="lazy" decoding="async" width="48" height="48" class="{{ implode(' ', $presets::Thumb) }}">
@else
    <span class="{{ implode(' ', $presets::FileIcon) }}" aria-hidden="true">{{ app(\Gcego\Tables\Support\Icons::class)->svg(\Gcego\Media\Support\FileIcon::for($media->kind()), 'size-6') }}</span>
@endif
