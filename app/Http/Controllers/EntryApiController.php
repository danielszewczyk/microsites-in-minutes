<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $created = 0;

        foreach ($rows as $row) {
            $data = validator(is_array($row) ? $row : [], [
                'name' => ['required', 'string', 'max:80'],
                'email' => ['nullable', 'email', 'max:120'],
                'phone' => ['nullable', 'string', 'max:40'],
                'message' => ['nullable', 'string', 'max:1000'],
                'source' => ['nullable', 'string', 'max:40'],
            ])->validate();

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

        $site->increment('entry_count', $created);

        return response()->json(['ok' => true, 'created' => $created], 201);
    }
}
