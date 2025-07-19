<x-filament-panels::page>
    <div class="space-y-6">
        @livewire('token-display')

        <div class="flex justify-center">
            <x-filament::button
                wire:click="$dispatch('close-modal')"
                onclick="window.history.back()"
                color="gray"
            >
                Back to Users
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
