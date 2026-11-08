<?php

namespace App\Services;

use Illuminate\Support\Str;

class AddressLocationResolver
{
    public function resolve(string $address, iterable $references): array
    {
        $matches = [];
        foreach ($references as $reference) {
            $reference = is_array($reference) ? $reference : $reference->toArray();
            $location = trim((string) ($reference['location'] ?? ''));
            $province = trim((string) ($reference['province'] ?? ''));
            if ($location === '' || $province === ''
                || !$this->containsPhrase($address, $location)
                || !$this->containsPhrase($address, $province)) {
                continue;
            }

            $cluster = trim((string) ($reference['cluster'] ?? ''));
            $region = trim((string) ($reference['region'] ?? ''));
            $key = implode('|', [
                $this->normalize($cluster),
                $this->normalize($location),
                $this->normalize($province),
                $this->normalize($region),
            ]);
            $matches[$key] ??= [
                'cluster' => $cluster,
                'location' => $location,
                'province' => $province,
                'region' => $region,
                'reference_agents' => [],
            ];
            $agentName = trim((string) ($reference['assigned_agent_name'] ?? ''));
            if ($agentName !== '') {
                $matches[$key]['reference_agents'][$this->normalize($agentName)] = $agentName;
            }
        }

        $matches = array_values(array_map(function (array $match): array {
            $match['reference_agents'] = array_values($match['reference_agents']);
            return $match;
        }, $matches));

        if ($matches === []) {
            return ['status' => 'unmatched', 'matches' => []];
        }
        if (count($matches) !== 1) {
            return ['status' => 'ambiguous', 'matches' => $matches];
        }

        return ['status' => 'resolved'] + $matches[0];
    }

    private function containsPhrase(string $address, string $phrase): bool
    {
        $addressWords = preg_split('/[^a-z0-9]+/', Str::ascii(Str::lower($address)), -1, PREG_SPLIT_NO_EMPTY);
        $phraseWords = preg_split('/[^a-z0-9]+/', Str::ascii(Str::lower(trim($phrase))), -1, PREG_SPLIT_NO_EMPTY);
        if ($addressWords === false || $phraseWords === false || $phraseWords === []) {
            return false;
        }

        $phraseLength = count($phraseWords);
        for ($offset = 0; $offset <= count($addressWords) - $phraseLength; $offset++) {
            if (array_slice($addressWords, $offset, $phraseLength) === $phraseWords) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::ascii(Str::lower(trim($value)))) ?? '';
    }
}
