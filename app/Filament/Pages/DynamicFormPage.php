<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Redis;
use Filament\Notifications\Notification;

class DynamicFormPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.dynamic-form-page';

    public $selected_app;
    public $formData = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selected_app')
                    ->required()
                    ->options($this->getAvailableForms())
                    ->live()
                    ->afterStateUpdated(fn() => $this->form->fill()),

                ...($this->selected_app ? $this->getDynamicFields() : []),
            ]);
    }

    protected function getAvailableForms(): array
    {
        $forms = Redis::connection()->hgetall('app_forms');
        return collect($forms)
            ->map(fn($form) => json_decode($form, true))
            ->pluck('app_name', 'app_name')
            ->toArray();
    }

    protected function getDynamicFields(): array
    {
        if (!$this->selected_app) {
            return [];
        }

        $formStructure = Redis::connection()->hget('app_forms', $this->selected_app);
        if (!$formStructure) {
            return [];
        }

        $form = json_decode($formStructure, true);

        return collect($form['fields'])->map(function ($field) {
            $componentClass = match ($field['field_type']) {
                'text' => TextInput::class,
                'number' => TextInput::class,
                'email' => TextInput::class,
                'date' => DatePicker::class,
                'textarea' => Textarea::class,
                default => TextInput::class,
            };

            $component = $componentClass::make($field['field_name'])
                ->label(ucwords(str_replace('_', ' ', $field['field_name'])))
                ->required($field['required']);

            if ($field['field_type'] === 'number') {
                $component->numeric();
            }
            if ($field['field_type'] === 'email') {
                $component->email();
            }
            if ($field['field_type'] === 'select' && isset($field['options'])) {
                $options = collect($field['options'])
                    ->pluck('option_label', 'option_value')
                    ->toArray();
                $component->options($options);
            }

            return $component;
        })->toArray();
    }

    public function submit(): void
    {
        try {
            $data = $this->form->getState();
            $submissionId = uniqid();

            // Store form submission in Redis
            Redis::connection()->hset(
                $this->selected_app . '_submissions',
                $submissionId,
                json_encode([
                    'id' => $submissionId,
                    'data' => $data,
                    'created_at' => now()->toDateTimeString(),
                ])
            );

            $this->form->fill(['selected_app' => $this->selected_app]);

            Notification::make()
                ->title('Form submitted successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
