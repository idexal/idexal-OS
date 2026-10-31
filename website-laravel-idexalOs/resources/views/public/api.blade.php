@extends('layouts.public')

@section('title', __('ui.docs.title').' — '.config('idexal.brand.name'))
@section('description', __('ui.docs.subtitle'))

@php
    $chatCurl = "curl {$baseUrl}/chat/completions \\\n  -H \"Authorization: Bearer ixo_live_…\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"model\": \"gpt-4o-mini\",\n    \"messages\": [\n      {\"role\": \"user\", \"content\": \"مرحبًا، اكتب فكرة مشروع في سطرين\"}\n    ]\n  }'";

    $openaiSnippet = "from openai import OpenAI\n\nclient = OpenAI(\n    base_url=\"{$baseUrl}\",\n    api_key=\"ixo_live_…\",\n)\n\nresponse = client.chat.completions.create(\n    model=\"gpt-4o-mini\",\n    messages=[{\"role\": \"user\", \"content\": \"مرحبًا\"}],\n)\n\nprint(response.choices[0].message.content)";

    $anthropicSnippet = "from anthropic import Anthropic\n\nclient = Anthropic(\n    base_url=\"{$baseUrl}\",\n    api_key=\"ixo_live_…\",\n)\n\nmessage = client.messages.create(\n    model=\"claude-sonnet-4\",\n    max_tokens=1024,\n    messages=[{\"role\": \"user\", \"content\": \"مرحبًا\"}],\n)\n\nprint(message.content[0].text)";

    $desktopSnippet = "idexalos://den-auth?grant=<grant>&denBaseUrl=".url('/');
@endphp

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
        <div class="container-x relative py-14 lg:py-20">
            <span class="eyebrow">
                <span class="dot bg-lime-dark"></span>
                API v1
            </span>
            <h1 class="mt-5 max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] sm:text-5xl">{{ __('ui.docs.title') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-ink-500">{{ __('ui.docs.subtitle') }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 rounded-xl border border-ink-950/10 bg-white px-3 py-2.5 shadow-sm">
                    <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-400">{{ __('ui.docs.base_url') }}</span>
                    <code class="font-mono text-xs text-ink-950" dir="ltr">{{ $baseUrl }}</code>
                    <button
                        type="button"
                        class="text-ink-400 transition hover:text-ink-950"
                        x-data="copyButton(@js($baseUrl))"
                        @click="copy()"
                        aria-label="{{ __('ui.docs.copy') }}"
                    >
                        <x-icon name="check" class="size-4 text-lime-dark" x-show="copied" x-cloak />
                        <x-icon name="copy" class="size-4" x-show="!copied" />
                    </button>
                </div>
                <a href="{{ localized_route('dashboard.api-keys.index') }}" class="btn btn-primary btn-sm h-10 px-4">
                    <x-icon name="key" class="size-4" />
                    {{ __('ui.dashboard.create_key') }}
                </a>
            </div>
        </div>
    </section>

    <div class="container-x grid gap-12 py-12 lg:grid-cols-[220px_1fr] lg:py-16">
        <aside class="hidden lg:block min-w-0">
            <nav class="sticky top-28 space-y-1 text-sm" aria-label="docs">
                @foreach ([
                    ['quickstart', __('ui.docs.quickstart')],
                    ['auth', __('ui.docs.auth')],
                    ['chat', __('ui.docs.chat')],
                    ['models', __('ui.docs.models')],
                    ['features', __('ui.docs.features')],
                    ['streaming', __('ui.docs.streaming')],
                    ['errors', __('ui.docs.errors')],
                    ['rate-limits', __('ui.docs.rate_limits')],
                    ['webhooks', __('ui.docs.webhooks')],
                    ['desktop', __('ui.docs.desktop')],
                ] as [$anchor, $label])
                    <a href="#{{ $anchor }}" class="block rounded-lg px-3 py-2 text-ink-500 transition hover:bg-ink-950/5 hover:text-ink-950">{{ $label }}</a>
                @endforeach
            </nav>
        </aside>

        <div class="max-w-4xl space-y-14 min-w-0">
            <section id="quickstart" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.quickstart') }}</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['01', __('ui.landing.steps.account.title'), __('ui.landing.steps.account.body')],
                        ['02', __('ui.landing.steps.key.title'), __('ui.landing.steps.key.body')],
                        ['03', __('ui.landing.steps.call.title'), __('ui.landing.steps.call.body')],
                    ] as [$number, $stepTitle, $body])
                        <div class="card p-5">
                            <span class="font-mono text-xs text-ink-300" dir="ltr">{{ $number }}</span>
                            <p class="mt-2 text-sm font-semibold">{{ $stepTitle }}</p>
                            <p class="mt-1 text-xs leading-6 text-ink-500">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section id="auth" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.auth') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.auth_body') }}</p>
                <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-6 text-amber-800">
                    <x-icon name="shield" class="mt-0.5 size-4" />
                    <p>{{ __('ui.docs.note_privacy') }}</p>
                </div>
                <div class="mt-4 overflow-hidden rounded-2xl border border-ink-950/10 bg-white">
                    <div class="flex items-center justify-between border-b border-ink-950/10 px-4 py-3">
                        <span class="text-xs font-semibold text-ink-500">HTTP header</span>
                    </div>
                    <pre class="code-block !rounded-none !border-0" dir="ltr">Authorization: Bearer ixo_live_xxxxxxxxxxxxxxxxx
