<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                Account Created Successfully!
            </h2>
            <div class="mt-4 bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="space-y-4">
                    <p class="text-gray-600 text-center">
                        Welcome to <strong>{{ $company->name }}</strong>, {{ $user->name }}!
                    </p>
                    <p class="text-sm text-gray-500 text-center">
                        Your account has been created and is ready to use.
                    </p>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Email:</dt>
                                <dd class="text-sm text-gray-900">{{ $user->email }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Role:</dt>
                                <dd class="text-sm text-gray-900">{{ ucfirst($user->role) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Company:</dt>
                                <dd class="text-sm text-gray-900">{{ $company->name }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="pt-4">
                        @php
                            // Construct the tenant-specific login URL
                            $loginUrl = $company->domains->count() > 0 
                                ? (request()->isSecure() ? 'https://' : 'http://') . $company->domains->first()->domain . '/admin/login'
                                : config('app.url') . '/admin/login';
                        @endphp
                        <a href="{{ $loginUrl }}"
                           class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Login to Your Account
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <p class="text-xs text-gray-500 text-center">
                    You can now login using your email and the password you just created.
                    @if($company->domains->count() > 0)
                        <br>Access your company dashboard at: <strong>{{ $company->domains->first()->domain }}</strong>
                    @endif
                </p>
            </div>
        </div>
    </div>
</body>
</html>