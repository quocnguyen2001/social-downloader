{{-- Language Switcher Dropdown Component for Filament Header --}}
{{-- Ensure Tailwind classes are detected by including them in comments:
     sm:h-[200px] w-48 sm:w-64 overflow-y-auto hidden sm:block border-l-2 border-primary-500
     border-transparent bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300
     bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-xl
     shadow-xl ring-1 ring-gray-200 dark:ring-gray-700 border-gray-100 dark:border-gray-800
     bg-gray-50 dark:bg-gray-800/50 rounded-b-xl
--}}
<div class="relative w-[200px]" x-data="{ open: false }" x-on:click.away="open = false">
    {{-- Dropdown Trigger Button --}}
    <button
        type="button"
        x-on:click="open = !open"
        class="w-[500px] flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-all duration-200 border border-transparent hover:border-gray-200 dark:hover:border-gray-700"
        :class="{ 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700': open }"
        aria-expanded="false"
        aria-haspopup="true"
    >
        {{-- Current Language Flag --}}
        <span class="text-lg leading-none">
            @if(app()->getLocale() === 'vi')
                🇻🇳
            @else
                🇺🇸
            @endif
        </span>

        {{-- Current Language Display --}}
        <span class="hidden sm:block font-medium">
            @if(app()->getLocale() === 'vi')
                Tiếng Việt
            @else
                English
            @endif
        </span>

        {{-- Dropdown Arrow --}}
        <x-heroicon-m-chevron-down
            class="w-4 h-4 transition-transform duration-200 text-gray-500 dark:text-gray-400"
            x-bind:class="{ 'rotate-180': open }"
        />
    </button>

    {{-- Dropdown Menu --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 z-50 mt-2 w-48 sm:w-64 sm:h-[200px] origin-top-right bg-white dark:bg-gray-900 rounded-xl shadow-xl ring-1 ring-gray-200 dark:ring-gray-700 focus:outline-none border border-gray-100 dark:border-gray-800 overflow-y-auto"
        role="menu"
        aria-orientation="vertical"
        style="display: none;"
    >
        <div class="py-2" role="none">
            {{-- English Option --}}
            <a
                href="{{ request()->fullUrlWithQuery(['locale' => 'en']) }}"
                class="group flex items-center px-4 py-3 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 {{ app()->getLocale() === 'en' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 border-l-2 border-primary-500' : 'border-l-2 border-transparent' }}"
                role="menuitem"
            >
                <div class="flex-1 hidden sm:block">
                    <div class="font-semibold text-gray-900 dark:text-gray-100">English</div>
                </div>
                @if(app()->getLocale() === 'en')
                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900/30">
                        <x-heroicon-s-check class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </div>
                @endif
            </a>

            {{-- Vietnamese Option --}}
            <a
                href="{{ request()->fullUrlWithQuery(['locale' => 'vi']) }}"
                class="group flex items-center px-4 py-3 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 {{ app()->getLocale() === 'vi' ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 border-l-2 border-primary-500' : 'border-l-2 border-transparent' }}"
                role="menuitem"
            >
                <div class="flex-1 hidden sm:block">
                    <div class="font-semibold text-gray-900 dark:text-gray-100">Tiếng Việt</div>
                </div>
                @if(app()->getLocale() === 'vi')
                    <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900/30">
                        <x-heroicon-s-check class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </div>
                @endif
            </a>
        </div>
    </div>
</div>

{{-- Alpine.js initialization script (if needed) --}}
@push('scripts')
<script>
    // Ensure Alpine.js is available for the dropdown functionality
    document.addEventListener('alpine:init', () => {
        // Language switcher is ready
    });
</script>
@endpush