Content-Type: application/json</pre>
                </div>
            </section>

            <section id="chat" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.chat') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">
                    OpenAI-compatible endpoint. Use any OpenAI SDK by changing <code class="rounded bg-paper-2 px-1.5 py-0.5 font-mono text-xs">base_url</code>.
                </p>

                <div class="mt-5 space-y-4">
                    <div class="overflow-hidden rounded-2xl border border-ink-950/10">
                        <div class="flex items-center justify-between border-b border-ink-950/10 bg-white px-4 py-2.5">
                            <span class="font-mono text-[11px] font-semibold text-ink-500">cURL</span>
                            <button
                                type="button"
                                x-data="copyButton(@js($chatCurl))"
                                @click="copy()"
                                class="flex items-center gap-1.5 text-[11px] font-medium text-ink-400 transition hover:text-ink-950"
                            >
                                <x-icon name="check" class="size-3.5 text-lime-dark" x-show="copied" x-cloak />
                                <x-icon name="copy" class="size-3.5" x-show="!copied" />
                                {{ __('ui.docs.copy') }}
                            </button>
                        </div>
                        <pre class="code-block !rounded-none !border-0" dir="ltr">{{ $chatCurl }}</pre>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div class="overflow-hidden rounded-2xl border border-ink-950/10">
                            <div class="border-b border-ink-950/10 bg-white px-4 py-2.5 font-mono text-[11px] font-semibold text-ink-500">{{ __('ui.docs.openai_sdk') }}</div>
                            <pre class="code-block !rounded-none !border-0 !text-[11px] !leading-6" dir="ltr">{{ $openaiSnippet }}</pre>
                        </div>
                        <div class="overflow-hidden rounded-2xl border border-ink-950/10">
                            <div class="border-b border-ink-950/10 bg-white px-4 py-2.5 font-mono text-[11px] font-semibold text-ink-500">{{ __('ui.docs.anthropic_sdk') }}</div>
                            <pre class="code-block !rounded-none !border-0 !text-[11px] !leading-6" dir="ltr">{{ $anthropicSnippet }}</pre>
                        </div>
                    </div>
                </div>

                <h3 class="mt-8 text-base font-semibold">{{ __('ui.docs.response') }}</h3>
                <pre class="code-block mt-3" dir="ltr">{
  "id": "chatcmpl_ixo_01J...",
  "object": "chat.completion",
  "created": 1730000000,
  "model": "gpt-4o-mini",
  "choices": [{
    "index": 0,
    "message": { "role": "assistant", "content": "..." },
    "finish_reason": "stop"
  }],
  "usage": { "prompt_tokens": 18, "completion_tokens": 64, "total_tokens": 82 }
}</pre>
            </section>

            <section id="compatibility" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.compat_title') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.compat_body') }}</p>
            </section>

            <section id="models" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.models') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.model_switch') }}</p>

                <div class="mt-5 overflow-hidden rounded-2xl border border-ink-950/10 bg-white">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.common.model') }}</th>
                                    <th>{{ __('ui.common.provider') }}</th>
                                    <th>{{ __('ui.common.code') }}</th>
                                    <th class="text-end">{{ __('ui.billing.input_price') }} / 1M</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($models as $model)
                                    <tr>
                                        <td class="font-medium text-ink-950">{{ $model->name }}</td>
                                        <td>{{ $model->provider?->name ?? '—' }}</td>
                                        <td><code class="rounded bg-paper-2 px-1.5 py-0.5 font-mono text-[11px]" dir="ltr">{{ $model->model_id }}</code></td>
                                        <td class="text-end font-mono text-xs" dir="ltr">{{ $model->input_price_per_million ? '$'.number_format((float) $model->input_price_per_million, 2) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-ink-400">{{ __('ui.common.no_results') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="features" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.features') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.features_body') }}</p>
                <ul class="mt-5 space-y-3">
                    @foreach (['feature_web_search', 'feature_vision', 'feature_privacy', 'feature_sanitizer', 'feature_substitute', 'feature_retry', 'feature_repetition', 'feature_headers'] as $feature)
                        <li class="flex gap-3">
                            <span class="mt-2.5 size-1.5 shrink-0 rounded-full bg-lime-dark"></span>
                            <p class="text-sm leading-7 text-ink-500">{{ __('ui.docs.'.$feature) }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section id="streaming" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.streaming') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.streaming_body') }}</p>
                <pre class="code-block mt-4" dir="ltr">stream = client.chat.completions.create(
    model="gpt-4o-mini",
    messages=[{"role": "user", "content": "اكتب فقرة"}],
    stream=True,
)

