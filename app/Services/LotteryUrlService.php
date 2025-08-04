<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LotteryUrlService
{
    /**
     * Get all active states
     */
    public function getActiveStates(): array
    {
        $states = config('lottery_urls.states');
        return array_filter($states, function($state) {
            return $state['active'] ?? true;
        });
    }
    
    /**
     * Get state configuration by key
     */
    public function getStateConfig(string $stateKey): ?array
    {
        $states = config('lottery_urls.states');
        return $states[$stateKey] ?? null;
    }
    
    /**
     * Get state configuration by domain
     */
    public function getStateByDomain(string $domain): ?array
    {
        $states = $this->getActiveStates();
        
        foreach ($states as $stateKey => $state) {
            if (in_array($domain, $state['domains'])) {
                return array_merge($state, ['key' => $stateKey]);
            }
        }
        
        return null;
    }
    
    /**
     * Build game detail URL for a state
     */
    public function buildGameDetailUrl(string $stateKey, string $gameId): ?string
    {
        $state = $this->getStateConfig($stateKey);
        if (!$state) {
            Log::error("State not found: $stateKey");
            return null;
        }
        
        $pattern = $state['urls']['game_detail_pattern'] ?? null;
        if (!$pattern) {
            Log::error("Game detail pattern not found for state: $stateKey");
            return null;
        }
        
        return str_replace('{game_id}', $gameId, $pattern);
    }
    
    /**
     * Get games list URL for a state
     */
    public function getGamesListUrl(string $stateKey): ?string
    {
        $state = $this->getStateConfig($stateKey);
        return $state['urls']['games_list'] ?? null;
    }
    
    /**
     * Get all games list URLs for active states
     */
    public function getAllGamesListUrls(): array
    {
        $urls = [];
        $activeStates = $this->getActiveStates();
        
        foreach ($activeStates as $stateKey => $state) {
            $url = $state['urls']['games_list'] ?? null;
            if ($url) {
                $urls[$stateKey] = [
                    'url' => $url,
                    'name' => $state['name'],
                    'state_key' => $stateKey
                ];
            }
        }
        
        return $urls;
    }
    
    /**
     * Validate if a URL belongs to any configured state
     */
    public function validateUrl(string $url): ?array
    {
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return null;
        }
        
        $domain = $parsedUrl['host'];
        return $this->getStateByDomain($domain);
    }
    
    /**
     * Get state key from URL
     */
    public function getStateKeyFromUrl(string $url): ?string
    {
        $state = $this->validateUrl($url);
        return $state['key'] ?? null;
    }
    
    /**
     * Get all supported domains
     */
    public function getSupportedDomains(): array
    {
        $domains = [];
        $activeStates = $this->getActiveStates();
        
        foreach ($activeStates as $stateKey => $state) {
            $domains[$stateKey] = [
                'name' => $state['name'],
                'domains' => $state['domains'],
                'base_url' => $state['base_url']
            ];
        }
        
        return $domains;
    }
    
    /**
     * Check if a state is active
     */
    public function isStateActive(string $stateKey): bool
    {
        $state = $this->getStateConfig($stateKey);
        return ($state['active'] ?? true) === true;
    }
    
    /**
     * Enable/disable a state
     */
    public function setStateActive(string $stateKey, bool $active): bool
    {
        $states = config('lottery_urls.states');
        if (!isset($states[$stateKey])) {
            Log::error("State not found: $stateKey");
            return false;
        }
        
        // Note: This would require config caching to persist changes
        // For now, we'll just log the change
        Log::info("State $stateKey " . ($active ? 'enabled' : 'disabled'));
        return true;
    }
    
    /**
     * Get state statistics
     */
    public function getStateStats(): array
    {
        $activeStates = $this->getActiveStates();
        $totalStates = count(config('lottery_urls.states'));
        
        return [
            'total_states' => $totalStates,
            'active_states' => count($activeStates),
            'inactive_states' => $totalStates - count($activeStates),
            'states' => array_keys($activeStates)
        ];
    }
} 