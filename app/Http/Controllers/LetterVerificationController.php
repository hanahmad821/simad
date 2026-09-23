<?php

namespace App\Http\Controllers;

use App\Models\OutgoingLetter;

class LetterVerificationController extends Controller
{
    public function __invoke(string $token)
    {
        $letter = OutgoingLetter::query()
            ->with([
                'letterType',
                'creator',
                'approver',
            ])
            ->where('verification_token', $token)
            ->where('status', 'issued')
            ->first();

        return view('letters.verify', [
            'letter' => $letter,
        ]);
    }
}
