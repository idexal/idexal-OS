@if (session('status'))
    <div class="alert alert-success" role="status">
        <x-icon name="check-circle" class="mt-0.5 size-4" />
        <div class="font-medium">{{ session('status') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-error" role="alert">
        <x-icon name="alert" class="mt-0.5 size-4" />
        <div class="font-medium">{{ session('error') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <x-icon name="alert" class="mt-0.5 size-4" />
        <div>
            <p class="font-semibold">{{ __('ui.common.error') }}</p>
            <ul class="mt-1 list-disc space-y-1 ps-4 text-[13px]">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
