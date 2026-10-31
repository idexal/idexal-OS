@extends('layouts.admin')

@section('title', __('ui.admin.invoices.create'))
@section('page', __('ui.admin.nav.invoices'))

@php
    $initialItems = old('items', [
        ['description' => '', 'quantity' => 1, 'unit_price' => 0],
    ]);
    $initialItems = array_values($initialItems);
@endphp

@section('content')
    <div>
        <a href="{{ route('admin.invoices.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-400 transition hover:text-ink-950">
            <x-icon name="arrow-right" class="size-3.5 rtl:rotate-180" />
            {{ __('ui.admin.invoices.title') }}
        </a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.admin.invoices.create') }}</h1>
        <p class="mt-1.5 text-sm text-ink-500">{{ __('ui.admin.invoices.create_subtitle') }}</p>
    </div>

    <form
        method="POST"
        action="{{ route('admin.invoices.store') }}"
        class="grid gap-4 xl:grid-cols-[1.6fr_1fr]"
        x-data="{
            items: @js($initialItems),
            addItem() {
                this.items.push({ description: '', quantity: 1, unit_price: 0 });
            },
            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },
            get subtotal() {
                return this.items.reduce((total, item) => total + (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0), 0);
            },
        }"
    >
        @csrf

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.admin.invoices.customer') }}</h2>
                <div class="mt-4">
                    <label class="label" for="organization_id">{{ __('ui.admin.organizations.title') }}</label>
                    <select id="organization_id" name="organization_id" required class="select h-10 @error('organization_id') input-error @enderror">
                        <option value="">{{ __('ui.common.select') }}</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->id }}" @selected(old('organization_id', $selectedOrganization) === $organization->id)>
                                {{ $organization->name }} — {{ $organization->slug }}
                            </option>
                        @endforeach
                    </select>
                    @error('organization_id')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-ink-950/10 p-5">
                    <div>
                        <h2 class="font-semibold">{{ __('ui.admin.invoices.items') }}</h2>
                        <p class="mt-0.5 text-xs text-ink-400">{{ __('ui.admin.invoices.items_subtitle') }}</p>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" @click="addItem()">
                        <x-icon name="plus" class="size-3.5" />
                        {{ __('ui.admin.invoices.add_item') }}
                    </button>
                </div>

                <div class="divide-y divide-ink-950/[0.06]">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid gap-3 p-4 sm:grid-cols-[1fr_100px_140px_auto] sm:items-end">
                            <div>
                                <label class="label" :for="`item-description-${index}`">{{ __('ui.common.description') }}</label>
                                <input :id="`item-description-${index}`" type="text" :name="`items[${index}][description]`" x-model="item.description" required maxlength="190" class="input h-10">
                            </div>
                            <div>
                                <label class="label" :for="`item-quantity-${index}`">{{ __('ui.billing.quantity') }}</label>
                                <input :id="`item-quantity-${index}`" type="number" :name="`items[${index}][quantity]`" x-model="item.quantity" required min="0.01" step="0.01" class="input h-10 font-mono" dir="ltr">
                            </div>
                            <div>
                                <label class="label" :for="`item-price-${index}`">{{ __('ui.billing.unit_price') }}</label>
                                <input :id="`item-price-${index}`" type="number" :name="`items[${index}][unit_price]`" x-model="item.unit_price" required min="0" step="0.01" class="input h-10 font-mono" dir="ltr">
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm mb-0.5 text-red-600" @click="removeItem(index)" :disabled="items.length === 1" aria-label="{{ __('ui.common.delete') }}">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        </div>
                    </template>
                </div>

                @error('items')
                    <p class="border-t border-red-200 bg-red-50 p-4 text-xs text-red-600">{{ $message }}</p>
                @enderror
                @error('items.*.description')
                    <p class="border-t border-red-200 bg-red-50 p-4 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex items-center justify-end gap-4 border-t border-ink-950/10 bg-paper/50 p-5">
                    <span class="text-xs text-ink-400">{{ __('ui.common.subtotal') }}</span>
                    <span class="font-mono text-lg font-semibold" x-text="subtotal.toFixed(2)"></span>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-semibold">{{ __('ui.common.details') }}</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="label" for="due_at">{{ __('ui.billing.due') }}</label>
                        <input id="due_at" type="date" name="due_at" value="{{ old('due_at', now()->addDays(config('idexal.billing.grace_days', 3) + 11)->toDateString()) }}" class="input h-10 @error('due_at') input-error @enderror" dir="ltr">
                        @error('due_at')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="label" for="notes">{{ __('ui.billing.notes') }}</label>
                        <textarea id="notes" name="notes" rows="5" maxlength="2000" class="input resize-y @error('notes') input-error @enderror">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-white p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="invoice" class="mt-0.5 size-5 text-lime-dark" />
                    <div>
                        <p class="text-xs font-semibold">{{ __('ui.admin.invoices.note_title') }}</p>
                        <p class="mt-1 text-[11px] leading-5 text-ink-400">{{ __('ui.admin.invoices.note_body') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline h-11 flex-1">{{ __('ui.common.cancel') }}</a>
                <button type="submit" class="btn btn-primary h-11 flex-1">{{ __('ui.admin.invoices.create') }}</button>
            </div>
        </div>
    </form>
@endsection
