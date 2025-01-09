<x-filament::page>
    <form wire:submit.prevent="submit">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">
                Save Form Structure
            </x-filament::button>
        </div>
    </form>
</x-filament::page>
