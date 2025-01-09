<?php

namespace App\Filament\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Redis;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;

 
class CustomFormPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.custom-form-page';
    protected static ?string $slug = 'custom-form';

 // Form data properties
    public $brand;
    public $model;
    public $year;
    public $type;
    
    public function mount(): void
    {
        // Initialize the form with default state
        $this->form->fill([]);
    }

    public function form(Form $form): Form{
        return $form
        ->schema([
            TextInput::make('brand')
                ->required()
                ->maxLength(255),
            TextInput::make('model')
                ->required()
                ->maxLength(255),
            TextInput::make('year')
                ->required()
                ->numeric()
                ->minValue(1900)
                ->maxValue(date('Y') + 1),
            Select::make('type')
                ->required()
                ->options([
                    'sedan' => 'Sedan',
                    'suv' => 'SUV',
                    'truck' => 'Truck',
                    'van' => 'Van',
                ]),
            ]);
    }

    // Handle form submission
    public function submit(): void
    {
        try {
            // Get form data
        $data = $this->form->getState();

        if (!Redis::ping()) {
            Notification::make()
                ->title('Redis connection failed')
                ->danger()
                ->send();
            return;
        }

        // Generate unique ID for the car
        $carId = 'car_' . uniqid();

        $car_data = [
            'id' => $carId,
            'brand' => $data['brand'],
            'model' => $data['model'],
            'year' => $data['year'],
            'type' => $data['type'],
            'created_at' => now()->toDateTimeString(),
        ];
        
        // Using Predis to store data
        Redis::connection()->hset(
            'cars', 
            $carId, 
            json_encode($car_data)
        );
        // Reset the form
        $this->form->fill();

        Notification::make()
            ->title('Car saved successfully')
            ->success()
            ->send();
        } catch (\Throwable $th) {
            Notification::make()
            ->title('Error: ' . $th->getMessage())
            ->danger()
            ->send();        }
    }
}
