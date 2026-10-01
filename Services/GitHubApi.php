<?php

namespace Leantime\Plugins\LeanGitHub\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubApi
{
    private function request(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15);
    }

    public function get(string $accessToken, string $path): array
    {
        $response = $this->request($accessToken)->get('https://api.github.com/'.ltrim($path, '/'));
        if (! $response->successful()) throw new RuntimeException('GitHub returned HTTP '.$response->status().'.', $response->status());
        return $response->json() ?? [];
    }

    public function post(string $accessToken, string $path, array $body): array
    {
        $response = $this->request($accessToken)->post('https://api.github.com/'.ltrim($path, '/'), $body);
        if (! $response->successful()) {
            $message = $response->json('message');
            throw new RuntimeException(is_string($message) ? $message : 'GitHub returned HTTP '.$response->status().'.', $response->status());
        }
        return $response->json() ?? [];
    }

    public function deleteToken(string $clientId, string $clientSecret, string $accessToken): void
    {
        Http::withBasicAuth($clientId, $clientSecret)
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15)
            ->delete('https://api.github.com/applications/'.$clientId.'/grant', ['access_token' => $accessToken]);
    }
}
