{{-- gcego-media::uploads.uploader --}}
@php($presets = \Gcego\Media\Presets\MediaPresets::class)
<div class="flex flex-col gap-3">
    @include('gcego-media::partials.upload-input', ['property' => 'files', 'id' => 'gcego-media-uploader-'.$this->getId(), 'rules' => $rules, 'messages' => $uploadErrors])
    @if ($files !== [])
        <p class="text-sm text-gcego-text">{{ trans_choice('gcego-media::messages.ready', count($files), ['count' => count($files)]) }}</p>
        <button type="button" wire:click="save" wire:loading.attr="disabled" class="{{ implode(' ', $presets::Button) }} self-start">{{ __('gcego-media::messages.upload') }}</button>
    @endif
    @if ($stored > 0)
        <p role="status" class="text-sm text-gcego-success">{{ trans_choice('gcego-media::messages.uploaded', $stored, ['count' => $stored]) }}</p>
    @endif
    @if ($current->isNotEmpty())
        <ul role="list" class="{{ implode(' ', $presets::FileList) }}">
            @foreach ($current as $media)
                <li wire:key="uploaded-{{ $media->ulid }}" class="{{ implode(' ', $presets::FileItem) }}">
                    @include('gcego-media::partials.thumbnail', ['media' => $media, 'manager' => $manager])
                    <span class="{{ implode(' ', $presets::FileName) }}">{{ $media->original_name }}<span class="{{ implode(' ', $presets::FileMeta) }}">{{ \Gcego\Media\Support\Bytes::format($media->size) }}</span></span>
                    <a href="{{ $manager->url($media, download: true) }}" class="{{ implode(' ', $presets::Link) }}">{{ __('gcego-media::messages.download') }}<span class="sr-only">: {{ $media->original_name }}</span></a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
