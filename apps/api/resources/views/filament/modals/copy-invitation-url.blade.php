<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Share this invitation URL with the invited user:
    </p>
    
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="space-y-3">
            <div class="flex-1 min-w-0">
                <label for="invitation-url" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Invitation URL
                </label>
                <textarea 
                    id="invitation-url" 
                    readonly 
                    rows="3"
                    class="w-full text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-gray-900 dark:text-gray-100 font-mono resize-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    style="word-break: break-all; white-space: pre-wrap;"
                    onclick="this.select()"
                >{{ $url }}</textarea>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Click on the URL above to select it, then copy it manually.
                </p>
            </div>
        </div>
    </div>
    
    <div class="bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    <strong>Note:</strong> This invitation link will expire and can only be used once to create an account.
                </p>
            </div>
        </div>
    </div>
</div>