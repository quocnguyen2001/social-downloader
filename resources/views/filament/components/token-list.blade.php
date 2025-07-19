<div class="space-y-4">
    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
        <div class="flex">
            <svg class="w-5 h-5 text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <h4 class="text-sm font-medium text-blue-800">Token Management</h4>
                <p class="text-sm text-blue-700 mt-1">
                    Manage API tokens for {{ $user->name }}. Tokens cannot be viewed after creation for security reasons.
                </p>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        @foreach($tokens as $token)
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium text-gray-900 truncate">
                                {{ $token->name }}
                            </h4>
                            <div class="flex items-center space-x-4 text-xs text-gray-500 mt-1">
                                <span>ID: {{ $token->id }}</span>
                                <span>Created: {{ $token->created_at->format('M j, Y g:i A') }}</span>
                                @if($token->last_used_at)
                                    <span>Last used: {{ $token->last_used_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-yellow-600">Never used</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-2 flex items-center space-x-4">
                        @if($token->expires_at)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                {{ $token->expires_at->isPast() ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                @if($token->expires_at->isPast())
                                    Expired {{ $token->expires_at->diffForHumans() }}
                                @else
                                    Expires {{ $token->expires_at->diffForHumans() }}
                                @endif
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Never expires
                            </span>
                        @endif
                        
                        <span class="text-xs text-gray-500">
                            Abilities: {{ implode(', ', $token->abilities) }}
                        </span>
                    </div>
                </div>
                
                <div class="flex-shrink-0 ml-4">
                    <button 
                        onclick="revokeToken({{ $token->id }}, '{{ $token->name }}')"
                        class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                    >
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        Revoke
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="text-sm font-medium text-gray-900">Total Active Tokens</h4>
                <p class="text-sm text-gray-600">{{ $tokens->count() }} token(s) currently active</p>
            </div>
            <div class="text-right">
                <button 
                    onclick="revokeAllTokens('{{ $user->name }}')"
                    class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                        <path fill-rule="evenodd" d="M10 5a2 2 0 00-2 2v6a2 2 0 104 0V7a2 2 0 00-2-2zM8 7a2 2 0 012-2h2a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2V7z" clip-rule="evenodd"></path>
                    </svg>
                    Revoke All Tokens
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function revokeToken(tokenId, tokenName) {
    if (confirm(`Are you sure you want to revoke the token "${tokenName}"? This action cannot be undone.`)) {
        // Make AJAX request to revoke token
        fetch(`/admin/users/tokens/${tokenId}/revoke`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Refresh the modal content
            } else {
                alert('Failed to revoke token. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}

function revokeAllTokens(userName) {
    if (confirm(`Are you sure you want to revoke ALL tokens for ${userName}? This action cannot be undone.`)) {
        // This will be handled by the existing bulk action
        // For now, just show a message
        alert('Use the "Revoke All Tokens" action button to revoke all tokens.');
    }
}
</script>
