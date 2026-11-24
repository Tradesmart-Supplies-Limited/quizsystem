<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReactionMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReactionController extends Controller
{
    public function index()
    {
        return view('admin.reactions.index', ['reactions' => ReactionMessage::orderBy('type')->orderBy('message')->get()]);
    }

    public function store(Request $request)
    {
        ReactionMessage::create($request->validate([
            'type' => ['required', Rule::in(['correct', 'incorrect'])],
            'message' => ['required', 'string', 'max:120'],
        ]) + ['is_active' => true]);

        return back()->with('success', 'Reaction added.');
    }

    public function update(Request $request, ReactionMessage $reaction)
    {
        $data = $request->validate(['message' => ['sometimes', 'string', 'max:120']]);
        $data['is_active'] = $request->has('message') ? $request->boolean('is_active') : ! $reaction->is_active;
        $reaction->update($data);

        return back()->with('success', 'Reaction updated.');
    }

    public function destroy(ReactionMessage $reaction)
    {
        $reaction->delete();

        return back()->with('success', 'Reaction deleted.');
    }
}
