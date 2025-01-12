<x-filament::page>
    <form wire:submit.prevent="submit">
        {{ $this->form }}
        
        @if($selected_app)
            <x-filament::button type="submit" class="mt-4">
                Submit
            </x-filament::button>
        @endif
    </form>
</x-filament::page>