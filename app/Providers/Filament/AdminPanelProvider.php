<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default() 
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Nexus')
            ->sidebarFullyCollapsibleOnDesktop(true)
            ->colors([
                'primary' => [
                    50 => '#eff6ff',  // Very light blue
                    100 => '#dbeafe', // Light blue
                    200 => '#bfdbfe', // Medium light blue
                    300 => '#93c5fd', // Light-medium blue
                    400 => '#60a5fa', // Medium blue
                    500 => '#3b82f6', // Standard blue
                    600 => '#2563eb', // Medium-dark blue
                    700 => '#1d4ed8', // Dark blue (primary accent)
                    800 => '#1e40af', // Darker blue
                    900 => '#1e3a8a', // Very dark blue
                    950 => '#172554', // Deepest blue
                ],
                'secondary' => [
                    50 => '#f8fafc',  // Very light gray
                    100 => '#f1f5f9', // Light gray
                    200 => '#e2e8f0', // Medium light gray
                    300 => '#cbd5e1', // Medium gray
                    400 => '#94a3b8', // Medium-dark gray
                    500 => '#64748b', // Standard gray
                    600 => '#475569', // Dark gray
                    700 => '#334155', // Very dark gray
                    800 => '#1e293b', // Almost black gray
                    900 => '#0f172a', // Very dark gray
                    950 => '#020617', // Black gray
                ],
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
                'gray' => [
                    50 => '#f8fafc',  // Very light gray
                    100 => '#f1f5f9', // Light gray
                    200 => '#e2e8f0', // Medium light gray
                    300 => '#cbd5e1', // Medium gray
                    400 => '#94a3b8', // Medium-dark gray
                    500 => '#64748b', // Standard gray
                    600 => '#475569', // Dark gray
                    700 => '#334155', // Very dark gray
                    800 => '#1e293b', // Almost black gray
                    900 => '#0f172a', // Very dark gray
                    950 => '#020617', // Black gray
                ],
            ])
            ->darkMode(false) // Force light mode (white theme)
            ->renderHook(
                'panels::head.end',
                fn (): string => '<link rel="stylesheet" href="' . asset('css/app.css') . '">
                <style>
                    /* Ensure light theme and custom colors */
                    :root {
                        --fi-primary: 29 78 216; /* Dark blue */
                        --fi-background: 255 255 255; /* Pure white */
                        --fi-surface: 255 255 255; /* White for cards */
                    }

                    body {
                        background: white !important;
                    }

                    /* Force light mode */
                    .dark {
                        display: none !important;
                    }
                </style>'
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class, // Removed to hide Filament version widget
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
