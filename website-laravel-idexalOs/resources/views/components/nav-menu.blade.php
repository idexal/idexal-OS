@props(['label', 'href', 'active' => false, 'align' => 'start', 'panel' => 'w-[42rem]'])

{{--
    A menu entry that opens a panel.

    Behaviour that makes it feel deliberate rather than twitchy:

    - Opening waits ~90ms and closing waits ~180ms, so moving the pointer
      diagonally from the label into the panel does not close it, and brushing
      past a label on the way to another one does not flash a panel open.
    - The panel closes on Escape (returning focus to the label), on a click
      outside, and when focus leaves the whole entry — not on every focusout,
      which used to close the panel as soon as you tabbed from one item to the
      next inside it.
    - Focus opens the panel and Down moves into it, so the same content is
      reachable without a mouse.
    - `align="end"` flips the panel to the other edge; logical start/end keep
      that correct in RTL without a second rule.

    A wide panel can still overhang a narrow viewport even when it is aligned
    to the "right" edge, so `place()` measures it on open and shifts the
    wrapper back inside the viewport. It measures the inner panel (whose own
    box is not translated) and writes the offset straight onto the wrapper, so
    the enter/leave transform on the panel is never fought over.

    The label stays a real link: clicking it goes to the section page, while
    the panel is a pointer/focus affordance on top of it.
--}}
@php($menuId = 'menu-'.substr(md5($label), 0, 8))

<div
    class="relative"
    x-data="{
        open: false,
        timer: null,
        suppress: false,
        init() {
            this.$watch('open', (value) => {
                if (!value) {
                    // Start the next open unshifted; place() measures from the
                    // element's natural position.
                    this.$refs.wrap.style.transform = '';
                    return;
                }
                // Once after the DOM shows the panel, and again after the
                // enter transition: the first measurement can land before
                // x-show has given the panel a box.
                this.$nextTick(() => this.place());
                setTimeout(() => this.place(), 200);
            });
        },
        show() { if (this.suppress) return; clearTimeout(this.timer); this.timer = setTimeout(() => { this.open = true }, 90) },
        hide() { clearTimeout(this.timer); this.timer = setTimeout(() => { this.open = false }, 180) },
        close() { clearTimeout(this.timer); this.open = false },
        closeAndFocus() {
            // Returning focus fires focusin on the trigger, which would reopen
            // the panel immediately. Suppress open for a beat so Escape
            // actually closes it.
            this.suppress = true; this.close(); this.$refs.trigger.focus();
            setTimeout(() => { this.suppress = false }, 250);
        },
        place() {
            const wrap = this.$refs.wrap;
            const panel = this.$refs.panel;
            if (!wrap || !panel) return;

            wrap.style.transform = 'none';
            const rect = panel.getBoundingClientRect();
            const pad = 12;
            let delta = 0;

            if (rect.right > window.innerWidth - pad) delta = (window.innerWidth - pad) - rect.right;
            if (rect.left + delta < pad) delta = pad - rect.left;

            wrap.style.transform = delta === 0 ? '' : `translateX(${Math.round(delta)}px)`;
        },
    }"
    @mouseenter="show()"
    @mouseleave="hide()"
    @focusin="show()"
    @focusout="if (!$el.contains($event.relatedTarget)) close()"
    @click.outside="close()"
    @keydown.escape.stop.prevent="closeAndFocus()"
    @resize.window="if (open) place()"
>
    <a
        x-ref="trigger"
        href="{{ $href }}"
        class="nav-link {{ $active ? 'nav-link-active' : '' }}"
        :class="open ? 'bg-ink-950/5 text-ink-950' : ''"
        aria-haspopup="true"
        aria-controls="{{ $menuId }}"
        :aria-expanded="open ? 'true' : 'false'"
        @keydown.down.prevent="open = true; $nextTick(() => $refs.panel.querySelector('a')?.focus())"
        @if ($active) aria-current="page" @endif
    >
        {{ $label }}
        <x-icon
            name="chevron-down"
            class="size-3.5 opacity-60 transition-transform duration-200"
            x-bind:class="open ? 'rotate-180' : ''"
        />
    </a>

    <div
        x-ref="wrap"
        class="absolute top-full z-50 mt-2 {{ $align === 'end' ? 'end-0' : 'start-0' }}"
    >
        <div
            id="{{ $menuId }}"
            x-ref="panel"
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="max-w-[calc(100vw-2rem)] rounded-3xl border border-ink-950/10 bg-white p-3 shadow-float {{ $panel }}"
        >
            {{ $slot }}
        </div>
    </div>
</div>
