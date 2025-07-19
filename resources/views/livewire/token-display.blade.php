@if($token && $tokenVisible)
<div class="bg-white rounded-lg shadow-lg border border-gray-200 p-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            API Token Generated Successfully
        </h3>
        <button
            wire:click="hideToken"
            class="text-gray-400 hover:text-gray-600 transition-colors"
            title="Hide Token"
        >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Token Name</label>
            <p class="text-gray-900 font-mono text-sm bg-gray-50 p-2 rounded border">{{ $tokenName }}</p>
        </div>

        @if($expiresAt)
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Expires At</label>
            <p class="text-gray-900 text-sm bg-gray-50 p-2 rounded border">{{ $expiresAt }}</p>
        </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">API Token</label>
            <div class="relative">
                <textarea
                    readonly
                    class="w-full p-3 border border-gray-300 rounded-md font-mono text-sm bg-gray-50 resize-none"
                    rows="3"
                    id="token-field"
                >{{ $token }}</textarea>
                <button
                    onclick="copyToClipboard()"
                    class="absolute top-2 right-2 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition-colors"
                    title="Copy to clipboard"
                >
                    <span id="copy-text">{{ $tokenCopied ? 'Copied!' : 'Copy' }}</span>
                </button>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-yellow-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <h4 class="text-sm font-medium text-yellow-800">Important Security Notice</h4>
                    <p class="text-sm text-yellow-700 mt-1">
                        This token will only be displayed once. Make sure to copy and store it securely.
                        You won't be able to see it again after closing this dialog.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
            <button
                onclick="copyToClipboard()"
                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors"
            >
                Copy Token
            </button>
            <button
                wire:click="hideToken"
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors"
            >
                I've Saved It
            </button>
        </div>
    </div>
</div>

<script>
function copyToClipboard() {
    const tokenField = document.getElementById('token-field');
    tokenField.select();
    tokenField.setSelectionRange(0, 99999); // For mobile devices

    navigator.clipboard.writeText(tokenField.value).then(function() {
        document.getElementById('copy-text').textContent = 'Copied!';
        setTimeout(() => {
            document.getElementById('copy-text').textContent = 'Copy';
        }, 2000);

        // Trigger Livewire event
        @this.call('copyToken');
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
}
</script>
@elseif(!$tokenVisible)
<div class="bg-gray-50 rounded-lg border border-gray-200 p-6 max-w-2xl mx-auto text-center">
    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">Token Hidden</h3>
    <p class="text-gray-600">The token has been hidden for security. Generate a new token if needed.</p>
</div>
@else
<div class="bg-gray-50 rounded-lg border border-gray-200 p-6 max-w-2xl mx-auto text-center">
    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No Token to Display</h3>
    <p class="text-gray-600">Generate a new API token to see it here.</p>
</div>
@endif