for chunk in stream:
    delta = chunk.choices[0].delta.content
    if delta:
        print(delta, end="", flush=True)</pre>
            </section>

            <section id="errors" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.errors') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.errors_body') }}</p>
                <div class="mt-4 overflow-hidden rounded-2xl border border-ink-950/10 bg-white">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.common.status') }}</th>
                                <th>{{ __('ui.common.description') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (['401', '403', '429', '502', '503'] as $code)
                                <tr>
                                    <td><code class="font-mono text-xs font-semibold">{{ $code }}</code></td>
                                    <td>{{ __('ui.docs.error_'.$code) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="rate-limits" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.rate_limits') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.rate_limits_body') }}</p>
                <pre class="code-block mt-4" dir="ltr">HTTP/1.1 200 OK
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 118</pre>
            </section>

            <section id="webhooks" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.webhooks') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.webhooks_body') }}</p>
                <a href="{{ localized_route('dashboard.developer') }}" class="btn btn-outline mt-4 btn-sm">
                    <x-icon name="code" class="size-4" />
                    {{ __('ui.developer.title') }}
                </a>
            </section>

            <section id="desktop" class="scroll-mt-28">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('ui.docs.desktop') }}</h2>
                <p class="mt-3 text-sm leading-7 text-ink-500">{{ __('ui.docs.desktop_body') }}</p>

                <ol class="mt-5 space-y-4">
                    @foreach (['one', 'two', 'three'] as $index => $step)
                        <li class="flex gap-4">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-ink-950 font-mono text-[11px] text-lime">{{ $index + 1 }}</span>
                            <p class="pt-1 text-sm leading-7 text-ink-600">{{ __('ui.docs.desktop_steps.'.$step) }}</p>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-5 overflow-hidden rounded-2xl border border-ink-950/10">
                    <div class="flex items-center justify-between border-b border-ink-950/10 bg-white px-4 py-2.5">
                        <span class="font-mono text-[11px] font-semibold text-ink-500">Deep link</span>
                        <button
                            type="button"
                            x-data="copyButton(@js($desktopSnippet))"
                            @click="copy()"
                            class="flex items-center gap-1.5 text-[11px] font-medium text-ink-400 transition hover:text-ink-950"
                        >
                            <x-icon name="check" class="size-3.5 text-lime-dark" x-show="copied" x-cloak />
                            <x-icon name="copy" class="size-3.5" x-show="!copied" />
                            {{ __('ui.docs.copy') }}
                        </button>
                    </div>
                    <pre class="code-block !rounded-none !border-0" dir="ltr">{{ $desktopSnippet }}</pre>
                </div>
            </section>

            <div class="flex flex-col items-start justify-between gap-4 rounded-3xl border border-ink-950/10 bg-white p-6 sm:flex-row sm:items-center">
                <div>
                    <p class="font-semibold">{{ __('ui.contact.support') }}</p>
                    <p class="mt-1 text-sm text-ink-500">{{ __('ui.contact.response_time') }}</p>
                </div>
                <a href="{{ localized_route('contact') }}" class="btn btn-primary">{{ __('ui.nav.contact') }}</a>
            </div>
        </div>
    </div>
@endsection
