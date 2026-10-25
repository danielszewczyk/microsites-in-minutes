<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Import API: push entries into a site programmatically. Authenticated with the
 * site's manage token as a bearer token (the same secret that opens the
 * dashboard). Accepts a single entry or a batch under "entries".
 */
class EntryApiController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        abort_unless($site->managedBy($request->bearerToken()), 401, 'Invalid or missing API token.');

        $rows = $request->has('entries') ? $request->input('entries') : [$request->all()];

        if (! is_array($rows) || $rows === [] || count($rows) > 500) {
            return response()->json(['message' => 'Provide an entry, or an "entries" array of up to 500.'], 422);
        }

        // Validate every row up front so a bad row rejects the whole batch
        // before anything is written.
        $validated = [];
        foreach ($rows as $i => $row) {
            $validated[] = validator(is_array($row) ? $row : [], [
                'name' => ['required', 'string', 'max:80'],
                'email' => ['nullable', 'email', 'max:120'],
                'phone' => ['nullable', 'string', 'max:40'],
                'message' => ['nullable', 'string', 'max:1000'],
                'source' => ['nullable', 'string', 'max:40'],
            ])->validate();
        }

        $existing = $site->entries()->whereNotNull('email')->pluck('email')
            ->map(fn ($e): string => mb_strtolower($e))->flip();

        $created = 0;

        DB::transaction(function () use ($site, $validated, $existing, &$created): void {
            foreach ($validated as $data) {
                $email = isset($data['email']) ? mb_strtolower($data['email']) : null;

                // Skip emails already captured (existing or earlier in the batch).
                if ($email !== null && $existing->has($email)) {
                    continue;
                }
                if ($email !== null) {
                    $existing[$email] = true;
                }

                SiteEntry::query()->create([
                    'site_id' => $site->id,
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'message' => $data['message'] ?? null,
                    'source' => $data['source'] ?? 'api',
                    'is_amoe' => false,
                    'confirmed_at' => now(),
                ]);

                $created++;
            }

            if ($created > 0) {
                Site::withoutTimestamps(fn () => $site->increment('entry_count', $created));
            }
        });

        return response()->json(['ok' => true, 'created' => $created], 201);
    }
}
