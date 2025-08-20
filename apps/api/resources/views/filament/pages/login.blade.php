@php
    $isTenantDomain = !\App\Services\SessionManager::isCentralDomain(request());
@endphp

@if($isTenantDomain)
    <!-- Split-screen layout for tenant domains -->
    <div class="fixed inset-0 overflow-auto">
        <div class="tenant-login-container">
        <!-- Left side - Login Form -->
        <div class="tenant-login-left">
            <div class="mx-auto w-full max-w-sm lg:max-w-md xl:max-w-lg">
                <!-- Logo and Welcome Section -->
                <div class="text-center mb-8">
                    <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-xl bg-primary-500 text-white mb-4">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Welcome Back</h2>
                    <p class="text-gray-600 dark:text-gray-400">Welcome back! Select method to login:</p>
                </div>

                {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.before') }}

                <!-- Social Login Buttons -->
                @if (method_exists($this, 'getSocialLoginButtons') && count($this->getSocialLoginButtons()) > 0)
                    <div class="mb-6 space-y-3">
                        @foreach ($this->getSocialLoginButtons() as $provider)
                            @php
                                $providerConfig = [
                                    'google' => [
                                        'bg' => 'bg-white hover:bg-gray-50 border-gray-300',
                                        'text' => 'text-gray-700',
                                        'focus' => 'focus:ring-blue-500',
                                        'shadow' => 'shadow-sm hover:shadow-md',
                                    ],
                                    'facebook' => [
                                        'bg' => 'bg-[#1877F2] hover:bg-[#166FE5] border-[#1877F2]',
                                        'text' => 'text-white',
                                        'focus' => 'focus:ring-blue-500',
                                        'shadow' => 'shadow-sm hover:shadow-md',
                                    ],
                                    'instagram' => [
                                        'bg' => 'bg-gradient-to-r from-purple-600 via-pink-600 to-orange-500 hover:from-purple-700 hover:via-pink-700 hover:to-orange-600 border-transparent',
                                        'text' => 'text-white',
                                        'focus' => 'focus:ring-pink-500',
                                        'shadow' => 'shadow-sm hover:shadow-lg',
                                    ],
                                ];
                                $config = $providerConfig[$provider['provider']] ?? $providerConfig['google'];
                            @endphp
                            
                            <a
                                href="{{ $provider['url'] }}"
                                class="w-full flex items-center justify-center px-4 py-3 border rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 group {{ $config['bg'] }} {{ $config['text'] }} {{ $config['focus'] }} {{ $config['shadow'] }} dark:focus:ring-offset-gray-900 min-h-[48px]"
                                aria-label="{{ $provider['label'] }}"
                            >
                                @if ($provider['provider'] === 'google')
                                    <x-icons.google class="mr-3 flex-shrink-0" />
                                    <span class="text-sm font-medium">Continue with Google</span>
                                @elseif ($provider['provider'] === 'facebook')
                                    <x-icons.facebook class="mr-3 flex-shrink-0" />
                                    <span class="text-sm font-medium">Continue with Facebook</span>
                                @elseif ($provider['provider'] === 'instagram')
                                    <x-icons.instagram class="mr-3 flex-shrink-0" />
                                    <span class="text-sm font-medium">Continue with Instagram</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    <!-- Divider -->
                    <div class="relative mb-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400">Or Continue with Email</span>
                        </div>
                    </div>
                @endif

                <!-- Email/Password Form -->
                <form wire:submit="authenticate" class="space-y-4 w-full">
                    <div class="space-y-4">
                        {{ $this->form }}
                    </div>

                    <x-filament::button
                        type="submit"
                        size="lg"
                        class="w-full"
                        color="primary"
                    >
                        Sign in to CheckRight
                    </x-filament::button>
                </form>

                <!-- Register Link -->
                @if (filament()->hasRegistration())
                    <div class="mt-6 text-center">
                        <span class="text-gray-600 dark:text-gray-400">New on our platform? </span>
                        {{ $this->registerAction }}
                    </div>
                @endif

                {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.after') }}
            </div>
        </div>

        <!-- Right side - Illustration -->
        <div class="tenant-login-right">
            <div class="relative flex-1 bg-gradient-to-br from-primary-50 to-primary-100 dark:from-gray-800 dark:to-gray-900">
                <div class="absolute inset-0 flex items-center justify-center p-12">
                    <div class="text-center">
                        <!-- Illustration Placeholder -->
                        <div class="w-80 h-80 mx-auto mb-8 bg-primary-500/10 rounded-2xl flex items-center justify-center">
                            <svg class="w-32 h-32 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                            Secure Business Management
                        </h3>
                        <p class="text-lg text-gray-600 dark:text-gray-400 max-w-md">
                            Access your personalized dashboard and manage your business operations with confidence and security.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@else
    <!-- Simple layout for central domain -->
    <x-filament-panels::page.simple>
        @if (filament()->hasRegistration())
            <x-slot name="subheading">
                {{ __('filament-panels::pages/auth/login.actions.register.before') }}

                {{ $this->registerAction }}
            </x-slot>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.before') }}

        <div class="w-full max-w-sm mx-auto">
            <!-- Welcome Section -->
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Welcome Back</h2>
                <p class="text-gray-600 dark:text-gray-400">Welcome back! Select method to login:</p>
            </div>

            <!-- Social Login Buttons -->
            @if (method_exists($this, 'getSocialLoginButtons') && count($this->getSocialLoginButtons()) > 0)
                <div class="mb-6 space-y-3">
                    @foreach ($this->getSocialLoginButtons() as $provider)
                        @php
                            $providerConfig = [
                                'google' => [
                                    'bg' => 'bg-white hover:bg-gray-50 border-gray-300',
                                    'text' => 'text-gray-700',
                                    'focus' => 'focus:ring-blue-500',
                                    'shadow' => 'shadow-sm hover:shadow-md',
                                ],
                                'facebook' => [
                                    'bg' => 'bg-[#1877F2] hover:bg-[#166FE5] border-[#1877F2]',
                                    'text' => 'text-white',
                                    'focus' => 'focus:ring-blue-500',
                                    'shadow' => 'shadow-sm hover:shadow-md',
                                ],
                                'instagram' => [
                                    'bg' => 'bg-gradient-to-r from-purple-600 via-pink-600 to-orange-500 hover:from-purple-700 hover:via-pink-700 hover:to-orange-600 border-transparent',
                                    'text' => 'text-white',
                                    'focus' => 'focus:ring-pink-500',
                                    'shadow' => 'shadow-sm hover:shadow-lg',
                                ],
                            ];
                            $config = $providerConfig[$provider['provider']] ?? $providerConfig['google'];
                        @endphp
                        
                        <a
                            href="{{ $provider['url'] }}"
                            class="w-full flex items-center justify-center px-4 py-3 border rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 group {{ $config['bg'] }} {{ $config['text'] }} {{ $config['focus'] }} {{ $config['shadow'] }} dark:focus:ring-offset-gray-900 min-h-[48px]"
                            aria-label="{{ $provider['label'] }}"
                        >
                            @if ($provider['provider'] === 'google')
                                <x-icons.google class="mr-3 flex-shrink-0" />
                                <span class="text-sm font-medium">Continue with Google</span>
                            @elseif ($provider['provider'] === 'facebook')
                                <x-icons.facebook class="mr-3 flex-shrink-0" />
                                <span class="text-sm font-medium">Continue with Facebook</span>
                            @elseif ($provider['provider'] === 'instagram')
                                <x-icons.instagram class="mr-3 flex-shrink-0" />
                                <span class="text-sm font-medium">Continue with Instagram</span>
                            @endif
                        </a>
                    @endforeach
                </div>

                <!-- Divider -->
                <div class="relative mb-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400">Or Continue with Email</span>
                    </div>
                </div>
            @endif

            <!-- Email/Password Form -->
            <form wire:submit="authenticate" class="space-y-4">
                <div class="space-y-4">
                    {{ $this->form }}
                </div>

                <x-filament::button
                    type="submit"
                    size="lg"
                    class="w-full"
                    color="primary"
                >
                    Sign in to CheckRight
                </x-filament::button>
            </form>

            <!-- Register Link -->
            @if (filament()->hasRegistration())
                <div class="mt-6 text-center">
                    <span class="text-gray-600 dark:text-gray-400">New on our platform? </span>
                    {{ $this->registerAction }}
                </div>
            @endif
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook('panels::auth.login.form.after') }}
    </x-filament-panels::page.simple>
@endif