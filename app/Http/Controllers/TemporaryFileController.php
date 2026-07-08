<?php

namespace App\Http\Controllers;

use App\Models\TemporaryFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemporaryFileController extends Controller
{
    public function store(Request $request): JsonResponse
    {   
        try {
            $data = $request->validate([
                'max_size' => ['nullable', 'numeric'],
                'file' => ['required', 'file', 'max:' . min($request->input('max_size', 4048), 102400)], // ensure if there is an attack, the file still belows 100MB
            ]);

            $file = $data['file'];
            $uniqueName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '_' . rand(0, 999999) . time() . '.' . $file->getClientOriginalExtension();
            $Originalpath = $file->storeAs('', $uniqueName, 'temp');

            TemporaryFile::create([
                'user_id' => $request->user()->id,
                'path' => $Originalpath
            ]);

            $path = route('temp-file.show', ['path' => $Originalpath]);
            return response()->json(['path' => $path]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Handle any other unexpected errors
            Log::error('An unexpected error occurred during the file upload.', [
                'exception' => $e,
            ]);
            return response()->json(['message' => 'An unexpected error occurred during the file upload.'], 500);
        }
    }

    public function show(string $path): StreamedResponse
    {
        return streamPrivateFile(
            model: TemporaryFile::class,
            routeName: null,
            path: $path,
            column: 'path',
            permission: fn (User $user, TemporaryFile $record) => $user->id === $record->user_id,
            disk: 'temp'
        );
    }
}
