<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}
        
        @if($selected_app)
            <x-filament::button type="submit" class="mt-4">
                Submit
            </x-filament::button>
        @endif
    </form>
</x-filament-panels::page>