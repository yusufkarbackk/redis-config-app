<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Redis;
use Filament\Notifications\Notification;

class AppRegistration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static string $view = 'filament.pages.app-registration';
    protected static ?string $slug = 'app-form';

    public $app_name;
    public $fields = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('app_name')
                    ->required()
                    ->maxLength(255)
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                if (Redis::hexists('app_forms', $value)) {
                                    $fail("This app name is already taken.");
                                }
                            };
                        },
                    ]),

                Repeater::make('fields')
                    ->schema([
                        TextInput::make('field_name')
                            ->required()
                            ->maxLength(255),
                        Select::make('field_type')
                            ->required()
                            ->options([
                                'text' => 'Text Input',
                                'number' => 'Number Input',
                                'email' => 'Email Input',
                                'date' => 'Date Input',
                                'textarea' => 'Text Area'
                            ]),
                        Select::make('required')
                            ->required()
                            ->options([
                                true => 'Yes',
                                false => 'No'
                            ]),
                        // For select/dropdown fields
                        Repeater::make('options')
                            ->schema([
                                TextInput::make('option_value')
                                    ->required(),
                                TextInput::make('option_label')
                                    ->required(),
                            ])
                            ->visible(fn(callable $get) => $get('field_type') === 'select')
                            ->columns(2),
                    ])
                    ->addActionLabel('Add Field')
                    ->columns(3)
            ]);
    }

    public function submit(): void
    {
        try {
            $data = $this->form->getState();

            if (!Redis::ping()) {
                Notification::make()
                    ->title('Redis connection failed')
                    ->danger()
                    ->send();
                return;
            }

            // Store form structure in Redis
            Redis::connection()->hset('app_forms', $data['app_name'], json_encode([
                'app_name' => $data['app_name'],
                'fields' => $data['fields'],
                'created_at' => now()->toDateTimeString(),
            ]));

            // Create a new hash in Redis for storing this app's form submissions
            Redis::connection()->hset($data['app_name'] . '_submissions', 'created_at', now()->toDateTimeString());

            $this->form->fill();

            Notification::make()
                ->title('App structure saved successfully')
                ->success()
                ->send();
        } catch (\Throwable $th) {
            Notification::make()
                ->title('Error: ' . $th->getMessage())
                ->danger()
                ->send();
        }
    }
}
