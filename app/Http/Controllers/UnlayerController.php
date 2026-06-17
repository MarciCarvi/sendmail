<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnlayerController extends Controller
{
    public function blocks()
    {
        $blocks = DB::table('sm_unlayer_blocks')->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $blocks->map(fn($b) => [
                'id'          => $b->id,
                'displayName' => $b->name,
                'body'        => json_decode($b->body, true),
                'created_at'  => $b->created_at,
            ]),
        ]);
    }

    public function saveBlock(Request $request)
    {
        $request->validate([
            'displayName' => 'required|string|max:100',
            'body'        => 'required|array',
        ]);

        $id = DB::table('sm_unlayer_blocks')->insertGetId([
            'name'       => $request->displayName,
            'body'       => json_encode($request->body),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function updateBlock(Request $request, int $id)
    {
        $request->validate([
            'displayName' => 'required|string|max:100',
            'body'        => 'required|array',
        ]);

        DB::table('sm_unlayer_blocks')->where('id', $id)->update([
            'name' => $request->displayName,
            'body' => json_encode($request->body),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function deleteBlock(int $id)
    {
        DB::table('sm_unlayer_blocks')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
