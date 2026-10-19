{{--
    <x-gcego-chroma::controls />   The shared Alpine.js form controls (gcegoListbox, gcegoDatePicker), inlined
                                   with the Vite CSP nonce. gcego/forms and gcego/tables emit it themselves.
--}}
@props(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
{{ app(\Gcego\Chroma\ChromaManager::class)->controlsTag($nonce) }}
