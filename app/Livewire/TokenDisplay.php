<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

class TokenDisplay extends Component
{
    public ?string $token = null;
    public ?string $tokenName = null;
    public ?string $expiresAt = null;
    public bool $tokenCopied = false;
    public bool $tokenVisible = true;

    public function mount(): void
    {
        $tokenData = session()->pull('generated_token');

        if ($tokenData) {
            $this->token = $tokenData['token'];
            $this->tokenName = $tokenData['name'];
            $this->expiresAt = $tokenData['expires_at'];
        }
    }

    public function copyToken(): void
    {
        $this->tokenCopied = true;
        $this->dispatch('token-copied');
    }

    public function hideToken(): void
    {
        $this->tokenVisible = false;
        $this->token = null;
    }

    public function render()
    {
        return view('livewire.token-display');
    }
}
