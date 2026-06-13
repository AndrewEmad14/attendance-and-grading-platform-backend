<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\StudentProfile;

class NoteController extends Controller
{
    // appends a note to student profile not replace existing notes
    public function append(UpdateNoteRequest $request, int $studentId)
    {
        // return response()->json(['message' => 'reached']);
        // dd(auth()->user()->role);
        // only admin and instructor can add notes
        if (! in_array(auth()->user()->role, ['track_admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $profile = StudentProfile::where('user_id', $studentId)->firstOrFail();

        // format note with timestamp and author name
        $formatted = '['.now()->format('Y-m-d H:i').' | '.auth()->user()->name.']: '.$request->note."\n---\n";

        $profile->notes = $profile->notes.$formatted;  // append to existing notes not overwrite
        $profile->save();

        return response()->json([
            'message' => 'Note appended',
            'notes' => $profile->notes,
        ]);
    }
}
