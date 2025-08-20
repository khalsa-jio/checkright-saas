<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CheckRight') }} - Set New Password</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        .checkright-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .password-strength {
            transition: all 0.3s ease;
        }
        
        .password-strength.weak {
            background-color: #fee2e2;
            border-color: #fca5a5;
        }
        
        .password-strength.medium {
            background-color: #fef3c7;
            border-color: #fbbf24;
        }
        
        .password-strength.strong {
            background-color: #d1fae5;
            border-color: #6ee7b7;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-12 w-12 checkright-bg rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-xl">CR</span>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Set new password
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Enter your new password below. Make sure it's strong and secure.
                </p>
            </div>

            <!-- Form -->
            <form class="mt-8 space-y-6" action="{{ route('password.update') }}" method="POST">
                @csrf
                
                <input type="hidden" name="token" value="{{ $token }}">
                
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        autocomplete="email" 
                        required 
                        value="{{ $email ?? old('email') }}"
                        readonly
                        class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 bg-gray-50 focus:outline-none sm:text-sm" 
                        placeholder="Email address"
                    >
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="sr-only">New Password</label>
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        autocomplete="new-password" 
                        required 
                        class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('password') border-red-300 @enderror" 
                        placeholder="New password"
                        onkeyup="checkPasswordStrength(this.value)"
                    >
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    
                    <!-- Password strength indicator -->
                    <div id="password-strength" class="mt-2 p-3 border rounded-md hidden">
                        <p class="text-sm font-medium mb-2">Password strength:</p>
                        <div class="space-y-1 text-xs">
                            <div id="length-check" class="flex items-center">
                                <span class="w-4 h-4 mr-2">❌</span>
                                <span>At least 8 characters</span>
                            </div>
                            <div id="lowercase-check" class="flex items-center">
                                <span class="w-4 h-4 mr-2">❌</span>
                                <span>One lowercase letter</span>
                            </div>
                            <div id="uppercase-check" class="flex items-center">
                                <span class="w-4 h-4 mr-2">❌</span>
                                <span>One uppercase letter</span>
                            </div>
                            <div id="number-check" class="flex items-center">
                                <span class="w-4 h-4 mr-2">❌</span>
                                <span>One number</span>
                            </div>
                            <div id="special-check" class="flex items-center">
                                <span class="w-4 h-4 mr-2">❌</span>
                                <span>One special character (@$!%*?&)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="password_confirmation" class="sr-only">Confirm New Password</label>
                    <input 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        type="password" 
                        autocomplete="new-password" 
                        required 
                        class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                        placeholder="Confirm new password"
                    >
                </div>

                <div>
                    <button 
                        type="submit" 
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white checkright-bg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out"
                    >
                        Update Password
                    </button>
                </div>

                @if (session('status'))
                    <div class="rounded-md bg-green-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">
                                    {{ session('status') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="text-center">
                    <a href="/admin/login" class="font-medium text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out">
                        Back to login
                    </a>
                </div>
            </form>

            <!-- Security Information -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Security Tips:</strong> Use a unique password that you don't use anywhere else. Consider using a password manager to generate and store strong passwords.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function checkPasswordStrength(password) {
            const strengthIndicator = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthIndicator.classList.add('hidden');
                return;
            }
            
            strengthIndicator.classList.remove('hidden');
            
            // Check each requirement
            const checks = {
                length: password.length >= 8,
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                number: /\d/.test(password),
                special: /[@$!%*?&]/.test(password)
            };
            
            // Update visual indicators
            updateCheck('length-check', checks.length);
            updateCheck('lowercase-check', checks.lowercase);
            updateCheck('uppercase-check', checks.uppercase);
            updateCheck('number-check', checks.number);
            updateCheck('special-check', checks.special);
            
            // Calculate strength
            const passedChecks = Object.values(checks).filter(Boolean).length;
            
            // Update strength indicator styling
            strengthIndicator.className = strengthIndicator.className.replace(/\b(weak|medium|strong)\b/g, '');
            
            if (passedChecks <= 2) {
                strengthIndicator.classList.add('weak');
            } else if (passedChecks <= 4) {
                strengthIndicator.classList.add('medium');
            } else {
                strengthIndicator.classList.add('strong');
            }
        }
        
        function updateCheck(elementId, passed) {
            const element = document.getElementById(elementId);
            const icon = element.querySelector('span:first-child');
            
            if (passed) {
                icon.textContent = '✅';
                element.classList.add('text-green-600');
                element.classList.remove('text-gray-600');
            } else {
                icon.textContent = '❌';
                element.classList.add('text-gray-600');
                element.classList.remove('text-green-600');
            }
        }
    </script>
</body>
</html>