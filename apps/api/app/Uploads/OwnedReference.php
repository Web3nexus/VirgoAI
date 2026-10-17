<?php

namespace App\Uploads;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * A reference url must be one of the signed-in user's own uploads, on https.
 */
final class OwnedReference implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if (! is_string($value) || ! str_starts_with($value, 'https://')) {
            $fail('A reference image must be on a public https address. Set APP_URL to a public https address, then attach it again.');

            return;
        }

        if ($user === null || ! ReferenceImage::ownedBy($user, $value)) {
            $fail('That reference image is not one of your uploads. Attach the image again.');
        }
    }
}
