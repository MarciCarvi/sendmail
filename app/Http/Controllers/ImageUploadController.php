<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    public function index()
    {
        $files = Storage::disk('public')->files('campaign-images');

        $images = collect($files)
            ->sortByDesc(fn($f) => Storage::disk('public')->lastModified($f))
            ->map(fn($path) => [
                'url'  => asset('storage/' . $path),
                'name' => basename($path),
            ])
            ->values();

        return response()->json(['data' => $images]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $ext      = strtolower($request->file('file')->getClientOriginalExtension());
        $basename = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);

        $slug = strtolower($basename);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-') ?: 'image';

        $filename = $slug . '.' . $ext;
        $counter  = 1;
        while (Storage::disk('public')->exists('campaign-images/' . $filename)) {
            $filename = $slug . '-' . $counter . '.' . $ext;
            $counter++;
        }

        $path = $request->file('file')->storeAs('campaign-images', $filename, 'public');

        return response()->json([
            'url'  => asset('storage/' . $path),
            'name' => basename($path),
        ]);
    }

    public function destroy(string $name)
    {
        $path = 'campaign-images/' . basename($name);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['success' => true]);
    }
}
